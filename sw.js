const CACHE_NAME = 'sudha-crm-cache-v1';
const ASSETS_TO_CACHE = [
  'dashboard.php',
  'assets/css/style.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap',
  'assets/images/logo.png'
];

// Install Event
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      console.log('Caching essential assets...');
      return cache.addAll(ASSETS_TO_CACHE);
    }).then(() => self.skipWaiting())
  );
});

// Activate Event
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.map(key => {
          if (key !== CACHE_NAME) {
            console.log('Clearing old cache:', key);
            return caches.delete(key);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Event
self.addEventListener('fetch', event => {
  // Only intercept GET requests
  if (event.request.method !== 'GET') return;

  // Do not intercept external API calls or non-http requests
  if (!event.request.url.startsWith(self.location.origin) && !event.request.url.startsWith('https://fonts.gstatic.com') && !event.request.url.startsWith('https://cdnjs.cloudflare.com')) return;

  event.respondWith(
    fetch(event.request)
      .then(networkResponse => {
        // If successful and is static asset, cache it dynamically
        const url = new URL(event.request.url);
        if (
          url.pathname.includes('/assets/') ||
          url.pathname.endsWith('.css') ||
          url.pathname.endsWith('.js') ||
          url.pathname.endsWith('.png') ||
          url.pathname.endsWith('.jpg') ||
          url.pathname.endsWith('.jpeg') ||
          url.pathname.endsWith('.woff2')
        ) {
          caches.open(CACHE_NAME).then(cache => {
            cache.put(event.request, networkResponse.clone());
          });
        }
        return networkResponse;
      })
      .catch(() => {
        // Offline Fallback: Serve from cache
        return caches.match(event.request).then(cachedResponse => {
          if (cachedResponse) {
            return cachedResponse;
          }
          // If offline and request is HTML/PHP page, fallback to index.php
          if (event.request.headers.get('accept') && event.request.headers.get('accept').includes('text/html')) {
            return caches.match('dashboard.php');
          }
        });
      })
  );
});
