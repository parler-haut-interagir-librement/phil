<!-- phil-dev source-sha256=e0e7364dc6e46923326ff0d85616f51e9cdb26e1b9df651ea09de6c4a9b10fe2 -->
# Tests

## Comment le module est testé

| Domaine | Ce qui est couvert |
|---|---|
| `Product`, `ProductComponent`, `ProductTranslation` | Chaque règle de création, de changement de nom, de changement de prix, de classement et d'attributs ; le retrait ; l'offre dans un canal, retiré ou hors de la sélection, et celle d'une variante venue de sa famille ; les axes, les variantes et leurs valeurs, les produits composés et leurs parties, chaque refus ; les noms traduits ; les numéros de série des produits physiques et de leurs variantes ; payez ce que vous voulez et ses bornes, les prix des copies communautaires |
| `PriceBook`, `PriceTier`, `PriceBookOrder` | Les conditions de groupe, de canal et de période, la fin exclue ; les paliers de quantité ; le remplacement et le retrait d'un prix ; les listes `after`, leurs bornes et chaque refus ; les grilles classées par code puis par leurs listes, les noms inconnus ignorés, les boucles mises de côté |
| `ProductVariants`, `CompositeProducts` | Les variantes créées, trouvées par leurs valeurs dans n'importe quel ordre, les retirées sur demande, les choix de celles encore vendues, un *SKU* ou des valeurs déjà pris refusés ; les attributs par-dessus ceux de la famille, ce qu'une famille transmet, la fourchette de prix des variantes encore vendues ; les parties vérifiées contre le catalogue, sur un seul niveau, lues dans une ligne de commande |
| `PriceRuleSellables`, `CommunityCopies` | Un prix choisi déclaré comme option de montant du prix de base jusqu'au maximum du produit ou à celui par défaut du site, son libellé traduit, refusé pour un produit sans maximum, un produit dont l'acheteur ne choisit plus, ou une commande dans une autre devise ; vendu dans ses bornes et refusé hors d'elles ou laissé vide, un autre prix faisant une autre ligne, par un vrai `OrderModifier`, `OptionSupplementProcessor` et les sélections des canaux ; une copie communautaire à son prix, décrite par le traducteur ou en anglais ; un financement une fois par commande, à partir des copies achetées et de ce qui est payé au-dessus du prix suggéré, *SKU* de chiffres compris ; des réclamations une par personne tant qu'il reste des copies, sous le verrou du produit ; le `OrderPurchased` du noyau |
| `PriceBookOrderMigration` | Une table classée par `priority` construite, migrée et comparée au mappage ; l'ordre des grilles gardé, retirées comprises ; une table vide ; une seconde exécution qui ne change rien — sur SQLite dans la suite unitaire, sur MySQL 8 et PostgreSQL 17 dans la suite d'intégration |
| `ProductAvailability` | L'offre d'un produit dans un canal, celle d'une variante rendue plus stricte par celle de sa famille, une famille laissée à son lien direct, une famille disparue, les autres types laissés aux autres ; un produit non commandable refusé par un vrai `OrderModifier` et répondu par `SellableOffers` |
| `DbalProductListing` | La sélection d'un canal ou de tous les canaux, l'offre la plus stricte d'une variante et de sa famille, ce que seul son lien direct atteint jamais listé, une rangée stockée commandable sans son prix lue comme non commandable, les familles omises sur demande, le curseur à travers les pages et les lots, les deux sens, dix lots au plus, les noms le long de la chaîne des locales, les bornes d'une page — sur SQLite dans la suite unitaire, sur MySQL 8 et PostgreSQL 17 dans la suite d'intégration |
| `CatalogProductSource`, `ProductFeeds`, la commande et le *handler* | Chaque champ lu dans les attributs, les axes et les gabarits, le prix soldé tiré des grilles de prix quand il est plus bas, la disponibilité d'un produit non commandable, un produit listé sans son prix omis, un article que le flux ne peut pas porter omis et journalisé, les pages lues, les flux publiés par nom, par le message et par la commande, un flux inconnu ou non accessible en écriture signalé |
| `ProductFeedController`, `config/routes.php` | Le fichier d'un flux avec son type de média, `nosniff`, `Last-Modified`, son étiquette d'entité et son `max_age` ; `304` sur l'étiquette ou la date renvoyée ; un flux non déclaré, pas encore publié ou un répertoire introuvable ; le chemin, l'exigence et les méthodes de la route |
| `ProductTexts`, `ProductSellables` | Les noms lus le long d'une chaîne de repli à partir des traductions stockées, une traduction écrite dans sa propre locale, les lignes décrites dans la locale de l'acheteur, les familles jamais vendues |
| `TaxonomyProductClassification` | Les étiquettes et les catégories posées, lues et oubliées dans les tables de `phil/taxonomy-bundle`, les produits de la branche d'une catégorie, une catégorie inconnue refusée |
| `ProductSellables`, `PriceBookPrices` | Les prix de base, les produits retirés, la première grille qui s'applique et donne un prix l'emportant, les autres devises et les articles qui ne sont pas des produits ignorés, les grilles lues une seule fois jusqu'à la réinitialisation, une ligne dont le prix passe par un vrai `OrderModifier` |
| Mappage | Chaque colonne des cinq tables — produits, leurs traductions, grilles de prix, le financement et les réclamations des copies communautaires —, telles que les crée la migration d'une application |
| `PhilCommerceCatalogBundle` | Les résolveurs branchés, la liste et les flux câblés avec leur commande et leur *handler*, les liens et les champs d'un flux vérifiés, les grilles de prix réinitialisées avec le noyau, les copies communautaires à l'écoute de `OrderPurchased`, les traductions configurées ou non, la taxonomie gardée ou retirée, les adaptateurs et le mappage Doctrine, le refus sans le noyau |

