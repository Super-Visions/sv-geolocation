function make_interactive_map(oAttOptions, oMapOptions) {
    var oFieldInputZone = $('.attribute-edit[data-attcode="' + oAttOptions.code + '"] .field_input_zone');

    // hide or disable input field
    var oFieldInput = oFieldInputZone.children();
    if (oAttOptions.display) oFieldInput.prop('disabled', true);
    else oFieldInput.prop('type','hidden');

    // get location
    var aLocation = oFieldInput.val().split(',');
    var oLocation = (aLocation.length == 2) ? new google.maps.LatLng(aLocation[0], aLocation[1]) : null;

    // create map
    var oMapDiv = $(document.createElement('div'));
    oMapDiv.width(oAttOptions.width);
    oMapDiv.height(oAttOptions.height);
    oMapDiv.css('background', 'white url('+ GetAbsoluteUrlModulesRoot() +'sv-geolocation/images/world-map.jpg) center/contain no-repeat');
    oFieldInputZone.prepend(oMapDiv);
    var oMap = new google.maps.Map(oMapDiv.get(0), oMapOptions);

    // create marker
    var oMarker = new google.maps.Marker({
        map: null,
        draggable: true,
        position: oLocation,
    });

    // pan map and place marker
    if (oLocation) {
        oMap.setCenter(oLocation);
        oMarker.setMap(oMap);
    }

    // save marker location
    oMarker.addListener('dragend', function () {
        map.panTo(oMarker.position);
        map_save_location(oFieldInput, oMarker.position);
    });

    // remove marker
    oMarker.addListener('click', function(){
        oMarker.setMap();
        map_save_location(oFieldInput);
    });

    // set marker to location
    oMap.addListener( 'click', function (event) {
        oMarker.setPosition(event.latLng);
        oMap.panTo(event.latLng);
        map_save_location(oFieldInput, event.latLng);
        oMarker.setMap(oMap);
    });

    // recover after DOM rewrites
    var oObserverField = oFieldInputZone.parents('.field_value').get(0);
    var oObserver = new MutationObserver(function (aMutations, oObserver) {
        var oAddedNode = aMutations.shift().addedNodes.item(0);

        if (oAddedNode && oAddedNode.id == oObserverField.firstElementChild.id) {
            oObserver.disconnect();

            oMapOptions.center = oMap.getCenter();
            oMapOptions.zoom = oMap.getZoom();

            make_interactive_map(oAttOptions, oMapOptions);
        }
    });
    oObserver.observe(oObserverField, {childList: true, subtree: true})
}

function map_save_location(oField, oLatLng) {
    if (oLatLng) oField.val(oLatLng.lat() + ',' + oLatLng.lng());
    else oField.val('');
}

function render_geomap(oDashlet, aLocations) {
    var oMap = new google.maps.Map(document.getElementById(oDashlet.id), oDashlet.map);
    var oGeo = new google.maps.Geocoder();

    oDashlet.locations.map(function (oLocation) {
        var oMarker = new google.maps.Marker({
            position: oLocation.position,
            icon: oLocation.icon,
            title: oLocation.title,
            map: oMap
        });

        var oTooltip = new google.maps.InfoWindow({
            content: oLocation.tooltip
        });

        oMarker.addListener('click', function () {
            oTooltip.open(oMap, oMarker);
        });
    });

    // add additional marker
    var oMarker = new google.maps.Marker();

    // add create object functionality
    if (oDashlet.createUrl) {
        oMarker.setDraggable(true);
        oMarker.setTitle(Dict.Format('UI:ClickToCreateNew', oDashlet.classLabel));
        oMarker.setCursor('copy');

        // create object
        oMarker.addListener('click', function () {
            window.location = AddAppContext(oDashlet.createUrl + oMarker.getPosition().toUrlValue());
        });
    }

    // remove marker
    oMarker.addListener('rightclick', function () {
        oMarker.setMap();
    });

    // set marker to location
    oMap.addListener('click', function (event) {
        oMarker.setPosition(event.latLng);
        oMap.panTo(event.latLng);
        oMarker.setMap(oMap);
    });

    // search location
    document.getElementById(oDashlet.id + '_submit').addEventListener('click', function () {
        var sAddress = document.getElementById(oDashlet.id + '_address').value;
        oGeo.geocode({address: sAddress, bounds: oMap.getBounds()}, function (results, status) {
            if (status === 'OK') {
                oMap.setCenter(results[0].geometry.location);
                oMarker.setPosition(results[0].geometry.location);
                oMarker.setMap(oMap);
            } else {
                console.log('Geocode was not successful for the following reason: ' + status);
            }
        });
    });
}
