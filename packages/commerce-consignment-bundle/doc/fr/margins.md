<!-- phil-dev source-sha256=73e06b5e8e29b36ad3f19d1c211704d6a8076f07d968fd04d4474ec5dd930db0 -->
# Marges et commissions

Chaque ligne de vente porte une `Margin`, calculée par le port `MarginCalculator` :

| Propriétaire | `revenue` | `cost` | `margin` |
|---|---|---|---|
| La boutique | Le prix payé pour les unités | Leur coût unitaire, multiplié par les unités | La marge : revenu moins coût |
| Un consignataire | Le prix payé pour les unités | La part du consignataire : ce que la boutique doit | La commission : revenu moins part |

Chaque montant est un `Phil\Money\Money`, en unités mineures entières, jamais un nombre à virgule flottante.

## Le calculateur par défaut

`StandardMargins` est le calculateur par défaut :

- les unités de la boutique : le prix unitaire multiplié par les unités, moins le coût unitaire
  multiplié par les unités ;
- une part : la part multipliée par les unités est due quel que soit le prix payé, si bien qu'une unité vendue sous sa
  part donne une commission négative, que le grand livre enregistre telle quelle ;
- un taux de commission : le prix de toutes les unités est scindé d'un coup avec `Money::allocate()`, qui ne perd ni ne
  crée jamais un cent — la commission de la boutique et la part du consignataire font toujours le prix payé. 30 % de
  19,99 donne 6,00 à la boutique et 13,99 au consignataire.

Les mêmes unités au même prix donnent toujours la même marge : un retour est la marge de la vente, inversée, au prix où
les unités ont été vendues.

## Un autre calculateur

Une application qui a ses propres règles — par `phil/pricing`, un arrondi à elle, une part qui dépend du prix —
implémente `MarginCalculator` et crée un alias de l'interface vers son service : voir
[Points d'extension](extension-points.md).

## Ce qu'une vente doit

`Sale::owedToConsignors()` additionne, par consignataire, les parts des lignes consignées d'une vente. Les écritures du
grand livre gardent les mêmes chiffres — `revenue` et `cost`, par consignataire et par instant — : les
[relevés](statements.md) et le [solde](payouts.md#le-solde) de chaque consignataire les lisent, et ne les recalculent
jamais à partir des ventes.
