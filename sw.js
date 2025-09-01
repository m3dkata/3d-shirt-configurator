const CACHE_NAME = 'shirt-configurator-v1';
const CACHE_DURATION = 60 * 1000; // 1 minute in milliseconds
const urlsToCache = [
  '/models/men1.glb',
  '/models/men2.glb',
  '/models/men3.glb',
  '/models/women1.glb',
  '/models/women2.glb',
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
  );
});

self.addEventListener('fetch', event => {
  // Skip non-GET requests completely
  if (event.request.method !== 'GET') {
    return;
  }
  
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (response) {
          const fetchDate = response.headers.get('sw-fetched-on');
          if (fetchDate) {
            const age = Date.now() - parseInt(fetchDate);
            if (age > CACHE_DURATION) {
              return fetchAndCache(event.request).catch(() => response);
            }
          }
          return response;
        }
        return fetchAndCache(event.request).catch(error => {
          console.error('Fetch failed:', error);
          // Return a fallback response or let the error propagate
          // For now, we'll just let the error propagate
          throw error;
        });
      })
  );
});

function fetchAndCache(request) {
  // Double-check that we're only dealing with GET requests
  if (request.method !== 'GET') {
    return fetch(request);
  }
  
  return fetch(request)
    .then(response => {
      // Don't cache bad responses or non-basic responses
      if (!response || response.status !== 200 || response.type !== 'basic') {
        return response;
      }
      
      // Clone the response so we can modify it for caching
      const responseToCache = response.clone();
      
      // Store in cache with timestamp
      caches.open(CACHE_NAME)
        .then(cache => {
          const headers = new Headers(responseToCache.headers);
          headers.append('sw-fetched-on', Date.now());
          const responseWithDate = new Response(responseToCache.body, {
            status: responseToCache.status,
            statusText: responseToCache.statusText,
            headers: headers
          });
          
          // Final safety check before putting in cache
          if (request.method === 'GET') {
            cache.put(request, responseWithDate);
          }
        })
        .catch(error => {
          console.error('Caching error:', error);
          // We don't rethrow here because we still want to return the response
        });
      
      return response;
    })
    .catch(error => {
      console.error('Fetch failed:', error, 'URL:', request.url);
      throw error; // Rethrow so the caller can handle it
    });
}
