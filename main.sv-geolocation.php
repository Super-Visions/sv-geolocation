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
		$aColumns[$this->GetCode().'_lat'] = 'DECIMAL(10,8)';
		$aColumns[$this->GetCode().'_lng'] = 'DECIMAL(11,8)';
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
	 * @param null|string|ormGeolocation $proposedValue
	 * @param DBObject $oHostObj
	 * @return ormGeolocation
	 */
	public function MakeRealValue($proposedValue, $oHostObj)
	{
		if ($proposedValue instanceof ormGeolocation) return $proposedValue;
		
		return ormGeolocation::fromString($proposedValue);
	}

	/**
	 * Geolocation raw value always contains comma character
	 *
	 * @inheritDoc
	 */
	public function GetAsCSV($sValue, $sSeparator = ',', $sTextQualifier = '"', $oHostObject = null, $bLocalize = true, $bConvertToPlainText = false) {
		if (!empty($sValue) && strpos($sSeparator, ',') !== false) return $sTextQualifier.$sValue.$sTextQualifier;
		return parent::GetAsCSV($sValue, $sSeparator, $sTextQualifier,$oHostObject, $bLocalize, $bConvertToPlainText);
	}

	/**
	 * @inheritDoc
	 * @param ormGeolocation $value
	 * @throws ConfigException
	 * @throws CoreException
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
	
	/**
	 * @inheritDoc
	 */
	function EnumTemplateVerbs()
	{
		return array(
			'' => 'Plain text representation in EPSG:4326 (lat,lon)',
			'wgs_84' => 'Plain text representation in EPSG:4326 (lat,lon)',
			'rd' => 'Plain text representation in EPSG:28992 (X,Y)',
			'rijksdriehoek' => 'Plain text representation in EPSG:28992 (X,Y)',
			'html' => 'HTML representation',
		);
	}

	/**
	 * @inheritDoc
	 * @param ormGeolocation|null $value
	 * @return string
	 */
	function GetForTemplate($value, $sVerb, $oHostObject = null, $bLocalize = true)
	{
		switch ($sVerb)
		{
			case 'rijksdriehoek':
			case 'rd':
				if ($value instanceof ormGeolocation) return sprintf('%f,%f', $value->getRijksdriehoekX(), $value->getRijksdriehoekY());
				else return;

			case 'wgs_84':
				if ($value instanceof ormGeolocation) return sprintf('%f,%f', $value->getLatitude(), $value->getLongitude());
				else return;

			default:
				return parent::GetForTemplate($value, $sVerb, $oHostObject, $bLocalize);
		}
	}

	/**
	 * @inheritDoc
	 */
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
	 * @throws ConfigException
	 * @throws CoreException
	 */
	public static function GetStaticMapUrl()
	{
		$sStaticMapUrl = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'staticmapurl');
		if ($sStaticMapUrl) return $sStaticMapUrl;
		
		$sApiKey = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'api_key');
		switch (utils::GetConfig()->GetModuleSetting('sv-geolocation', 'provider'))
		{
			case 'GoogleMaps':
				if ($sApiKey) return 'https://maps.googleapis.com/maps/api/staticmap?markers=%1$.8f,%2$.8f&size=%3$dx%4$d&key=%5$s';
				break;
				
			case 'OpenStreetMap':
				return 'http://staticmap.openstreetmap.de/staticmap.php?center=%1$.8f,%2$.8f&markers=%1$.8f,%2$.8f,red-pushpin&size=%3$dx%4$d&zoom=%6$d';
				
			case 'MapQuest':
				if ($sApiKey) return 'https://www.mapquestapi.com/staticmap/v5/map?locations=%1$.8f,%2$.8f&size=%3$d,%4$d&zoom=%6$d&key=%5$s';
				break;
		}
		return null;
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

	/**
	 * Calculate the X coordinate in the Rijksdriehoek (RD) system
	 *
	 * @since 1.6.0
	 * @return float
	 */
	public function getRijksdriehoekX()
	{
		static $aPQR = [
			[0,1,190094.945],
			[1,1,-11832.228],
			[2,1,-114.221],
			[0,3,-32.391],
			[1,0,-0.705],
			[3,1,-2.34],
			[1,3,-0.608],
			[0,2,-0.008],
			[2,3,0.148],
		];

		$fX = 155E3;
		$oRijksdriehoekReference = static::getRijksdriehoekReference();
		$oD = new static(
			0.36 * ($this->getLatitude() - $oRijksdriehoekReference->getLatitude()),
			0.36 * ($this->getLongitude() - $oRijksdriehoekReference->getLongitude())
		);

		foreach ($aPQR as list($iP, $iQ, $fR))
		{
			$fX += $fR * pow($oD->getLatitude(), $iP) * pow($oD->getLongitude(), $iQ);
		}

		return $fX;
	}

	/**
	 * Calculate the Y coordinate in the Rijksdriehoek (RD) system
	 *
	 * @since 1.6.0
	 * @return float
	 */
	public function getRijksdriehoekY()
	{
		static $aPQS = [
			[1, 0, 309056.544],
			[0, 2, 3638.893],
			[2, 0, 73.077],
			[1, 2, -157, 984],
			[3, 0, 59.788],
			[0, 1, 0.433],
			[2, 2, -6.439],
			[1, 1, -0.032],
			[0, 4, 0.092],
			[1, 4, -0.054]
		];

		$fY = 463E3;
		$oRijksdriehoekReference = static::getRijksdriehoekReference();
		$oD = new static(
			0.36 * ($this->getLatitude() - $oRijksdriehoekReference->getLatitude()),
			0.36 * ($this->getLongitude() - $oRijksdriehoekReference->getLongitude())
		);

		foreach ($aPQS as list($iP, $iQ, $fS))
		{
			$fY += $fS * pow($oD->getLatitude(), $iP) * pow($oD->getLongitude(), $iQ);
		}

		return $fY;
	}
	
	public function __toString()
	{
		return sprintf('%.8f,%.8f', $this->fLatitude, $this->fLongitude);
	}
	
	/**
	 * @inheritDoc
	 * @return array{lat: float, lng: float}
	 */
	public function jsonSerialize(): array
	{
		return array(
			'lat' => $this->fLatitude,
			'lng' => $this->fLongitude,
		);
	}

	/**
	 * @since 1.6.0
	 * @return static
	 */
	public static function getRijksdriehoekReference()
	{
		return new static(52.1551744, 5.38720621);
	}

	/**
	 * Create ormGeolocation object from string input
	 *
	 * @since 1.8.0
	 * @param string|null $sInput
	 * @return static|null
	 */
	public static function fromString(?string $sInput)
	{
		if (preg_match('{^([-+]?(?:[1-8]?\d(?:\.\d+)?|90(?:\.0+)?)),\s*([-+]?(?:180(?:\.0+)?|(?:(?:1[0-7]\d)|(?:[1-9]?\d))(?:\.\d+)?))$}', trim($sInput), $aMatches))
		{
			return new static(floatval($aMatches[1]), floatval($aMatches[2]));
		}
		else
		{
			// TODO: Implement location to coordinates
			return null;
		}
	}
}
