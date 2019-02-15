<?php
/**
 * @copyright Copyright (C) 2019 Super-Visions
 * @license   http://opensource.org/licenses/AGPL-3.0
 */

class AttributeGeolocation extends AttributeDBField
{
	public function GetEditClass()
	{
		return 'GeoLocation';
	}
	
	public function GetSQLColumns($bFullSpec = false)
	{
		$aColumns = array();
		$aColumns[$this->GetCode().'_lat'] = 'DECIMAL(8,6)';
		$aColumns[$this->GetCode().'_lng'] = 'DECIMAL(9,6)';
		return $aColumns;
	}
	
	public function GetSQLExpressions($sPrefix = '')
	{
		if ($sPrefix == '')
		{
			$sPrefix = $this->GetCode();
		}
		$aColumns = array();
		$aColumns[''] = $sPrefix.'_lat';
		$aColumns['latitude'] = $sPrefix.'_lat';
		$aColumns['longitude'] = $sPrefix.'_lng';
		return $aColumns;
	}
	
	/**
	 * @param $aCols
	 * @param string $sPrefix
	 * @return ormGeolocation
	 * @throws MissingColumnException
	 */
	public function FromSQLToValue($aCols, $sPrefix = '')
	{
		if (!array_key_exists($sPrefix.'latitude', $aCols))
		{
			$sAvailable = implode(', ', array_keys($aCols));
			throw new MissingColumnException("Missing column '".$sPrefix."latitude' from {$sAvailable}");
		}
		
		if (!array_key_exists($sPrefix.'longitude', $aCols))
		{
			$sAvailable = implode(', ', array_keys($aCols));
			throw new MissingColumnException("Missing column '".$sPrefix."longitude' from {$sAvailable}");
		}
		
		if (isset($aCols[$sPrefix.'latitude'], $aCols[$sPrefix.'longitude']))
		{
			return new ormGeolocation(floatval($aCols[$sPrefix.'latitude']), floatval($aCols[$sPrefix.'longitude']));
		}
	}
	
	public function GetSQLValues($value)
	{
		$aValues = array();
		if ($value instanceof ormGeolocation)
		{
			$aValues[$this->GetCode().'_lat'] = $value->getLatitude();
			$aValues[$this->GetCode().'_lng'] = $value->getLongitude();
		}
		else
		{
			$aValues[$this->GetCode().'_lat'] = null;
			$aValues[$this->GetCode().'_lng'] = null;
		}
		return $aValues;
	}
	
	/**
	 * @param string|ormGeolocation $proposedValue
	 * @param DBObject $oHostObj
	 * @return ormGeolocation
	 */
	public function MakeRealValue($proposedValue, $oHostObj)
	{
		if ($proposedValue instanceof ormGeolocation) return $proposedValue;
		
		if (preg_match('{^([-+]?(?:[1-8]?\d(?:\.\d+)?|90(?:\.0+)?)),\s*([-+]?(?:180(?:\.0+)?|(?:(?:1[0-7]\d)|(?:[1-9]?\d))(?:\.\d+)?))$}', trim($proposedValue), $aMatches))
		{
			return new ormGeolocation($aMatches[1], $aMatches[2]);
		}
		else
		{
			// TODO: Implement location to coordinates
			return;
		}
	}
	
