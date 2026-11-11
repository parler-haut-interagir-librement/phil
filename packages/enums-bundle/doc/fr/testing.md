<!-- phil-dev source-sha256=5ec16a690c296a08f357d7e2a34de82fd84177fdb03218e34ea365371597f52e -->
# Tests

## Les tests du bundle

`tests/Unit` couvre chaque partie sans noyau : `CaseSelection` et `EnumTranslation` directement ; le trait traduisible
et `TranslatableText` avec le `Translator` de Symfony et des catalogues en mémoire ; l'extension de formulaire au moyen
d'une fabrique de formulaires construite avec elle ; le validateur avec le `ConstraintValidatorTestCase` de Symfony ;
l'extension Twig directement et dans un gabarit rendu par un environnement Twig ; le JSON Schema et l'export TypeScript
directement ; le chercheur sur les fichiers de `tests/Unit/Finder` et `tests/Unit/Pair` ; l'extracteur sur un
`MessageCatalogue` ; les commandes avec le `CommandTester` de Symfony ; le normaliseur à l'intérieur d'un `Serializer` ;
le type de colonne avec une plateforme DBAL ; le type de formulaire d'ensemble au moyen d'une fabrique de formulaires ;
et la classe du bundle (configuration, services et types qu'elle enregistre au démarrage) sur un `ContainerBuilder`. Les
jeux de données d'essai sous `tests/Unit/Fixture` sont des enums avec des libellés, des descriptions, des groupes, des
métadonnées vides, un nom hors ASCII, des indicateurs binaires, une valeur par défaut, une couleur et une icône, et un
cas obsolète.

`tests/Functional` exécute le bundle dans son propre noyau (FrameworkBundle avec le traducteur, le sérialiseur, le
validateur et un workflow ; DoctrineBundle sur SQLite ; TwigBundle) : le gabarit de badge trouvé sous `@PhilEnums`, un
ensemble stocké en masque de bits puis relu, `phil:enums:check-sets` sur une mauvaise rangée, le workflow construit à
partir des transitions, une route lue avec `#[ParseEnum]` et une autre protégée par `enum_in_group()`, le sérialiseur et
le validateur, les commandes sur les enums du `src/` de l'application, et `translation:extract`. Un second noyau
enregistre API Platform, NelmioApiDoc et MakerBundle : le schéma des propriétés d'enum d'une ressource, les composants
d'enum du document NelmioApiDoc, et `make:enum` qui écrit dans l'application.

À partir de la racine du monodépôt :

```sh
vendor/bin/phpunit packages/enums-bundle/tests
```

Installé seul :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Chaque mutant de `src/` est tué par ces tests.

## Tester vos enums et vos formulaires

- Une enum traduisible : traduisez chaque cas avec un `Translator` qui contient votre catalogue, et vérifiez la clé
  générée d'un cas sans libellé.
- Un champ de formulaire avec un sous-ensemble : construisez le formulaire et lisez les valeurs de
  `createView()->vars['choices']`.
- Une contrainte : étendez `ConstraintValidatorTestCase` avec `EnumCaseValidator`, comme le fait le test du bundle.
- Une colonne `EnumSet` : convertissez un ensemble vers la valeur de base de données, puis dans l'autre sens, avec
  `EnumSetType` et n'importe quelle plateforme DBAL.
- Les libellés et les traductions : lancez `debug:enums <Enum> --locale=<locale>` en intégration continue ; la commande
  échoue si une méta-propriété exigée ou une traduction manque.

Lorsque le bundle est installé seul, la suite fonctionnelle est ignorée : comme `symfony/event-dispatcher` n'est requis
que pour le développement, le conteneur de Symfony laisse de côté le répartiteur d'événements et aucun noyau ne démarre.
Elle s'exécute à partir d'une application qui requiert un noyau HTTP, comme la racine du monodépôt.

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. À partir de la
racine du monodépôt :

```sh
composer bench -- packages/enums-bundle/tests/Benchmark
```
