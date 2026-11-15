<!-- phil-dev source-sha256=29ddde3dbbe07c81b7031aeff923537d6a7dfa3367d77e87959fd88a57d0006f -->
# Flux

Un flux est un fichier qu'un comparateur ou une place de marché récupère à intervalles réguliers : un article par produit
ou variante que la boutique vend. Le paquet l'écrit à partir d'une `ProductSource`, dans l'un de deux formats, et le met
en place d'un bloc.

## Les articles

Un `FeedItem` se fait avec ses cinq champs obligatoires, puis tous les autres par nom :

```php
new FeedItem(
    'SHIRT-M-RED',
    'Shirt',
    Url::fromString('https://shop.example/shirt?size=m&colour=red'),
    Money::of(3_000, Currency::of('CAD')),
    Availability::InStock,
    description: "Soft cotton.\nWashes cold.",
    imageLink: Url::fromString('https://shop.example/images/shirt-red.jpg'),
    salePrice: Money::of(2_400, Currency::of('CAD')),
    salePeriod: new SalePeriod(new DateTimeImmutable('2026-11-27'), new DateTimeImmutable('2026-12-01')),
    brand: 'Gnome',
    mpn: 'SH-1',
    itemGroupId: 'SHIRT',
    color: 'Red',
    size: 'M',
    ageGroup: AgeGroup::Adult,
    gender: Gender::Unisex,
    shipping: [
        new Shipping('CA', Money::of(500, Currency::of('CAD'))),
        new Shipping('CA', Money::of(1_200, Currency::of('CAD')), 'Express'),
    ],
    shippingWeight: 250,
    customLabels: ['Autumn sale'],
);
```

| Champ | Type | Règle | Écrit comme |
|---|---|---|---|
| `id` | chaîne | 1 à 50 caractères ASCII visibles, unique dans le flux | `id` |
| `title` | chaîne | 1 à 150 caractères | `title` |
| `link` | `Url` | La page du produit | `link` |
| `price` | `Money` | Non négatif | `price`, `25.00 CAD` |
| `availability` | `Availability` | `InStock`, `OutOfStock`, `Preorder`, `Backorder` | `availability`, `in_stock` |
| `description` | chaîne | 5 000 caractères au plus ; sauts de ligne conservés | `description` |
| `imageLink` | `Url` | L'image principale ; Google Merchant Center refuse un article sans image | `image_link` |
| `additionalImageLinks` | liste d'`Url` | 10 au plus | `additional_image_link`, une fois par image |
| `availabilityDate` | `DateTimeImmutable` | Seulement avec `Preorder` ou `Backorder` ; écrite en UTC | `availability_date` |
| `salePrice` | `Money` | Dans la devise du prix, inférieur à lui, non négatif | `sale_price` |
| `salePeriod` | `SalePeriod` | Seulement avec un prix soldé ; sa fin après son début | `sale_price_effective_date`, un intervalle ISO 8601 en UTC |
| `condition` | `Condition` | `New` par défaut, `Refurbished`, `Used` | `condition` |
| `brand` | chaîne | 70 caractères au plus | `brand` |
| `gtin` | chaîne | 8, 12, 13 ou 14 chiffres, pas tous des zéros, dont le dernier vérifie les autres | `gtin` |
| `mpn` | chaîne | 70 caractères au plus | `mpn` |
| `itemGroupId` | chaîne | 1 à 50 caractères ASCII visibles, partagés par les variantes d'un même produit | `item_group_id` |
| `color`, `size`, `pattern` | chaîne | 100 caractères au plus | `color`, `size`, `pattern` |
| `material` | chaîne | 200 caractères au plus | `material` |
| `productType` | chaîne | 750 caractères au plus, le chemin de catégorie propre à la boutique | `product_type` |
| `googleProductCategory` | chaîne | 750 caractères au plus : une catégorie de la taxonomie de produits que publie la spécification, par son numéro ou son chemin | `google_product_category` |
| `ageGroup` | `AgeGroup` | `Newborn`, `Infant`, `Toddler`, `Kids`, `Adult` ; demandée pour les vêtements | `age_group`, `adult` |
| `gender` | `Gender` | `Male`, `Female`, `Unisex` ; demandé pour les vêtements | `gender`, `unisex` |
| `shipping` | liste de `Shipping` | 100 au plus, un par pays et par service | `shipping`, une fois par frais |
| `shippingWeight` | entier | 1 à 1 000 000 de grammes | `shipping_weight`, `250 g` |
| `multipack` | entier | 2 à 10 000 unités d'un même produit vendues comme un seul article | `multipack` |
| `isBundle` | booléen | `false` par défaut : un produit principal vendu avec d'autres que la boutique a réunis | `is_bundle`, `yes` si vrai |
| `customLabels` | liste de chaînes | 5 au plus, 100 caractères chacune ; une étiquette vide garde les suivantes à leur numéro | `custom_label_0` à `custom_label_4` |

