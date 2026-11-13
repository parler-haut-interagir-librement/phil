<!-- phil-dev source-sha256=70ae178f31f33f717daeec4aa4af40f36ce39d54be028b115bf3ab91374e6f33 -->
# La règle d'accord croisé

Une majorité de votants dit ce que la plupart d'entre eux pensent ; une note communautaire devrait dire ce que des gens
qui pensent d'ordinaire différemment trouvent utile ensemble. La règle cherche cet accord entre camps, paire par paire,
et garde la preuve.

## Comment une note est décidée

`CrossAgreementRule::decide()` prend les évaluations d'une note et l'historique de ses évaluateurs, et passe par quatre
étapes.

1. **Assez d'évaluations.** Avec moins de `minRatings`, la note a besoin de plus d'évaluations : `too_few_ratings`.
2. **Un verdict net.** Le verdict qui détient au moins la part `share` des évaluations l'emporte ; quand aucun des deux
   ne la détient, la note a besoin de plus d'évaluations : `no_clear_majority`.
3. **Des paires d'évaluateurs qui divergent d'ordinaire.** Parmi les évaluateurs du verdict dominant, les plus anciens
   d'abord, la règle cherche des paires qui divergent d'ordinaire. Deux évaluateurs divergent d'ordinaire quand, sur les
   autres notes qu'ils ont évaluées tous les deux — au moins `minShared` d'entre elles —, ils ont rendu le même verdict
   sur moins que la part `divergence`. Chaque évaluateur est dans une paire au plus, pour qu'aucun évaluateur seul ne
   porte une décision ; la règle s'arrête dès qu'elle a `pairs` paires.
4. **La décision.** Avec `pairs` paires, la note reçoit le verdict dominant, `helpful` ou `not_helpful` :
   `cross_agreement`. Avec moins, elle a besoin de plus d'évaluations : `no_cross_agreement`.

La note en cours de décision est exclue de l'historique, pour qu'une note ne puisse pas se prouver elle-même.

## Les nombres

| Argument | Valeur par défaut | Ce que c'est |
|---|---|---|
| `minRatings` | 5 | Les évaluations dont une note a besoin avant que quoi que ce soit soit décidé, 1 ou plus. |
| `share` | `[2, 3]` | La part des évaluations que le verdict dominant détient au moins, au-dessus de la moitié et au plus toutes. |
| `pairs` | 2 | Les paires d'évaluateurs qui divergent d'ordinaire dont une décision a besoin, 1 ou plus. |
| `minShared` | 3 | Les autres notes que deux évaluateurs doivent partager avant que leur historique dise quoi que ce soit, 1 ou plus. |
| `divergence` | `[1, 2]` | Deux évaluateurs divergent d'ordinaire quand ils se sont accordés sur moins que cette part des notes qu'ils ont partagées. |
| `maxRaters` | 50 | Les évaluateurs du verdict dominant examinés, les plus anciens d'abord, de deux fois `pairs` à 200. |

Les parts s'écrivent avec un numérateur et un dénominateur, et chaque comparaison se fait en entiers : `agreed × 2 <
shared` pour `[1, 2]`. Une règle hors de ces bornes lève `InvalidArgumentException`.

Une petite communauté commence avec les valeurs par défaut, ou `pairs: 1` tant que peu de membres évaluent ; une grande
augmente `pairs` et `minRatings`.

## Un exemple

Ann et Bob trouvent d'ordinaire utiles les mêmes notes ; Cid et Dan aussi ; les deux paires ont divergé sur les trois
notes qu'elles ont toutes évaluées. Une note de Zoe reçoit cinq évaluations utiles, d'Ann, Bob, Cid, Dan et Eve, dans
cet ordre. Cinq évaluations, toutes utiles : le verdict l'emporte. Les plus anciens évaluateurs sont appariés d'abord :
Ann et Bob s'accordent trop souvent ; Ann et Cid ont divergé sur 3 des 3 notes partagées — une paire ; Bob et Dan de
même — une deuxième paire. La note est utile, et sa décision le dit :

```php
[
    'status' => 'helpful',
    'reason' => 'cross_agreement',
    'helpful' => 5,
    'not_helpful' => 0,
    'verdict' => 'helpful',
    'bridges' => [
        ['first' => 'user:ann', 'second' => 'user:cid', 'shared' => 3, 'agreed' => 0],
        ['first' => 'user:bob', 'second' => 'user:dan', 'shared' => 3, 'agreed' => 0],
    ],
    'rule' => ['min_ratings' => 5, 'share' => '2/3', 'pairs' => 2, 'min_shared' => 3, 'divergence' => '1/2', 'max_raters' => 50],
]
```

Si seuls Ann et Bob l'avaient évaluée, avec Eve et deux autres de leur camp, aucune paire ne divergerait : la note
attendrait, `no_cross_agreement`, peu importe le nombre de personnes d'un même camp qui l'ont évaluée.

## L'explication

`Decision` contient tout ce qui explique l'état d'une note : l'état, la raison, les évaluations de chaque verdict, le
verdict examiné, les paires — chacune un `Bridge` de deux évaluateurs avec leur `PairHistory` — et la règle elle-même.
Elle est conservée avec la note, pour que l'explication soit celle de la décision, non d'une règle changée depuis.

`toArray()` la donne en valeurs simples, avec les références des évaluateurs, pour les modérateurs et un audit ;
`toPublicArray()` omet les références et garde, pour chaque paire, les notes partagées et celles sur lesquelles elles se
sont accordées, pour les lecteurs. La raison est un code stable, `DecisionReason`, que l'application traduit.

## Coût

La règle demande l'historique d'au plus `maxRaters × (maxRaters − 1) ÷ 2` paires : 1225 avec les valeurs par défaut,
quand aucune paire n'est trouvée. Avec cinquante évaluateurs sur un historique de dix mille évaluations gardé en
mémoire, une décision prend environ 0,3 ms quand deux paires sont trouvées d'emblée, 1,2 ms pour dix paires, et environ
110 ms dans le pire cas, chaque paire demandée. Un stockage en base de données répond à `between()` à partir d'un index
sur l'évaluateur et la note, ou abaisse `maxRaters`.

## Ce que la règle n'est pas

La règle ne calcule pas de score caché d'un évaluateur, et ne range pas les évaluateurs dans des camps une fois pour
toutes : les camps se voient paire par paire, d'après ce que les évaluateurs ont fait, et la décision nomme les paires
qu'elle a trouvées. Elle n'apprend pas non plus de milliers d'évaluations ; une communauté qui atteint cette taille peut
placer un modèle bien à elle derrière la même `Decision`.
