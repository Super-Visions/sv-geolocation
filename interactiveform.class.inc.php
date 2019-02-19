<?php
/**
 * @copyright Copyright (C) 2019 Super-Visions
 * @license   http://opensource.org/licenses/AGPL-3.0
 */

class GeolocationInteractiveForm implements iApplicationUIExtension
{
	
	/**
	 *    Invoked when an object is being displayed (wiew or edit)
	 *
	 * The method is called right after the main tab has been displayed.
	 * You can add output to the page, either to change the display, or to add a form input
	 *
	 * @param DBObject $oObject The object being displayed
	 * @param WebPage $oPage The output context
	 * @param boolean $bEditMode True if the edition form is being displayed
	 * @return void
	 * @throws CoreException
	 */
	public function OnDisplayProperties($oObject, WebPage $oPage, $bEditMode = false)
	{
		if (!$bEditMode) return;
		
		$aAttributes = MetaModel::FlattenZList(MetaModel::GetZListItems(get_class($oObject), 'details'));
		foreach($aAttributes as $sAttCode)
		{
			$oAttDef = MetaModel::GetAttributeDef(get_class($oObject), $sAttCode);
			if (is_a($oAttDef, AttributeGeolocation::class))
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
		
		switch (utils::GetConfig()->GetModuleSetting('sv-geolocation', 'provider'))
		{
			case 'GoogleMaps':
				$oPage->add_linked_script(sprintf('https://maps.googleapis.com/maps/api/js?key=%s', $sApiKey));
				
				$sLocation = json_encode($oObject->Get($oAttDef->GetCode()));
				$sCenter = ($sLocation == 'null') ? json_encode(array('lat' => $iDefaultLat, 'lng' => $iDefaultLng)) : $sLocation;
				$sStyle = sprintf("width: %dpx; height: %dpx; background: url('/env-%s/sv-geolocation/images/world-map.jpg') 50%%/contain no-repeat;", $oAttDef->GetWidth(), $oAttDef->GetHeight(), MetaModel::GetEnvironment());
				
				$oPage->add_script(<<<"SCRIPT"
$(function () {
	var oFieldInputZone = $('.attribute-edit[data-attcode="{$oAttDef->GetCode()}"] .field_input_zone')[0];
	
	// hide input field
	oFieldInputZone.classList.remove('field_input_string');
	oFieldInputZone.classList.add('field_input_html');
	var oFieldInput = oFieldInputZone.firstChild;
	oFieldInput.setAttribute('type','hidden');
	
	// create map
	var oMapDiv = document.createElement('div');
	oMapDiv.setAttribute('style', "{$sStyle}");
	oFieldInputZone.appendChild(oMapDiv);
	var oMap = new google.maps.Map(oMapDiv, {center: {$sCenter}, zoom: {$iZoom}});
	
	// helper function
	var save_location = function (latLng) {
		if (latLng) oFieldInput.value = latLng.lat() + ',' + latLng.lng();
		else oFieldInput.value = '';
	};
	
	// create marker
	var oMarker = new google.maps.Marker({
		map: null,
		draggable: true,
		position: {$sLocation},
	});
	
	// save marker location
	oMarker.addListener('dragend', function () {
		map.panTo(oMarker.position);
		save_location(oMarker.position);
	});
	
	// remove marker
	oMarker.addListener('click', function(){
		oMarker.setMap();
		save_location();
	});
	
	// set marker to location
	oMap.addListener( 'click', function (event) {
		oMarker.setPosition(event.latLng);
		oMap.panTo(event.latLng);
		save_location(event.latLng);
		oMarker.setMap(oMap);
	});
	
	if (oMarker.position) {
		google.maps.event.trigger(oMap, 'click', {latLng: oMarker.position});
	}
});
SCRIPT
);
			default:
				break;
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
