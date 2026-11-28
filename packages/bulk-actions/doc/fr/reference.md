<!-- phil-dev source-sha256=441899bb8ac1f60702a0b5ccdb43674b64320ed6331cb406d5081f64ccb81e93 -->
# Référence

Chaque type public du paquet, avec son rôle.

## Services

| Type | Rôle |
|---|---|
| `BulkSelector` | Range une sélection après avoir vérifié la cible, l'action, le droit et les limites ; rend son jeton. |
| `BulkPreviewer` | Donne le `Preview` d'une sélection et d'une modification, sans utiliser le jeton. |
| `BulkConfirmer` | Utilise le jeton une fois, vérifie tout de nouveau, range l'exécution et l'envoie ; `start()` le fait sans jeton. |
| `BulkExecutor` | Exécute un lot d'une exécution ; dit si l'exécution a encore du travail. |
| `BulkRetrier` | Remet en attente les éléments en échec d'une exécution terminée, et les conflits sur demande, et envoie l'exécution. |
| `Messenger\ExecuteBulkRun` | Le message d'un lot : l'identifiant de l'exécution seulement. |
| `Messenger\ExecuteBulkRunHandler` | Exécute un lot et envoie le suivant tant que l'exécution a du travail. |
| `Bridge\ExternalSelection` | Construit une sélection à partir des lignes d'une table de données ou des identifiants et de la classe d'une action groupée. |

## Configuration

| Type | Rôle |
|---|---|
| `Target\Target` | Un genre d'élément : son nom, sa classe, son dépôt et ses actions. |
| `Target\Targets` | Les cibles par nom ; `forClass()` vérifie une classe que nomme un outil externe. |
| `Target\Limits` | Les bornes des identifiants, des éléments, des lots, des échantillons et des durées de vie. |

## Sélection et modification

| Type | Rôle |
|---|---|
| `Selection\Selection` | Des identifiants avec leurs versions, ou un filtre moins ses éléments décochés. |
| `Selection\SelectionKind` | `Identifiers` ou `Filter`. |
| `Selection\FilterCriteria` | Le nom et les paramètres d'un filtre, bornés et triés. |
| `Selection\SelectionToken` | Le secret qu'une page porte ; `fingerprint()` en est le SHA-256. |
| `Selection\PendingSelection` | Une sélection qui attend sa confirmation : à qui elle est, pour quelle action, jusqu'à quand. |
| `Change\ChangeSet` | Les champs que définit une modification groupée ; `fromSubmission()` lit une case par champ. |

## Aperçu

| Type | Rôle |
|---|---|
| `Preview\Preview` | Le nombre, l'échantillon, la modification, l'indicateur de destruction et l'expiration. |
| `Preview\SampleItem` | Un élément de l'échantillon : son libellé, si le voter l'accorde, et ses changements de valeur. |
| `Preview\ValueChange` | L'ancienne et la nouvelle valeur d'un champ ; `changes()` dit si elles diffèrent. |

## Exécutions

| Type | Rôle |
|---|---|
| `Run\BulkRun` | Une action confirmée et le résultat de chaque élément, conservés entre les lots par `toArray()` et `fromArray()`. |
| `Run\RunId` | 16 octets aléatoires en hexadécimal. |
| `Run\RunStatus` | `Pending`, `Running`, `Finished` ou `Stopped`. |
| `Run\ItemOutcome` | `Pending`, `Succeeded`, `Denied`, `Conflict`, `Refused`, `Failed` ou `Missing`, avec sa clé de traduction. |
| `Run\ItemResult` | Un élément, son résultat et la clé de traduction de sa raison. |
| `Run\Report` | Les résultats d'une exécution, comptés par résultat, et combien une nouvelle tentative en traiterait. |
| `Run\ItemContext` | Ce qu'une action sait pendant qu'elle modifie un élément, avec la clé d'idempotence de l'élément. |
| `Run\AuditEvent` | `Confirmed`, `Finished`, `Stopped` ou `Retried`. |

## Ports et adaptateurs en mémoire

| Type | Rôle |
|---|---|
| `Contract\BulkAction` | Une action d'une cible, écrite par l'application. |
| `Contract\ItemRepository` | Lit les éléments d'une cible par identifiants et par filtres. |
| `Contract\Authorizer` | Dit si une personne peut lancer une action et l'appliquer à un élément. |
| `Contract\SelectionStore` | Garde les sélections en attente ; `take()` est atomique. |
| `Contract\RunStore` | Garde les exécutions entre les lots. |
| `Contract\Transactions` | Exécute un lot dans une seule transaction. |
| `Contract\RunDispatcher` | Demande le lot suivant d'une exécution. |
| `Contract\AuditTrail` | Enregistre les événements d'une exécution. |
| `Memory\InMemorySelectionStore` | Un magasin de sélections dans un tableau ; `forgetExpired()` abandonne les sélections expirées. |
| `Memory\InMemoryRunStore` | Un magasin d'exécutions dans un tableau. |

## Exceptions

Chaque exception implémente `Exception\BulkActionsException`.

| Type | Lancée quand |
|---|---|
| `Exception\InvalidInput` | un identifiant, un filtre, une modification, un réglage ou une ligne conservée sort de ses bornes |
| `Exception\UnknownAction` | la configuration ne déclare ni cette cible ni cette action |
| `Exception\AccessDenied` | la personne ne peut pas lancer l'action |
| `Exception\SelectionUnavailable` | un jeton est inconnu, utilisé, expiré, celui d'une autre personne ou mal formé |
| `Exception\RunNotFound` | une nouvelle tentative nomme une exécution que le magasin ne connaît pas |
| `Exception\NothingToRetry` | une nouvelle tentative trouve l'exécution encore en cours, ou rien à relancer |
| `Exception\ItemRefused` | une action refuse de modifier un élément ; porte la clé de traduction de la raison |
| `Exception\VersionConflict` | une action constate que l'élément a changé depuis sa lecture |

## Clés de traduction

| Clé | Signification |
|---|---|
| `bulk_actions.outcome.<outcome>` | le résultat d'un élément, d'après `ItemOutcome::translationKey()` |
| `bulk_actions.reason.access_lost` | la personne a perdu le droit de lancer l'action pendant l'exécution |
| `bulk_actions.reason.unknown_action` | l'action a quitté la configuration pendant l'exécution |
| `bulk_actions.reason.filter_refused` | le dépôt n'accepte plus le filtre |
| `bulk_actions.reason.error` | l'action s'est arrêtée sur une erreur, annulée |
