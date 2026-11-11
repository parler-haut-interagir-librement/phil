<!-- phil-dev source-sha256=8fd0574d83acfada45973629067e3f8e663b1743ae4c55ebb423826a72c734ff -->
# Référence

## `Node`

`Phil\StructuredData\Node` — une chose schema.org : ses types, son `@id` quand elle en a un, et ses propriétés. Un
nœud ne change jamais ; chaque `with…()` en donne un nouveau.

| Membre | Donne |
|---|---|
| `Node::of(string\|list<string> $type, array $properties = [], ?string $id = null)` | Un nœud d'au moins un type ; une propriété `null` est omise |
| `Node::fromArray(array $data)` | Un nœud lu à partir de données schema.org, telles que le JSON-LD les décode ou qu'un paquet les fournit |
| `$types`, `$id` | Ses types, chacun une fois, et son identifiant |
| `properties()`, `get($name)`, `has($name)`, `isA($type)` | Ce qu'il contient |
| `with($name, $value)`, `withProperties($map)`, `without($name)` | Le nœud avec des propriétés définies ou retirées ; `null` ou `[]` retire |
| `withId(?string)`, `withType(string ...)` | Le nœud avec un autre identifiant, ou plus de types |
| `reference()` | Une `Reference` au nœud, qui a un `@id` |
| `mergedWith(Node $other)` | Les types des deux, les propriétés des deux — la valeur de l'autre l'emporte —, cet `@id` ou, à défaut, celui de l'autre |
| `toArray()` | Le nœud sous forme de données JSON-LD |

### Valeurs

| Donnée | Conservée comme |
|---|---|
| `string`, `int`, `bool` | Elle-même |
| `float` | Elle-même ; `INF` et `NAN` sont refusés |
| `DateTimeInterface` | Un texte ISO 8601 avec son décalage, `2027-04-10T10:00:00-04:00` ; une date seule se donne en texte, `2027-04-10` |
| `BackedEnum` | Sa valeur : `EventStatus::Cancelled` vaut `https://schema.org/EventCancelled` |
| `LanguageString` | `{"@value": "…", "@language": "…"}` |
| `Reference` | `{"@id": "…"}` |
| `Node` | Un nœud imbriqué |
| Une liste | Chaque valeur ; les éléments `null` sont retirés ; une liste de listes est refusée |
| Un tableau associatif | Lu comme `Node::fromArray()` lit une valeur imbriquée |
| `null`, `[]` | Rien : la propriété est omise |

`Node::fromArray()` lit `@type` (un texte ou une liste) et `@id` comme ceux du nœud lui-même, et retire `@context`.
Dans une valeur, un tableau associatif qui ne contient qu'un `@id` est une `Reference`, un tableau associatif avec
`@value` et `@language` une `LanguageString`, un tableau associatif avec `@value` seul son texte, et tout autre tableau
associatif un nœud imbriqué — avec ou sans type.

### Termes

Un type et un nom de propriété sont un terme schema.org (`LocalBusiness`, `name`) ou un IRI
(`https://schema.org/name`). Ils comptent 256 caractères au plus, commencent par une lettre ou `_`, et ne contiennent
ni espace, ni caractère de contrôle, ni guillemet, ni chevron, ni aucun des autres caractères qu'un IRI ne contient
jamais. Un nom de propriété ne commence jamais par `@` — les mots-clés de JSON-LD appartiennent au nœud lui-même — ni
par un chiffre, qu'aucun vocabulaire n'utilise et dont PHP ferait une clé entière.

### Profondeur

Les nœuds s'imbriquent sur 32 niveaux au plus : un nœud lu dans une page hostile ne peut pas épuiser la pile, et aucune
page n'a un graphe aussi profond. Chaque nœud connaît sa `$depth` et sait si un nœud doté d'un `@id` est imbriqué sous
lui (`$nestsIdentified`), ce qui évite au graphe de parcourir les autres.

## Identifiants

`Phil\StructuredData\Id` — l'`@id` d'un nœud le nomme de la même façon sur chaque page. Ainsi, les nœuds que plusieurs
paquets fournissent pour une page, et les graphes de deux pages, parlent d'une même organisation, d'un même site,
d'une même personne.

| Membre | Donne |
|---|---|
| `Id::of(string $url, string $fragment)` | L'adresse sans son fragment, `#`, puis le fragment : `Id::of('https://example.com/', 'organization')` donne `https://example.com/#organization` |
| `Id::check(string $id)` | L'identifiant, vérifié : un IRI, absolu ou relatif, ou un nœud anonyme (`_:b0`), de 2 048 caractères au plus |

