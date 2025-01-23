<?php
/**
 * @copyright 2019-2025 Super-Visions
 * @license   http://opensource.org/licenses/AGPL-3.0
 */

class GeolocationInteractiveForm implements iApplicationUIExtension
{

	/**
	 * @inheritDoc
	 */
	public function OnDisplayProperties($oObject, WebPage $oPage, $bEditMode = false): void
	{
		if (!$bEditMode) return;

		$aAttributes = MetaModel::FlattenZList(MetaModel::GetZListItems(get_class($oObject), 'details'));
		foreach($aAttributes as $sAttCode)
		{
			try
			{
				$oAttDef = MetaModel::GetAttributeDef(get_class($oObject), $sAttCode);

				if (is_a($oAttDef, AttributeGeolocation::class) && !($oObject->GetAttributeFlags($sAttCode) & (OPT_ATT_READONLY | OPT_ATT_SLAVE)))
				{
					$this->DisplayInteractiveField($oAttDef, $oObject, $oPage);
				}
			} catch (Exception $e) {}
		}
	}

	/**
	 * Add additional code to the page to alter the specified attribute into an interactive map
	 *
	 * @param AttributeGeolocation $oAttDef The field to enhance
	 * @param DBObject $oObject The object being displayed
	 * @param WebPage $oPage The output context
	 * @throws ConfigException
	 * @throws CoreException
	 * @throws Exception
	 */
	protected function DisplayInteractiveField(AttributeGeolocation $oAttDef, DBObject $oObject, WebPage $oPage): void
	{
		$sApiKey = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'api_key');
		$iDefaultLat = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_latitude');
		$iDefaultLng = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_longitude');
		$iZoom = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_zoom');
		$bDisplay = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'display_coordinates');
		list($sLang, $sRegion) = explode(' ', UserRights::GetUserLanguage(), 2);

		$oAttOptions = ['code' => $oAttDef->GetCode(), 'width' => $oAttDef->GetWidth(), 'height' => $oAttDef->GetHeight(), 'display' => $bDisplay];

		switch (utils::GetConfig()->GetModuleSetting('sv-geolocation', 'provider'))
		{
			case 'GoogleMaps':
				$sLang = match (UserRights::GetUserLanguage())
				{
					'PT BR', 'ZH CN' => strtolower($sLang) . '-' . $sRegion,
					default => strtolower($sLang),
				};

				$oPage->LinkScriptFromURI(sprintf('https://maps.googleapis.com/maps/api/js?key=%s&callback=$.noop&language=%s', $sApiKey, $sLang));
				$oPage->LinkScriptFromModule('sv-geolocation/js/google-maps-utils.js');

				$oMapOptions = array('center' => new ormGeolocation($iDefaultLat, $iDefaultLng), 'zoom' => $iZoom);
				break;

			case 'MapLibre':
			case 'MapTiler':
			case 'OpenStreetMap':
				$oPage->LinkScriptFromURI('https://unpkg.com/maplibre-gl/dist/maplibre-gl.js');
				$oPage->LinkStylesheetFromURI('https://unpkg.com/maplibre-gl/dist/maplibre-gl.css');
				$oPage->LinkScriptFromModule('sv-geolocation/js/maplibre-utils.js');

				$oMapOptions = ['center' => [$iDefaultLng, $iDefaultLat], 'zoom' => $iZoom, 'style' => AttributeGeolocation::GetStyle()];
				break;
			default:
				return;
		}
		$oPage->add_ready_script(sprintf('make_interactive_map(%s, %s);', json_encode($oAttOptions), json_encode($oMapOptions)));
	}

	/**
	 * @ignore Unused
	 */
	public function OnDisplayRelations($oObject, WebPage $oPage, $bEditMode = false)
	{
	}

	/**
	 * @ignore Unused
	 */
	public function OnFormSubmit($oObject, $sFormPrefix = '')
	{
	}

	/**
	 * @ignore Unused
	 */
	public function OnFormCancel($sTempId)
	{
	}

	/**
	 * @ignore Unused
	 */
	public function EnumUsedAttributes($oObject): array
	{
		return [];
	}

	/**
	 * @ignore Unused
	 */
	public function GetIcon($oObject)
	{
	}

	/**
	 * @ignore Unused
	 */
	public function GetHilightClass($oObject)
	{
	}

	/**
	 * @ignore Unused
	 */
	public function EnumAllowedActions(DBObjectSet $oSet): array
	{
		return [];
	}
}
