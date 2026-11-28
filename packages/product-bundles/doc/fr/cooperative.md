<!-- phil-dev source-sha256=6dbd5f9942a7567cd0fd0ebae232e3cd3d78dbd9fc735ca89a602ea7b55d647e -->
# Lots coopératifs

Un lot coopératif rassemble les produits de plusieurs vendeurs — créateurs, petites maisons d'édition, boutiques — sous
un seul lot, et partage chaque vente entre eux.

## Proposer

`CooperativeBundle::propose($sku, $host, $weights, $charity)` produit un brouillon :

| Partie | Règle |
|---|---|
| `sku` | Le SKU du lot, tel que `ProductBundle` le nomme. |
| `host` | Le participant qui le propose, l'un des participants. |
| `weights` | De 2 à 50 participants, chacun un identifiant d'au plus 255 caractères imprimables, pesant de 1 à 10 000. |
| `charity` | Un `Rate` de 0 à 100 % prélevé d'abord sur chaque vente ; aucun par défaut. |

Jusqu'à la première approbation, l'hôte peut changer les participants et leurs poids avec `reweigh()`, et la part de
l'organisme de bienfaisance avec `withCharity()`.

## Approuver et le verrou

Chaque participant approuve avec `approve($participant, $at)` ; approuver de nouveau ne change rien, et quiconque ne
participe pas est refusé. La première approbation verrouille le lot : dès lors, `reweigh()` et `withCharity()` lancent
`CooperativeRefused`, de sorte qu'aucun participant n'approuve un partage et ne soit payé selon un autre. `lockedAt`
garde l'instant d'approbation le plus ancien ; `pending()` liste ceux qui n'ont pas encore approuvé.

Le lot est en vente quand tous les participants ont approuvé : `isOnSale()`.

## Partager un revenu

`shares(Money $revenue)` calcule les parts d'un revenu : celle de l'organisme de bienfaisance d'abord, s'il a une part,
puis le reste selon les poids, dans l'ordre où les participants ont été invités. Les parts s'additionnent au revenu à
l'unité près. Avant que tous les participants aient approuvé, elle lance `CooperativeRefused`.

La bibliothèque calcule les parts ; les verser, et garder ce qui a été versé, relève de l'application.

## Stocker

`toArray()` ne donne que des scalaires, les instants en horodatages Unix ; `fromArray()` le relit et refuse un verrou
qui n'est pas l'instant de l'approbation la plus ancienne.
