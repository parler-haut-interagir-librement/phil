# Reference

Every public type of the package, with its role. The types marked internal are not part of the public API.

| Type | Kind | Role |
|---|---|---|
| `FeedItem` | `final readonly class` | One product or variant, its fields checked when it is made; `hasIdentifier()` |
| `Availability` | `enum` | `InStock`, `OutOfStock`, `Preorder`, `Backorder`; `takesADate()` |
| `Condition` | `enum` | `New`, `Refurbished`, `Used` |
| `AgeGroup` | `enum` | `Newborn`, `Infant`, `Toddler`, `Kids`, `Adult` |
| `Gender` | `enum` | `Male`, `Female`, `Unisex` |
| `Shipping` | `final readonly class` | What delivering an item to a country costs, by one service or by any |
| `SalePeriod` | `final readonly class` | When a sale price applies, in UTC; `toString()` as an ISO 8601 interval |
| `FeedChannel` | `final readonly class` | The feed's title, link and description |
| `FeedFormat` | `enum` | `Xml`, `Tsv`; `serializer()`, `extension()`, `mediaType()` |
| `FeedPublisher` | `final readonly class` | Writes a feed file whole, in constant memory, and renames it into place; `publish()` |
| `PublishedFeed` | `final readonly class` | The path, items and bytes of a feed written |
| `Contract\ProductSource` | interface | The products a feed publishes |
| `Contract\FeedSerializer` | interface | Writes a feed's header, items and footer |
| `Serializer\RssSerializer` | `final readonly class` | RSS 2.0 with the `g:` namespace of product data |
| `Serializer\TsvSerializer` | `final readonly class` | Tab-separated values with a header row |
| `Serializer\Fields` | `final class`, internal | An item's fields by name, in the order both formats write them |
| `Text` | `final class`, internal | The cleaning and the lengths of the text fields |
| `Gtin` | `final class`, internal | The check of a GTIN's digits |
| `Exception\ProductFeedException` | interface | Every exception of the package |
| `Exception\InvalidFeedItem` | `final class`, `InvalidArgumentException` | A field an item, a channel, a period or a shipping cost cannot carry |
| `Exception\FeedNotWritten` | `final class`, `RuntimeException` | A directory, a disk or a source that failed, or a feed too long; the previous file is left in place |
