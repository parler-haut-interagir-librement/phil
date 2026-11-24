<!-- phil-dev source-sha256=87bc3dbd7cdd0cb1488040a76cb4097151dd99d0260ac776df62c9421376d0a6 -->
# Lire le journal

Le bundle relit sa propre piste sous forme de services d'application : des pages de lignes trouvées par qui a agi, ce
qui a été fait, sur quel enregistrement, dans quel locataire et quand; l'historique d'un enregistrement; une ligne par
son emplacement; chaque ligne d'une requête, pour un export; et l'état de la chaîne d'empreintes, segment par segment.
Ce sont des services sans écrans : les pages d'une interface d'administration sont celles de `phil/audit-viewer`,
construites sur eux. Le journal est en lecture seule. Rien ici ne modifie ni ne retire une ligne, et revenir à une
version antérieure d'un enregistrement relève du bundle qui garde de vraies versions de celui-ci.

## Les faits : ce qui indexe une ligne

La charge utile d'une ligne, ce sont les octets du `CanonicalSerializer` de l'application, que le bundle ne peut pas
lire. Ce qu'il lit, ce sont les **faits** de la ligne, `Domain\Chain\Facts`, donnés quand la ligne est consignée :

| Fait | Forme | Exemple |
|---|---|---|
| `action` | de 1 à 64 lettres minuscules, chiffres, `.`, `_`, `:` ou `-`, commençant par une lettre | `order.paid`, `workflow.order.ship` |
| `actor` | l'identifiant stable du compte ou du processus : de 1 à 128 lettres, chiffres, `.`, `_`, `:` ou `-` | `acct-7`, `01JC…` |
| `subject` | `Subject::of($type, $id)` : l'enregistrement dont parle l'événement, un type que l'application nomme et un identifiant stable | `order` `42` |
| `tenant` | la référence d'un locataire : de 1 à 255 octets d'UTF-8, sans caractère de contrôle ni espace en périphérie | `shop-a` |

```php
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\Subject;

$trail->record('orders', ['amount' => '10.00', 'email' => $commitment->bytes], Facts::of(
    action: 'order.paid',
    actor: $account->id(),
    subject: Subject::of('order', (string) $order->id()),
));
```

Chaque fait est un identifiant, jamais une valeur personnelle. Un acteur refuse `@` : une adresse de courriel est
personnelle, et elle n'est pas stable. Les faits sont des colonnes à part, indexées, et l'empreinte de la ligne les
couvre ([hash-chain.md](hash-chain.md)) : un fait modifié dans la table se voit comme une charge utile modifiée. Comme
un fait ne peut pas être effacé sans briser la chaîne, un nom ou une adresse n'en devient jamais un; il va dans la
charge utile sous forme d'engagement ([commitments-and-erasure.md](commitments-and-erasure.md)).

Sans faits, `record($segment, $fields)` écrit une ligne qu'on ne retrouve que par son segment et son instant. Une
ligne de discontinuité n'a aucun fait.

## Lectures de données sensibles

La piste consigne les changements; elle peut aussi consigner qui a **lu** un enregistrement dont la lecture compte. Une
lecture est une action sous le préfixe `Facts::READ_PREFIX`, `read.`, suivi de ce qui a été lu, et elle nomme toujours
l'enregistrement lu :

```php
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\Subject;

$subject = Subject::of('account', $memberId);
$trail->recordRead('members', 'account.profile', $subject, $operator->id(), ['reason' => 'support']);

// The same row, with the facts built by hand:
$trail->record('members', ['reason' => 'support'], Facts::read('account.profile', $subject, $operator->id()));
```

`what` suit la forme d'une action et compte 59 caractères au plus, de sorte que l'action, avec son préfixe, en compte
64. `Facts::isRead()` distingue une lecture d'un changement, et `AuditQuery::reads()` ne garde que les lectures; c'est
`withAction('read.', prefix: true)`, le filtre que tout lecteur d'actions par préfixe offre déjà. La charge utile
contient ce que l'application retient de la lecture — une raison, un écran, le format d'une exportation —, jamais les
données lues.

