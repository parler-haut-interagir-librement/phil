# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\CommerceProductTemplateBundle\PhilCommerceProductTemplateBundle` | `final class` | The bundle: configuration, mapping, services |
| `Phil\CommerceProductTemplateBundle\Application\CatalogGeneration` | `final readonly class` | Generates and withdraws the products of a group |
| `Phil\CommerceProductTemplateBundle\Application\ProductGroups` | `final readonly class` | Reads the page of a group |
| `Phil\CommerceProductTemplateBundle\Application\GroupPage` | `final readonly class` | A group's name, selectors and members in a locale |
| `Phil\CommerceProductTemplateBundle\Application\TemplateOptions` | `final readonly class` | The core's `SellableOptions` for generated products |
| `Phil\CommerceProductTemplateBundle\Application\Mockups` | `final readonly class` | Composes the mockups of a group |
| `Phil\CommerceProductTemplateBundle\Application\Port\ImageStore` | interface | Reads images and writes mockups by path |
| `Phil\CommerceProductTemplateBundle\Application\PrintProduction` | `final readonly class` | Composes the print files of an order and writes its production order |
| `Phil\CommerceProductTemplateBundle\Application\ProductionCache` | `final class` | Internal: what one production run reads once |
| `Phil\CommerceProductTemplateBundle\Application\Message\ProducePrintFiles` | `final readonly class` | The message that asks for the production of an order, by its number |
| `Phil\CommerceProductTemplateBundle\Application\Port\ProductionStore` | interface | Reads and writes print files and production orders by path |
| `Phil\CommerceProductTemplateBundle\Application\Port\PrintFonts` | interface | The fonts texts are printed in, by name |
| `Phil\CommerceProductTemplateBundle\Domain\Print\PrintElementKind` | enum | Image, text, choice or dice |
| `Phil\CommerceProductTemplateBundle\Domain\ValueObject\PrintElement` | `final readonly class` | One element of a design's print layout |
| `Phil\CommerceProductTemplateBundle\Domain\ValueObject\DiceSlot` | `final readonly class` | One die of a dice element: its box, faces and images |
| `Phil\CommerceProductTemplateBundle\Domain\ValueObject\DiceCode` | `final readonly class` | The results of dice, read from a draw and written one character per die |
| `Phil\CommerceProductTemplateBundle\Domain\ValueObject\PrintFile` | `final readonly class` | One print file: face, zone, ink, size, resolution, path |
| `Phil\CommerceProductTemplateBundle\Domain\ValueObject\ProductionLine` | `final readonly class` | The units of a product printed alike |
| `Phil\CommerceProductTemplateBundle\Domain\ValueObject\ProductionOrder` | `final readonly class` | What the printer receives, in JSON and CSV |
| `Phil\CommerceProductTemplateBundle\Domain\Model\ProductTemplate` | `final class` | A stored template, its kind, tax category and options |
| `Phil\CommerceProductTemplateBundle\Domain\Model\ProductDesign` | `final class` | A stored design, its options and its print layout |
| `Phil\CommerceProductTemplateBundle\Domain\Model\ProductGroup` | `final class` | The group of a design on a template |
| `Phil\CommerceProductTemplateBundle\Domain\Model\GroupMember` | `final class` | A product of a group, by SKU |
| `Phil\CommerceProductTemplateBundle\Domain\ValueObject\TemplateOption` | `final readonly class` | An option of the core with a condition |
| `Phil\CommerceProductTemplateBundle\Domain\ValueObject\GenerationReport` | `final readonly class` | What a generation did, by SKU |
| `Phil\CommerceProductTemplateBundle\Domain\Exception\TemplateRefused` | `final class` | What the module refuses |
| `Phil\CommerceProductTemplateBundle\Domain\Repository\ProductTemplateRepository` | interface | Finds and stores templates |
| `Phil\CommerceProductTemplateBundle\Domain\Repository\ProductDesignRepository` | interface | Finds and stores designs |
| `Phil\CommerceProductTemplateBundle\Domain\Repository\ProductGroupRepository` | interface | Finds and stores groups and members |
| `Phil\CommerceProductTemplateBundle\Infrastructure\Doctrine\DoctrineProductTemplateRepository` | `final readonly class` | Templates on Doctrine |
| `Phil\CommerceProductTemplateBundle\Infrastructure\Doctrine\DoctrineProductDesignRepository` | `final readonly class` | Designs on Doctrine |
| `Phil\CommerceProductTemplateBundle\Infrastructure\Doctrine\DoctrineProductGroupRepository` | `final readonly class` | Groups and members on Doctrine |
| `Phil\CommerceProductTemplateBundle\Infrastructure\Filesystem\DirectoryImageStore` | `final readonly class` | The store of images in a directory |
| `Phil\CommerceProductTemplateBundle\Infrastructure\Filesystem\DirectoryProductionStore` | `final readonly class` | The production store in a directory readable by its owner only |
| `Phil\CommerceProductTemplateBundle\Infrastructure\Fonts\ConfiguredFonts` | `final readonly class` | The fonts of `production.fonts` |
| `Phil\CommerceProductTemplateBundle\Infrastructure\Messenger\ProducePrintFilesHandler` | `final readonly class` | Handles `ProducePrintFiles`, retrying what may succeed later |
| `Phil\CommerceProductTemplateBundle\Infrastructure\Messenger\ProduceOnPurchase` | `final readonly class` | Dispatches `ProducePrintFiles` on `OrderPurchased`, with `production.on_purchase` |
| `@phil/commerce-product-template-bundle/variant-selector` | Stimulus controller | Chooses a product of a group, the combinations it does not sell disabled |
