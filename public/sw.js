const CACHE_NAME = 'smcl-cache-v1';
const ASSETS_TO_CACHE = [
  'index.php',
  'components/ui_head.php',
  'uploads/league_logo.png',
  'uploads/player_placeholder.jpg',
  'uploads/team_placeholder.jpg'
];

// Install Event
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
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
            return caches.delete(key);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Event (Network First, fallback to cache for offline support, bypass caching for APIs)
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  
  // Skip caching for live API endpoints or admin/manager panels that require real-time updates
  if (url.pathname.includes('/api/') || url.pathname.includes('setup.php')) {
    event.respondWith(fetch(event.request));
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then(response => {
        // Cache successful GET requests for static assets
        if (response.status === 200 && event.request.method === 'GET') {
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(event.request, responseClone);
          });
        }
        return response;
      })
      .catch(() => {
        // Fallback to cache if network fails
        return caches.match(event.request);
      })
  );
});