Une suite fonctionnelle, sous `tests/Functional/`, démarre un vrai noyau avec FrameworkBundle, DoctrineBundle sur une
base SQLite en mémoire, le noyau du commerce et le module : `CatalogFlowTest` stocke des produits et des grilles de
prix, les relit, donne un prix aux commandes et les taxe par le conteneur, stocke une famille, ses variantes et un
produit composé et les vend comme des lignes, refuse de vendre la famille, relit un nom traduit, et finance des copies
communautaires à partir d'une commande que nomme le `OrderPurchased` du noyau, livré deux fois et compté une seule, puis
en réclame une dans une transaction, et publie le flux de sa configuration à partir du catalogue stocké. Quand le module
est installé seul, là où FrameworkBundle est un prérequis de développement, la suite est sautée.

La suite d'intégration, `tests/Integration`, exécute `PriceBookOrderMigration` et `DbalProductListing` sur MySQL 8 et PostgreSQL 17
quand `PHIL_TEST_MYSQL_URL` et `PHIL_TEST_POSTGRESQL_URL` nomment un serveur, et est sautée autrement :

```sh
eval "$(vendor/bin/phil-dev services env)"
vendor/bin/phpunit packages/commerce-catalog-bundle/tests/Integration
```

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/commerce-catalog-bundle/tests
```

Installé seul :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

## Fuzzing

`tests/Fuzz/chosen-price.php` donne à la valeur de l'option `price` d'un prix choisi du texte que personne n'a écrit :
quoi qu'il lise, l'option le refuse, ou le garde tel qu'écrit et facture ce nombre d'unités mineures, dans les bornes du
produit.
Depuis la racine du monodépôt :

```sh
composer fuzz -- chosen-price
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark/` mesurent `PriceBookPrices::priceFor()`, avec les grilles déjà lues et
sur la première ligne d'une requête, sur des grilles de 10 à 500 *SKU*. Depuis la racine du monodépôt :

```bash
composer bench -- packages/commerce-catalog-bundle/tests/Benchmark
```
