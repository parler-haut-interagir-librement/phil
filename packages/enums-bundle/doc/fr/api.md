<!-- phil-dev source-sha256=1f6f32d584ae3eb64e42c9493a36dd977942f1c14ed0287885a5eea20a937d6c -->
# API

Il y a deux façons de mettre des enums dans une API : le sérialiseur écrit les structures de `phil/enums` et les cas
avec leur libellé, et `EnumSchema` décrit une enum pour un document OpenAPI.

## Sérialiseur

`EnumsNormalizer` est enregistré lorsque `symfony/serializer` est installé, avant le normaliseur d'enums de Symfony.

| Donnée | Écrite sous la forme |
|---|---|
| `EnumSet` | La liste de ses valeurs (les noms pour une enum pure), dans l'ordre de déclaration : `[1, 4]`. |
| `EnumMap` | Un objet dont les clés sont les valeurs des cas, chaque valeur étant normalisée à son tour : `{"draft": "grey", "published": "green"}` ; une table vide donne `{}`. |
| Un cas, avec l'option de contexte `EnumsNormalizer::LABELS` | `{"value": "pending", "label": "En attente", "description": "En attente du paiement."}`. |
| Un cas, sans cette option | Laissé au normaliseur de Symfony : sa valeur. |

```php
use Phil\EnumsBundle\Infrastructure\Serializer\EnumsNormalizer;

$serializer->serialize($order, 'json', [EnumsNormalizer::LABELS => true]);
```

Le libellé et la description sont traduits, dans la locale courante du traducteur, lorsque l'enum est traduisible et que
l'application a un traducteur. Sinon, ils valent le `Label` du cas (ou son nom) et sa `Description`, ou `null` s'il n'en
a pas.

**La relecture des ensembles et des tables de correspondance** a besoin de leur enum, qu'un nom de classe ne porte pas :
`EnumSet<Permission>` est un type PHPDoc. Nommez-la dans le contexte, sur la propriété avec `#[Context]` ou dans
l'appel :

```php
use Symfony\Component\Serializer\Attribute\Context;

#[Context([EnumsNormalizer::ENUM => Permission::class])]
public EnumSet $permissions;

$serializer->deserialize('[1, 4]', EnumSet::class, 'json', [EnumsNormalizer::ENUM => Permission::class]);
```

**Laissez le générique hors du PHPDoc de la propriété.** Lorsqu'un lecteur PHPDoc est installé (`phpstan/phpdoc-parser`
ou `phpdocumentor/reflection-docblock`), Symfony lit `@var EnumSet<Permission>` comme une collection de cas, puisqu'un
ensemble est itérable. Il dénormalise alors les cas et, comme `EnumSet` n'est ni une interface ni construit à partir
d'un tableau, il donne à la propriété un simple tableau au lieu de s'adresser au bundle. Typez nativement la propriété
`?EnumSet`, nommez l'enum avec `#[Context]` et faites taire le `missingType.generics` de PHPStan sur cette ligne.

Les valeurs d'une table sont conservées telles que décodées, ou dénormalisées vers le type que nomme
`EnumsNormalizer::VALUE_TYPE`. Sans l'enum dans le contexte, la lecture échoue avec l'`InvalidArgumentException` du
sérialiseur. Des données qui ne désignent aucun cas (une valeur qu'aucun cas n'a, une table à laquelle il manque un cas)
échouent avec une `NotNormalizableValueException` dont le message peut être montré à l'utilisateur, au chemin de la
propriété. Un cas se lit par sa valeur, avec le normaliseur de Symfony.

## JSON Schema

`EnumSchema::of()` donne le schéma d'une enum sous la forme dont un document OpenAPI décrit une propriété :

```php
use Phil\EnumsBundle\Application\Schema\EnumSchema;

EnumSchema::of(OrderStatus::class);
// [
//     'type' => 'string',
//     'enum' => ['pending', 'in_transit', 'cancelled'],
//     'x-enum-varnames' => ['PENDING', 'IN_TRANSIT', 'CANCELLED'],
//     'x-enum-descriptions' => ['Waiting for payment.', 'The parcel has left.', 'Cancelled'],
//     'default' => 'pending',
// ]
```

- **`type`** vaut `integer` pour une enum adossée à des entiers, `string` sinon ; une enum pure s'écrit par ses noms.
- **`x-enum-varnames`** et **`x-enum-descriptions`** sont les extensions que lisent les générateurs de clients pour les
  noms et les commentaires des constantes : les noms des cas, et la `Description` de chaque cas, sinon son `Label`,
  sinon son nom. Ces textes ne sont pas traduits, car un contrat est un document unique pour tous les clients.
- **`default`** est le cas marqué `#[DefaultCase]`, s'il y en a un parmi les valeurs.
- **Les cas obsolètes sont exclus** : un contrat offre ce que les clients peuvent envoyer.
  `EnumSchema::of($enum, withDeprecated: true)` les conserve, pour une réponse qui peut encore en contenir un.

C'est un simple tableau : fusionnez-le dans le schéma que votre outil de documentation construit pour la propriété.

## API Platform et NelmioApiDoc

Lorsque l'un de ces outils est installé, le bundle complète le schéma que l'outil construit pour une enum, et il n'y a
donc rien à fusionner à la main avec `EnumSchema` :

| Outil | Ce que fait le bundle | Enregistré |
|---|---|---|
| API Platform | Décore la fabrique de métadonnées de propriété autour de la fabrique de schémas d'API Platform : une propriété typée par une enum reçoit les valeurs (les noms pour une enum pure, qu'API Platform ne décrit pas), `x-enum-varnames`, `x-enum-descriptions` et le `#[DefaultCase]`. Une propriété nullable garde `null` parmi ses valeurs et ses types. | Avec `api-platform/symfony` ; retiré lorsque le bundle d'API Platform n'est pas enregistré |
| NelmioApiDoc | Décrit chaque modèle d'enum (NelmioApiDoc donne aux enums leur propre schéma de composant), avant le descripteur d'enums de NelmioApiDoc : mêmes type, valeurs, extensions et valeur par défaut, enums pures comprises. | Avec `nelmio/api-doc-bundle` |

Les cas obsolètes sont exclus, comme dans `EnumSchema`. Une propriété dont le schéma est déclaré à la main
(`#[ApiProperty(schema: …)]`) reste telle qu'écrite, comme le fait API Platform. Une propriété `EnumSet` n'est pas
décrite comme un ensemble : donnez-lui son schéma à la main, un tableau des valeurs de l'enum.
