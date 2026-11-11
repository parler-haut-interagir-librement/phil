<!-- phil-dev source-sha256=90cedc74c18000b75cf568b6bfc92ff711b049316e44222248c3e987fa88af53 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `CoordinatesType`, `CoordinatesTransformer` | La soumission, l'affichage, une position facultative vide, une moitié de position, une position hors de la Terre |
| `GeoExtension` | Les distances, leur écriture dans chaque système et chaque locale, les attributs d'un lieu brouillé ou non |
| `PhilGeoBundle` | Les services, l'objet intégrable ajouté en préfixe avec l'ORM seulement, et laissé à l'application sur demande |
| `GeocodingServices` | Aucun fournisseur, un seul, plusieurs en chaîne et mis en cache, le localisateur IP, chaque option et chaque borne, les options dont un fournisseur a besoin |
| `DatabaseUpdater`, `phil:geo:maxmind:update` | Des archives que les tests écrivent (en-têtes tels que tar les écrit, un répertoire, un fichier qui gêne, une archive dans l'archive, une base après la fin) ; la somme de contrôle conservée et le téléchargement évité, ou forcé ; chaque refus qui laisse la base en place ; le compte envoyé ; la sortie, simple et en couleurs |
| Le géocodage dans une application | Nominatim servi par un `MockHttpClient` comme `http_client`, à travers `cache.app` : une seule requête pour deux questions |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur une base SQLite en mémoire, TwigBundle
et le bundle : `GeoBundleTest` stocke une position dans ses deux colonnes, soumet le formulaire en `fr_CA` et produit le
rendu des fonctions Twig. Quand le bundle est installé seul, FrameworkBundle n'étant qu'un prérequis de développement,
la suite est ignorée.

`tests/js` exécute `distance.js` et le contrôleur sous l'exécuteur de tests de Node, Stimulus étant résolu vers un stub.

```sh
vendor/bin/phpunit packages/geo-bundle/tests
```

```sh
node --import ./packages/geo-bundle/tests/js/register.mjs --test packages/geo-bundle/tests/js/distance-sort.test.mjs
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. À la racine du
monodépôt :

```sh
composer bench -- packages/geo-bundle/tests/Benchmark
```