Un article sans GTIN ni MPN est écrit avec `identifier_exists` à `no`, comme l'est un produit artisanal ou sur mesure.

**Le texte** est vérifié de la même façon dans chaque champ. Il doit être en UTF-8, sinon l'article est refusé. Les
caractères de contrôle que XML 1.0 ne peut pas contenir, et les points de code U+FFFE et U+FFFF, sont retirés ; les
suites d'espaces, de tabulations et de sauts de ligne deviennent une seule espace, sauf dans une description, qui garde
ses sauts de ligne ; le texte est rogné. Un texte vide n'est aucune valeur, et un titre vide fait refuser l'article. Les
longueurs comptent des caractères, non des octets.

**La livraison** est ce que coûte la livraison de l'article, par pays et, quand la boutique en a plusieurs, par
service. Un `Shipping` porte le pays, deux majuscules de l'ISO 3166-1, le prix, une valeur `phil/money` non négative,
éventuellement nulle, et un nom de service facultatif de 100 caractères au plus qui ne contient ni deux-points ni
virgule, car ils séparent les valeurs du fichier TSV. Un article sans frais de livraison laisse sa livraison aux tarifs
qu'un lecteur détient dans le compte de la boutique.

**Les adresses** sont des valeurs `phil/url` : absolues, HTTP ou HTTPS, sans identifiants de connexion, sous leur forme
canonique. Une source les fait avec `Url::fromString()`, qui refuse tout le reste.

Un champ que l'article ne peut pas porter lève `InvalidFeedItem`, avec un message qui nomme l'article et le champ.

## Le canal

Un `FeedChannel` est ce que le flux dit de lui-même : le nom de la boutique, de 1 à 150 caractères, son adresse, et une
description de 1 à 5 000 caractères. Le fichier RSS les écrit dans son `channel` ; le fichier TSV n'a pas de place pour
eux.

## Les deux formats

`FeedFormat` les nomme et donne à chacun son sérialiseur, son extension et son type de média.

| Format | Fichier | Type de média |
|---|---|---|
| `FeedFormat::Xml` | RSS 2.0, `<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">`, un `<item>` par article, chaque champ un élément `g:` | `application/rss+xml; charset=UTF-8` |
| `FeedFormat::Tsv` | Une ligne d'en-tête qui nomme les champs, puis une ligne par article, chaque champ dans sa colonne, les champs vides compris | `text/tab-separated-values; charset=UTF-8` |

En RSS, un champ sans valeur est omis, chaque frais de livraison est un élément `g:shipping` qui contient ses
`g:country`, `g:service` et `g:price`, et le texte est échappé pour XML. En TSV, une tabulation ou un saut de ligne dans
une valeur devient une espace, si bien que chaque ligne garde ses colonnes ; les images supplémentaires forment une
seule valeur séparée par des virgules, une virgule dans une adresse s'écrivant `%2C` ; les frais de livraison forment
une seule valeur séparée par des virgules, chacun écrit `country:region:service:price`, la région restant vide,
`CA:::5.00 CAD,CA::Express:12.00 CAD`. Les deux fichiers terminent chaque ligne par un saut de ligne.

Les lecteurs qui acceptent cette spécification lisent les mêmes fichiers : Google Merchant Center et les catalogues de
Meta récupèrent tous deux le fichier RSS ou TSV à son adresse. Ce que chaque lecteur exige en plus des cinq champs
diffère : Google refuse un article sans image, Meta un article sans description et sans marque, et tous deux demandent
la tranche d'âge, le genre, la couleur et la taille des vêtements vendus dans certains pays. La source donne ce que ses
lecteurs demandent.

## La sûreté du fichier

- **L'échappement.** Chaque valeur est nettoyée à la création de son article, puis échappée par le sérialiseur : en
  RSS, `&`, `<`, `>`, `"` et `'` deviennent des entités, et les noms d'éléments sont ceux de la spécification, jamais
  des données ; en TSV, les séparateurs d'une ligne — tabulation, retour chariot, saut de ligne — n'atteignent jamais
  une cellule, et une cellule n'a pas besoin de guillemets.
