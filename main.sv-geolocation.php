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
			return new ormGeolocation(floatval($aMatches[1]), floatval($aMatches[2]));
		}
		else
		{
			// TODO: Implement location to coordinates
			return;
		}
	}

	/**
	 * Geolocation raw value always contains comma character
	 *
	 * @param string $sValue
	 * @param string $sSeparator
	 * @param string $sTextQualifier
	 * @param \DBObject $oHostObject
	 * @param bool $bLocalize
	 * @param bool $bConvertToPlainText
	 *
	 * @return string
	 */
	public function GetAsCSV($sValue, $sSeparator = ',', $sTextQualifier = '"', $oHostObject = null, $bLocalize = true, $bConvertToPlainText = false) {
		if (!empty($sValue) && strpos($sSeparator, ',') !== false) return $sTextQualifier.$sValue.$sTextQualifier;
		return parent::GetAsCSV($sValue, $sSeparator, $sTextQualifier,$oHostObject, $bLocalize, $bConvertToPlainText);
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

	public function GetImportColumns()
	{
		$aColumns = array();
		$aColumns[$this->GetCode()] = 'VARCHAR(25)'.CMDBSource::GetSqlStringColumnDefinition();

		return $aColumns;
	}

	/**
	 * @param array $aCols
	 * @param string $sPrefix
	 * @return ormGeolocation|null
	 * @throws MissingColumnException
	 */
	public function FromImportToValue($aCols, $sPrefix = '')
	{
		if (!isset($aCols[$sPrefix]))
		{
			$sAvailable = implode(', ', array_keys($aCols));
			throw new MissingColumnException("Missing column '$sPrefix' from {$sAvailable}");
		}

		return $this->MakeRealValue($aCols[$sPrefix], null);
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
