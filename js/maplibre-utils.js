function render_geomap(oDashlet, aLocations) {
    const oMap = new maplibregl.Map({
        container: oDashlet.id,
        style: 'https://api.maptiler.com/maps/bright/style.json?key=' + oDashlet.map.key,
        center: oDashlet.map.center,
        zoom: oDashlet.map.zoom,
    });

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
            clusterMaxZoom: oDashlet.map.zoom + 3, // Max zoom to cluster points on
            clusterRadius: 50 // Radius of each cluster when clustering points (defaults to 50)
        });

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
            type: 'circle',
            source: 'locations',
            filter: ['!', ['has', 'point_count']],
            paint: {
                'circle-color': '#ea7d1e',
                'circle-radius': 4,
                'circle-stroke-width': 1,
                'circle-stroke-color': '#fff'
            }
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

            new maplibregl.Popup()
                .setLngLat(aCoordinates)
                .setHTML(e.features[0].properties.tooltip)
                .addTo(oMap);
        });
    });

    const oMarker = new maplibregl.Marker({draggable: true});

    oMap.on('contextmenu', (e) => {
        oMarker.setLngLat(e.lngLat);
        oMarker.addTo(oMap);

        const oCreate = new DOMParser().parseFromString(oDashlet.create, "text/xml").firstChild;
        const sURL = oCreate.getAttribute('href');
        oCreate.setAttribute('href', sURL + e.lngLat.toArray().reverse());

        oMarker.setPopup(new maplibregl.Popup().setHTML(oCreate.outerHTML));
    });

    oMarker.on('dragend', () => {
        const oCreate = new DOMParser().parseFromString(oDashlet.create, "text/xml").firstChild;
        const sURL = oCreate.getAttribute('href');
        oCreate.setAttribute('href', sURL + oMarker.getLngLat().toArray().reverse());

        oMarker.setPopup(new maplibregl.Popup().setHTML(oCreate.outerHTML));
    });
}
