<!-- phil-dev source-sha256=b5a53fab2fe1180a9832167bd0c3e06d5f5e205d796a963c7ce8bfb737547af9 -->
# Sécurité

## Les limites

| Limite | Valeur |
|---|---|
| Composants d'un lot | 1 à 50 |
| Unités d'un composant dans un lot | 1 à 1 000 |
| Lots dans une vente | 1 à 1 000 |
| SKU | 64 caractères : lettres, chiffres, `_`, `.`, `/`, `-` |
| Participants d'un lot coopératif | 2 à 50 |
| Poids d'un participant | 1 à 10 000 |
| Identifiant d'un participant ou de l'hôte | 1 à 255 caractères imprimables |
| Lots trouvés par `containing()` | 1 à 1 000 |

Chaque boucle de la bibliothèque porte sur ces bornes, de sorte qu'un devis, un décompte de stock ou une ligne stockée
relue prend un temps borné.

## Les lignes stockées

`ProductBundle::fromArray()`, `SoldBundle::fromArray()` et `CooperativeBundle::fromArray()` lisent des lignes que
personne n'a tapées à la main. Chacune vérifie le type et les bornes de chaque champ et lance `InvalidBundle` sur une
ligne mal formée — jamais une erreur de type, jamais une autre exception — et une ligne relue écrit le même tableau. La
cible de fuzzing `product-bundles-stored` les y tient.

Une ligne vendue dont les parts ne s'additionnent pas à son prix unitaire est refusée, de sorte qu'une ligne trafiquée
ne peut pas rembourser plus que ce qui a été payé.

## Ce qui est stocké

Les lots et les lignes vendues contiennent des SKU, des quantités et des montants, aucun renseignement personnel. Un
lot coopératif contient les identifiants que l'application donne à ses participants ; donnez-lui des identifiants
opaques, non des noms ni des adresses.

## Les messages

Les messages d'exception nomment le lot, le composant ou le champ en cause, jamais un prix.
