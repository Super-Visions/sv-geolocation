Geolocation
===========

The module `sv-geolocation` is an [iTop](https://www.combodo.com/itop-193) extension to add a new attribute type called `AttributeGeolocation` to provide geographic coordinates.
This new attribute type is added to the `Location` class as a new field.

Installation
------------

Place this in the `extensions` folder of your iTop instance and run iTop setup again.
Be sure to enable the extension during setup.

Configuration
-------------

After installation, it is required to set the correct `staticmapurl` value in order to have map locations displayed on a map.
This value depends on which map provider (Google, OSM, ...) you want to use.
Depending on each map provider, there are several paremeters to give.
The following parameters will be filled in:

1. Latitude from the object.
2. Longitude from the object.
3. width from datamodel.
4. height from datamodel.

### Google Static Maps API

To use static maps from Google Maps, you will need to [acquire an API key](https://developers.google.com/maps/documentation/static-maps/get-api-key).
An example value for `staticmapurl` could be:
`https://maps.googleapis.com/maps/api/staticmap?markers=%f,%f&size=%dx%d&key=YOUR_API_KEY`.
For more information, see [documentation](https://developers.google.com/maps/documentation/static-maps/intro).

### OpenStreetMap

OpenStreetMap itself does not provide static map images, but there are some 3rd party services listed on the [Wiki page](https://wiki.openstreetmap.org/wiki/Static_map_images).
Some of these require an API key while others don't, but might be slower.

Example values for `staticmapurl`:

* [staticMapLite](https://wiki.openstreetmap.org/wiki/StaticMapLite):
`http://staticmap.openstreetmap.de/staticmap.php?center=%1$f,%2$f&markers=%1$f,%2$f,red-pushpin&size=%3$dx%4$d&zoom=17`
* [MapQuest](https://developer.mapquest.com/documentation/static-map-api/v5/):
`https://www.mapquestapi.com/staticmap/v5/map?locations=%f,%f&size=%d,%d&zoom=17&key=YOUR_API_KEY`

XML Data Model Reference
------------------------

### Definition

* sql _(mandatory)_
The column used to store the value into the MySQL database.
* default_value _(mandatory)_
The default value (can be specified as an empty string).
* is_null_allowed _(mandatory)_
Set to "true" to let users leave this value undefined, false otherwise.
* width _(optional)_
Width of the static image, in pixels.
Defaults to 200.
* height _(optional)_
Height of the static image, in pixels.
Defaults to 150.

### Example

```xml
<field id="geo" xsi:type="AttributeGeolocation">
    <sql>geo</sql>
    <default_value/>
    <is_null_allowed>true</is_null_allowed>
    <width>200</width>
    <height>150</height>
</field>
```

Preview
-------

### Location detail view
![Location detail](images/preview-location-detail.png "Properties tab of location Paris from example data")

### Location list view
![Location list](images/preview-location-list.png "List view of locations from example data")