**Quand consigner une lecture.** Consignez les lectures dont quelqu'un pourrait devoir répondre plus tard : un membre du
personnel qui ouvre le profil d'un membre, ses données de santé ou de paiement, ou ses messages privés; une
exportation ou un téléchargement de renseignements personnels; un document déchiffré pour qu'une personne le voie; une
recherche dans les enregistrements d'autrui par quelqu'un qui n'est pas du soutien. Laissez de côté un membre qui lit
ses propres données, les pages que tout visiteur voit et les lectures qu'un processus fait pour accomplir son travail :
ce sont le volume, non l'exception, et la piste n'est pas un journal d'accès.

**Quel volume prévoir.** Une ligne de lecture est une ligne ordinaire : une par lecture consignée, à peu près de la
taille des autres lignes du segment, chaînée, vérifiée et ancrée comme elles. Les lectures sont bien plus fréquentes
que les changements quand on les consigne largement; consignez-les par enregistrement ouvert, non par champ affiché ni
par page d'une liste, et gardez-les dans un segment à part (`members` ci-dessus) pour que les chaînes de changements
restent courtes à vérifier. Un outil d'administration où dix membres du personnel ouvrent chacun cent enregistrements
par jour écrit environ mille lignes par jour, quelque 365 000 par année : bien en deçà de ce qu'une table et une
passe de vérification supportent, mais assez pour planifier l'ancrage et la conservation de ce segment.

## Comparaison exacte

Un locataire, un acteur et l'identifiant d'un enregistrement sont comparés octet par octet, afin qu'un locataire ne lise
jamais la piste d'un autre. La table donne aux trois colonnes la collation binaire de MySQL et de MariaDB (`utf8mb4_bin`)
et la collation `C` de PostgreSQL; SQLite les compare déjà octet par octet. Sous la collation par défaut d'un serveur
(`utf8mb4_0900_ai_ci`), `tenant = 'acme'` trouverait aussi les lignes de `ACME` et de `acmé`.

La comparaison est refaite en PHP, avec `===` : `DbalAuditLogReader` ne garde que les lignes qui passent chaque filtre
de la requête (`AuditQuery::matches()`), et `AuditLog` fait de même avec le lecteur qu'on lui donne, après avoir limité
la requête au locataire courant. Une table dont les colonnes ont gardé une collation qui ignore la casse et les accents
— créée à la main, ou avant que ses colonnes prennent la collation binaire — ne laisse donc jamais fuir une ligne; ses
pages peuvent contenir moins de lignes que leur limite, et la page suivante suit toujours la dernière ligne lue. Une
telle table passe à la collation binaire à la prochaine différence de schéma (`doctrine:migrations:diff`), puisque
l'écouteur de schéma la déclare.

## `AuditQuery`

Une requête immuable : chaque méthode en retourne une nouvelle.

| Méthode | Filtre |
|---|---|
| `AuditQuery::all()` | Chaque ligne, la plus récente d'abord, 50 par page |
| `AuditQuery::historyOf(Subject)` | Les lignes d'un enregistrement, la plus ancienne d'abord |
| `byActor(string)` | Un acteur |
| `withAction(string, bool $prefix = false)` | Une action, ou, avec `$prefix`, chaque action qui commence ainsi, comme `workflow.order.` |
| `reads()` | Les lectures seulement, les actions sous `read.` |
| `about(Subject)`, `aboutType(string)` | Un enregistrement, ou chaque enregistrement d'un type |
| `inSegment(string)` | Un segment |
| `inTenant(?string)` | Un locataire; `null`, chaque locataire et aucun |
| `between(?DateTimeImmutable, ?DateTimeImmutable)` | Du premier instant inclus au second exclu; chaque borne peut être ouverte, et une période qui finit avant de commencer est refusée |
| `ordered(Order)` | `Order::NewestFirst` ou `Order::OldestFirst` |
| `limitedTo(int)` | De 1 à 500 lignes par page |
| `after(?Cursor)` | La page après un curseur; `null`, la première page |

Chaque filtre s'applique. Une valeur qui n'est pas un identifiant est refusée avec `InvalidValue` à la construction de
la requête : une requête qui existe ne contient donc que des valeurs que la table peut contenir.

## Les pages et les curseurs

