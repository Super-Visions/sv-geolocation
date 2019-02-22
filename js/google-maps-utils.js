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