Le fragment compte de 1 à 128 lettres, chiffres, `.`, `_`, `~` ou `-`. Les fragments habituels nomment ce qu'est le
nœud : `#website` et `#organization` à la racine du site, `#webpage`, `#breadcrumb` et la sorte du contenu —
`#event`, `#book`, `#code` — à l'adresse d'une page.

## `Reference`

`Phil\StructuredData\Reference` — `Reference::to($id)`, écrit `{"@id": "…"}`. Une référence à un nœud absent du
graphe est permise : le JSON-LD renvoie à des choses décrites ailleurs. Le validateur en avertit.

## Langues

`Phil\StructuredData\LanguageString` — `new LanguageString('Tournoi', 'fr-CA')`, un texte dans une autre langue que
celle du graphe, ou l'un des noms d'une chose en plusieurs langues, donnés en liste.

`Phil\StructuredData\LanguageTag` — `normalize()` donne une étiquette BCP 47 dans sa casse habituelle (`fr-CA`,
`zh-Hant-TW`, `es-419`) et lit les locales écrites avec des traits de soulignement (`fr_CA`) ; `isValid()` indique si
un texte en est déjà une.

Un graphe peut porter une langue : ses textes sont dans cette langue, sauf si une `LanguageString` dit autre chose. La
propriété `inLanguage` d'un site, d'une page ou d'une œuvre indique la langue de la chose elle-même.

## `Graph`

`Phil\StructuredData\Graph` — les nœuds d'une page.

| Membre | Donne |
|---|---|
| `Graph::of(Node ...)` | Un graphe des nœuds |
| `with(Node ...)` | Le graphe avec les nœuds ajoutés |
| `merge(Graph $other)` | Les nœuds des deux ; la langue de ce graphe, ou celle de l'autre |
| `withLanguage(?string)` | Le graphe dont les textes sont dans cette langue |
| `nodes()`, `get($id)`, `resolve(Reference\|Node)`, `count()`, `isEmpty()`, `$language` | Ce qu'il contient |
| `toArray()` | `{"@context": …, "@graph": […]}` |

Ajout d'un nœud :

1. Chaque nœud doté d'un `@id` qui y est imbriqué est ajouté d'abord, puis remplacé par une référence à lui — à
   n'importe quelle profondeur, dans les listes aussi. Un nœud imbriqué sans `@id` reste où il est.
2. Un nœud dont le graphe contient déjà l'`@id` se fusionne dans ce nœud, à sa place : les types des deux, les
   propriétés des deux, la nouvelle valeur remplaçant l'ancienne là où les deux en ont une.
3. Tout autre nœud est ajouté après les autres.

Ainsi, deux paquets qui fournissent chacun l'organisation du site, l'un avec son logo et l'autre avec son téléphone,
impriment une seule organisation avec les deux. Un graphe contient 10 000 nœuds au plus.

## Fabriques

Chaque fabrique de `Phil\StructuredData\Type` construit un nœud avec des arguments nommés et typés, puis les propriétés
libres de `$properties` ; un argument nommé laissé à `null` garde la propriété libre du même nom. Celles qui prennent
`$type` construisent l'une des sortes du type.

| Fabrique | Arguments requis | Sortes par `$type` |
|---|---|---|
| `Thing::of()` | `type` | N'importe quel type |
| `WebSite::of()` | `name`, `url` ; `searchUrl` avec `{search_term_string}` ajoute une `SearchAction` | |
| `WebPage::of()` | `url` | `AboutPage`, `ContactPage`, `CollectionPage`, `ProfilePage`, `ItemPage` |
| `BreadcrumbList::of()` | `items` : `name` et, au besoin, `url`, depuis le sommet du site | |
| `Organization::of()` | `name` | `Corporation`, `NGO`, `EducationalOrganization`, `PerformingGroup` |
| `LocalBusiness::of()` | `name`, `address` ; `latitude` et `longitude` donnent ses `GeoCoordinates` | `Store`, `Restaurant`, `CafeOrCoffeeShop`, `BarOrPub`, `EntertainmentBusiness` |
| `Person::of()` | `name` | |
| `Event::of()` | `name`, `startDate`, `location` — un texte est une `Place` de ce nom | `Festival`, `MusicEvent`, `SocialEvent`, `EducationEvent`, `BusinessEvent` |
| `Place::of()` | | |
| `PostalAddress::of()` | | |
| `Product::of()` | `name` ; une `brand` donnée en texte est une `Brand` | |
| `Offer::of()` | `price`, `priceCurrency` | |
| `Book::of()` | `name`, `author` — un texte est une `Person` de ce nom | |
| `CreativeWork::of()` | `name` | `Game`, `Course`, `Dataset`, … |
| `Article::of()` | `headline` | `NewsArticle`, `BlogPosting`, `TechArticle` |
| `SoftwareSourceCode::of()` | `name`, `codeRepository` ; `keywords` joints par des virgules | |
| `SoftwareApplication::of()` | `name` | `WebApplication`, `MobileApplication`, `VideoGame` |
| `FAQPage::of()` | `questions` : `question` et `answer`, chacune une `Question` avec sa `Answer` | |
| `ImageObject::of()` | `contentUrl` | |

