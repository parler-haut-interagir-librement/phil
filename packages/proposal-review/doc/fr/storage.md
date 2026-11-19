<!-- phil-dev source-sha256=33f25417cbde8197b1dad2ea179e4998f16972300fa9c1ea0aee917eb6db09de -->
# Stockage

Le moteur lit et écrit par quatre ports sous `Contract\`. Le paquet les fournit en mémoire, sous `Memory\`, pour les
tests et les démonstrations, et sur Doctrine DBAL, sous `Dbal\`, pour une base de données ; une application peut aussi
les implémenter sur son propre stockage — une autre base de données, une API.

| Port | Conserve | En mémoire | Sur Doctrine DBAL |
|---|---|---|---|
| `CampaignStore` | Les campagnes, avec la dernière phase qu'`advance()` a enregistrée | `InMemoryCampaignStore` | `DbalCampaignStore` |
| `ProposalStore` | Les propositions, les évaluateurs assignés à chacune, les conflits déclarés | `InMemoryProposalStore` | `DbalProposalStore` |
| `ReviewStore` | Les évaluations, une par évaluateur et par proposition ; les décisions, une par proposition | `InMemoryReviewStore` | `DbalReviewStore` |
| `VoteLog` | Les votes par paires de chaque campagne, numérotés | `InMemoryVoteLog` | `DbalVoteLog` |

## Les magasins DBAL

Chaque magasin prend la `Doctrine\DBAL\Connection` de l'application et un préfixe de table, `phil_review_` par défaut :
40 lettres minuscules, chiffres ou `_` au plus, vérifiés avant d'atteindre le SQL. Ils fonctionnent sur MySQL, MariaDB,
PostgreSQL et SQLite.

### Les tables

Chaque magasin ajoute ses tables à un schéma avec `configureSchema()`, et laisse telle quelle une table déjà présente.
Une migration les construit à partir de ce schéma :

```php
use Doctrine\DBAL\Schema\Schema;

$schema = Schema::editor()->create();

foreach ([$campaigns, $proposals, $reviews, $votes] as $store) {
    $schema = $store->configureSchema($schema);
}

foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
    $connection->executeStatement($sql);
}
```

| Table | Clé primaire | Contient |
|---|---|---|
| `campaign` | `id` | Le calendrier, les critères et la politique en un seul document JSON ; la dernière phase enregistrée |
| `proposal` | `campaign`, `id` | Les auteurs, en liste JSON |
| `assignment` | `campaign`, `proposal`, `reviewer` | Une ligne par évaluateur assigné |
| `conflict` | `campaign`, `reviewer`, `kind`, `target` | Un conflit avec une proposition (`kind` `proposal`) ou avec un auteur (`author`) |
| `review` | `campaign`, `proposal`, `reviewer` | Les notes en liste JSON ; l'instant |
| `decision` | `campaign`, `proposal` | La décision, qui l'a consignée, quand ; quand elle a été appliquée, vide tant qu'elle est en attente |
| `vote` | `campaign`, `sequence` | Le votant, le gagnant, le perdant et l'instant |

### Ce qu'ils garantissent

- Les identifiants sont comparés octet par octet : `CFP` et `cfp`, `café` et `cafe` sont des campagnes différentes, sur
  MySQL aussi, où les colonnes d'identifiants prennent la collation `utf8mb4_bin`. Un identifiant gardé dans une base de
  données est de l'UTF-8 valide et ne se termine pas par une espace, que MySQL ignorerait à la comparaison ; une
  écriture en refuse un avec `InvalidInput`, et une lecture n'en trouve aucun.
- Les instants sont écrits en texte, en UTC, `2026-11-12T10:00:00.123456Z` : exacts à la microseconde, triés comme du
  texte, les mêmes sur toutes les plateformes. Un instant hors des années 1 à 9999 est refusé.
- Les listes reviennent dans les ordres que les ports promettent, triées en PHP par ordre d'octets, quelle que soit la
  collation de la base de données.
- Un enregistrement remplace la ligne dans une transaction. Une assignation ou un conflit déjà présent, même écrit par
  une autre requête entre la lecture et l'insertion, n'est conservé qu'une fois.
- Un vote prend le numéro qui suit le dernier de la campagne. Quand une autre requête l'a pris la première, la clé
  primaire refuse la ligne, dans un point de sauvegarde quand l'appelant a une transaction ouverte, et le vote prend le
  numéro suivant, jusqu'à `DbalVoteLog::MAX_ATTEMPTS` fois ; après quoi l'`UniqueConstraintViolationException` parvient
  à l'appelant.
- Une ligne que le magasin n'a pas écrite — une phase, une décision ou un genre qu'il ne connaît pas, du JSON qui n'est
  pas ce qu'il a écrit, un nombre qui n'est pas entier — est refusée avec une `UnexpectedValueException`, jamais lue
  comme autre chose.

## Ce qu'un adaptateur conserve

- Les identifiants comme chaînes, exactement tels qu'ils sont donnés : `'7'` et `7` ne sont pas la même proposition pour
  une application, et un identifiant numérique doit revenir sous forme de chaîne.
- Les instants en UTC, comme les objets-valeurs les donnent : `submittedAt`, `recordedAt`, `appliedAt`, `castAt` et les
  fenêtres d'un calendrier.
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
