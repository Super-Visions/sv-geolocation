function make_interactive_map(oAttOptions, oMapOptions) {
    const oFieldInputZone = $('.attribute-edit[data-attcode="' + oAttOptions.code + '"] .field_input_zone');

    // hide input field
    const oFieldInput = oFieldInputZone.children('input');
    if (!oAttOptions.display) oFieldInput.prop('type','hidden');

    // get location
    const aLocation = oFieldInput.val().split(',');
    const oLocation = (aLocation.length === 2) ? new google.maps.LatLng(aLocation[0], aLocation[1]) : null;

    // create map
    const oMapDiv = $(document.createElement('div'));
    oMapDiv.width(oAttOptions.width);
    oMapDiv.height(oAttOptions.height);
    oMapDiv.css('background', 'white url('+ GetAbsoluteUrlModulesRoot() +'sv-geolocation/images/world-map.jpg) center/contain no-repeat');
    oFieldInputZone.prepend(oMapDiv);
    const oMap = new google.maps.Map(oMapDiv[0], oMapOptions);

    // create marker
    const oMarker = new google.maps.marker.AdvancedMarkerElement({
        gmpDraggable: true,
        position: oLocation,
    });

    // pan map and place marker
    if (oLocation) {
        oMap.setCenter(oLocation);
        oMarker.map = oMap;
    }

    // save marker location
    oMarker.addListener('dragend', function() {
        oMap.panTo(oMarker.position);
        map_save_location(oFieldInput, new google.maps.LatLng(oMarker.position));
    });

    // remove marker
    oMarker.addListener('click', function(){
        oMarker.map = null;
        map_save_location(oFieldInput);
    });

    // set marker to location
    oMap.addListener('click', function(event) {
        oMarker.position = event.latLng;
        oMap.panTo(event.latLng);
        map_save_location(oFieldInput, event.latLng);
        oMarker.map = oMap;
    });

    // set coordinates
    oFieldInput.on('change', function() {
        const aLocation = oFieldInput.val().split(',');
        const oLocation = (aLocation.length === 2) ? new google.maps.LatLng(aLocation[0], aLocation[1]) : null;

        oMarker.position = oLocation;
        oMap.panTo(oLocation);
    });

    // recover after DOM rewrites
    const oObserverField = oFieldInputZone.parents('.field_value,.ibo-field--value').get(0);
    const oObserver = new MutationObserver(function (aMutations, oObserver) {
        const oAddedNode = aMutations.shift().addedNodes.item(0);

        if (oAddedNode && oAddedNode.id === oObserverField.firstElementChild.id) {
            oObserver.disconnect();

            oMapOptions.center = oMap.getCenter();
            oMapOptions.zoom = oMap.getZoom();

            make_interactive_map(oAttOptions, oMapOptions);
        }
    });
    oObserver.observe(oObserverField, {childList: true, subtree: true})
}

function map_save_location(oField, oLatLng) {
    if (oLatLng) oField.val(oLatLng.toUrlValue());
    else oField.val('');
}

function render_geomap(oDashlet, aLocations) {
    const oMap = new google.maps.Map(document.getElementById(oDashlet.id), {
        mapId: oDashlet.id,
        center: oDashlet.center,
        zoom: oDashlet.zoom,
    });
    const oGeo = new google.maps.Geocoder();

    oDashlet.locations.map((oLocation) => {
        const oIcon = $(document.createElement('img'));
        oIcon.attr('src', oLocation.icon);
        oIcon.css({'max-height': '48px'});

        const oMarker = new google.maps.marker.AdvancedMarkerElement({
            position: oLocation.position,
            content: oIcon[0],
            title: oLocation.title,
            map: oMap
        });

        const oTooltip = new google.maps.InfoWindow({
            content: oLocation.tooltip
        });

        oMarker.addListener('click', function () {
            oTooltip.open(oMap, oMarker);
        });
    });

    // add additional marker
    const oMarker = new google.maps.marker.AdvancedMarkerElement({
        gmpDraggable: true,
        title: Dict.Format('UI:ClickToCreateNew', oDashlet.classLabel),
    });

    // add create object functionality
    if (oDashlet.createUrl) {
        $(oMarker.content).css('cursor', 'copy');

        // create object
        oMarker.addListener('click', function () {
            window.location = AddAppContext(oDashlet.createUrl + new google.maps.LatLng(oMarker.position).toUrlValue());
        });

        // set marker to location
        oMap.addListener('rightclick', function (event) {
            oMarker.position = event.latLng;
            oMap.panTo(event.latLng);
            oMarker.map = oMap;
        });
    }

    // search location
    $('#' + oDashlet.id + '_submit').on('click', () => {
        const sAddress = $('#' + oDashlet.id + '_address').val();
        oGeo.geocode({address: sAddress, bounds: oMap.getBounds()}, (results, status) => {
            if (status === 'OK') {
                oMap.setCenter(results[0].geometry.location);
                oMarker.position = results[0].geometry.location;
                oMarker.map = oMap;
            } else {
                console.log('Geocode was not successful for the following reason: ' + status);
            }
        });
    });
}
