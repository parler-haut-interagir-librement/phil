<!-- phil-dev source-sha256=c1370af0ea87d4cdb97e65447b9c5516bdfacce632968d27d18ad4e5a8f2017c -->
# Force

La force est le nombre affiché à côté des séries : de 0 à 100 %, elle dit dans quelle mesure une habitude a été tenue
récemment. Elle monte à chaque période respectée et baisse un peu à chaque période manquée, de sorte qu'une mauvaise
semaine après une bonne saison coûte quelques points plutôt que tout.

## La formule

La force `s` part de 0. Après chaque période respectée ou manquée, elle se déplace vers la part de la période qui a été
faite, `c`, de 0 à 1, d'un poids `w` qui dépend du nombre de jours `d` que la période représente :

```text
w = 1 − 2^(−d / h)
s′ = s + w × (c − s)
```

`h` est la demi-vie, quatorze jours par défaut : après quatorze jours, une validation pèse la moitié de ce qu'elle
pesait. C'est une moyenne à lissage exponentiel dont la mémoire se compte en jours plutôt qu'en périodes :

- une habitude quotidienne faite chaque jour pendant quatorze jours atteint 50 %, pendant vingt-huit jours 75 %, pendant
  deux mois 95 % ;
- une semaine faite trois fois sur trois pèse autant que sept jours faits un à un, de sorte que le même effort atteint
  la même force, quel que soit l'horaire ;
- un jour manqué retire environ 5 % de la force acquise, une semaine manquée environ 29 % ;
- un jour fait en partie déplace la force vers sa part : la moitié de la cible la tire vers 50 %.

Une période respectée compte pour 1. Une période manquée compte pour sa part, les unités faites sur les unités
demandées. Une période excusée, et la période en cours pas encore respectée, laissent la force telle quelle.

`HalfLifeStrength` implémente cette formule ; `new HalfLifeStrength(7.0)` donne une force qui réagit plus vite, `30.0`
une force qui se souvient plus longtemps. La demi-vie va de 1 à 365 jours.

## La lire

`Progress::$strength` est le nombre de 0 à 1 ; `strengthPercent()` l'arrondit à un pourcentage entier, tel qu'on
l'affiche. Montrez-la à côté des séries plutôt qu'à leur place : la série dit une suite, la force dit une tendance
qu'un seul jour manqué n'efface pas.

## Une autre formule

Le modèle de force est un port, `Contract\StrengthModel` : `next($strength, $completion, $days)` renvoie la force après
une période. Passez un autre modèle à `ProgressCalculator`, et ce calculateur à `Habits`.

## Le coût

Le progrès est calculé à chaque lecture à partir du premier jour de l'habitude : un seul passage sur les périodes, avec
les validations en mémoire. Un banc d'essai tient le coût de dix ans d'une habitude quotidienne — 3 653 périodes — à
quelques millisecondes.
