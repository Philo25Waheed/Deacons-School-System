const CACHE_NAME = 'deacons-school-cache-v1';
const urlsToCache = [
  'assets/css/style.css',
  'assets/js/main.js',
  'assets/js/dynamic-dropdowns.js',
  'assets/js/qr-scanner.js',
  'assets/js/qrcode.min.js'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (response) {
          return response;
        }
        return fetch(event.request);
      })
  );
});
