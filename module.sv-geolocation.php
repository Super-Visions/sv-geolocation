<?php
//
// iTop module definition file
//

SetupWebPage::AddModule(
	__FILE__, // Path to the current file, all other file names are relative to the directory containing this file
	'sv-geolocation/1.9.2',
	array(
		// Identification
		//
		'label' => 'Geolocation',
		'category' => 'business',

		// Setup
		//
		'dependencies' => array(
			'(itop-config-mgmt/2.5.0 & itop-config-mgmt/<3.0.0)||itop-structure/3.0.0||combodo-location-hierarchy/1.0.0',
		),
		'mandatory' => false,
		'visible' => true,

		// Components
		//
		'datamodel' => array(
			'main.sv-geolocation.php',
			'geomap.class.inc.php',
			'interactiveform.class.inc.php',
		),
		'webservice' => array(

		),
		'data.struct' => array(
			// add your 'structure' definition XML files here,
		),
		'data.sample' => array(
			'data.sample.locations.xml',
		),

		// Documentation
		//
		'doc.manual_setup' => '', // hyperlink to manual setup documentation, if any
		'doc.more_information' => '', // hyperlink to more information, if any

		// Default settings
		//
		'settings' => array(
			'provider' => 'GoogleMaps',
			'input_type' => 'provider',
			'api_key' => null,
			'default_latitude' => 45.157389,
			'default_longitude' => 5.748830,
			'default_zoom' => 17,
			'staticmapurl' => null,
			'display_coordinates' => true,
		),
	)
);

?>
