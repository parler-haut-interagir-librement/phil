<!-- phil-dev source-sha256=909d03b6485dadcacb5081b55e2eb993b4850156341127804d8e4fddd67ff1c5 -->
# Planification

## Les éléments prévus

Un `PlannedItem` est une publication que le calendrier planifie :

| Propriété | Ce qu'elle contient |
|---|---|
| `id` | Son identifiant : de 1 à 128 lettres, chiffres, `.`, `_`, `:` ou `-` |
| `content` | Le contenu qu'il publie, sous forme de `ResourceReference` de `phil/resource` (`article:42`) |
| `title` | Son titre, de 1 à 500 caractères |
| `locale` | Sa langue, sous forme d'étiquette de langue (`fr`, `fr-CA`, `zh-Hant-TW`) |
| `author` | Son auteur, sous forme d'`AuthorId` |
| `state` | Où il en est, sous forme d'`ItemState` |
| `publishAt` | Le moment pour lequel il est prévu, en UTC, ou `null` |
| `section` | La section du site à laquelle il appartient (`news`, `blog`), de 1 à 128 lettres, chiffres, `.`, `_`, `:` ou `-`, ou `null` |

`PlannedItem::plan()` crée un élément à l'état d'idée ; `PlannedItem::restore()` en reconstruit un tel qu'il a été
stocké, dans n'importe quel état. Chaque changement donne un nouvel élément : `transitionTo()`, `reschedule()`,
`edit()` — dont les valeurs laissées à `null` sont conservées —, et `withoutSection()`.

Un `AuthorId` est un identifiant stable — un identifiant d'utilisateur, un UUID —, jamais une adresse courriel : une
adresse change et est un renseignement personnel. Un identifiant qui contient `@` est refusé.

## Le flux de travail

| De | Vers |
|---|---|
| `Idea` | `Drafting`, `Withdrawn` |
| `Drafting` | `Idea`, `Ready`, `Withdrawn` |
| `Ready` | `Drafting`, `Scheduled`, `Published`, `Withdrawn` |
| `Scheduled` | `Ready`, `Published`, `Withdrawn` |
| `Published` | `Withdrawn` |
| `Withdrawn` | `Idea` |

`ItemState::canBecome()` dit si une étape est permise ; `transitionTo()` lance `InvalidTransition` sinon. Un élément
planifié a toujours un moment : celui qu'on donne à `transitionTo()` remplace celui qui était prévu, et un élément
planifié sans l'un ni l'autre est refusé. Un élément publié garde le moment auquel il a été publié. `isPending()` est
vrai jusqu'à ce qu'un élément soit publié ou retiré ; un élément terminé n'est plus replanifié.

## Les moments

Chaque moment est gardé en UTC, quel que soit le fuseau horaire dans lequel il a été donné : `2026-12-01 09:00
America/Toronto` est stocké comme `2026-12-01T14:00:00Z`. Les moments se comparent par leur ordre, jamais avec `==` ni
`===`. Les vues les retransforment en jours d'un fuseau horaire.

## Le calendrier

`EditorialCalendar::of($items, $batches)` contient les éléments et les lots par identifiant ; un identifiant n'y est
qu'une fois, un élément est dans un lot au plus, et un lot ne contient que des éléments du calendrier. Chaque
changement donne un nouveau calendrier :

| Méthode | Donne |
|---|---|
| `with($item)` | Le calendrier avec l'élément ajouté, ou remplaçant celui de son identifiant |
| `without($id)` | Le calendrier sans l'élément ; son lot le perd, et disparaît s'il ne contenait rien d'autre |
| `withBatch($batch)`, `withoutBatch($id)` | Le calendrier avec un lot ajouté ou remplacé, ou retiré |
| `transition($id, $state, $at)` | Le calendrier avec un élément dans un autre état |
| `applyBatch($id, $now)` | Le calendrier dont chaque élément du lot est publié ; voir les [lots de publication](batches.md) |

Il répond à `items()`, `item($id)`, `batches()`, `batch($id)`, `batchOf($itemId)`, `moment($item)` — le moment du lot
pour un élément d'un lot, sinon le sien —, `dueBatches($now)`, `manualBatches()` et `unscheduled($filter)` : les
éléments en attente sans moment et sans lot, les idées et brouillons qui ne sont pas encore placés dans le temps,
parmi ceux qu'un [filtre](views.md#les-filtres) accepte. Un identifiant inconnu lance `UnknownItem`.

L'application stocke les éléments et les lots comme elle l'entend, et reconstruit le calendrier avec `of()` et
`restore()`.
