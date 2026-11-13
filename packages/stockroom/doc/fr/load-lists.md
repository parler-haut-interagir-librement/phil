<!-- phil-dev source-sha256=80cda94af9049fa0edf0518085d28e284da71171e967364e76dc460c6119ff98 -->
# Listes de chargement

Un événement sort des choses de la réserve : sa liste de chargement dit lesquelles. `Deployments` garde une liste par
événement et la suit des étagères jusqu'à l'événement, puis au retour.

## Planifier

```php
$deployments->plan('tavern-scene', [
    new LoadLine($swords, 12),
    new LoadLine($lanterns, 4, 'crate-3'),   // from that container
]);
```

Une ligne nomme un article, une quantité et, facultativement, le contenant où le prendre. Les lignes d'un même article
venant du même contenant sont fusionnées. Une liste compte 500 lignes au plus ; ses articles et ses contenants doivent
exister. Planifier de nouveau remplace la liste.

## Prélever

`picking($event)` donne un `PickLine` pour chaque ligne : les contenants qui renferment l'article — son lieu d'origine
en premier, puis les autres dans l'ordre de leurs clés, ou seulement le contenant que la ligne nomme —, combien de
pièces s'y trouvent en tout, et si la ligne est incomplète. Un article qui ne peut pas sortir en compte aucune.

## Déployer

`deploy($event)` envoie à l'événement les pièces qui manquent encore : ce qui s'y trouve déjà compte, de sorte que
déployer de nouveau n'envoie rien de plus, et qu'un déploiement interrompu se termine par le même appel plus tard.
Chaque article est pris dans les contenants que ses lignes nomment, jusqu'à la quantité de chaque ligne, puis dans son
lieu d'origine et dans les autres.

Lorsque la réserve manque de quelque chose, rien ne bouge et le `Deployment` liste chaque `Shortfall`. Avec
`deploy($event, partial: true)`, ce qui est là part et le reste est listé. Chaque mouvement renvoie à l'événement.

## Vérifier au retour

```php
$check = $deployments->checkIn('tavern-scene', [$swords => 11, $lanterns => 4]);
$check->movements;  // the returns, to each item's home
$check->missing;    // one sword: expected 12, found 11
$check->surplus;    // what was counted beyond what was out
```

Ce qui a été compté retourne au lieu d'origine de l'article, ou au premier contenant où une ligne de la liste l'a pris.
Ce qui est dehors et n'a pas été compté est listé comme manquant et reste à l'événement, de sorte que
`outstanding($event)` le montre encore ; avec `checkIn($event, $counted, writeOff: true)`, il est plutôt enregistré
comme perdu. Un article compté qui n'a nulle part où aller arrête la vérification avant que quoi que ce soit bouge. Un
décompte va de 0 à 1 000 000.

## Le format texte

`LoadListReader` lit une liste de chargement tapée à la main, une ligne par article, et en réécrit une :

```text
# The tavern scene
12 x foam-sword
4 lantern @ crate-3
banner-red
```

Une ligne est une quantité — un, si elle est omise —, un `x` ou un `×` facultatif, le code de l'étiquette de l'article
et, facultativement, `@` suivi de l'identifiant du contenant où le prendre. Les lignes vides et celles qui commencent
par `#` sont ignorées.

```php
$reader = new LoadListReader($items);
$deployments->plan('tavern-scene', $reader->read($text));
$reader->write($deployments->loadList('tavern-scene')->lines);
```

Le texte est de l'UTF-8 d'au plus 64 Kio et compte 500 lignes au plus. Une ligne qui n'en est pas une, ou qui nomme un
article qui n'existe pas, est refusée avec son numéro : `Line 3 names the item "ghost", which does not exist.`. Quel que
soit le texte, le lecteur retourne des lignes ou lève `InvalidStock` ; ce qu'il écrit se relit comme les mêmes lignes.
