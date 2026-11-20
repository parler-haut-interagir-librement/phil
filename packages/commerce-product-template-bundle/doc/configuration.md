# Configuration

```yaml
# config/packages/phil_commerce_product_template.yaml
phil_commerce_product_template:
    sellable_type: product
    default_locale: en
    images_directory: '%kernel.project_dir%/public/shop-images'
    mockups:
        prefix: mockups
        shading: 0.8
        quality: 82
    production:
        directory: '%kernel.project_dir%/var/production'
        files_prefix: print-files
        orders_prefix: production-orders
        fonts:
            'Example Serif': '%kernel.project_dir%/assets/fonts/ExampleSerif.ttf'
        on_purchase: false
```

| Key | Default | What it changes |
|---|---|---|
| `sellable_type` | `product` | The reference type the catalogue sells products under: `TemplateOptions` declares the options of that type |
| `default_locale` | `en` | The locale a product is named in when its design has no language version |
| `images_directory` | `null` | The directory of the store of images: photos and artworks are read there, mockups written there; without it, `Mockups` and `ImageStore` are not registered |
| `mockups.prefix` | `mockups` | The directory of the mockups inside the store |
| `mockups.shading` | `0.8` | The strength of the fabric's shading over the artworks, from 0 to 1 |
| `mockups.quality` | `82` | The WebP quality of the mockups, from 0 to 100 |
| `production.directory` | `null` | The private directory print files and production orders are written to; without it, `PrintProduction`, `ProductionStore` and the Messenger handler are not registered. It requires `images_directory` |
| `production.files_prefix` | `print-files` | The directory of the print files inside the production store |
| `production.orders_prefix` | `production-orders` | The directory of the production orders inside the production store |
| `production.fonts` | `{}` | The TrueType or OpenType file of each font, by the name text options and print elements give |
| `production.on_purchase` | `false` | Dispatch `ProducePrintFiles` when the core announces `OrderPurchased` |

The store of images is usually under the public directory, so the paths of the mockups a member keeps are the URLs
of the page, relative to the store's base URL.

The production directory holds what buyers typed: keep it outside the public directory. With `asset_mapper`
configured, the module adds its Stimulus controller to AssetMapper's paths as `@phil/commerce-product-template-bundle`.
