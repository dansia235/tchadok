/**
 * Service Worker pour Tchadok Platform
 * Progressive Web App (PWA)
 * @author Tchadok Team
 * @version 1.0
 */

const CACHE_NAME = 'tchadok-v1.0.0';
const CACHE_URLS = [
    '/',
    '/index.php',
    '/login.php',
    '/register.php',
    '/blog.php',
    '/assets/css/main.css',
    '/assets/js/main.js',
    '/assets/images/logo.png',
    '/assets/images/default-avatar.png',
    '/assets/images/default-cover.jpg',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'https://code.jquery.com/jquery-3.7.1.min.js'
];

// Installation du Service Worker
self.addEventListener('install', event => {
    console.log('🎵 Tchadok Service Worker: Installation en cours...');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('📁 Cache ouvert, mise en cache des ressources...');
                return cache.addAll(CACHE_URLS.map(url => {
                    // Convertir les URLs relatives en URLs absolues
                    if (url.startsWith('/')) {
                        return new Request(url, { mode: 'no-cors' });
                    }
                    return url;
                }));
            })
            .then(() => {
                console.log('✅ Toutes les ressources ont été mises en cache');
                self.skipWaiting();
            })
            .catch(error => {
                console.warn('⚠️ Erreur lors de la mise en cache:', error);
            })
    );
});

// Activation du Service Worker
self.addEventListener('activate', event => {
    console.log('🚀 Tchadok Service Worker: Activation...');
    
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('🗑️ Suppression de l\'ancien cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => {
            console.log('✅ Service Worker activé et prêt');
            self.clients.claim();
        })
    );
});

// Interception des requêtes réseau
self.addEventListener('fetch', event => {
    // Ignorer les requêtes non-GET
    if (event.request.method !== 'GET') {
        return;
    }
    
    // Ignorer les requêtes vers des domaines externes spécifiques
    const url = new URL(event.request.url);
    if (url.hostname !== self.location.hostname && 
        !url.hostname.includes('cdn.jsdelivr.net') && 
        !url.hostname.includes('cdnjs.cloudflare.com') &&
        !url.hostname.includes('code.jquery.com')) {
        return;
    }
    
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                // Retourner depuis le cache si disponible
                if (response) {
                    console.log('📄 Depuis le cache:', event.request.url);
                    return response;
                }
                
                // Sinon, aller chercher sur le réseau
                return fetch(event.request)
                    .then(response => {
                        // Vérifier que la réponse est valide
                        if (!response || response.status !== 200 || response.type !== 'basic') {
                            return response;
                        }
                        
                        // Cloner la réponse car elle ne peut être consommée qu'une fois
                        const responseToCache = response.clone();
                        
                        // Mettre en cache pour les prochaines fois
                        caches.open(CACHE_NAME)
                            .then(cache => {
                                cache.put(event.request, responseToCache);
                            });
                        
                        return response;
                    })
                    .catch(error => {
                        console.warn('🌐 Erreur réseau pour:', event.request.url, error);
                        
                        // Retourner une page hors ligne pour les pages HTML
                        if (event.request.headers.get('accept').includes('text/html')) {
                            return new Response(`
                                <!DOCTYPE html>
                                <html lang="fr">
                                <head>
                                    <meta charset="UTF-8">
                                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                    <title>Hors ligne - Tchadok</title>
                                    <style>
                                        body { 
                                            font-family: Arial, sans-serif; 
                                            text-align: center; 
                                            padding: 50px; 
                                            background: #f8f9fa;
                                        }
                                        .offline-container {
                                            max-width: 400px;
                                            margin: 0 auto;
                                            padding: 30px;
                                            background: white;
                                            border-radius: 10px;
                                            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                                        }
                                        .offline-icon {
                                            font-size: 4rem;
                                            color: #0066CC;
                                            margin-bottom: 20px;
                                        }
                                        h1 { color: #0066CC; }
                                        .retry-btn {
                                            background: #0066CC;
                                            color: white;
                                            border: none;
                                            padding: 10px 20px;
                                            border-radius: 5px;
                                            cursor: pointer;
                                            margin-top: 20px;
                                        }
                                    </style>
                                </head>
                                <body>
                                    <div class="offline-container">
                                        <div class="offline-icon">🎵</div>
                                        <h1>Tchadok</h1>
                                        <h2>Mode Hors Ligne</h2>
                                        <p>Vous êtes actuellement hors ligne. Vérifiez votre connexion internet et réessayez.</p>
                                        <button class="retry-btn" onclick="window.location.reload()">
                                            Réessayer
                                        </button>
                                        <p style="margin-top: 20px; font-size: 0.9em; color: #666;">
                                            La musique tchadienne à portée de clic
                                        </p>
                                    </div>
                                </body>
                                </html>
                            `, {
                                headers: { 'Content-Type': 'text/html' }
                            });
                        }
                        
                        // Pour les autres types de requêtes, lever l'erreur
                        throw error;
                    });
            })
    );
});

// Gestion des messages depuis l'application principale
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

// Notification de mise à jour disponible
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'GET_VERSION') {
        event.ports[0].postMessage({ version: CACHE_NAME });
    }
});

console.log('🎵 Tchadok Service Worker chargé et prêt !');