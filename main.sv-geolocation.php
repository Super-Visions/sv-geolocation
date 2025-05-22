<?php
/**
 * @copyright 2019-2025 Super-Visions
 * @license   http://opensource.org/licenses/AGPL-3.0
 */

/**
 * Geolocation attribute
 */
class AttributeGeolocation extends AttributeDBField
{
	public function GetEditClass(): string
	{
		return 'GeoLocation';
	}

	/**
	 * @inheritDoc
	 */
	public function GetSQLColumns($bFullSpec = false): array
	{
		$aColumns = array();
		$aColumns[$this->GetCode() . '_lat'] = 'DECIMAL(8,6)';
		$aColumns[$this->GetCode() . '_lng'] = 'DECIMAL(9,6)';
		return $aColumns;
	}

	/**
	 * @inheritDoc
	 */
	public function GetSQLExpressions($sPrefix = ''): array
	{
		if ($sPrefix == '')
		{
			$sPrefix = $this->GetCode();
		}
		$aColumns = array();
		$aColumns[''] = $sPrefix . '_lat';
		$aColumns['latitude'] = $sPrefix . '_lat';
		$aColumns['longitude'] = $sPrefix . '_lng';
		return $aColumns;
	}

	/**
	 * @param $aCols
	 * @param string $sPrefix
	 * @return ormGeolocation
	 * @throws MissingColumnException
	 */
	public function FromSQLToValue($aCols, $sPrefix = ''): ormGeolocation
	{
		if (!array_key_exists($sPrefix . 'latitude', $aCols))
		{
			$sAvailable = implode(', ', array_keys($aCols));
			throw new MissingColumnException("Missing column '" . $sPrefix . "latitude' from {$sAvailable}");
		}

		if (!array_key_exists($sPrefix . 'longitude', $aCols))
		{
			$sAvailable = implode(', ', array_keys($aCols));
			throw new MissingColumnException("Missing column '" . $sPrefix . "longitude' from {$sAvailable}");
		}

		return new ormGeolocation(floatval($aCols[$sPrefix . 'latitude']), floatval($aCols[$sPrefix . 'longitude']));
	}

	public function GetSQLValues($value): array
	{
		$aValues = array();
		if ($value instanceof ormGeolocation)
		{
			$aValues[$this->GetCode() . '_lat'] = $value->getLatitude();
			$aValues[$this->GetCode() . '_lng'] = $value->getLongitude();
		}
		else
		{
			$aValues[$this->GetCode() . '_lat'] = null;
			$aValues[$this->GetCode() . '_lng'] = null;
		}
		return $aValues;
	}

	/**
	 * @inheritDoc
	 * @param null|string|ormGeolocation $proposedValue
	 * @param DBObject|null $oHostObj
	 * @return ormGeolocation|null
	 */
	public function MakeRealValue($proposedValue, $oHostObj): ?ormGeolocation
	{
		if ($proposedValue instanceof ormGeolocation) return $proposedValue;

		return ormGeolocation::fromString($proposedValue ?? '');
	}

	/**
	 * Geolocation raw value always contains comma character
	 *
	 * @inheritDoc
	 */
	public function GetAsCSV($sValue, $sSeparator = ',', $sTextQualifier = '"', $oHostObject = null, $bLocalize = true, $bConvertToPlainText = false): string
	{
		if (!empty($sValue) && str_contains($sSeparator, ',')) return $sTextQualifier . $sValue . $sTextQualifier;
		return parent::GetAsCSV($sValue, $sSeparator, $sTextQualifier, $oHostObject, $bLocalize, $bConvertToPlainText);
	}

