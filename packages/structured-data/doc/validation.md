# Validation

`Validator::validate(Graph $graph)` checks every node — those at the top of the graph and the nested ones — and
returns a `Report`.

```php
$report = new Validator()->validate($graph);

foreach ($report->errors() as $issue) {
    echo $issue->message, "\n"; // https://example.com/e#event needs startDate.
}
```

## Required and recommended properties

Each type has its `TypeRules`: the properties a node of that type needs, an error when one lacks, and those it should
have, a warning. An entry may be a list of which one property is enough. A type with a parent needs what its parent
needs too, and a node of several types what each of them needs. A property both required and recommended is only
required.

| Type | Needs | Should have |
|---|---|---|
| `WebSite` | `name`, `url` | `inLanguage`, `publisher` |
| `WebPage` | `url` | `name`, `inLanguage`, `isPartOf` |
| `FAQPage` (a `WebPage`) | `mainEntity` | |
| `Question` | `name`, `acceptedAnswer` | |
| `Answer` | `text` | |
| `BreadcrumbList` | `itemListElement` | |
| `ListItem` | `position`, `name` | `item` |
| `Organization` | `name` | `url`, `logo` |
| `LocalBusiness` (an `Organization`) | `address` | `telephone`, `url`, `image`, `openingHoursSpecification`, `geo` |
| `Person` | `name` | `url`, `sameAs` |
| `Event` | `name`, `startDate`, `location` | `endDate`, `eventStatus`, `description`, `image`, `offers`, `organizer` |
| `Place` | one of `name`, `address` | `address` |
| `PostalAddress` | | `streetAddress`, `addressLocality`, `addressRegion`, `postalCode`, `addressCountry` |
| `Product` | `name`, one of `offers`, `review`, `aggregateRating` | `image`, `description`, `sku`, `brand` |
| `Offer` | `price`, `priceCurrency` | `availability`, `url` |
| `CreativeWork` | | `name`, `author` |
| `Book` (a `CreativeWork`) | `name`, `author` | `isbn`, `url`, `inLanguage`, `bookFormat` |
| `Article` (a `CreativeWork`) | `headline` | `author`, `datePublished`, `dateModified`, `image`, `publisher` |
| `SoftwareSourceCode` (a `CreativeWork`) | `name`, `codeRepository` | `programmingLanguage`, `license`, `description`, `version` |
| `SoftwareApplication` | `name` | `applicationCategory`, `operatingSystem`, `offers`, `softwareVersion` |
| `ImageObject` | one of `contentUrl`, `url` | `caption`, `width`, `height`, `license`, `creator` |

The kinds the factories build need what their parent needs: `Corporation`, `NGO`, `EducationalOrganization` and
`PerformingGroup` are organisations; `Store`, `Restaurant`, `CafeOrCoffeeShop`, `BarOrPub` and
`EntertainmentBusiness` local businesses; `BusinessEvent`, `EducationEvent`, `ExhibitionEvent`, `Festival`,
`MusicEvent`, `SocialEvent`, `SportsEvent` and `TheaterEvent` events; `NewsArticle`, `BlogPosting` and `TechArticle`
articles; `AboutPage`, `CollectionPage`, `ContactPage`, `ItemPage` and `ProfilePage` pages; `WebApplication`,
`MobileApplication` and `VideoGame` applications.

## Values

| Property | Checked | Code |
|---|---|---|
| `startDate`, `endDate`, `datePublished`, `dateModified`, `dateCreated`, `uploadDate`, `priceValidUntil`, `validFrom`, `validThrough`, `birthDate`, `foundingDate` | An ISO 8601 date, or date and time with maybe seconds, fractions and an offset; a day that exists | `invalid-date` |
| `url`, `logo`, `image`, `sameAs`, `codeRepository`, `contentUrl`, `thumbnailUrl`, `downloadUrl`, `installUrl`, `item` | An absolute HTTP or HTTPS address with a host and no spaces | `invalid-url` |
| `priceCurrency` | Three capitals, as ISO 4217 writes a currency | `invalid-currency` |
| `inLanguage` | A BCP 47 tag | `invalid-language` |

Only texts are checked: a node in `image` or `logo`, a number, a `LanguageString` are left alone. Each wrong value is
an error.

## References and types

A reference to a node the graph lacks is a warning, `unresolved-reference`: it may name a thing described elsewhere,
or a node a package forgot to give. A node at the top of the graph without a type is a warning, `untyped-node`: no
rule applies to it.

## The report

| Member | Gives |
|---|---|
| `$issues` | Every `Issue`, in the order of the graph |
| `errors()`, `warnings()` | The issues of one `Severity` |
| `isValid()` | Whether there is no error; warnings may remain |
| `toArray()` | The issues as data, for JSON |

An `Issue` holds its `severity`, its `code` — `missing-property`, `missing-recommended`, `invalid-date`,
`invalid-url`, `invalid-currency`, `invalid-language`, `unresolved-reference`, `untyped-node` —, the `node` it is
about — its `@id`, or its type and place, such as `Event @graph[2]`, then `›` and the property for a nested node —, the
`property`, and an English `message`.
