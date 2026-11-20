<!-- phil-dev source-sha256=1b75dca09fbb97533d32c3588aac291372c6ad7c623895012eff56909746eef1 -->
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

| Clé | Valeur par défaut | Ce qu'elle change |
|---|---|---|
| `sellable_type` | `product` | Le type de référence sous lequel le catalogue vend les produits : `TemplateOptions` déclare les options de ce type |
| `default_locale` | `en` | La locale dans laquelle un produit est nommé quand son motif n'a aucune version linguistique |
| `images_directory` | `null` | Le répertoire du magasin d'images : les photos et les illustrations y sont lues, les maquettes y sont écrites ; sans lui, `Mockups` et `ImageStore` ne sont pas enregistrés |
| `mockups.prefix` | `mockups` | Le répertoire des maquettes dans le magasin |
| `mockups.shading` | `0.8` | L'intensité de l'ombrage du tissu par-dessus les illustrations, de 0 à 1 |
| `mockups.quality` | `82` | La qualité WebP des maquettes, de 0 à 100 |
| `production.directory` | `null` | Le répertoire privé où sont écrits les fichiers d'impression et les ordres de production ; sans lui, `PrintProduction`, `ProductionStore` et le *handler* de Messenger ne sont pas enregistrés. Il exige `images_directory` |
| `production.files_prefix` | `print-files` | Le répertoire des fichiers d'impression dans le magasin de production |
| `production.orders_prefix` | `production-orders` | Le répertoire des ordres de production dans le magasin de production |
| `production.fonts` | `{}` | Le fichier TrueType ou OpenType de chaque police, par le nom que donnent les options de texte et les éléments d'impression |
| `production.on_purchase` | `false` | Envoie `ProducePrintFiles` quand le noyau annonce `OrderPurchased` |

Le magasin d'images se trouve habituellement sous le répertoire public : les chemins des maquettes que garde un membre
sont alors les URL de la page, relatives à l'URL de base du magasin.

Le répertoire de production contient ce que les acheteurs ont saisi : gardez-le hors du répertoire public. Quand
`asset_mapper` est configuré, le module ajoute son contrôleur Stimulus aux chemins d'AssetMapper sous le nom
`@phil/commerce-product-template-bundle`.
