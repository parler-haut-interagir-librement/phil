# Reference

## `Node`

`Phil\StructuredData\Node` — one schema.org thing: its types, its `@id` when it has one, and its properties. A node
never changes; each `with…()` gives a new one.

| Member | Gives |
|---|---|
| `Node::of(string\|list<string> $type, array $properties = [], ?string $id = null)` | A node of at least one type; a `null` property is left out |
| `Node::fromArray(array $data)` | A node read from schema.org data, as JSON-LD decodes it or as a package gives it |
| `$types`, `$id` | Its types, each once, and its identifier |
| `properties()`, `get($name)`, `has($name)`, `isA($type)` | What it holds |
| `with($name, $value)`, `withProperties($map)`, `without($name)` | The node with properties set or removed; `null` or `[]` removes |
| `withId(?string)`, `withType(string ...)` | The node with another identifier, or more types |
| `reference()` | A `Reference` to the node, which has an `@id` |
| `mergedWith(Node $other)` | The types of both, the properties of both — the other's value wins —, this `@id` or else the other's |
| `toArray()` | The node as JSON-LD data |

### Values

| Given | Kept as |
|---|---|
| `string`, `int`, `bool` | Itself |
| `float` | Itself; `INF` and `NAN` are refused |
| `DateTimeInterface` | ISO 8601 text with its offset, `2027-04-10T10:00:00-04:00`; a date alone is given as text, `2027-04-10` |
| `BackedEnum` | Its value: `EventStatus::Cancelled` is `https://schema.org/EventCancelled` |
| `LanguageString` | `{"@value": "…", "@language": "…"}` |
| `Reference` | `{"@id": "…"}` |
| `Node` | A nested node |
| A list | Each value; `null` items dropped; a list of lists is refused |
| A map | Read as `Node::fromArray()` reads a nested value |
| `null`, `[]` | Nothing: the property is left out |

`Node::fromArray()` reads `@type` (a text or a list) and `@id` as the node's own, and drops `@context`. In a value, a
map holding only an `@id` is a `Reference`, a map with `@value` and `@language` a `LanguageString`, a map with
`@value` alone its text, and any other map a nested node — with or without a type.

### Terms

A type and a property name are a schema.org term (`LocalBusiness`, `name`) or an IRI (`https://schema.org/name`),
of 256 characters at most, starting with a letter or `_`, without spaces, control characters, quotes, angle brackets
nor the other characters an IRI never holds. A property name never starts with `@` — the keywords of JSON-LD are the
node's own — nor with a digit, which no vocabulary uses and PHP would turn into an integer key.

### Depth

Nodes nest 32 levels deep at most: a node read from a hostile page cannot exhaust the stack, and a graph that deep
is no page's. Each node knows its `$depth` and whether a node with an `@id` nests under it (`$nestsIdentified`), which
spares the graph a walk through the others.

## Identifiers

`Phil\StructuredData\Id` — the `@id` of a node names it the same way on every page, so that the nodes several
packages give for one page, and the graphs of two pages, speak of one organisation, one site, one person.

| Member | Gives |
|---|---|
| `Id::of(string $url, string $fragment)` | The address without its fragment, `#`, then the fragment: `Id::of('https://example.com/', 'organization')` is `https://example.com/#organization` |
| `Id::check(string $id)` | The identifier, checked: an IRI, absolute or relative, or a blank node (`_:b0`), 2,048 characters at most |

The fragment is 1 to 128 letters, digits, `.`, `_`, `~` or `-`. The usual fragments name what the node is:
`#website` and `#organization` at the root of the site, `#webpage`, `#breadcrumb` and the kind of the content —
`#event`, `#book`, `#code` — at the address of a page.

## `Reference`

`Phil\StructuredData\Reference` — `Reference::to($id)`, written `{"@id": "…"}`. A reference to a node the graph lacks
is allowed: JSON-LD links to things described elsewhere. The validator warns of it.

## Languages

`Phil\StructuredData\LanguageString` — `new LanguageString('Tournoi', 'fr-CA')`, a text in a language other than the
graph's, or one of the names of a thing in several languages, given as a list.

`Phil\StructuredData\LanguageTag` — `normalize()` gives a BCP 47 tag in its usual case (`fr-CA`, `zh-Hant-TW`,
`es-419`) and reads locales written with underscores (`fr_CA`); `isValid()` tells whether a text is already one.

A graph may carry a language: its texts are in it unless a `LanguageString` says otherwise. The `inLanguage`
property of a site, a page or a work says which language the thing itself is in.

## `Graph`

`Phil\StructuredData\Graph` — the nodes of a page.

| Member | Gives |
|---|---|
| `Graph::of(Node ...)` | A graph of the nodes |
| `with(Node ...)` | The graph with the nodes added |
| `merge(Graph $other)` | The nodes of both; this graph's language, or the other's |
| `withLanguage(?string)` | The graph whose texts are in that language |
| `nodes()`, `get($id)`, `resolve(Reference\|Node)`, `count()`, `isEmpty()`, `$language` | What it holds |
| `toArray()` | `{"@context": …, "@graph": […]}` |

Adding a node:

1. Each node with an `@id` nested in it is added first, and replaced by a reference to it — at any depth, in lists
   too. A nested node without an `@id` stays where it is.
2. A node whose `@id` the graph holds merges into that node, where it stands: the types of both, the properties of
   both, the new value replacing the old where both have one.
