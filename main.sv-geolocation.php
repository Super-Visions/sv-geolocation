<?php
/**
 * @copyright Copyright (C) 2018 Super-Visions
 * @license   http://opensource.org/licenses/AGPL-3.0
 */

class AttributeGeolocation extends AttributeDBField
{
	
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
			return new ormGeolocation($aCols[$sPrefix.'latitude'], $aCols[$sPrefix.'longitude']);
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
		elseif(preg_match('{^([-+]?(?:[1-8]?\d(?:\.\d+)?|90(?:\.0+)?)),\s*([-+]?(?:180(?:\.0+)?|(?:(?:1[0-7]\d)|(?:[1-9]?\d))(?:\.\d+)?))$}', $value, $aMatches))
		{
			$aValues[$this->GetCode().'_lat'] = $aMatches[1];
			$aValues[$this->GetCode().'_lng'] = $aMatches[2];
		}
		else
		{
			// TODO: Implement location to coordinates
			$aValues[$this->GetCode().'_lat'] = null;
			$aValues[$this->GetCode().'_lng'] = null;
		}
		return $aValues;
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
			$sUrl = sprintf(utils::GetConfig()->GetModuleSetting('sv-geolocation', 'staticmapurl'), $value->GetLatitude(), $value->GetLongitude());
			$sHTML = '<img src="'.$sUrl.'"/>';
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
}

class ormGeolocation
{
	protected $fLatitude = 0.0;
	protected $fLongitude = 0.0;
	
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
	
	public function __toString()
	{
		return sprintf('%f,%f',$this->fLatitude, $this->fLongitude);
	}
}