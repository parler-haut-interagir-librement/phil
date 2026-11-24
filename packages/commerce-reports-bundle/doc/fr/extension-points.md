<!-- phil-dev source-sha256=bd45f5f06fc17f761b8955c642b331822d009f679d70fd0be62d8ce5e689e813 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Lire les ventes d'un autre canal | Créez un alias de `SoldLines` vers votre propre service |
| Compter les retours | Créez un alias de `ReturnedLines` vers votre propre service |
| Lire le stock ailleurs | Créez un alias de `StockFigures` vers votre propre service |
| Lister les UGS en stock | Créez un alias de `StockedSkus` vers votre propre service |

Un alias ou un service que l'application définit sous le nom d'un port est conservé ; `PortsPass` ne remplit que les
ports laissés de côté. Un port fournit ce qu'il lit un élément à la fois : les rapports ne retiennent jamais les lignes.

## Les ports

Les quatre se trouvent dans `Phil\CommerceReportsBundle\Application\Port`.

| Port | Méthode | Ce qu'il donne |
|---|---|---|
| `SoldLines` | `between(Period): iterable<SoldLine>` | Les lignes vendues pendant la période : UGS, produit, catégorie, canal, lieu, caissier, quantité, montant brut et remises dans une devise, moment de la vente. |
| `ReturnedLines` | `between(Period): iterable<ReturnedLine>` | Les lignes retournées pendant la période : les mêmes dimensions, la quantité, le montant remboursé sans les taxes, le moment du retour. |
| `StockFigures` | `of(string $sku, Period): StockFigure` | Les unités en main au début de la période, reçues pendant celle-ci, disponibles maintenant, et le moment de la dernière vente ; des zéros et aucune vente pour une UGS inconnue. |
| `StockedSkus` | `all(): iterable<StockedSku>`, `find(string $sku): ?StockedSku` | Les UGS en stock, chacune une fois, avec leurs noms et leurs catégories. |

Une dimension qu'un module ne connaît pas — une vente sans caissier, un produit sans catégorie — vaut `null`. Les
objets-valeurs refusent avec `InvalidFigure` un texte vide, un texte de plus de 200 caractères, une quantité hors de
1 à un milliard et un montant négatif.

## Les adaptateurs

| Adaptateur | Port | Lit |
|---|---|---|
| `PosSoldLines` | `SoldLines` | Les sessions de caisse fermées pendant la période (`CashSessionRepository::closedBetween()`), leurs ventes (`RegisterSaleRepository::salesOf()`), la caisse de chaque vente (`RegisterRepository::findRegister()`) et sa commande (`OrderRepository::find()`). |
| `LogisticsStockFigures` | `StockFigures` | Les niveaux de l'UGS (`StockRepository::levelsOf()`) et le journal de chacune (`StockRepository::journal()`). |
| `InMemorySoldLines`, `InMemoryReturnedLines`, `InMemoryStockFigures`, `InMemoryStockedSkus` | chacun son port | Les éléments donnés à leur constructeur : pour les tests, et la valeur par défaut d'un port sans module. |

`PosSoldLines` compte une vente une fois sa session de caisse fermée, dans la période où elle a été saisie ; le montant
brut d'une ligne est son sous-total avec ses options, moins les taxes que ses prix comprennent ; ses remises sont ses
promotions et sa part des promotions propres à la commande, répartie selon le montant brut avec `Money::allocate()`.
Les pourboires, les frais de livraison, les frais et les commandes annulées sont laissés de côté. La catégorie d'une
ligne est celle que `StockedSkus` donne à son UGS.

`LogisticsStockFigures` additionne les changements en main avant la période pour le stock de départ, les mouvements
`received` à l'intérieur de celle-ci pour les unités reçues — les transferts entre lieux ne déplacent aucune unité de
l'UGS dans son ensemble —, les unités disponibles des niveaux maintenant, et prend le dernier mouvement `shipped` qui
a retiré des unités de l'étagère comme dernière vente. Il lit chaque journal en entier : son coût croît avec les
mouvements de l'UGS.

## Ce que les modules ne listent pas encore

Ces lectures n'ont aucune API publique dans les modules de commerce ; le bundle garde le port et son adaptateur en
mémoire jusqu'à ce qu'elles en aient une :

- le noyau de commerce ne liste aucune commande passée pendant une période : `OrderRepository` trouve une commande par
  son numéro, de sorte que les ventes des canaux en ligne n'atteignent `SoldLines` que par l'application ;
- le point de vente ne liste aucune vente saisie pendant une période : `PosSoldLines` les atteint par les sessions
  fermées pendant celle-ci, et manque une vente d'une session encore ouverte ou fermée après la période ;
- le module des retours ne liste aucun retour reçu pendant une période : `ReturnRepository` liste les retours d'une
  commande, et ceux autorisés avant un moment ;
- ni le catalogue ni le module de logistique ne listent les UGS qu'ils gardent : `ProductRepository` et
  `StockRepository` trouvent une UGS, ou les niveaux d'UGS déjà nommées.