3. Any other node is added after the others.

So two packages that each give the site's organisation, one with its logo and one with its telephone, print one
organisation with both. A graph holds 10,000 nodes at most.

## Factories

Each factory in `Phil\StructuredData\Type` builds a node with named, typed arguments, then the free properties of
`$properties`; a named argument left `null` keeps the free property of that name. Those that take `$type` build one of
the type's kinds.

| Factory | Required arguments | Kinds through `$type` |
|---|---|---|
| `Thing::of()` | `type` | Any type |
| `WebSite::of()` | `name`, `url`; `searchUrl` with `{search_term_string}` adds a `SearchAction` | |
| `WebPage::of()` | `url` | `AboutPage`, `ContactPage`, `CollectionPage`, `ProfilePage`, `ItemPage` |
| `BreadcrumbList::of()` | `items`: `name` and maybe `url`, from the top of the site | |
| `Organization::of()` | `name` | `Corporation`, `NGO`, `EducationalOrganization`, `PerformingGroup` |
| `LocalBusiness::of()` | `name`, `address`; `latitude` and `longitude` give its `GeoCoordinates` | `Store`, `Restaurant`, `CafeOrCoffeeShop`, `BarOrPub`, `EntertainmentBusiness` |
| `Person::of()` | `name` | |
| `Event::of()` | `name`, `startDate`, `location` — a text is a `Place` of that name | `Festival`, `MusicEvent`, `SocialEvent`, `EducationEvent`, `BusinessEvent` |
| `Place::of()` | | |
| `PostalAddress::of()` | | |
| `Product::of()` | `name`; a `brand` given as text is a `Brand` | |
| `Offer::of()` | `price`, `priceCurrency` | |
| `Book::of()` | `name`, `author` — a text is a `Person` of that name | |
| `CreativeWork::of()` | `name` | `Game`, `Course`, `Dataset`, … |
| `Article::of()` | `headline` | `NewsArticle`, `BlogPosting`, `TechArticle` |
| `SoftwareSourceCode::of()` | `name`, `codeRepository`; `keywords` joined by commas | |
| `SoftwareApplication::of()` | `name` | `WebApplication`, `MobileApplication`, `VideoGame` |
| `FAQPage::of()` | `questions`: `question` and `answer`, each a `Question` with its `Answer` | |
| `ImageObject::of()` | `contentUrl` | |

| Enum | Property | Cases |
|---|---|---|
| `EventStatus` | `eventStatus` | `Scheduled`, `Cancelled`, `Postponed`, `Rescheduled`, `MovedOnline` |
| `EventAttendanceMode` | `eventAttendanceMode` | `Offline`, `Online`, `Mixed` |
| `ItemAvailability` | `availability` | `InStock`, `OutOfStock`, `SoldOut`, `PreOrder`, `PreSale`, `BackOrder`, `LimitedAvailability`, `OnlineOnly`, `InStoreOnly`, `Discontinued` |
| `BookFormat` | `bookFormat` | `Hardcover`, `Paperback`, `EBook`, `Audiobook`, `GraphicNovel` |

A price is best given as text with a dot, `'19.99'`: no rounding of a float changes it.

### Checked factories

`Phil\StructuredData\Schema` gathers the types a site gives most, each with the arguments of its factory above, and
checks the node at once with the [validator](validation.md): a node that lacks a required property, or holds a date,
an address, a currency or a language in a form nobody reads — in itself or in a node it holds —, throws
`InvalidStructuredData` with the errors, instead of reaching the page. The warnings, such as a recommended property
missing or a reference to a node of another part of the page, are left to the validator of the whole graph. An
address on `localhost` or under `.localhost` is refused too; an application calls `Schema::allowLocalhost(true)` once
in development and in its tests, and the factories then take it for every node built after.

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

| Method | Factory | Checked against |
|---|---|---|
| `Schema::article()` | `Article::of()` | `headline` |
| `Schema::product()` | `Product::of()` | `name`, and one of `offers`, `review`, `aggregateRating` |
| `Schema::offer()` | `Offer::of()` | `price`, `priceCurrency` in ISO 4217 |
| `Schema::event()` | `Event::of()` | `name`, `startDate` in ISO 8601, `location` |
| `Schema::place()` | `Place::of()` | `name` or `address` |
| `Schema::postalAddress()` | `PostalAddress::of()` | |
| `Schema::organization()` | `Organization::of()` | `name` |
| `Schema::person()` | `Person::of()` | `name` |
| `Schema::webSite()` | `WebSite::of()` | `name`, `url` |
| `Schema::webPage()` | `WebPage::of()` | `url` |
| `Schema::breadcrumbList()` | `BreadcrumbList::of()` | at least one item |
| `Schema::imageObject()` | `ImageObject::of()` | `contentUrl` |

Every method also checks the formats of the values: dates in ISO 8601, absolute addresses, currencies in ISO 4217,
languages in BCP 47. Any other type is built with `Node::of()` or a factory of `Type`, and checked with the graph.
The nonce of the script element is always given by the application.

## `InvalidStructuredData`

`Phil\StructuredData\Exception\InvalidStructuredData` — an `InvalidArgumentException` for a type, a property name, a
value, an identifier, a language, a nonce, a depth or a size the library refuses, and for a JSON-LD document it cannot
read. Its message says what is expected and what was given.
