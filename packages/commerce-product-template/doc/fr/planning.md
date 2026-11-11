<!-- phil-dev source-sha256=644ea8f88557284c77d5857a1f543706e2fa5f1dc72cc33d587ba13d23b0c5ce -->
# Planification

`VariantPlanner::plan($template, $design)` donne les produits d'un visuel fabriqué sur un gabarit, sous forme de liste
de `Variant` : par version linguistique, puis dans l'ordre des combinaisons du gabarit.

## Ce que contient un plan

Un produit pour chaque version linguistique du visuel — ou un seul, pour un visuel en une langue — et pour chaque
combinaison que le gabarit fabrique, que le visuel permet et qu'un canal vend. Chaque `Variant` a :

| Membre | Ce que c'est |
|---|---|
| `sku` | Le patron rendu avec les codes du visuel, de la langue, du gabarit et des valeurs |
| `design`, `template`, `language`, `combination` | Ce à partir de quoi il a été planifié |
| `price` | Le prix que donne le gabarit à sa combinaison |
| `channels` | Les canaux qui le vendent ; tous les canaux quand la liste est vide |
| `artworks` | Un `PlacedArtwork` par zone qu'il imprime, dans l'ordre des faces et des zones du gabarit |
| `name($defaultLocale)` | `Design – Template (value, value, value)` dans la locale de sa langue, sinon celle par défaut |
| `attributes()` | `design`, `template`, `language` quand il en a une, et le code de chaque valeur par axe |
| `photo($face)` | La photo de l'article vierge sur cette face : la première valeur de sa combinaison qui en a une |

**Canaux** : les valeurs qui nomment des canaux restreignent leurs produits aux canaux qu'elles partagent toutes. Une
combinaison dont les valeurs n'en partagent aucun — une valeur vendue seulement en ligne avec une autre vendue seulement
à un kiosque — n'est pas planifiée.

## Les mêmes entrées, le même plan

Un plan ne dépend que de son gabarit et de son visuel, et liste ses produits dans un ordre stable. Un générateur qui
l'exécute de nouveau après un changement retrouve les produits qu'il a déjà créés par leurs UGS, les met à jour, crée
les nouveaux et retire ceux que le plan n'a plus. Le planificateur lui-même ne stocke jamais rien.

## Ce que refuse un plan

`PlanRefused`, avant qu'aucun produit ne soit donné :

| Fabrique | Quand |
|---|---|
| `templateNotAllowed()` | Le visuel n'est pas fabriqué sur le gabarit |
| `collision()` | Deux produits partagent une UGS : le message nomme l'UGS et les deux produits |
| `invalidSku()` | Une UGS n'est pas formée de 1 à 64 lettres, chiffres, `.`, `_` ou `-`, commençant par une lettre ou un chiffre |
| `unknownZone()` | Une illustration nomme une zone absente de sa face |
| `ambiguousArtwork()` | Deux illustrations s'appliquent également à une zone d'un produit |
| `tooMany()` | Le plan a plus de produits que la limite du planificateur |

## Limites

Un gabarit a au plus 8 axes et 10 000 combinaisons, un visuel au plus 8 versions linguistiques : un plan reste donc
borné. La limite du planificateur est de 20 000 produits par défaut, et plus basse quand elle est donnée :
`new VariantPlanner(5_000)`. Planifier 5 400 produits prend quelques dizaines de millisecondes ([Tests](testing.md)).
