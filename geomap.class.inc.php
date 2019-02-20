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
		$this->aProperties['search'] = false;
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
		// Load values
		$sApiKey = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'api_key');
		$iDefaultLat = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_latitude');
		$iDefaultLng = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_longitude');
		$iZoom = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_zoom');
		$sBackgroundUrl = sprintf('/env-%s/sv-geolocation/images/world-map.jpg', MetaModel::GetEnvironment());
		$sId = sprintf('map_%d%s', $this->sId, $bEditMode ? '_edit' : '' );
		$sSearch = Dict::S('UI:Button:Search');
		$sDisplaySearch = $this->aProperties['search'] ? 'block' : 'none';
		
		// Prepare page
		$oPage->add_dict_entry('UI:ClickToCreateNew');
		$oPage->add_linked_script(sprintf('https://maps.googleapis.com/maps/api/js?key=%s', $sApiKey));
		$oPage->add_style(<<<STYLE
.map_panel {
	position: absolute;
	left: 40%;
	z-index: 5;
	margin: 10px;
	
	padding: 10px;
	box-shadow: rgba(0, 0, 0, 0.298039) 0px 1px 4px -1px;
	background-color: white;
}
STYLE
		);
		
		$oPage->add(<<<HTML
<div id= class="dashlet-content">
	<div id="{$sId}_panel" class="map_panel" style="display: {$sDisplaySearch};"><input id="{$sId}_address" type="text" /><button id="{$sId}_submit">{$sSearch}</button></div>
	<div id="{$sId}" style="height: {$this->aProperties['height']}px; background: url('{$sBackgroundUrl}') 50%/contain no-repeat;"></div>
</div>
HTML
		);
		
		if ($bEditMode) return;
		
		// Load objects
		$oFilter = DBObjectSearch::FromOQL($this->aProperties['query']);
		$oSet = new DBObjectSet($oFilter);
		$sClassLabel = MetaModel::GetName($oFilter->GetClass());
		
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
		$sLocations = json_encode($aLocations);
		
		$sCreateUrl = '';
		if (UserRights::IsActionAllowed($oFilter->GetClass(), UR_ACTION_MODIFY))
		{
			$sCreateUrl = sprintf(utils::GetAbsoluteUrlAppRoot().'pages/UI.php?operation=new&class=%s&default[%s]=', $oFilter->GetClass(), $this->aProperties['attribute']);
		}
		
		// Make interactive
		$oPage->add_script(<<<SCRIPT
$(function() {
	var oMap = new google.maps.Map(document.getElementById('{$sId}'), {
		center: {lat: {$iDefaultLat}, lng: {$iDefaultLng}},
		zoom: {$iZoom}
	});
	var oGeo = new google.maps.Geocoder();
	var aLocations = {$sLocations};
	var sCreateUrl = '{$sCreateUrl}';
	
	aLocations.map(function(oLocation, i) {
		var oMarker = new google.maps.Marker({
			position: oLocation.position,
			icon: oLocation.icon,
			title: oLocation.title,
			map: oMap
		});
		
		var oTooltip = new google.maps.InfoWindow({
          content: oLocation.tooltip
        });
        
        oMarker.addListener('click', function() {
          oTooltip.open(oMap, oMarker);
        });
	});
	
	// add additional marker
	var oMarker = new google.maps.Marker();
	
	// add create object functionality
	if (sCreateUrl) {
		oMarker.setDraggable(true);
		oMarker.setTitle(Dict.Format('UI:ClickToCreateNew', '{$sClassLabel}'));
		oMarker.setCursor('copy');
		
		// create object
		oMarker.addListener('click', function() {
			window.location = sCreateUrl + oMarker.getPosition().toUrlValue();
		});
	}
	
	// remove marker
	oMarker.addListener('rightclick', function() {
		oMarker.setMap();
	});
	
	// set marker to location
	oMap.addListener( 'click', function (event) {
		oMarker.setPosition(event.latLng);
		oMap.panTo(event.latLng);
		oMarker.setMap(oMap);
	});
	
	// search location
	document.getElementById('{$sId}_submit').addEventListener('click', function() {
		var sAddress = document.getElementById('{$sId}_address').value;
		oGeo.geocode({address: sAddress, bounds: oMap.getBounds()}, function(results, status) {
			if (status === 'OK') {
				oMap.setCenter(results[0].geometry.location);
				oMarker.setPosition(results[0].geometry.location);
				oMarker.setMap(oMap);
			} else {
				console.log('Geocode was not successful for the following reason: ' + status);
			}
        });
	});
});
SCRIPT
		);
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
		
		$oSearchField = new DesignerBooleanField('search', Dict::S('UI:DashletGeoMap:Prop-Search'), $this->aProperties['search']);
		$oForm->AddField($oSearchField);
		
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