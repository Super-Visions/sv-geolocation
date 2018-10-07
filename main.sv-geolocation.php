<?php
/**
 * @copyright Copyright (C) 2018 Super-Visions
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
		
		if(preg_match('{^([-+]?(?:[1-8]?\d(?:\.\d+)?|90(?:\.0+)?)),\s*([-+]?(?:180(?:\.0+)?|(?:(?:1[0-7]\d)|(?:[1-9]?\d))(?:\.\d+)?))$}', trim($proposedValue), $aMatches))
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
			$sStaticMapUrl = sprintf(static::GetStaticMapUrl(), $value->GetLatitude(), $value->GetLongitude(), $iWidth, $iHeight, $sApiKey, $iZoom);
			if (empty($sStaticMapUrl))
			{
				$sHTML = '<pre>'.$value.'</pre>';
			}
			else
			{
				$sHTML = sprintf('<img src="%s" width="%d" height="%d"/>', $sStaticMapUrl, $iWidth, $iHeight);
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
		$this->aProperties['attribute'] = '';
	}
	
	/**
	 * @param WebPage $oPage
	 * @param bool $bEditMode
	 * @param array $aExtraParams
	 * @return mixed
	 */
	public function Render($oPage, $bEditMode = false, $aExtraParams = array())
	{
		$sApiKey = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'api_key');
		$oPage->add_linked_script(sprintf('https://maps.googleapis.com/maps/api/js?key=%s', $sApiKey));
		
		$iDefaultLat = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_latitude');
		$iDefaultLng = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_longitude');
		$iZoom = utils::GetConfig()->GetModuleSetting('sv-geolocation', 'default_zoom');
		
		$sId = sprintf('map_%d%s', $this->sId, $bEditMode ? '_edit' : '' );
		$sStyle = sprintf("height: %dpx; background: url('/env-%s/sv-geolocation/images/world-map.jpg') 50%%/contain no-repeat;", $this->aProperties['height'], MetaModel::GetEnvironment());
		$oPage->add(sprintf('<div id="%s" class="dashlet-content" style="%s"></div>', $sId, $sStyle));
		
		$oFilter = DBObjectSearch::FromOQL("SELECT Location");
		$oSet = new DBObjectSet($oFilter);
		
		$aLocations = array();
		while ($oCurrObj = $oSet->Fetch())
		{
			if ($oCurrObj->Get('geo'))
			{
				$aLocations[] = array(
					'title' => $oCurrObj->GetName(),
					'icon' => $oCurrObj->GetIcon(false),
					'position' => $oCurrObj->Get('geo'),
					'tooltip' => static::GetTooltip($oCurrObj),
				);
			}
		}
		
		$oPage->add_script("
var ".$sId.";
$(function() {
	".$sId." = new google.maps.Map(document.getElementById('".$sId."'), {
		center: {lat: ".$iDefaultLat.", lng: ".$iDefaultLng."},
		zoom: ".$iZoom."
	});
	
	var locations = ".json_encode($aLocations)."
	
	locations.map(function(location, i){
		var marker = new google.maps.Marker({
			position: location.position,
			icon: location.icon,
			title: location.title,
			map: ".$sId."
		});
		
		var tooltip = new google.maps.InfoWindow({
          content: location.tooltip
        });
        
        marker.addListener('click', function() {
          tooltip.open(".$sId.", marker);
        });
	});
	
	
	
});");
	
	}
	
	/**
	 * Add properties fields
	 * @param DesignerForm $oForm
	 * @return mixed
	 */
	public function GetPropertiesFields(DesignerForm $oForm)
	{
		$oHeightField = new DesignerIntegerField('height', Dict::S('UI:DashletGeoMap:Prop-Height', 'Height'), $this->aProperties['height']);
		$oForm->AddField($oHeightField);
		
		if (is_null(static::$aAttributeList)) static::$aAttributeList = static::FindGeolocationAttributes();
		
		$oAttributeField = new DesignerComboField('attribute', 'attribute', $this->aProperties['attribute']);
		$oAttributeField->SetAllowedValues(static::$aAttributeList);
		$oForm->AddField($oAttributeField);
		
	}
	
	/**
	 * Dashlet info
	 * @return array
	 * @throws DictExceptionMissingString
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
		$sTooltip .= '<table><tbody>';
		foreach(MetaModel::GetZListItems($sClass, 'list') as $sAttCode)
		{
			$oAttDef = MetaModel::GetAttributeDef($sClass, $sAttCode);
			$sTooltip .= '<tr><td>'.$oAttDef->GetLabel().':&nbsp;</td><td>'.$oCurrObj->GetAsHtml($sAttCode).'</td></tr>';
		}
		$sTooltip .= '</tbody></table>';
		return $sTooltip;
	}
	
	protected static function FindGeolocationAttributes()
	{
		$aAttributes = array();
		foreach (MetaModel::GetClasses() as $sClass) foreach (MetaModel::ListAttributeDefs($sClass) as $sAttribute => $oAttributeDef)
		{
			if (is_a($oAttributeDef, 'AttributeGeolocation'))
			{
				$aAttributes[$sClass.'.'.$sAttribute] = MetaModel::GetName($sClass).' / '.$oAttributeDef->GetLabel();
			}
		}
		return $aAttributes;
	}
}
