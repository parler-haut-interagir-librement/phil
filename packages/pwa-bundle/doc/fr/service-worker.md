<!-- phil-dev source-sha256=303b74f97aa0100bab292b5bce9fa509943974a6e58c94d298d2f16a551ba3e7 -->
# Le service worker

## Ce qui est servi

`/sw.js` répond :

```js
// The service worker of phil/pwa-bundle: its settings, then its code.
self.philPwa = {"version":"3f9a…","cache":"phil-pwa","scope":"/","precache":["/offline","/assets/styles/app-a1b2.css","/"],"offline":"/offline","rules":[{"prefix":"/assets/","strategy":"cache-first"}],"exclude":["/_profiler/","/_wdt/"],"script":"/assets/@phil/pwa-bundle/service_worker-c3d4.js"};
importScripts("/assets/@phil/pwa-bundle/service_worker-c3d4.js");
```

Le code du worker, `assets/src/service_worker.js`, est un script de worker classique du bundle, servi par AssetMapper
comme toute ressource — compilé dans `public/assets/` en production, à une adresse qui change avec son contenu. Les
réglages viennent de la configuration, des routes et des fournisseurs de préchargement.

## Préchargement et versions

La liste de préchargement contient, chacun une fois et 200 au plus :

1. la page hors ligne, `/offline`, sauf si `offline` est faux ;
2. `service_worker.precache`, les ressources à leur adresse versionnée ;
3. les chemins de chaque `PrecacheProvider` ([Intégrations](integrations.md)).

La `version` est une empreinte de tous les réglages, ces adresses comprises. Un déploiement qui change une ressource, un
chemin ou une règle change la version : le navigateur voit un nouveau `/sw.js`, installe le nouveau worker, qui met la
liste en cache dans `<cache>-<version>`, récupérée en contournant le cache HTTP. Une fois actif, il supprime les caches
`<cache>-…` des autres versions et prend le contrôle des pages ouvertes. Un nouveau worker attend tant que des pages
utilisent l'ancien, jusqu'à ce que la page lui demande de prendre le relais — l'action `update` du contrôleur.

## Répondre aux requêtes

Le worker ne répond qu'aux requêtes `GET` de l'origine du site, dans sa portée, hors de `exclude` :

| Requête | Stratégie |
|---|---|
| Une navigation — une page ouverte | Réseau d'abord : le réseau, sinon la page en cache, sinon la page hors ligne, sinon une erreur réseau. |
| Un chemin dont le préfixe a une règle | La première règle dont le préfixe commence le chemin : `cache-first` — le cache, sinon le réseau, conservé —, `network-first` — le réseau, conservé, sinon le cache —, ou `network-only`. |
| Toute autre | Le réseau, comme s'il n'y avait pas de worker. |

Une réponse n'est conservée que si c'est un succès du site lui-même dont le `Cache-Control` n'a ni `no-store` ni
`private`. Une page destinée à une seule personne — un compte, un panier — envoie `private`, comme le fait Symfony pour
une page qui lit la session, et n'est jamais conservée sur un appareil partagé. Les réponses ne sont pas rafraîchies en
arrière-plan : une ressource en cache change avec son adresse versionnée.

## La page hors ligne

`/offline` est une page du bundle dans `layout`, en anglais et en français à partir du domaine de traduction
`phil_pwa` : un titre, une phrase avec le nom du site, et un lien vers `start_url` pour réessayer. Elle ne porte aucun
script. Remplacez `@PhilPwa/offline.html.twig` pour la changer.

## En-têtes, portée et CSP

| Réponse | En-têtes |
|---|---|
| `/sw.js` | `Content-Type: text/javascript`, `Cache-Control: no-cache` — le navigateur vérifie le worker à chaque visite —, `X-Content-Type-Options: nosniff`, et `Service-Worker-Allowed: <scope>` seulement quand la portée est plus large que le répertoire de `/sw.js`. |
| `/manifest.webmanifest`, `/offline` | `Cache-Control: public, max-age=<cache_max_age>`, `X-Content-Type-Options: nosniff`. |

Aucune réponse ne lit la session ni ne pose de témoin. La portée est un répertoire du site, vérifié comme tout chemin ;
le worker ne répond jamais à une autre origine.

Avec une politique de sécurité du contenu (CSP), l'origine du site doit être permise pour le worker et le manifeste :
`worker-src 'self'` et `manifest-src 'self'`, ce que la politique par défaut de `phil/browser-security-bundle` donne
déjà par `default-src 'self'`. L'`importScripts()` du worker charge son code depuis le site ; rien n'est en ligne dans
une page, donc aucun nonce n'est nécessaire. Avec un CDN devant AssetMapper, ajoutez son origine à `worker-src`.
