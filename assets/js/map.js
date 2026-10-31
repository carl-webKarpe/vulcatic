// Leaflet map helpers shared by the customer and vulcator screens.
const VMap = {
  // Placeholder centre until we know where the user is (Manila).
  fallback: [14.5995, 120.9842],

  create(el, center, zoom = 15) {
    const map = L.map(el, { zoomControl: false, attributionControl: true }).setView(center || VMap.fallback, zoom);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);
    L.control.zoom({ position: 'bottomright' }).addTo(map);
    return map;
  },

  icon(kind, label = '') {
    const html = {
      me:     '<span class="pin-me"><span></span></span>',
      vulc:   '<span class="pin-vulc"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/></svg></span>',
      shop:   '<span class="pin-shop"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"><path d="M4 10l8-6 8 6v10H4z M9 20v-6h6v6"/></svg></span>',
      off:    '<span class="pin-off"></span>',
      avatar: '<span class="pin-avatar">' + label + '</span>',
    }[kind];
    const size = kind === 'me' ? 26 : kind === 'avatar' ? 40 : 34;
    return L.divIcon({ className: 'vpin', html, iconSize: [size, size], iconAnchor: [size / 2, size / 2] });
  },

  // Fit the view around a set of [lat, lng] points.
  fit(map, points, maxZoom = 16) {
    const pts = points.filter(Boolean);
    if (pts.length === 1) map.setView(pts[0], maxZoom);
    else if (pts.length > 1) map.fitBounds(L.latLngBounds(pts), { padding: [60, 60], maxZoom });
  },

  // Ask the browser for the position once.
  locate(opts = {}) {
    return new Promise((resolve, reject) => {
      if (!navigator.geolocation) return reject(new Error('Location is not supported on this browser.'));
      navigator.geolocation.getCurrentPosition(
        (p) => resolve([p.coords.latitude, p.coords.longitude]),
        () => reject(new Error('Location is off. Allow location access, or tap the map to set your spot.')),
        { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000, ...opts }
      );
    });
  },
};
