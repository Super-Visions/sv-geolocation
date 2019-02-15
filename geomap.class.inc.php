<?php
/**
 * @copyright Copyright (C) 2019 Super-Visions
 * @license   http://opensource.org/licenses/AGPL-3.0
 */

class GeoMap extends Dashlet
{
	static protected $aAttributeList;
	
	/**
	 * @param ModelReflection $oModelReflection
	 * @param string $sId
	 */
	public function __construct(ModelReflection $oModelReflection, $sId)
	{
		parent::__construct($oModelReflection, $sId);
		$this->aProperties['height'] = 600;
		$this->aProperties['query'] = 'SELECT Location';
		$this->aProperties['attribute'] = '';
	}
	
	/**
	 * @param WebPage $oPage
	 * @param bool $bEditMode
	 * @param array $aExtraParams
	 * @return mixed
	 * @throws CoreException
	 */
	public function Render($oPage, $bEditMode = false, $aExtraParams = array())
	{
		$sApiKey = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'api_key');
		$oPage->add_linked_script(sprintf('https://maps.googleapis.com/maps/api/js?key=%s', $sApiKey));
		
		$iDefaultLat = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_latitude');
		$iDefaultLng = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_longitude');
		$iZoom = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_zoom');
		
		$sId = sprintf('map_%d%s', $this->sId, $bEditMode ? '_edit' : '' );
		$sStyle = sprintf("height: %dpx; background: url('/env-%s/sv-geolocation/images/world-map.jpg') 50%%/contain no-repeat;", $this->aProperties['height'], MetaModel::GetEnvironment());
		$oPage->add(sprintf('<div id="%s" class="dashlet-content" style="%s"></div>', $sId, $sStyle));
		
		$oFilter = DBObjectSearch::FromOQL($this->aProperties['query']);
		$oSet = new DBObjectSet($oFilter);
		
		$aLocations = array();
		while ($oCurrObj = $oSet->Fetch())
		{
			if ($oCurrObj->Get($this->aProperties['attribute']))
			{
				$aLocations[] = array(
					'title' => $oCurrObj->GetName(),
					'icon' => $oCurrObj->GetIcon(false),
					'position' => $oCurrObj->Get($this->aProperties['attribute']),
					'tooltip' => static::GetTooltip($oCurrObj),
				);
			}
		}
		
		$oPage->add_script("
var ".$sId.";
$(function() {
	".$sId." = new google.maps.Map(document.getElementById('".$sId."'), {
		center: {lat: ".$iDefaultLat.", lng: ".$iDefaultLng."},
		zoom: ".$iZoom."
	});
	
	var locations = ".json_encode($aLocations).";
	
	locations.map(function(location, i){
		var marker = new google.maps.Marker({
			position: location.position,
			icon: location.icon,
			title: location.title,
			map: ".$sId."
		});
		
		var tooltip = new google.maps.InfoWindow({
          content: location.tooltip
        });
        
        marker.addListener('click', function() {
          tooltip.open(".$sId.", marker);
        });
	});
	
	
	
});");
	
	}
	
	/**
	 * Add properties fields
	 * @param DesignerForm $oForm
	 * @return mixed
	 */
	public function GetPropertiesFields(DesignerForm $oForm)
	{
		$oHeightField = new DesignerIntegerField('height', Dict::S('UI:DashletGeoMap:Prop-Height'), $this->aProperties['height']);
		$oHeightField->SetMandatory();
		$oForm->AddField($oHeightField);
		
		$oQueryField = new DesignerLongTextField('query', Dict::S('UI:DashletGeoMap:Prop-Query'), $this->aProperties['query']);
		$oQueryField->SetMandatory();
		$oForm->AddField($oQueryField);
		
		try {
			$sClass = $this->oModelReflection->GetQuery($this->aProperties['query'])->GetClass();
			$oAttributeField = new DesignerComboField('attribute', Dict::S('UI:DashletGeoMap:Prop-Attribute'), $this->aProperties['attribute']);
			$oAttributeField->SetAllowedValues(static::GetGeolocationAttributes($sClass));
			$oAttributeField->SetMandatory();
		}
		catch (OQLException $e)
		{
			$oAttributeField = new DesignerStaticTextField('attribute', Dict::S('UI:DashletGeoMap:Prop-Attribute'));
		}
		
		$oForm->AddField($oAttributeField);
	}
	
	/**
	 * @param array $aValues
	 * @param array $aUpdatedFields
	 * @return Dashlet
	 */
	public function Update($aValues, $aUpdatedFields)
	{
		if (in_array('query', $aUpdatedFields))
		{
			try {
				$sCurrClass = $this->oModelReflection->GetQuery($aValues['query'])->GetClass();
				$sPrevClass = $this->oModelReflection->GetQuery($this->aProperties['query'])->GetClass();
				
				if ($sCurrClass != $sPrevClass) {
					$this->bFormRedrawNeeded = true;
				}
			}
			catch (OQLException $e)
			{
				$this->bFormRedrawNeeded = true;
			}
		}
		
		return parent::Update($aValues, $aUpdatedFields);
	}
	
	/**
	 * Dashlet info
	 * @return array
	 */
	public static function GetInfo()
	{
		return array(
			'label' => Dict::S('UI:DashletGeoMap:Label', 'GeoMap'),
			'icon' => 'env-'.MetaModel::GetEnvironment().'/sv-geolocation/images/geomap.png',
			'description' => Dict::S('UI:DashletGeoMap:Description'),
		);
	}
	
	protected static function GetTooltip(DBObject $oCurrObj)
	{
		$sClass = get_class($oCurrObj);
		$sTooltip = $oCurrObj->GetHyperlink().'<hr/>'.PHP_EOL;
		$sTooltip .= '<table><tbody>'.PHP_EOL;
		foreach(MetaModel::GetZListItems($sClass, 'list') as $sAttCode)
		{
			$oAttDef = MetaModel::GetAttributeDef($sClass, $sAttCode);
			$sTooltip .= '<tr><td>'.$oAttDef->GetLabel().':&nbsp;</td><td>'.$oCurrObj->GetAsHtml($sAttCode).'</td></tr>'.PHP_EOL;
		}
		$sTooltip .= '</tbody></table>';
		return $sTooltip;
	}
	
	/**
	 * @param string $sClass
	 * @return array
	 * @throws CoreException
	 */
	protected static function GetGeolocationAttributes($sClass)
	{
		if (isset(static::$aAttributeList[$sClass])) return static::$aAttributeList[$sClass];
		
		$aAttributes = array();
		foreach (MetaModel::ListAttributeDefs($sClass) as $sAttribute => $oAttributeDef)
		{
			if (is_a($oAttributeDef, 'AttributeGeolocation'))
			{
				$aAttributes[$sAttribute] = $oAttributeDef->GetLabel();
			}
		}
		static::$aAttributeList[$sClass] = $aAttributes;
		
		return $aAttributes;
	}
}