Les lignes viennent par instant, puis par segment, puis par séquence, de sorte que deux lignes du même instant arrivent
toujours dans le même ordre. `AuditPage` contient les lignes d'une page et `next`, le `Cursor` de la page suivante,
`null` sur la dernière. La page suivante commence après la clé de la dernière ligne lue, sur l'index : aucun décalage
n'est compté, et la millième page coûte autant que la première. Un curseur voyage sous forme de jeton,
`Cursor::token()`, l'instant, le segment et la séquence joints par `~`; `Cursor::fromToken()` le relit et refuse tout
autre contenu.

```php
$page = $log->page(AuditQuery::all()->withAction('workflow.order.', prefix: true)->limitedTo(100));

foreach ($page->rows as $row) {
    echo $row->instant(), ' ', $row->facts->action, ' ', $row->facts->actor, "\n";
}

$next = $log->page(AuditQuery::all()->withAction('workflow.order.', prefix: true)->limitedTo(100)->after($page->next));
```

## `AuditLog`

Le service qui lit, public dans le conteneur.

| Méthode | Retourne |
|---|---|
| `page(AuditQuery)` | Une `AuditPage` |
| `history(Subject, ?Cursor = null, int $limit = 50)` | L'historique d'un enregistrement, le plus ancien d'abord : les changements d'état que ses workflows ont consignés et tout autre événement qui le concerne |
| `row(string $segment, int $sequence)` | Une ligne, ou `null` |
| `each(AuditQuery)` | Chaque ligne de la requête à partir de son curseur, lue 500 à la fois : la mémoire contient une page, si long que soit le journal |

Dans un locataire — quand le port `TenantScope` en donne un, comme avec `phil/tenancy-bundle`
([integrations.md](integrations.md)) —, chaque lecture est limitée aux lignes de ce locataire, quel que soit le
locataire que nomme la requête, et `row()` répond `null` pour une ligne d'un autre locataire. Hors de tout locataire,
le journal est lu en entier.

## L'état de la chaîne : `ChainStatus`

| Méthode | Retourne |
|---|---|
| `segments()` | Chaque segment, par nom, avec sa ligne de tête, lue sur la clé : un `SegmentStatus` dont l'état est `ChainState::Unverified` |
| `verify(string $segment)` | Le segment vérifié par rapport à ses ancres, comme le fait `phil:audit:verify` : `Holds`, `HoldsWithExplainedBreaks` ou `Broken`, avec ses ruptures (`breaks`, `alarms()`) |

Vérifier lit chaque ligne du segment : un afficheur la demande donc segment par segment plutôt qu'à chaque page. Dans
un locataire, les deux méthodes ne voient que les segments de site de ce locataire : le segment d'un autre site, ou un
segment partagé, n'est pas listé, et le vérifier répond un segment sans lignes ([sites.md](sites.md)). Un
`SegmentStatus` nomme son site avec `site()` et le segment que l'application a nommé avec `base()`.

## Les adaptateurs du côté lecture

`Domain\Contract\AuditLogReader` est le port : `page(AuditQuery)` et `row($segment, $sequence)`.

- `DbalAuditLogReader`, enregistré avec une connexion, lit la table de la piste avec DBAL : chaque filtre est une
  égalité sur une colonne indexée — le préfixe d'action, un `LIKE` qui prend `_` littéralement —, le curseur est une
  condition sur la clé, et une ligne de plus que la limite dit s'il existe une page suivante. La condition du curseur
  s'ouvre par une borne sur le seul instant, sur laquelle un index balaie une plage. La table porte un index pour
  chaque façon de lire, chacun terminé par la clé de l'ordre — `recorded_at, segment, sequence` —, de sorte qu'une page
  n'est ni cherchée par balayage ni triée : sur la clé seule, et après `actor`, `action`, `subject_type, subject_id` ou
  `tenant`.
- `ScanningAuditLogReader` lit n'importe quel `AuditRowRepository` en balayant chaque ligne, avec
  `AuditQuery::matches()` et `AuditQuery::isAfterCursor()` : pour une application qui garde son propre dépôt et une
  petite piste, et pour les tests. Chaque page lit toute la piste.

Les deux donnent les mêmes pages pour les mêmes requêtes; les tests du bundle les y obligent.
