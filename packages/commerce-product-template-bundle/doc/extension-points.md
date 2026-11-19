# Extension points

The module is at `0.x`: its extension points are stable only from its 1.0 release.

| To | Do |
|---|---|
| Store templates, designs or groups elsewhere | Implement `ProductTemplateRepository`, `ProductDesignRepository` or `ProductGroupRepository` and alias the interface to the service |
| Keep images elsewhere than in a directory | Implement `Application\Port\ImageStore` — a bucket, a media library — and alias the interface to it |
| Sell products under another reference type | Set `sellable_type` |
| Name products in another locale | Set `default_locale`, or give the design language versions |
| Bound the size of a plan | Redefine the `VariantPlanner` service with its `$maxVariants` argument |
| Generate in a worker | Call `CatalogGeneration` and `Mockups` from a message handler of the application, its message carrying the design and template codes |
| Show a group | Read `ProductGroups::page()`, and choose the SKU with the `variant-selector` Stimulus controller ([Choosing a product](selector.md)) |
| Print what changes per product or line | Declare a print layout on the design ([Print files and production orders](production.md)) |
| Keep print files and production orders elsewhere | Implement `Application\Port\ProductionStore` — a private bucket — and alias the interface to it |
| Find fonts elsewhere | Implement `Application\Port\PrintFonts` and alias the interface to it |
| Produce in a worker | Route `ProducePrintFiles` to an asynchronous transport, and dispatch it after the draws, or set `production.on_purchase` |
| Send the production order to the printer | Read `PrintProduction::orderPath()` from the store once `produce()` returns, in a handler of the application |

The public services are `CatalogGeneration`, `ProductGroups`, `Mockups` (with `images_directory`), `PrintProduction`
(with `production.directory`) and the repositories' and stores' interfaces. `TemplateOptions` is private: the core
finds it by its tag. Every service holds no state between two requests, so a worker serves them as they are.