	/**
	 * @inheritDoc
	 * @param ormGeolocation $value
	 * @throws ConfigException
	 * @throws CoreException
	 * @noinspection PhpParameterNameChangedDuringInheritanceInspection
	 */
	public function GetAsHTML($value, $oHostObject = null, $bLocalize = true): string
	{
		if ($value instanceof ormGeolocation)
		{
			$iWidth = $this->GetWidth();
			$iHeight = $this->GetHeight();
			$sApiKey = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'api_key');
			$iZoom = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_zoom');
			$bDisplay = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'display_coordinates');
			$sStaticMapUrl = static::GetStaticMapUrl();
			if (empty($sStaticMapUrl))
			{
				$sHTML = sprintf('<pre>%s</pre>', $value);
			}
			else
			{
				$sStaticMapUrl = sprintf($sStaticMapUrl, $value->GetLatitude(), $value->GetLongitude(), $iWidth, $iHeight, $sApiKey, $iZoom);
				$sHTML = sprintf('<img src="%s" width="%d" height="%d" title="%s"/>', $sStaticMapUrl, $iWidth, $iHeight, $value);
				if ($bDisplay) $sHTML .= sprintf('<pre>%s</pre>', $value);
			}
		}
		else
		{
			$sHTML = '<em>' . Dict::S('UI:UndefinedObject') . '</em>';
		}
		return $sHTML;
	}

	/**
	 * @inheritDoc
	 */
	function EnumTemplateVerbs(): array
	{
		return array(
			''              => 'Plain text representation in EPSG:4326 (lat,lon)',
			'wgs_84'        => 'Plain text representation in EPSG:4326 (lat,lon)',
			'rd'            => 'Plain text representation in EPSG:28992 (X,Y)',
			'rijksdriehoek' => 'Plain text representation in EPSG:28992 (X,Y)',
			'html'          => 'HTML representation',
		);
	}

	/**
	 * @inheritDoc
	 * @param ormGeolocation|null $value
	 * @return string|void
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
	public function GetAsHTMLForHistory($sValue, $oHostObject = null, $bLocalize = true): string
	{
		return (string) $sValue;
	}

	public function GetImportColumns(): array
	{
		$aColumns = array();
		$aColumns[$this->GetCode()] = 'VARCHAR(25)' . CMDBSource::GetSqlStringColumnDefinition();

		return $aColumns;
	}

	/**
	 * @param array $aCols
	 * @param string $sPrefix
	 * @return ormGeolocation|null
	 * @throws MissingColumnException
	 */
	public function FromImportToValue($aCols, $sPrefix = ''): ?ormGeolocation
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
	public function GetWidth(): int
	{
		return (int) $this->GetOptional('width', 200);
	}

	/**
	 * @return int Height of the map
	 */
	public function GetHeight(): int
	{
		return (int) $this->GetOptional('height', 150);
	}

	/**
	 * @return string|null Image URL to use as static map
	 * @throws ConfigException
	 * @throws CoreException
	 */
	public static function GetStaticMapUrl(): ?string
	{
		$sStaticMapUrl = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'staticmapurl');
		if (!is_null($sStaticMapUrl)) return $sStaticMapUrl;

		$sApiKey = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'api_key');
		return match (utils::GetConfig()->GetModuleSetting('sv-geolocation', 'provider')) {
			'GoogleMaps' => ($sApiKey) ? 'https://maps.googleapis.com/maps/api/staticmap?markers=%1$f,%2$f&size=%3$dx%4$d&key=%5$s' : null,
			'MapQuest'   => ($sApiKey) ? 'https://www.mapquestapi.com/staticmap/v5/map?locations=%1$f,%2$f&size=%3$d,%4$d&zoom=%6$d&key=%5$s' : null,
			'MapTiler'   => ($sApiKey) ? 'https://api.maptiler.com/maps/bright-v2/static/auto/%3$dx%4$d@2x.png?markers=%1$f,%2$f&key=%5$s' : null,
			default      => null,
		};
	}

	/**
	 * Get the configured style, or a default one based on th configured provider, if possible.
	 * @return array|string|null
	 * @throws ConfigException
	 * @throws CoreException
	 * @see https://maplibre.org/maplibre-gl-js/docs/style-spec/
	 */
	public static function GetStyle(): array|string|null
	{
		$style = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'style');
		if ($style) return $style;

		$sApiKey = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'api_key');
		switch (utils::GetConfig()->GetModuleSetting('sv-geolocation', 'provider'))
		{
			case 'OpenStreetMap':
				return [
					'version' => 8,
					'glyphs'  => 'https://demotiles.maplibre.org/font/{fontstack}/{range}.pbf',
					'sources' => ['osm' => [
						'type'        => 'raster',
						'tiles'       => ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
						'tileSize'    => 256,
						'attribution' => '<a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a>',
					]],
					'layers'  => [[
						'id'     => 'osm',
						'type'   => 'raster',
						'source' => 'osm',
					]]
				];

			case 'MapTiler':
				if ($sApiKey) return sprintf('https://api.maptiler.com/maps/bright-v2/style.json?key=%s', $sApiKey);
				break;
		}

		return null;
	}
}

class ormGeolocation implements JsonSerializable
{
	protected float $fLatitude = 0.0;
	protected float $fLongitude = 0.0;

	/**
	 * @param float $fLatitude Latitude
	 * @param float $fLongitude Longitude
	 */
	public function __construct(float $fLatitude, float $fLongitude)
	{
		$this->fLatitude = $fLatitude;
		$this->fLongitude = $fLongitude;
	}

	/**
	 * @return float
	 */
	public function getLatitude(): float
	{
		return $this->fLatitude;
	}

	/**
	 * @return float
	 */
	public function getLongitude(): float
	{
		return $this->fLongitude;
	}

	/**
	 * Calculate the X coordinate in the Rijksdriehoek (RD) system
	 *
	 * @return float
	 * @since 1.6.0
	 */
	public function getRijksdriehoekX(): float
	{
		static $aPQR = [
			[0, 1, 190094.945],
			[1, 1, -11832.228],
			[2, 1, -114.221],
			[0, 3, -32.391],
			[1, 0, -0.705],
			[3, 1, -2.34],
			[1, 3, -0.608],
			[0, 2, -0.008],
			[2, 3, 0.148],
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
	 * @return float
	 * @since 1.6.0
	 */
	public function getRijksdriehoekY(): float
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
		return sprintf('%f,%f', $this->fLatitude, $this->fLongitude);
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
	 * @return static
	 * @since 1.6.0
	 */
	public static function getRijksdriehoekReference(): static
	{
		return new static(52.1551744, 5.38720621);
	}

	/**
	 * Create ormGeolocation object from string input
	 *
	 * @since 1.8.0
	 */
	public static function fromString(string $sInput): ?static
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
