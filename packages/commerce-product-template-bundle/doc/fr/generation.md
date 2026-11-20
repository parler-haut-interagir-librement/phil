<!-- phil-dev source-sha256=2bf3bf620484bcbb522a590fc6f4c661b12686378f7948777132acdd412077e9 -->
# Génération

## Enregistrer un gabarit et un motif

Un gabarit et un motif sont construits avec `phil/commerce-product-template` puis enregistrés. Un gabarit dit aussi ce
que le catalogue fait de ses produits — leur sorte et leur catégorie de taxe :

```php
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductDesign;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductTemplate;

$templates->add(ProductTemplate::create($unisexShirt, ProductKind::Physical, 'goods'));
$designs->add(ProductDesign::create($gnome));
$entityManager->flush();
```

`redefine()` remplace la définition sous le même code, `classify()` la sorte et la catégorie de taxe ; les produits
changent à la génération suivante.

## Générer

```php
$report = $generation->generate('NAQ001', 'unisex');
$entityManager->flush();
```

`CatalogGeneration::generate()` planifie les produits du motif sur le gabarit, puis, pour chaque UGS planifiée :

| Le catalogue | La génération |
|---|---|
| N'a aucun produit de cette UGS | Le crée : la sorte et la catégorie de taxe du gabarit, le nom, le prix, les attributs, les canaux |
| A le produit, comme membre de ce groupe | Le renomme, change son prix, le classe, le décrit de nouveau et le vend par ses canaux |
| A le produit à l'état retiré | Fait de même et le rétablit |

Ensuite, elle **retire** chaque membre du groupe que le plan n'a plus, avec son produit : une couleur que le motif ne
permet plus, une version linguistique abandonnée. Rien n'est jamais supprimé : les commandes et les reçus qui nomment
un produit gardent donc leur sens ; générer de nouveau une fois la couleur revenue rétablit ses produits.

Le **nom** est celui du motif, celui du gabarit et le libellé de chaque valeur, dans la locale de la version
linguistique, ou dans la `default_locale` configurée : `Le gnome – T-shirt unisexe (Ordinaire, M, Noir)`. Les
**attributs** sont `design`, `template`, `language` quand le motif a des versions, et le code de chaque valeur par axe —
`size: M`, `colour: BK` ; un attribut qu'un produit n'a plus est retiré. Les **canaux** sont ceux que donne le plan :
la version congrès d'un t-shirt n'est vendue que par le canal du kiosque du congrès.

La génération ne fait pas de `flush` : l'application vide son unité de travail une fois la génération terminée, et
l'exécute dans une transaction quand elle veut tout ou rien.

## Le rapport

`GenerationReport` liste les UGS `created`, `updated`, `restored`, `unchanged` et `withdrawn`. Lancer une génération
deux fois de suite donne un rapport dont `changedNothing()` est vrai : générer est idempotent.

## Retirer un groupe

`CatalogGeneration::withdraw('NAQ001', 'unisex')` retire le groupe, ses membres et leurs produits ; son rapport liste
les membres qui étaient encore actifs. Générer le groupe de nouveau le rétablit.

## Ce qui est refusé

`TemplateRefused`, avant que quoi que ce soit change :

| Fabrique | Quand |
|---|---|
| `unknownTemplate()`, `unknownDesign()`, `unknownGroup()` | Aucun gabarit, motif ou groupe de ce nom |
| `plan()` | La bibliothèque refuse le plan : des UGS en collision, un gabarit sur lequel le motif n'est pas fait, une illustration qui ne peut pas être placée |
| `foreignSku()` | Une UGS planifiée appartient à un autre groupe, ou à un produit du catalogue hors de tout groupe |
| `kind()` | La sorte du gabarit a changé : un produit ne change jamais de sorte |
| `currency()` | La devise du gabarit a changé : un produit ne change jamais de devise |

Une génération refusée laisse le catalogue et les groupes comme ils étaient.

## L'exécuter

La génération lit et écrit une rangée par produit : pour un gabarit de milliers de combinaisons, exécutez-la dans un
*worker* — une commande de console ou un *handler* de message de l'application, dont le message ne porte que les codes
du motif et du gabarit.
