<!-- phil-dev source-sha256=4a793c1778ad11414d05cc63854af8a77130bc1fd1bf9a439a40592cbf476d99 -->
# Stockage

Le moteur lit et écrit par quatre ports sous `Contract\`. Le paquet les fournit en mémoire, sous `Memory\`, pour les
tests et les démonstrations ; une application les implémente sur son propre stockage — Doctrine, une autre base de
données, une API.

| Port | Conserve | En mémoire |
|---|---|---|
| `CampaignStore` | Les campagnes, avec la dernière phase qu'`advance()` a enregistrée | `InMemoryCampaignStore` |
| `ProposalStore` | Les propositions, les évaluateurs assignés à chacune, les conflits déclarés | `InMemoryProposalStore` |
| `ReviewStore` | Les évaluations, une par évaluateur et par proposition ; les décisions, une par proposition | `InMemoryReviewStore` |
| `VoteLog` | Les votes par paires de chaque campagne, numérotés | `InMemoryVoteLog` |

## Ce qu'un adaptateur conserve

- Les identifiants comme chaînes, exactement tels qu'ils sont donnés : `'7'` et `7` ne sont pas la même proposition pour
  une application, et un identifiant numérique doit revenir sous forme de chaîne.
- Les instants en UTC, comme les objets-valeurs les donnent : `submittedAt`, `recordedAt`, `appliedAt`, `castAt` et les
  fenêtres d'un calendrier. Un adaptateur Doctrine les associe à des colonnes `datetime_utc_immutable`.
- Les ordres que les ports promettent : propositions et décisions par identifiant de proposition, évaluations par
  identifiant d'évaluateur, évaluateurs par identifiant, votes par numéro.
- Le remplacement là où le port le dit : une évaluation remplace celle du même évaluateur pour la même proposition, une
  décision remplace celle de la proposition, une assignation ou un conflit conservé deux fois n'est conservé qu'une fois.
- Le journal des votes en ajout seul et numéroté à partir de 1 par campagne, sans trou, même quand deux votes arrivent
  en même temps : dans une base de données, une séquence par campagne sous verrou, ou une clé unique sur (campagne,
  numéro) et une nouvelle tentative.

Une `Campaign` tient son calendrier, ses critères et sa politique comme objets-valeurs ; un adaptateur peut les stocker
en JSON et les reconstruire, ou en lignes. `Campaign::withObservedPhase()` donne la campagne à enregistrer après
`advance()`.

## Transactions

Le moteur fait une écriture par action, sauf `applyDecisions()`, qui enregistre chaque décision en attente à tour de rôle
et répartit `DecisionsApplied` après la dernière. Une application qui veut tout ou rien enveloppe l'appel dans sa propre
transaction, et fait agir ses écouteurs une fois la transaction validée — par exemple en répartissant un message
Messenger qui porte l'identifiant de la campagne.