- **Les tableurs.** Le fichier TSV est écrit pour les lecteurs de flux, qui prennent chaque cellule comme du texte. Une
  cellule n'est pas modifiée pour empêcher un tableur de lire `=`, `+`, `-` ou `@` à son début comme une formule : le
  faire changerait les titres qu'un lecteur affiche. Ouvrez le fichier dans un tableur seulement avec les formules
  désactivées, ou importez-le comme du texte.
- **La mémoire.** Un flux n'est jamais détenu en entier : le publicateur écrit chaque article au moment où la source le
  donne.
- **La longueur.** Le publicateur s'arrête à son maximum d'articles, et chaque champ à son maximum de caractères, si bien
  que la taille d'un flux a une borne.
- **Les *workers*.** Le publicateur, les sérialiseurs et les articles ne gardent aucun état entre deux flux.

## Publier le fichier

```php
$feed = new FeedPublisher()->publish($source, $channel, FeedFormat::Tsv->serializer(), $path);
```

1. Le répertoire de `$path` doit exister et être accessible en écriture ; sinon `FeedNotWritten` est levée et rien
   n'est écrit.
2. Un fichier temporaire est créé dans ce répertoire, nommé d'après le flux et commençant par un point.
3. L'en-tête, puis chaque article tel que la source le donne, puis le pied y sont écrits. Seuls les comptes sont gardés,
   si bien que la mémoire reste plate quelle que soit la longueur du catalogue : le banc d'essai écrit dix mille
   articles dans environ 5 Mo, comme pour mille.
4. Le fichier est vidé, fermé, rendu lisible par tous (`0644`), et renommé par-dessus `$path`. Sur un même système de
   fichiers, le renommage est atomique : un lecteur récupère le fichier précédent ou le nouveau, jamais la moitié de
   l'un ou de l'autre.

Si la source lève une exception, que le disque se remplit ou que le renommage échoue, le fichier temporaire est
supprimé, le flux précédent reste en place, et l'exception remonte. Une source qui donne plus d'articles que le maximum
du publicateur — un million par défaut, `new FeedPublisher(maxItems: 50_000)` pour un autre — est refusée par
`FeedNotWritten`, pour qu'une source sans fin ne remplisse pas le disque.

`publish()` retourne un `PublishedFeed` : le chemin, le nombre d'articles et la taille en octets.

Le publicateur ne garde rien entre deux flux : une seule instance sert chaque requête ou chaque message d'un *worker* de
longue durée.

## Planifier le fichier

Un lecteur récupère le fichier à l'adresse qu'on lui a donnée, selon son propre calendrier ; la boutique le réécrit aussi
souvent que son catalogue change, toutes les quelques heures dans la plupart des boutiques. La bibliothèque écrit un
fichier par appel et ne planifie rien. Dans une application Symfony, le bundle qui implémente `ProductSource` envoie un
message qui publie le flux, et le calendrier de l'application, celui de Symfony Scheduler, envoie ce message :
`phil/commerce-catalog-bundle` le fait pour ses produits.

## Ce qui n'est pas écrit

- **L'API d'une place de marché.** La Merchant API de Google, et les API de catalogue des autres places de marché,
  prennent les produits un par un et voient un changement en quelques minutes. Le fichier est ce que chacune d'elles lit ;
  un adaptateur d'API attend un site dont les prix ou le stock changent plus vite que son calendrier.
- **Les règles de flux d'un lecteur.** Les règles d'un lecteur qui renomment ou remplissent des champs restent du côté du
  lecteur.
- **Les champs de moins de lecteurs.** La livraison régionale (une région ou des codes postaux à l'intérieur d'un
  pays), le prix unitaire, la taxe, les versements, les étiquettes énergétiques, les points forts et les détails de
  produit, les destinations qu'un lecteur écarte, et les attributs d'un seul lecteur ne sont pas écrits. Une source qui
  en a besoin écrit son propre `FeedSerializer`.
- **Les GTIN restreints.** Un GTIN est vérifié par sa longueur et sa clé de contrôle ; les plages que GS1 réserve à
  l'usage propre d'une boutique ou aux coupons ne sont pas refusées, et un lecteur qui les refuse le dit dans son
  rapport.
- **Les pays vérifiés dans une liste.** Un pays est deux majuscules ; savoir si l'ISO 3166-1 les attribue est laissé au
  lecteur, pour que la bibliothèque n'ait besoin d'aucune liste de pays.
- **La déduplication.** Chaque identifiant est donné une seule fois par la source : le vérifier dans le publicateur
  garderait chaque identifiant en mémoire.
