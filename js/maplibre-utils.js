function make_interactive_map(oAttOptions, oMapOptions) {
    const oFieldInputZone = $('.attribute-edit[data-attcode="' + oAttOptions.code + '"] .field_input_zone');

    // hide input field
    const oFieldInput = oFieldInputZone.children('input');
    if (!oAttOptions.display) oFieldInput.prop('type','hidden');

    // get location
    const aLocation = oFieldInput.val().split(',').reverse();

    // create map
    const oMapDiv = $(document.createElement('div'));
    oMapDiv.width(oAttOptions.width);
    oMapDiv.height(oAttOptions.height);
    oMapDiv.css('background', 'white url('+ GetAbsoluteUrlModulesRoot() +'sv-geolocation/images/world-map.jpg) center/contain no-repeat');
    oFieldInputZone.prepend(oMapDiv);
    const oMap = new maplibregl.Map({
        container: oMapDiv[0],
        style: oMapOptions.style,
        center: (aLocation.length === 2) ? aLocation : oMapOptions.center,
        zoom: oMapOptions.zoom,
        attributionControl: false,
        dragRotate: false,
    });

    // Add fullscreen control to the map
    oMap.addControl(new maplibregl.FullscreenControl());

    // create marker
    const oMarker = new maplibregl.Marker({draggable: true});

    // pan map and place marker
    if (aLocation.length === 2) {
        oMarker.setLngLat(aLocation);
        oMarker.addTo(oMap);
    }

    // save marker location
    oMarker.on('dragend', () => {
        oMap.easeTo({center: oMarker.getLngLat()});
        map_save_location(oFieldInput, oMarker.getLngLat());
    });

    // remove marker
    $(oMarker.getElement()).on('click', (e) => {
        e.stopPropagation();
        oMarker.remove();
        map_save_location(oFieldInput);
    });

    // set marker to location
    oMap.on('click', (e) => {
        oMarker.setLngLat(e.lngLat)
        oMap.easeTo({center: e.lngLat});
        map_save_location(oFieldInput, e.lngLat);
        oMarker.addTo(oMap);
    });

    // set coordinates
    oFieldInput.on('change', () => {
        const aLocation = oFieldInput.val().split(',').reverse();

        if (aLocation.length === 2) {
            const oLocation = maplibregl.LngLat.convert(aLocation);

            oMarker.setLngLat(oLocation);
            oMap.easeTo({center: oLocation})
        } else {
            oMarker.remove();
        }
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

function map_save_location(oField, oLngLat) {
    if (oLngLat) oField.val(oLngLat.lat + ',' + oLngLat.lng);
    else oField.val('');
}

function render_geomap(oDashlet) {
    const oMap = new maplibregl.Map({
        container: oDashlet.id,
        style: oDashlet.style,
        center: maplibregl.LngLat.convert(oDashlet.center),
        zoom: oDashlet.zoom,
    });

    // Search not (yet) supported
    $('#' + oDashlet.id + '_panel').hide();

    // Add zoom and rotation controls to the map.
    oMap.addControl(new maplibregl.NavigationControl());
    // Add fullscreen control to the map
    oMap.addControl(new maplibregl.FullscreenControl());

    oMap.on('load', async () => {
        oMap.addSource('locations', {
            type: 'geojson',
            data: {
                type: 'FeatureCollection',
                features: oDashlet.locations.map((oLocation) => {
                    return {
                        type: 'Feature',
                        geometry: {
                            type: 'Point',
                            coordinates: maplibregl.LngLat.convert(oLocation.position).toArray(),
                        },
                        properties: oLocation
                    }
                })
            },
            cluster: true,
            clusterMaxZoom: oDashlet.zoom + 3, // Max zoom to cluster points on
            clusterRadius: 50 // Radius of each cluster when clustering points (defaults to 50)
        });

        // Load icons
        const oSvgManager = new maplibregl.SvgManager(oMap);
        try {
            if (oDashlet.classIcon.endsWith('.svg')) oSvgManager.add('default', oDashlet.classIcon, 32, 32);
            else {
                const oImage = await oMap.loadImage(oDashlet.classIcon);
                oMap.addImage('default', await window.createImageBitmap(oImage.data, {resizeWidth: 32, resizeHeight: 32}));
            }
        } catch (e) {}
        for (const oLocation of oDashlet.locations) {
            try {
                if (oLocation.icon.endsWith('.svg') && !oSvgManager.hasImage(oLocation.icon)) {
                    await oSvgManager.add(oLocation.icon, oLocation.icon, 32, 32);
                } else if (!oMap.hasImage(oLocation.icon)) {
                    const oImage = await oMap.loadImage(oLocation.icon);
                    oMap.addImage(oLocation.icon, await window.createImageBitmap(oImage.data, {resizeWidth: 32, resizeHeight: 32}));
                }
            } catch (e) {}
        }

        oMap.addLayer({
            id: 'clusters',
            type: 'circle',
            source: 'locations',
            filter: ['has', 'point_count'],
            paint: {
                // Use step expressions (https://maplibre.org/maplibre-style-spec/#expressions-step)
                // with three steps to implement three types of circles:
                //   * Blue, 20px circles when point count is less than 100
                //   * Yellow, 30px circles when point count is between 100 and 750
                //   * Pink, 40px circles when point count is greater than or equal to 750
                'circle-color': [
                    'step',
                    ['get', 'point_count'],
                    '#51bbd6',
                    100,
                    '#f1f075',
                    750,
                    '#f28cb1'
                ],
                'circle-radius': [
                    'step',
                    ['get', 'point_count'],
                    20,
                    100,
                    30,
                    750,
                    40
                ]
            }
        });

        oMap.addLayer({
            id: 'cluster-count',
            type: 'symbol',
            source: 'locations',
            filter: ['has', 'point_count'],
            layout: {
                'text-field': '{point_count_abbreviated}',
                'text-size': 12
            }
        });

        oMap.addLayer({
            id: 'unclustered-point',
            type: 'symbol',
            source: 'locations',
            filter: ['!', ['has', 'point_count']],
            layout: {
                'icon-overlap': 'cooperative',
                'icon-image': [
                    'coalesce',
                    ['image', ['get', 'icon']],
                    ['image', 'default']
                ],
                'text-allow-overlap': true,
                'text-field': ['get', 'title'],
                'text-variable-anchor': ['top', 'bottom', 'left', 'right'],
                'text-radial-offset': 1.1,
                'text-justify': 'auto',
            },
        });

        // inspect a cluster on click
        oMap.on('click', 'clusters', async (e) => {
            const aFeatures = oMap.queryRenderedFeatures(e.point, {
                layers: ['clusters']
            });
            const sClusterId = aFeatures[0].properties.cluster_id;
            const iZoom = await oMap.getSource('locations').getClusterExpansionZoom(sClusterId);
            oMap.easeTo({
                center: aFeatures[0].geometry.coordinates,
                zoom: iZoom
            });
        });

        oMap.on('mouseenter', 'clusters', () => {
            oMap.getCanvas().style.cursor = 'pointer';
        });
        oMap.on('mouseleave', 'clusters', () => {
            oMap.getCanvas().style.cursor = '';
        });

        // When a click event occurs on a feature in
        // the unclustered-point layer, open a popup at
        // the location of the feature, with
        // description HTML from its properties.
        oMap.on('click', 'unclustered-point', (e) => {
            const aCoordinates = e.features[0].geometry.coordinates.slice();

            // Ensure that if the map is zoomed out such that
            // multiple copies of the feature are visible, the
            // popup appears over the copy being pointed to.
            while (Math.abs(e.lngLat.lng - aCoordinates[0]) > 180) {
                aCoordinates[0] += e.lngLat.lng > aCoordinates[0] ? 360 : -360;
            }

            if (e.features[0].properties.summary){
                $.ajax(e.features[0].properties.summary, {
                    success: function (sData) {
                        const oMarker = new maplibregl.Marker()
                            .setLngLat(aCoordinates)
                            .addTo(oMap);
                        $(oMarker.getElement()).html(sData).on('mouseleave',() => { oMarker.remove(); });
                    }
                });
            } else new maplibregl.Popup()
                .setLngLat(aCoordinates)
                .setHTML(e.features[0].properties.tooltip)
                .addTo(oMap);
        });
    });

    let popupDelay = undefined;
    oMap.on('mouseenter', 'unclustered-point', (e) => {
        oMap.getCanvas().style.cursor = 'pointer';

        if (e.features[0].properties.summary){
            const aCoordinates = e.features[0].geometry.coordinates.slice();
            while (Math.abs(e.lngLat.lng - aCoordinates[0]) > 180) {
                aCoordinates[0] += e.lngLat.lng > aCoordinates[0] ? 360 : -360;
            }

            popupDelay = setTimeout(() => {
                $.ajax(e.features[0].properties.summary, {
                    success: function (sData) {
                        const oMarker = new maplibregl.Marker()
                            .setLngLat(aCoordinates)
                            .addTo(oMap);
                        $(oMarker.getElement()).html(sData).on('mouseleave',() => { oMarker.remove(); });
                    }
                });
            }, 800);
        }
    });
    oMap.on('mouseleave', 'unclustered-point', () => {
        oMap.getCanvas().style.cursor = '';
        clearTimeout(popupDelay);
    });

    // add create object functionality
    if (oDashlet.createUrl) {
        const oMarker = new maplibregl.Marker({draggable: true});
        const oMarkerElement = $(oMarker.getElement());
        oMarkerElement.attr('title', Dict.Format('UI:ClickToCreateNew', oDashlet.classLabel));
        oMarkerElement.css('cursor', 'copy');

        oMarkerElement.on('click', () => {
            window.location = AddAppContext(oDashlet.createUrl + oMarker.getLngLat().toArray().reverse());
        })

        oMap.on('contextmenu', (e) => {
            oMarker.setLngLat(e.lngLat);
            oMarker.addTo(oMap);
        });
    }
}
