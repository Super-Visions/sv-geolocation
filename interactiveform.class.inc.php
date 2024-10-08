<?php
/**
 * @copyright Copyright (C) 2019 Super-Visions
 * @license   http://opensource.org/licenses/AGPL-3.0
 */

class GeolocationInteractiveForm implements iApplicationUIExtension
{
	
	/**
	 * @inheritDoc
	 */
	public function OnDisplayProperties($oObject, WebPage $oPage, $bEditMode = false)
	{
		if (!$bEditMode) return;
		
		$aAttributes = MetaModel::FlattenZList(MetaModel::GetZListItems(get_class($oObject), 'details'));
		foreach($aAttributes as $sAttCode)
		{
			$oAttDef = MetaModel::GetAttributeDef(get_class($oObject), $sAttCode);

			if (is_a($oAttDef, AttributeGeolocation::class) && !($oObject->GetAttributeFlags($sAttCode) & (OPT_ATT_READONLY | OPT_ATT_SLAVE)))
			{
				$this->DisplayInteractiveField($oAttDef, $oObject, $oPage);
			}
		}
	}
	
	/**
	 * Add additional code to the page to alter the specified attribute into an interactive map
	 *
	 * @param AttributeGeolocation $oAttDef The field to enhance
	 * @param DBObject $oObject The object being displayed
	 * @param WebPage $oPage The output context
	 * @throws CoreException
	 */
	protected function DisplayInteractiveField(AttributeGeolocation $oAttDef, DBObject $oObject, WebPage $oPage)
	{
		$sApiKey = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'api_key');
		$iDefaultLat = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_latitude');
		$iDefaultLng = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_longitude');
		$iZoom = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_zoom');
		$bDisplay = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'display_coordinates');
		$sInputType = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'input_type');
		list($sLang, $sRegion) = explode(' ', UserRights::GetUserLanguage(), 2);

		if ($sInputType == 'provider') {
			switch (utils::GetConfig()->GetModuleSetting('sv-geolocation', 'provider'))
			{
				case 'GoogleMaps':
					switch (UserRights::GetUserLanguage())
					{
						case 'PT BR':
						case 'ZH CN':
							$sLang = strtolower($sLang).'-'.$sRegion;
							break;
						default:
							$sLang = strtolower($sLang);
							break;
					}

					$oPage->add_linked_script(sprintf('https://maps.googleapis.com/maps/api/js?key=%s&callback=$.noop&language=%s', $sApiKey, $sLang));
					$oPage->add_linked_script(utils::GetAbsoluteUrlModulesRoot().'sv-geolocation/js/google-maps-utils.js');

					$oAttOptions = array('code' => $oAttDef->GetCode(), 'width' => $oAttDef->GetWidth(), 'height' => $oAttDef->GetHeight(), 'display' => $bDisplay);
					$oMapOptions = array('center' => new ormGeolocation($iDefaultLat, $iDefaultLng), 'zoom' => $iZoom);

					$oPage->add_ready_script(sprintf('make_interactive_map(%s, %s);', json_encode($oAttOptions), json_encode($oMapOptions)));
					break;
				default:
					break;
			}
		}
	}
	
	/**
	 * Unused
	 */
	public function OnDisplayRelations($oObject, WebPage $oPage, $bEditMode = false)
	{
	}
	
	/**
	 * Unused
	 */
	public function OnFormSubmit($oObject, $sFormPrefix = '')
	{
	}
	
	/**
	 * Unused
	 */
	public function OnFormCancel($sTempId)
	{
	}
	
	/**
	 * Unused
	 */
	public function EnumUsedAttributes($oObject)
	{
		return array();
	}
	
	/**
	 * Unused
	 */
	public function GetIcon($oObject)
	{
	}
	
	/**
	 * Unused
	 */
	public function GetHilightClass($oObject)
	{
	}
	
	/**
	 * Unused
	 */
	public function EnumAllowedActions(DBObjectSet $oSet)
	{
		return array();
	}
}
