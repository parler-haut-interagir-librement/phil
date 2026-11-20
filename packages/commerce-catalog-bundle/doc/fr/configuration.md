<!-- phil-dev source-sha256=e4f44114b5441a1bb78b9090ebb733e08a6b6b28bf75a3c1f6ea7fb306676977 -->
# Configuration

La configuration du module se trouve sous `phil_commerce_catalog`. Sans configuration, chaque produit est affiché sous
son propre nom, aucun flux n'est publié, et un produit sans maximum propre n'est pas vendu à un prix choisi.

```yaml
# config/packages/phil_commerce_catalog.yaml
phil_commerce_catalog:
    pay_what_you_want:
        default_maximum: 50000      # 500.00 in the product's currency
    translations:
        locales:
            fr_CA: [fr]
            fr: [en]
            en: []
```

| Option | Valeur par défaut | Ce qu'elle fait |
|---|---|---|
| `pay_what_you_want.default_maximum` | `null` | Le prix le plus haut qu'un acheteur peut choisir pour un produit sans maximum propre, en unités mineures de la devise du produit, 1 ou plus. Sans lui, un tel produit n'est pas vendu à un prix choisi — voyez [Règles de prix](price-rules.md). |
| `translations.locales` | `{}` | Les locales dans lesquelles les noms des produits sont affichés, chacune avec les locales essayées après elle. Une locale que `phil/locale-dimensions` ne sait pas lire fait échouer la construction du conteneur. |

Quand des locales sont déclarées, `ProductTexts` lit chaque nom dans la locale de la requête — celle du traducteur,
lue à chaque appel, si bien qu'un *worker* sert chaque requête dans la sienne —, et une ligne de commande prend le nom
dans cette locale comme description. Une locale non déclarée, ou une traduction dont le nom n'est pas encore écrit,
retombe sur le nom propre du produit. Voyez [Noms et classement](names-and-classification.md).

## `product_feeds`

Les flux que `phil/product-feeds` écrit à partir du catalogue, par nom ; en déclarer un sans que la bibliothèque soit
installée fait échouer la construction du conteneur. Voyez [Flux de produits](product-feeds.md).

```yaml
phil_commerce_catalog:
    product_feeds:
        google:
            path: '%kernel.project_dir%/public/feeds/google.xml'
            title: 'The Dice Shop'
            link: 'https://shop.example/'
            description: 'Dice, games and accessories.'
            product_link: 'https://shop.example/products/{sku}'
```

| Option | Valeur par défaut | Ce qu'elle fait |
|---|---|---|
| `path` | obligatoire | L'endroit où le fichier est écrit, dans un répertoire existant et accessible en écriture : un que sert le serveur Web, ou tout autre que sert la route de `config/routes.php` |
| `format` | `xml` | `xml`, RSS 2.0 avec l'espace de noms `g:`, ou `tsv` |
| `title`, `link`, `description` | obligatoires | Le nom, l'adresse et la description de la boutique, écrits dans le canal RSS ; le lien est vérifié par `phil/url` à la construction du conteneur |
| `channel` | `null` | Le canal dont la sélection est publiée et dont les grilles de prix donnent les prix soldés ; `null` publie ce que vendent tous les canaux |
| `locale` | `null` | La locale des noms, le long de la chaîne de `translations.locales` ; le nom propre de chaque produit sinon |
| `product_link` | obligatoire | L'adresse de la page d'un produit ; elle nomme `{sku}`, `{family}` ou les deux, sinon la construction du conteneur échoue |
| `image_link` | `null` | L'adresse de l'image d'un produit, construite de la même façon ; un attribut `image` qui contient une adresse l'emporte |
| `attributes` | `{}` | L'attribut, ou l'axe de variante, qu'un champ lit quand ce n'est pas son propre nom : `description`, `image`, `brand`, `gtin`, `mpn`, `condition`, `availability`, `color`, `size`, `material`, `pattern`, `product_type` ; un autre champ fait échouer la construction du conteneur |
| `allow_localhost` | `false` | Si les adresses peuvent être sur `localhost` ou sous `.localhost`, en développement et dans les tests |
| `max_age` | `3600` | La durée, en secondes, pendant laquelle un cache garde le fichier que sert la route de `config/routes.php` ; zéro ou plus |
