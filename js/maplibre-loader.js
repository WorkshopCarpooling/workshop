(() => {
  let mapLibrePromise;

  window.loadMapLibre = function loadMapLibre() {
    if (!mapLibrePromise) {
      mapLibrePromise = import('https://unpkg.com/maplibre-gl@6.15.0/dist/maplibre-gl.mjs')
        .then((maplibregl) => {
          window.maplibregl = maplibregl;
          return maplibregl;
        })
        .catch((error) => {
          mapLibrePromise = null;
          throw error;
        });
    }
    return mapLibrePromise;
  };

  window.createOpenStreetMapStyle = function createOpenStreetMapStyle() {
    return {
      version: 8,
      sources: {
        openstreetmap: {
          type: 'raster',
          tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
          tileSize: 256,
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>',
          maxzoom: 19
        }
      },
      layers: [{ id: 'openstreetmap-tiles', type: 'raster', source: 'openstreetmap' }]
    };
  };
})();
