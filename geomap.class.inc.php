<?php
/**
 * @copyright Copyright (C) 2019-2025 Super-Visions
 * @license   http://opensource.org/licenses/AGPL-3.0
 */

use Combodo\iTop\Application\UI\Base\Component\Html\Html;
use Combodo\iTop\Application\UI\Base\Layout\UIContentBlockUIBlockFactory;

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
	 * @inheritDoc
	 */
	public function Render($oPage, $bEditMode = false, $aExtraParams = array())
	{
		// Load values
		$sApiKey = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'api_key');
		$iDefaultLat = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_latitude');
		$iDefaultLng = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_longitude');
		$iZoom = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_zoom');
		$sId = sprintf('map_%d%s', $this->sId, $bEditMode ? '_edit' : '' );

		// Prepare page
		$oPage->add_dict_entry('UI:ClickToCreateNew');
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

		$sDisplaySearch = $this->aProperties['search'] ? 'block' : 'none';
		$sSearch = Dict::S('UI:Button:Search');
		$sBackgroundUrl = utils::GetAbsoluteUrlModulesRoot().'sv-geolocation/images/world-map.jpg';

		$oBlock = UIContentBlockUIBlockFactory::MakeStandard(null, ["dashlet-content"]);
		$oBlock->AddSubBlock(new Html(<<<HTML
<div id="{$sId}_panel" class="map_panel" style="display: {$sDisplaySearch};"><input id="{$sId}_address" type="text" /><button id="{$sId}_submit">{$sSearch}</button></div>
<div id="{$sId}" class="ibo-panel--body" style="height: {$this->aProperties['height']}px; background: #ffffff url('{$sBackgroundUrl}') 50%/contain no-repeat;"></div>
HTML
		));

		if ($bEditMode) return $oBlock;

		$oFilter = DBObjectSearch::FromOQL($this->aProperties['query']);

		$sCreateHTML = null;
		if (UserRights::IsActionAllowed($oFilter->GetClass(), UR_ACTION_MODIFY))
		{
			$sCreateLabel = Dict::Format('UI:ClickToCreateNew', MetaModel::GetName($oFilter->GetClass()));
			$sCreateUrl = sprintf(utils::GetAbsoluteUrlAppRoot().'pages/UI.php?operation=new&amp;class=%s&amp;default[%s]=', $oFilter->GetClass(), $this->aProperties['attribute']);
			$sCreateHTML = <<<HTML
<a href="{$sCreateUrl}">
	<span class="fas fa-plus"/>
	<span>{$sCreateLabel}</span>
</a>
HTML;
		}

		$aDashletOptions = array(
			'id' => $sId,
			'create' => $sCreateHTML,
			'map' => [
				'center' => [$iDefaultLng, $iDefaultLat],
				'zoom' => $iZoom,
				'key' => $sApiKey
			],
			'locations' => array(),
		);
		
		// Load objects
		$oSet = new DBObjectSet($oFilter);
		while ($oCurrObj = $oSet->Fetch())
		{
			if ($oCurrObj->Get($this->aProperties['attribute']))
			{
				$aDashletOptions['locations'][] = array(
					'title' => $oCurrObj->GetName(),
					'icon' => $oCurrObj->GetIcon(false),
					'position' => $oCurrObj->Get($this->aProperties['attribute']),
					'tooltip' => static::GetTooltip($oCurrObj),
				);
			}
		}
		
		// Make interactive
		$oPage->LinkScriptFromURI('https://unpkg.com/maplibre-gl/dist/maplibre-gl.js');
		$oPage->LinkStylesheetFromURI('https://unpkg.com/maplibre-gl/dist/maplibre-gl.css');
		$oPage->LinkScriptFromModule('sv-geolocation/js/maplibre-utils.js');

		$oPage->add_ready_script(sprintf('render_geomap(%s);', json_encode($aDashletOptions)));

		return $oBlock;
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
		finally
		{
			$oForm->AddField($oAttributeField);
		}
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
			if (is_a($oAttributeDef, AttributeGeolocation::class))
			{
				$aAttributes[$sAttribute] = $oAttributeDef->GetLabel();
			}
		}
		static::$aAttributeList[$sClass] = $aAttributes;
		
		return $aAttributes;
	}
}