	/**
	 * @param ormGeolocation $value
	 * @param DBObject $oHostObject
	 * @param bool $bLocalize
	 * @return string
	 */
	public function GetAsHTML($value, $oHostObject = null, $bLocalize = true)
	{
		if ($value instanceOf ormGeolocation)
		{
			$iWidth = $this->GetWidth();
			$iHeight = $this->GetHeight();
			$sApiKey = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'api_key');
			$iZoom = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_zoom');
			$bDisplay = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'display_coordinates');
			$sStaticMapUrl = sprintf(static::GetStaticMapUrl(), $value->GetLatitude(), $value->GetLongitude(), $iWidth, $iHeight, $sApiKey, $iZoom);
			if (empty($sStaticMapUrl))
			{
				$sHTML = sprintf('<pre>%s</pre>', $value);
			}
			else
			{
				$sHTML = sprintf('<img src="%s" width="%d" height="%d" title="%s"/>', $sStaticMapUrl, $iWidth, $iHeight, $value);
				if ($bDisplay) $sHTML .= sprintf('<pre>%s</pre>', $value);
			}
		}
		else
		{
			$sHTML = '<em>'.Dict::S('UI:UndefinedObject').'</em>';
		}
		return $sHTML;
	}
	
	public function GetAsHTMLForHistory($sValue, $oHostObject = null, $bLocalize = true)
	{
		return (string) $sValue;
	}
	
	/**
	 * @return int Width of the map
	 */
	public function GetWidth()
	{
		return (int) $this->GetOptional('width', 200);
	}
	
	/**
	 * @return int Height of the map
	 */
	public function GetHeight()
	{
		return (int) $this->GetOptional('height', 150);
	}
	
	/**
	 * @return string Image URL to use as static map
	 */
	public static function GetStaticMapUrl()
	{
		$sStaticMapUrl = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'staticmapurl');
		if ($sStaticMapUrl) return $sStaticMapUrl;
		
		$sApiKey = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'api_key');
		switch (utils::GetConfig()->GetModuleSetting('sv-geolocation', 'provider'))
		{
			case 'GoogleMaps':
				if ($sApiKey) return 'https://maps.googleapis.com/maps/api/staticmap?markers=%1$f,%2$f&size=%3$dx%4$d&key=%5$s';
				break;
				
			case 'OpenStreetMap':
				return 'http://staticmap.openstreetmap.de/staticmap.php?center=%1$f,%2$f&markers=%1$f,%2$f,red-pushpin&size=%3$dx%4$d&zoom=%6$d';
				
			case 'MapQuest':
				if ($sApiKey) return 'https://www.mapquestapi.com/staticmap/v5/map?locations=%1$f,%2$f&size=%3$d,%4$d&zoom=%6$d&key=%5$s';
				break;
		}
	}
	
	/**
	 * @param iTopWebPage $oPage
	 * @param string $value
	 * @param string $sDisplayValue
	 * @param string $iId
	 * @param string $sNameSuffix
	 * @param string $sFieldPrefix
	 * @return string
	 * @throws Exception
	 */
	public function GetFormElement(iTopWebPage $oPage, $value, $sDisplayValue, $iId, $sNameSuffix = '', $sFieldPrefix = '')
	{
		$sAttCode = $this->GetCode();
		$sApiKey = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'api_key');
		$iDefaultLat = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_latitude');
		$iDefaultLng = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_longitude');
		$iZoom = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_zoom');
		
		switch (utils::GetConfig()->GetModuleSetting('sv-geolocation', 'provider'))
		{
			case 'GoogleMaps':
				$oPage->add_linked_script(sprintf('https://maps.googleapis.com/maps/api/js?key=%s', $sApiKey));
				
				$sStyle = sprintf("width: %dpx; height: %dpx; background: url('/env-%s/sv-geolocation/images/world-map.jpg') 50%%/contain no-repeat;", $this->GetWidth(), $this->GetHeight(), MetaModel::GetEnvironment());
				$sOptions = json_encode(array(
					'id' => $iId,
					'center' => isset($value) ? $value : array('lat' => $iDefaultLat, 'lng' => $iDefaultLng),
					'zoom' => $iZoom,
					'marker' => $value,
				));
				
				$oPage->add_script("
$(function () {
    var options = {$sOptions};
    var map = new google.maps.Map(document.getElementById('map_'+options.id), options);
    
    var save_location = function (latLng) {
        if (latLng) document.getElementById(options.id).value = latLng.lat() + ',' + latLng.lng();
        else document.getElementById(options.id).value = '';
    };
    
    var marker = new google.maps.Marker({
		map: null,
		draggable: true,
		position: options.center,
	});

	// save marker location
	marker.addListener('dragend', function () {
		map.panTo(marker.position);
		save_location(marker.position);
	});
	
	// remove marker
	marker.addListener('click', function(){
		marker.setMap();
		save_location();
	});
	
    // set marker to location
    map.addListener( 'click', function (event) {
        marker.setPosition(event.latLng);
        map.panTo(event.latLng);
        save_location(event.latLng);
        marker.setMap(map);
    });
	
	if (options.marker) {
	    google.maps.event.trigger(map, 'click', {latLng: new google.maps.LatLng(options.marker)});
	}
	
});");
				
				return "<div class=\"field_input_zone field_input_html\"><div id=\"map_{$iId}\" style=\"{$sStyle}\"></div><input type=\"hidden\" name=\"attr_{$sFieldPrefix}{$sAttCode}{$sNameSuffix}\" value=\"{$sDisplayValue}\" id=\"{$iId}\"/></div>";
				
			default:
				$sHelpText = htmlentities($this->GetHelpOnEdition(), ENT_QUOTES, 'UTF-8');
				return "<div class=\"field_input_zone field_input_string\"><input title=\"$sHelpText\" type=\"text\" name=\"attr_{$sFieldPrefix}{$sAttCode}{$sNameSuffix}\" value=\"{$sDisplayValue}\" id=\"{$iId}\"/></div>";
		}
	}
}

class ormGeolocation implements JsonSerializable
{
	protected $fLatitude = 0.0;
	protected $fLongitude = 0.0;
	
	public function __construct($fLatitude, $fLongitude)
	{
		$this->fLatitude = $fLatitude;
		$this->fLongitude = $fLongitude;
	}
	
	/**
	 * @return float
	 */
	public function getLatitude()
	{
		return $this->fLatitude;
	}
	
	/**
	 * @return float
	 */
	public function getLongitude()
	{
		return $this->fLongitude;
	}
	
	public function __toString()
	{
		return sprintf('%f,%f',$this->fLatitude, $this->fLongitude);
	}
	
	public function jsonSerialize() {
		return array(
			'lat' => $this->fLatitude,
			'lng' => $this->fLongitude,
		);
	}
}
