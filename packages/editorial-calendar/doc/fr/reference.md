<!-- phil-dev source-sha256=86e03f4a0367e4d001afc4d6d89969cd89ee47dcac83520929cf36aa6ba2a187 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `EditorialCalendar` | Les éléments prévus et les lots de publication, immuables, avec des lots appliqués en entier |
| `PlannedItem` | Une publication prévue : contenu, langue, auteur, section, état, moment en UTC |
| `ItemState` | Idée, rédaction, prêt, planifié, publié, retiré, et le flux de travail entre eux |
| `AuthorId` | L'identifiant stable d'un auteur, jamais une adresse courriel |
| `Filter` | Les éléments qu'une vue ou une liste montre, par auteur, section et état |
| `PublicationBatch` | Des éléments publiés ensemble, à un moment ou à la main |
| `View\CalendarView` | Les vues jour, semaine, mois et liste dans un fuseau horaire |
| `View\Period` | Ce qu'une vue montre : son intervalle, ses entrées, ses jours |
| `View\Day` | Un jour d'une vue : sa date locale, ses bornes en UTC, ses entrées |
| `View\Entry` | Une ligne d'une vue : un élément, un lot avec ses éléments, ou une transition |
| `View\EntryKind` | Lot, élément ou transition, dans l'ordre où les entrées d'un même moment sont listées |
| `Contract\TransitionReader` | Le port par lequel le calendrier lit les transitions prévues ailleurs |
| `Transition\PlannedTransition` | Une transition prévue pour une ressource à un moment |
| `Transition\NoTransitions` | Le lecteur d'un calendrier qui ne montre que ses propres éléments |
| `Review\Review` | Trouve le contenu répété, les éléments non prêts, les publications trop rapprochées et les périodes vides |
| `Review\ReviewRules` | L'espacement minimal et la plus longue période permise sans publication |
| `Review\Issue` | Un conflit ou une période vide : genre, moments, éléments |
| `Review\IssueKind` | Contenu en double, non prêt, trop rapproché, période vide |
| `ICalendar\ICalendarWriter` | Écrit les publications sous forme de flux iCalendar |
| `Exception\EditorialCalendarException` | Toute exception que lance le paquet |
| `Exception\InvalidItem` | Un élément, un auteur, un lot, une règle ou une vue construits à partir de valeurs qu'il refuse |
| `Exception\InvalidTransition` | Un changement d'état que le flux de travail refuse, ou un état planifié sans moment |
| `Exception\UnknownItem` | Un élément ou un lot que le calendrier ne contient pas |
| `Exception\BatchFailed` | Un lot non appliqué parce que l'un de ses éléments a refusé ; aucun n'a été appliqué |

`Moment` est propre au paquet et peut changer dans n'importe quelle version.
