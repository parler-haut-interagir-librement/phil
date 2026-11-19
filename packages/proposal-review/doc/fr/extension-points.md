<!-- phil-dev source-sha256=2bbfd804e1c4d87a121e2c6b67226bb3d5072d1c2e269a99544714cf0c64d3ff -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Garder les campagnes, les propositions, les évaluations et les votes dans votre stockage | Implémentez `Contract\CampaignStore`, `Contract\ProposalStore`, `Contract\ReviewStore` et `Contract\VoteLog`, comme [Stockage](storage.md) le décrit |
| Réagir aux phases, aux évaluations, aux décisions et aux votes | Passez un répartiteur d'événements PSR-14 et écoutez les événements de `Event\` |
| Choisir la façon dont une campagne évalue | Passez une `ReviewPolicy` : aveugle, minimum et maximum d'évaluations, `Aggregation`, `Visibility`, évaluateurs assignés seulement |
| Pondérer les critères | Donnez à chaque `Criterion` son poids et son échelle |
| Régler les notes par paires | Passez à `PairwiseVoting` un `Pairwise\BradleyTerry` avec un autre a priori, un autre nombre d'itérations ou une autre tolérance |
| Noter ailleurs — une évaluation par les pairs, un formulaire | Appelez `Scoring::check()`, `Scoring::review()` et `Scoring::aggregate()`, qui n'exigent ni le tableau d'évaluation ni un magasin |
| Ajuster Bradley–Terry à d'autres comparaisons | Appelez `BradleyTerry::fit()` avec une liste d'identifiants et une liste de `Pairwise\Vote` |
| Maîtriser le temps dans les tests | Passez une horloge PSR-20 comme le `MockClock` de Symfony |

## Une base pour l'évaluation par les pairs

Le moteur sépare ce qui est générique — phases datées, critères pondérés sur leurs propres échelles, agrégation,
conflits, décisions par lot — de ce qui est propre à un jury : les méthodes du tableau d'évaluation. Une évaluation par
les pairs, où les auteurs évaluent le travail les uns des autres par phases, réutilise les objets-valeurs (`Schedule`,
`PhaseWindow`, `Criterion`, `Score`, `ConflictOfInterest`), `Scoring` et les ports de stockage, et ajoute son propre
service pour les parties qu'un jury n'a pas, comme évaluer les évaluations.

Les classes sous `Internal\` ne sont pas des points d'extension : elles peuvent changer à toute version.
