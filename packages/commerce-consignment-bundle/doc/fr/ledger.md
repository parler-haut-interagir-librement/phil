<!-- phil-dev source-sha256=99f85e3e1a3071f3b97193ac2e8e0963f751146532964727ba082e8c1d7a07d3 -->
# Le grand livre de propriété

Le module de logistique sait combien d'unités d'un SKU sont en main à chaque emplacement. Le grand livre sait à qui
elles appartiennent.

## Couches

Une **couche** contient des unités d'un SKU, à un emplacement, appartenant à un propriétaire, reçues ensemble aux
mêmes modalités :

| Propriétaire | Ce que la couche garde |
|---|---|
| La boutique | Le coût unitaire qu'elle a payé. |
| Un consignataire | Les modalités convenues : une part de chaque unité vendue, en `Money`, ou la commission de la boutique, en parties par million. |

Un consignataire est l'identifiant, dans l'application, d'un fournisseur, d'un auteur ou d'un artisan — le code de
fournisseur d'un bon de commande, l'identifiant d'un contact —, de 1 à 64 caractères comparés octet par octet.
`Owner::shop()` et `Owner::consignor('author-17')` nomment les propriétaires ; `ConsignmentStock::split()` liste ce que
chacun détient d'un SKU, à un emplacement ou à tous, la boutique en premier, puis les consignataires dans l'ordre de
leurs identifiants :

```php
foreach ($stock->split('BOOK-123', 'store') as $part) {
    $part->owner->consignor;   // null for the shop
    $part->quantity;
}
```

Chaque ligne de réception ouvre une couche. Un transfert, ou un retour vers un autre emplacement, met des unités dans
une couche à elles à l'autre emplacement, qui garde l'instant, le coût ou les modalités de la réception et nomme la
couche de la réception comme son **origine** ; les unités qui reviennent à un emplacement où se trouve déjà une couche
de la même réception retournent dans cette couche. Une couche n'est jamais supprimée : une couche vidée reste dans la
table sans rien de restant, et les recherches d'unités en main l'ignorent.

## Écritures

Chaque mouvement d'une couche est une **écriture** du grand livre, jamais modifiée ni supprimée : la couche, le SKU,
l'emplacement, le propriétaire, le genre, les unités gagnées ou perdues, la référence, l'instant et, pour une vente,
un retour ou un rachat, le prix unitaire, ce que les unités ont rapporté et ce qu'elles ont coûté ou ce qu'elles doivent
à leur consignataire.

| Genre | Écrite par | Unités |
|---|---|---|
| `received` | `receive()` | gagnées |
| `adopted` | `adopt()` — du stock en main dont le grand livre commence à tenir le propriétaire | gagnées |
| `sold` | `sell()` | perdues |
| `returned` | `returnSold()` | gagnées |
| `transferred_out`, `transferred_in` | `transfer()` | perdues à la source, gagnées à la cible |
| `counted` | `count()` | l'écart qu'un inventaire a trouvé |
| `given_back` | `UnsoldStock::giveBack()` — des unités invendues rendues à leur consignataire | perdues |
| `bought_out`, `bought_in` | `UnsoldStock::buyOut()` — la boutique achète des unités d'un consignataire au coût convenu | perdues par la couche du consignataire, gagnées par celle de la boutique |

Les relevés des consignataires sont lus dans ces écritures : voir [Relevés](statements.md). Les unités invendues rendues
et rachetées sont expliquées dans [Unités invendues](unsold-units.md).

## L'ordre de sortie

Quand la boutique détient des unités d'un SKU qu'elle a achetées et des unités en consignation, l'ordre de sortie dit
lesquelles sortent en premier :

| Ordre | Unités qui sortent en premier |
|---|---|
| `owned_first` | Celles de la boutique : son argent lui revient avant qu'elle vende pour les autres. |
| `consigned_first` | Les consignées : ce que la boutique ne possède pas sort avant ce qu'elle a payé. |
| `oldest_first` | La réception la plus ancienne, à qui qu'elle appartienne. |

Dans chaque groupe, et entre consignataires, la réception la plus ancienne sort en premier ; entre des couches reçues au
même instant, celle écrite en premier — la ligne la plus tôt de la réception. L'ordre s'applique aux ventes et aux
transferts ; il vient de la configuration, pour tous les SKU ou par SKU (voir [Configuration](configuration.md)).

## Ventes

`sell()` prend des unités du SKU à un emplacement dans l'ordre de sortie, écrit une écriture `sold` par couche où elle a
pris, et renvoie une `Sale` : ses lignes, une par couche, chacune avec son propriétaire, ses unités et sa `Margin` ;
`quantityOf()` d'un propriétaire ; `margin()` sur toutes les lignes ; `owedToConsignors()`, par consignataire. Un prix
unitaire est de zéro ou plus, et dans la même devise que les coûts et les parts des couches. Une vente de plus d'unités
que l'emplacement n'en détient refuse tout et ne déplace rien.

`sell()` n'écrit la vente qu'au grand livre : le stock sort avec l'expédition de la commande, dans le module de
logistique. `ShippedOrders` écrit les ventes des commandes expédiées après chaque expédition : voir
[Avec le module de logistique](logistics.md#commandes-expediees).

## Retours

`returnSold()` reprend des unités d'une vente, sous la référence de la vente, vers les couches qu'elles ont quittées :
les unités vendues en dernier reviennent en premier — dans une même vente, la couche prise en dernier. Chaque unité
retournée revient à son propriétaire au prix où elle a été vendue, et sa marge est celle de la vente, inversée : la
commission est retirée, et la part du consignataire n'est plus due. Les unités rapportées à un autre emplacement que
celui où elles ont été vendues vont dans une couche à cet emplacement, avec leur coût ou leurs modalités. Une vente
rend au plus ce qu'elle a vendu moins ce qui est déjà revenu ; un retour au-delà est refusé.

## Transferts

`transfer()` déplace des unités d'un SKU entre deux emplacements dans l'ordre de sortie, ou d'un seul propriétaire.
Chaque unité garde son propriétaire, son coût ou ses modalités et l'instant de sa réception, si bien que l'ordre des
plus anciennes d'abord reste vrai à la cible.

## Inventaires

`count()` fixe ce qu'un inventaire a trouvé d'un SKU à un emplacement et renvoie l'écart :

- les unités manquantes quittent d'abord les couches de la boutique, puis les couches consignées, les plus anciennes
  d'abord ;
- les unités trouvées vont dans la plus récente couche de la boutique à l'emplacement, ou dans une couche à
  l'emplacement scindée de la dernière réception du SKU par la boutique.

L'inventaire d'un seul propriétaire — `count(..., owner: Owner::consignor('author-17'))` — compare et modifie les unités
de ce propriétaire seulement, et les unités trouvées vont dans la plus récente couche de ce propriétaire. Les unités
trouvées d'un propriétaire qui n'a jamais reçu le SKU sont refusées : rien n'indique leur coût ni leurs modalités ;
recevez-les plutôt.

## Concurrence

Le grand livre ne valide rien : l'appelant appelle `flush` en une seule transaction pour le grand livre et le stock
qu'il a déplacé. Chaque couche porte une version : deux requêtes qui prennent en même temps dans la même couche ne
peuvent pas écrire toutes les deux, et le second `flush` lève l'`OptimisticLockException` de Doctrine. Reprenez
l'opération depuis le début, avec un gestionnaire d'entités neuf. Les services ne gardent aucun état entre les
requêtes.