| Énumération | Propriété | Cas |
|---|---|---|
| `EventStatus` | `eventStatus` | `Scheduled`, `Cancelled`, `Postponed`, `Rescheduled`, `MovedOnline` |
| `EventAttendanceMode` | `eventAttendanceMode` | `Offline`, `Online`, `Mixed` |
| `ItemAvailability` | `availability` | `InStock`, `OutOfStock`, `SoldOut`, `PreOrder`, `PreSale`, `BackOrder`, `LimitedAvailability`, `OnlineOnly`, `InStoreOnly`, `Discontinued` |
| `BookFormat` | `bookFormat` | `Hardcover`, `Paperback`, `EBook`, `Audiobook`, `GraphicNovel` |

Donnez de préférence un prix en texte avec un point, `'19.99'` : aucun arrondi de nombre à virgule flottante ne le
modifie.

### Fabriques vérifiées

`Phil\StructuredData\Schema` rassemble les types qu'un site fournit le plus, chacun avec les arguments de sa fabrique
ci-dessus, et vérifie aussitôt le nœud avec le [validateur](validation.md). Un nœud auquel manque une propriété requise,
ou qui contient une date, une adresse, une devise ou une langue sous une forme que personne ne lit — en lui-même ou
dans un nœud qu'il contient —, lance `InvalidStructuredData` avec les erreurs au lieu d'atteindre la page. Les
avertissements, comme une propriété recommandée absente ou une référence à un nœud d'une autre partie de la page,
restent l'affaire du validateur du graphe entier.

```php
use Phil\StructuredData\{Graph, JsonLd, Schema};
use Phil\StructuredData\Type\ItemAvailability;

$product = Schema::product(
    'T-shirt',
    sku: 'NAQ001',
    offers: Schema::offer('25.00', 'CAD', availability: ItemAvailability::InStock),
);

echo JsonLd::script(Graph::of($product), $nonce); // the nonce of the page's content security policy, if it has one
```

| Méthode | Fabrique | Vérifiée contre |
|---|---|---|
| `Schema::article()` | `Article::of()` | `headline` |
| `Schema::product()` | `Product::of()` | `name`, et l'une de `offers`, `review`, `aggregateRating` |
| `Schema::offer()` | `Offer::of()` | `price`, `priceCurrency` en ISO 4217 |
| `Schema::event()` | `Event::of()` | `name`, `startDate` en ISO 8601, `location` |
| `Schema::place()` | `Place::of()` | `name` ou `address` |
| `Schema::postalAddress()` | `PostalAddress::of()` | |
| `Schema::organization()` | `Organization::of()` | `name` |
| `Schema::person()` | `Person::of()` | `name` |
| `Schema::webSite()` | `WebSite::of()` | `name`, `url` |
| `Schema::webPage()` | `WebPage::of()` | `url` |
| `Schema::breadcrumbList()` | `BreadcrumbList::of()` | au moins un élément |
| `Schema::imageObject()` | `ImageObject::of()` | `contentUrl` |

Chaque méthode vérifie aussi le format des valeurs : dates en ISO 8601, adresses absolues, devises en ISO 4217,
langues en BCP 47. Tout autre type se construit avec `Node::of()` ou une fabrique de `Type`, et se vérifie avec le
graphe. Le *nonce* de l'élément de script est toujours fourni par l'application.

## `InvalidStructuredData`

`Phil\StructuredData\Exception\InvalidStructuredData` — une `InvalidArgumentException` levée pour un type, un nom de
propriété, une valeur, un identifiant, une langue, un *nonce*, une profondeur ou une taille que la bibliothèque refuse,
et pour un document JSON-LD qu'elle ne peut pas lire. Son message indique ce qui est attendu et ce qui a été donné.
