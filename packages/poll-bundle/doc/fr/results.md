<!-- phil-dev source-sha256=aef89fc28023e4d8d2e7e4442533a6dc2de9300b3fa67d56eb07cba84ce3f095 -->
# Résultats

## Dépouillement

`Polls::results($poll)` compte les bulletins du sondage selon sa méthode et renvoie le `Result` de `phil/poll` : les
bulletins et les abstentions comptés, le classement avec ses places partagées, les gagnants et, pour un vote
alternatif, ses tours. Voir la documentation du dépouillement de `phil/poll` pour chaque méthode. `turnout($poll)` est
le nombre de bulletins déposés.

`results()` ne consulte personne : il sert au code de l'application qui décide lui-même quoi afficher.
`resultsFor($poll, $viewer)` compte pour une personne qui regarde — `null` pour quelqu'un qui n'est pas connecté — et
lève `PollRefused` quand elle n'a pas encore le droit de voir les résultats. `maySeeResults($poll, $viewer)` répond à
la même question sans compter.

Un sondage qui pourvoit plusieurs sièges est dépouillé en une élection, avec ses tours : voir
[Vote unique transférable](single-transferable-vote.md). [L'interface de vote](voting-interface.md) montre les
résultats à une personne.

## Visibilité

Les personnes que la politique laisse gérer le sondage voient toujours ses résultats. Les autres les voient selon le
`ResultsVisibility` du sondage :

| Cas | Qui voit les résultats |
|---|---|
| `Always` | Tout le monde, en tout temps. |
| `AfterVoting` | Un votant dès qu'il a voté, de façon anonyme ou non, et tout le monde une fois le sondage fermé. |
| `AfterClosing` | Tout le monde une fois le sondage fermé, à la main ou à sa fermeture. |
| `Managers` | Personne d'autre. |

Afficher les résultats avant la fin change la façon dont les gens votent ; `AfterClosing` est le choix habituel pour
une décision, `AfterVoting` pour un sondage d'opinion.

## Coût

Un dépouillement lit chaque bulletin du sondage, ligne par ligne à partir de la table, et la bibliothèque regroupe les
classements identiques. Dix mille bulletins sur dix options se comptent en une dizaine de millisecondes, plus le temps
de les lire. Une application qui affiche les résultats de grands sondages à de nombreux visiteurs nomme un pool de cache sous
`results_cache` : un dépouillement est alors gardé jusqu'au prochain bulletin, et lu dans le pool en quelques
microsecondes. Voir [Configuration](configuration.md#le-cache-des-dépouillements).
