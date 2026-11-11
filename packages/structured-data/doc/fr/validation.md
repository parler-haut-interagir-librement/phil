<!-- phil-dev source-sha256=65c4a4391236b06cdb53487f0570db9a2289860cd4d515a039648641b02f7d68 -->
# Validation

`Validator::validate(Graph $graph)` vérifie chaque nœud — ceux du premier niveau du graphe et ceux qui sont imbriqués —
et retourne un `Report`.

```php
$report = new Validator()->validate($graph);

foreach ($report->errors() as $issue) {
    echo $issue->message, "\n"; // https://example.com/e#event needs startDate.
}
```

## Propriétés requises et recommandées

Chaque type a ses `TypeRules` : les propriétés dont un nœud de ce type a besoin, dont l'absence est une erreur, et
celles qu'il devrait avoir, dont l'absence est un avertissement. Une entrée peut être une liste dont une seule
propriété suffit. Un type doté d'un parent a aussi besoin de ce dont son parent a besoin, et un nœud de plusieurs types
de ce dont chacun d'eux a besoin. Une propriété à la fois requise et recommandée est seulement requise.

| Type | A besoin de | Devrait avoir |
|---|---|---|
| `WebSite` | `name`, `url` | `inLanguage`, `publisher` |
| `WebPage` | `url` | `name`, `inLanguage`, `isPartOf` |
| `FAQPage` (une `WebPage`) | `mainEntity` | |
| `Question` | `name`, `acceptedAnswer` | |
| `Answer` | `text` | |
| `BreadcrumbList` | `itemListElement` | |
| `ListItem` | `position`, `name` | `item` |
| `Organization` | `name` | `url`, `logo` |
| `LocalBusiness` (une `Organization`) | `address` | `telephone`, `url`, `image`, `openingHoursSpecification`, `geo` |
| `Person` | `name` | `url`, `sameAs` |
| `Event` | `name`, `startDate`, `location` | `endDate`, `eventStatus`, `description`, `image`, `offers`, `organizer` |
| `Place` | l'une de `name`, `address` | `address` |
| `PostalAddress` | | `streetAddress`, `addressLocality`, `addressRegion`, `postalCode`, `addressCountry` |
| `Product` | `name`, l'une de `offers`, `review`, `aggregateRating` | `image`, `description`, `sku`, `brand` |
| `Offer` | `price`, `priceCurrency` | `availability`, `url` |
| `CreativeWork` | | `name`, `author` |
| `Book` (un `CreativeWork`) | `name`, `author` | `isbn`, `url`, `inLanguage`, `bookFormat` |
| `Article` (un `CreativeWork`) | `headline` | `author`, `datePublished`, `dateModified`, `image`, `publisher` |
| `SoftwareSourceCode` (un `CreativeWork`) | `name`, `codeRepository` | `programmingLanguage`, `license`, `description`, `version` |
| `SoftwareApplication` | `name` | `applicationCategory`, `operatingSystem`, `offers`, `softwareVersion` |
| `ImageObject` | l'une de `contentUrl`, `url` | `caption`, `width`, `height`, `license`, `creator` |

Les sortes que construisent les fabriques ont besoin de ce dont leur parent a besoin : `Corporation`, `NGO`,
`EducationalOrganization` et `PerformingGroup` sont des organisations ; `Store`, `Restaurant`, `CafeOrCoffeeShop`,
`BarOrPub` et `EntertainmentBusiness` des commerces locaux ; `BusinessEvent`, `EducationEvent`, `ExhibitionEvent`,
`Festival`, `MusicEvent`, `SocialEvent`, `SportsEvent` et `TheaterEvent` des événements ; `NewsArticle`,
`BlogPosting` et `TechArticle` des articles ; `AboutPage`, `CollectionPage`, `ContactPage`, `ItemPage` et
`ProfilePage` des pages ; `WebApplication`, `MobileApplication` et `VideoGame` des applications.

## Valeurs

| Propriété | Vérification | Code |
|---|---|---|
| `startDate`, `endDate`, `datePublished`, `dateModified`, `dateCreated`, `uploadDate`, `priceValidUntil`, `validFrom`, `validThrough`, `birthDate`, `foundingDate` | Une date ISO 8601, ou une date et une heure avec, au besoin, les secondes, des fractions et un décalage ; un jour qui existe | `invalid-date` |
| `url`, `logo`, `image`, `sameAs`, `codeRepository`, `contentUrl`, `thumbnailUrl`, `downloadUrl`, `installUrl`, `item` | Une adresse HTTP ou HTTPS absolue, lue par phil/url comme un navigateur la lit : un nom d'hôte (ni adresse IP, ni nom seul), sans nom d'utilisateur ni mot de passe, sans espace | `invalid-url` |
| `priceCurrency` | Trois majuscules, comme ISO 4217 écrit une devise | `invalid-currency` |
| `inLanguage` | Une étiquette BCP 47 | `invalid-language` |

Seuls les textes sont vérifiés : un nœud dans `image` ou `logo`, un nombre, une `LanguageString` sont laissés tels
quels. Chaque valeur erronée est une erreur.

## Références et types

Une référence à un nœud absent du graphe est un avertissement, `unresolved-reference` : elle peut nommer une chose
décrite ailleurs, ou un nœud qu'un paquet a oublié de fournir. Un nœud du premier niveau du graphe sans type est un
avertissement, `untyped-node` : aucune règle ne s'y applique.

## Le rapport

| Membre | Donne |
|---|---|
| `$issues` | Chaque `Issue`, dans l'ordre du graphe |
| `errors()`, `warnings()` | Les problèmes d'une seule `Severity` |
| `isValid()` | S'il n'y a aucune erreur ; des avertissements peuvent rester |
| `toArray()` | Les problèmes sous forme de données, pour le JSON |

Une `Issue` contient sa `severity`, son `code` — `missing-property`, `missing-recommended`, `invalid-date`,
`invalid-url`, `invalid-currency`, `invalid-language`, `unresolved-reference`, `untyped-node` —, le `node` qu'elle
concerne — son `@id`, ou son type et sa place, comme `Event @graph[2]`, puis `›` et la propriété pour un nœud
imbriqué —, la `property`, et un `message` en anglais.
