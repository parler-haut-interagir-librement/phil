<!-- phil-dev source-sha256=d84cfc65258338e489819b9ac8a3769ab8c2793d601c8532e589cb7de0689600 -->
# Tests

## Les tests du bundle

`tests/Unit` teste la configuration et les services qu'elle enregistre, les passes CSRF et de catalogue, les catalogues
interrogés ensemble, le registre Doctrine sur SQLite en mémoire — sa table, ses clés, les présences et les refus — et
son écouteur de schéma, la confiance accordée à une requête, la configuration du poste, les règles d'admission et les
postes qu'elles délimitent, l'événement d'une lecture enregistrée, et chaque point de terminaison avec ses erreurs.
`tests/Functional` démarre une petite application avec FrameworkBundle, AssetMapper, DoctrineBundle sur SQLite, les
routes, une règle et des billets branchés par autoconfiguration. Il vérifie un billet à deux portes, un point de
contrôle interdit, un jeton CSRF falsifié, un billet que la règle refuse, un manifeste, un journal synchronisé deux
fois, les lectures entendues par un écouteur, et le contrôleur et la feuille de style servis par AssetMapper. Son noyau
déclare lui-même le répartiteur d'événements, pour démarrer de la même façon depuis le monodépôt et depuis le bundle
installé seul, où FrameworkBundle est une dépendance de développement.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/checkin-bundle/tests
```

Le contrôleur et ses bibliothèques — le contrôleur sur de faux nœuds du DOM, en ligne et hors ligne, avec un faux
IndexedDB ; les magasins, leurs replis et le passage de `localStorage` à IndexedDB ; la politique hors ligne, le
manifeste et ses empreintes, le journal, le jeton CSRF et les libellés — sont testés avec Node.js, sans rien installer,
Stimulus étant remplacé par une doublure :

```sh
cd packages/checkin-bundle && node --import ./tests/js/register.mjs --test 'tests/js/*.test.mjs'
```

`tests/Browser` pilote un vrai Chrome avec `symfony/panther` et le WebDriver du système pour Chrome, sur la page de
l'application fonctionnelle servie par le serveur intégré de PHP : une lecture en ligne, le réseau coupé par les
DevTools de Chrome, des lectures décidées à partir du manifeste gardé dans IndexedDB, et le journal synchronisé au
retour du réseau. Il s'exécute à part, depuis la racine du monodépôt, après `npm ci --ignore-scripts` dans
`tests/Browser` :

```sh
composer test:browser
```

Infection exige du bundle un score de mutation couvert de 100 %.

## Tester l'enregistrement d'une application

Définissez `store: memory` dans l'environnement `test` et donnez au client de test un utilisateur que votre *voter*
autorise. Envoyez l'en-tête du contrôleur et un jeton CSRF sans état — le témoin `<name>_<token>=<name>` et l'en-tête
`<name>: <token>`, où le nom est `DeskConfig::for()['csrfCookie']` —, puis faites un envoi au point de terminaison de
lecture. `tests/Functional/CheckinBundleTest.php` fait exactement cela.
