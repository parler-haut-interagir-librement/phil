<!-- phil-dev source-sha256=96a3b9dfd8fb229eddb5fcdfa54f54049735fb9ea0abf35ca86e87b24524f8aa -->
# PHPStan

Deux traits ajoutent des méthodes que PHPStan ne peut pas voir à lui seul : `AsInvocableEnumTrait` fait de
`Status::DRAFT()` un appel statique, et `AsMetadatableEnumTrait` fait de `Status::DRAFT->label()` un accesseur. La
bibliothèque fournit une extension pour chacun, et une règle pour `#[RequiredMeta]`, toutes chargées par
`extension.neon` :

```neon
includes:
    - vendor/phil/enums/extension.neon
```

Avec [`phpstan/extension-installer`](https://github.com/phpstan/extension-installer), l'entrée `extra.phpstan` du
`composer.json` de la bibliothèque l'inclut automatiquement.

## Ce que typent les extensions

- **`InvocableEnum\ReflectionExtension`** : un appel statique nommé d'après un cas d'une enum qui utilise
  `AsInvocableEnumTrait` renvoie la primitive du cas — sa valeur pour une enum adossée, son nom pour une enum pure.
- **`MetadatableEnum\ReflectionExtension`** : un accesseur nommé d'après une propriété méta d'une enum qui utilise
  `AsMetadatableEnumTrait` existe, et renvoie le type de valeur de la propriété méta. Les propriétés méta sont lues là
  où l'exécution les lit : l'attribut `#[Meta]` de l'enum, puis celui de chaque trait qu'utilise l'enum. Le type de
  retour est le `TValue` de la propriété, joint au type de son `defaultValue()` seulement lorsqu'un cas de l'enum ne
  porte pas l'attribut et peut donc se rabattre sur cette valeur.

## Ce que signalent les règles

- **`RequiredMeta\RequiredMetaRule`** : chaque cas d'une enum qui ne porte pas une propriété méta que demandent ses
  attributs `#[RequiredMeta]`, une erreur par cas et par propriété, avec l'identifiant `philEnums.requiredMeta` — `Case
  App\Status::PUBLISHED does not carry #[Phil\Enums\Attribute\Label], which the enum requires.` Les erreurs sont
  signalées sur la déclaration de l'enum.
- **`Declaration\EnumDeclarationRule`** : ce que les attributs d'une enum déclarent à tort, chacun avec son
  identifiant :

  | Identifiant | Signale |
  |---|---|
  | `philEnums.defaultCase` | Plus d'un cas marqué `#[DefaultCase]`. |
  | `philEnums.ambiguousAlias` | Un `#[Alias]` qui est la valeur ou le nom d'un autre cas, ou que deux cas partagent : `parse()` ne l'atteint jamais. |
  | `philEnums.negativeWeight` | Un `#[Weight]` inférieur à 0. |
  | `philEnums.notBitFlags` | Un cas d'une enum `#[BitFlags]` dont la valeur n'est pas une puissance entière de deux. |
  | `philEnums.requiredMetaUndeclared` | Une propriété que demande `#[RequiredMeta]` et que `#[Meta]` ne déclare pas, sur une enum avec `AsMetadatableEnumTrait` : elle n'a pas d'accesseur. |

- **`Ordinal\StoredOrdinalRule`** (`philEnums.storedOrdinal`) : `ordinal()` appelé sur une enum avec
  `AsOrdinalEnumTrait` dans `jsonSerialize()`, `__serialize()`, `normalize()` ou `convertToDatabaseValue()` — là où une
  valeur quitte le processus. Un ordinal change lorsque les cas sont réordonnés ; stockez la valeur. C'est une
  heuristique : elle connaît ces méthodes par leur nom et ne suit aucun flux de données.

Les extensions et la règle lisent les attributs par réflexion et n'instancient jamais de cas, puisque PHPStan interdit
le chargement automatique pendant l'analyse.
