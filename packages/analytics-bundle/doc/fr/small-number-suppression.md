<!-- phil-dev source-sha256=b517c6353c416b3f21b5a2bee10860a5102bd7413282650a2c5507c7128d9995 -->
# Masquage des petits nombres

Sur un petit site, un compteur de un ou deux désigne la personne qu'il compte. **Un compteur sous le seuil n'est jamais
montré seul.** La règle s'applique **à la lecture, jamais à l'écriture** : le compteur stocké reste entier, et seul ce
qu'un tableau de bord montre est regroupé.

État : `SmallNumberThreshold`, `SmallNumberSuppression` et `SuppressedCounts` sont implémentés et testés ; le bundle
configure `SmallNumberSuppression` avec `phil_analytics.small_number_threshold`. Les tableaux de bord, et qui peut les
lire, sont à l'application.

## `SmallNumberThreshold`

```php
$threshold = SmallNumberThreshold::of(5);
$threshold->value;        // 5
$threshold->isMetBy(4);   // false
$threshold->isMetBy(5);   // true
```

La valeur est au choix de l'application. Le bundle refuse seulement un seuil sous 2, qui ne masquerait rien.
Choisissez-le au-dessus du compte à partir duquel une valeur désigne une personne sur votre plus petit site.

## `SmallNumberSuppression`

```php
$counts = $store->counts($site, 'pageviews', 'country', UtcDay::fromString('2026-10-01'), UtcDay::fromString('2026-10-31'));
$result = new SmallNumberSuppression(SmallNumberThreshold::of(5))->apply($counts);

$result->shown;   // array<string, int>: counters shown on their own, largest first
$result->others;  // ?int: the "other" group, or null when it must not be shown
$result->hidden;  // int: how many counters are shown neither on their own nor in a group
```

`apply(array<string, int> $counts): SuppressedCounts` :

1. Un compteur négatif lance `InvalidValue`. Un compteur à zéro n'est pas un compteur et est laissé de côté.
2. Un compteur au seuil ou au-dessus est montré seul — **sauf le compteur stocké sous `CounterKey::OTHER`**, jamais
   montré sous son propre nom.
3. Un compteur sous le seuil, et le compteur stocké sous `(other)`, rejoignent le groupe « autre ».
4. **Le groupe n'est montré que si sa somme atteint le seuil.** Un groupe d'un seul petit compteur montrerait la valeur
   même qu'il doit cacher, et un groupe de plusieurs dont la somme est sous le seuil les trahit aussi : chaque compteur
   vaut au moins 1, donc deux compteurs dont la somme est 2 valent chacun 1. De tels compteurs sont cachés.

## Exemples (seuil 5)

| Entrée | `shown` | `others` | `hidden` |
|---|---|---|---|
| `['CA' => 5, 'FR' => 12]` | `['FR' => 12, 'CA' => 5]` | `null` | 0 |
| `['CA' => 40, 'BE' => 2, 'CH' => 3]` | `['CA' => 40]` | 5 | 0 |
| `['CA' => 40, 'BE' => 1, 'CH' => 2]` | `['CA' => 40]` | `null` | 2 |
| `['CA' => 40, 'BE' => 2]` | `['CA' => 40]` | `null` | 1 |
| `['home' => 40, '(other)' => 3, 'event' => 2]` | `['home' => 40]` | 5 | 0 |
| `['(other)' => 7]` | `[]` | 7 | 0 |
| `['(other)' => 4]` | `[]` | `null` | 1 |
| `['CA' => 40, 'BE' => 0, 'CH' => 1]` | `['CA' => 40]` | `null` | 1 |
| `[]` | `[]` | `null` | 0 |

Voyez les deuxième et troisième lignes : deux petits compteurs sont montrés en groupe quand leur somme atteint le seuil,
et cachés sinon. Un groupe montré révèle un total d'au moins le seuil, jamais l'un de ses compteurs.

## Pourquoi à la lecture

- Les données stockées restent exactes : changer le seuil plus tard ne demande aucune migration et ne perd rien.
- L'application peut donner des seuils différents à des lecteurs différents.
- L'ingestion reste une incrémentation atomique par compteur, sans lecture-modification-écriture.

La contrepartie : **chaque chemin de lecture doit passer par `SmallNumberSuppression`**. Un tableau de bord, un export
ou une API qui lit directement `CounterStore::counts()` montre des compteurs qui désignent des personnes.
