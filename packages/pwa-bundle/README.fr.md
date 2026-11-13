<!-- phil-dev source-sha256=fcde137be5dff8114e4513f19ef1ac1390b3e4f9b6430584ba64e67397f521d5 -->
# phil/pwa-bundle

**Un site installable et lisible hors ligne : un manifeste produit à partir de la configuration, un court service
worker maison servi par AssetMapper, une page hors ligne, et des contrôleurs Stimulus pour installer, mettre à jour,
passer en plein écran et présenter sur un second écran — sans Workbox, sans étape de construction.**

phil/pwa-bundle sert le **manifeste d'application Web** du site à `/manifest.webmanifest`, écrit à partir de la
configuration : noms, identité, début et portée, `display` et `display_override` — plein écran compris —, couleurs,
langue, icônes versionnées par AssetMapper, raccourcis et catégories. Chaque membre est vérifié à la construction du
conteneur.

Il sert le **service worker** à `/sw.js` : quelques lignes de réglages, puis le code du worker, un script du bundle
servi par AssetMapper à son adresse versionnée. Le worker met en cache une **liste de préchargement versionnée** à son
installation — la page hors ligne, les chemins et les ressources de la configuration, ceux des autres bundles —,
supprime les caches des versions plus anciennes à son activation, répond aux navigations **d'abord par le réseau**,
avec la **page hors ligne** en dernier recours, et aux autres requêtes **d'abord par le cache**, d'abord par le réseau
ou par le réseau seul, selon le préfixe de leur chemin. Une page dont le `Cache-Control` dit `private` ou `no-store`
n'est jamais conservée.

Un **contrôleur Stimulus** enregistre le worker, propose d'installer le site quand le navigateur le fait, propose de le
mettre à jour quand un nouveau worker attend, suit la connexion, et entre dans le plein écran ou en sort. Un autre
ouvre une page sur un **second écran** avec l'API Presentation, ou dans une fenêtre à part là où l'API manque, et
transporte les messages entre les deux.

C'est un bundle Symfony pour AssetMapper et le bundle Stimulus ; Webpack Encore n'est pas pris en charge.

## État

| Partie | État |
|---|---|
| Manifeste produit et vérifié à partir de la configuration, icônes versionnées par le composant Asset | Implémenté et testé |
| Service worker maison : préchargement, réseau d'abord et cache d'abord, page hors ligne, versions, prise de contrôle immédiate | Implémenté et testé |
| `/sw.js` avec ses réglages et l'`importScripts()` du worker servi par AssetMapper ; en-têtes sûrs et portée | Implémenté et testé |
| Fournisseurs de préchargement pour d'autres bundles, par autoconfiguration | Implémenté et testé |
| Page hors ligne et `phil_pwa_head()` | Implémenté et testé |
| Contrôleur Stimulus : enregistrement, installation, mise à jour, connexion, plein écran | Implémenté et testé |
| Contrôleur Stimulus : présentation sur un second écran, avec une fenêtre de repli | Implémenté et testé |
| Cible de fuzzing du lecteur de manifeste | Implémenté et testé |
| Recette Flex | Implémenté |
| Test en navigateur de l'installation et de la lecture hors ligne, sous Panther | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, la mise en page et les contrôleurs. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Le manifeste](doc/fr/manifest.md) | Chaque membre, ce qui est vérifié, les icônes et les raccourcis. |
| [Le service worker](doc/fr/service-worker.md) | Préchargement, stratégies, hors ligne, versions, en-têtes, portée et CSP. |
| [JavaScript](doc/fr/javascript.md) | Les contrôleurs Stimulus : installer, mettre à jour, plein écran, second écran. |
| [Intégrations](doc/fr/integrations.md) | Ce sur quoi s'appuient un affichage dynamique, un programme d'événement ou une borne. |
| [Points d'extension](doc/fr/extension-points.md) | Les services à remplacer, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests PHP et JavaScript du bundle, et sa cible de fuzzing. |
| [Référence](doc/fr/reference.md) | Chaque classe, service, route et fonction Twig du bundle. |

## Démarrage rapide

```sh
composer require phil/pwa-bundle
```

```yaml
# config/packages/phil_pwa.yaml
phil_pwa:
    manifest:
        name: Example
        short_name: Example
        theme_color: '#1b1b1b'
        background_color: '#ffffff'
        icons:
            - { src: images/icon-192.png, sizes: 192x192, type: image/png }
            - { src: images/icon-512.png, sizes: 512x512, type: image/png, purpose: [any, maskable] }
    service_worker:
        precache: [styles/app.css, /]
```

```twig
{# templates/base.html.twig #}
<head>
    {{ phil_pwa_head() }}
</head>
<body {{ stimulus_controller('phil--pwa-bundle--pwa', {url: phil_pwa_service_worker_path(), scope: phil_pwa_scope()}) }}>
```

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-mbstring` et `ext-uri`, Symfony 8.2 ou une version plus récente et Twig
3.21 ; AssetMapper et le bundle Stimulus pour le worker et les contrôleurs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
