<!-- phil-dev source-sha256=71b4e00b04588df1065ae93a97a5013e7294798ed8df4131b8ff864edfb87316 -->
# Points d'extension

Ce qu'une application implémente, étiquette, remplace ou utilise. Les règles et leurs occurrences sont celles de
`phil/recurrence`.

## Ports

### `ItemSource`

`Phil\CalendarBundle\Application\View\ItemSource` : ce que les vues montrent, en plus des événements du calendrier. Un
service étiqueté `phil_calendar.item_source` (à la main : l'interface n'est pas autoconfigurée) rejoint `EventItems` et,
avec `phil/almanac-bundle`, `AlmanacItems` ; `before` ou `after` dans l'étiquette nomme la source avant ou après
laquelle il va. Voir [Vues](views.md#éléments).

| Méthode | Contrat |
|---|---|
| `items(DateTimeImmutable $from, DateTimeImmutable $until): list<Item>` | Les éléments qui touchent la période, dans n'importe quel ordre (`Items` les trie) ; lit ce dont il a besoin une fois par période, non une fois par élément |

### `HostLabels`

`Phil\CalendarBundle\Application\View\HostLabels` : les noms que l'application donne à ses organisateurs. Un service
enregistré sous cet identifiant est utilisé par `CalendarViews` ; sans lui, un organisateur s'affiche sous la forme
`kind:reference`.

| Méthode | Contrat |
|---|---|
| `labels(list<string> $hosts): array<string, string>` | Un nom par organisateur qu'il connaît, indexé par `kind:reference` ; les autres sont laissés de côté |

### `EventTagging`

`Phil\CalendarBundle\Application\EventTagging` : l'endroit où les étiquettes d'un événement sont partagées. Avec
`phil/taxonomy-bundle`, c'est `TaxonomyEventTagging`, où un événement est la ressource `phil_calendar_event` ; sans lui,
les étiquettes restent des mots libres dans la ligne de l'événement. Voir
[Événements](events.md#les-étiquettes-dans-la-taxonomie).

| Méthode | Contrat |
|---|---|
| `tag(Event $event, list<string> $names): list<string>` | Donne ces étiquettes à l'événement ; retourne les noms à lui garder |
| `tagsOf(Event $event): list<string>` | Les noms des étiquettes qu'il a |
| `forget(Event $event): void` | Retire ses étiquettes |

### Dépôts

`EventRepository`, `VenueRepository`, `AttendanceRepository`, `CalendarChoiceRepository` et
`PersonalFeedTokenRepository` sont des alias de leurs adaptateurs Doctrine ; une application pointe n'importe lequel
vers son propre service, sous les mêmes contrats : `overlapping()` garde les événements qui commencent avant la fin
d'une période et dont la série n'a pas fini à son début, d'un organisateur lorsqu'on en donne un. Voir
[Événements](events.md#le-dépôt).
`EventRepository::proposalsOf()` et `forgetProposer()`, et `AttendanceRepository::forgetMember()`, servent
l'exportation et l'effacement des données d'un membre ; `CalendarChoiceRepository` garde une ligne par membre, source
et valeur ; `PersonalFeedTokenRepository::replace()` garde un jeton par membre et le retrouve par son empreinte, jamais
par le secret.

### `AttendancePolicy`

`Phil\CalendarBundle\Application\AttendancePolicy` : si l'application admet un membre à un événement, demandé une fois
les vérifications du calendrier passées. Enregistrée sous son interface, `Attendees` la prend. Voir
[Participants](members.md#participation-payante).

| Méthode | Contrat |
|---|---|
| `admits(Event $event, ?DateTimeImmutable $occurrence, Member $member): bool` | Vrai pour admettre ; le début de l'occurrence, ou null pour toutes les occurrences. La participation payante répond une fois la commande payée |

### La vie privée des membres

Avec `phil/privacy-bundle`, `CalendarPrivacy` porte les étiquettes `phil_privacy.exporter` et `phil_privacy.eraser` ; la
personne concernée est la référence d'un membre du genre `privacy_member_kind`. Voir [Membres](members.md#vie-privée).

### Le flux de travail des propositions

`phil_calendar_proposal`, une machine à états de `symfony/workflow` répartie par le répartiteur d'événements de
l'application : une garde retient une approbation ou un refus, un écouteur les suit. Voir
[Propositions](members.md#propositions).

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `budget.occurrences`, `budget.expansions` | Ce qu'une requête déroule au plus ; voir [le budget](events.md#le-budget-dune-requête) |
| `default_timezone` | Le fuseau horaire d'un nouvel événement, et des périodes et des dates des vues |
| `first_weekday` | Le jour où commencent les semaines dans les vues |
| `kit_subdirectory` | L'endroit où sont installées les recettes du kit PHIL UX, quand le Symfony UX Toolkit n'est pas activé pour le dire |
| `name` | Le nom des flux iCalendar, celui du calendrier et ceux des membres |
| `privacy_member_kind` | Le genre de membre dont une personne concernée de `phil/privacy-bundle` est la référence |
| `translations.locales` | Les locales dans lesquelles les événements et les lieux sont montrés, et leurs chaînes de repli |

## Gabarits, styles et mots

| Point | Comment |
|---|---|
| Le composant | `<twig:PhilCalendar:Calendar />` et ses propriétés : vues, ressources partagées, heures, voies ; voir [Vues](views.md#le-composant) |
| Les gabarits | `components/Calendar.html.twig` et `calendar/_toolbar`, `_month`, `_time`, `_list`, `_posters`, `_item`, `_choices` dans `@PhilCalendar`, remplacés comme ceux de n'importe quel bundle |
| Les recettes | Les recettes Popover, Badge, Card, Button, ButtonGroup et Empty, installées dans l'application et modifiées là, ou prises du kit Shadcn, qui a les mêmes noms, propriétés et blocs |
| L'apparence | `assets/styles/calendar.css`, dans la couche `phil.components` pour que les styles de l'application l'emportent ; `--phil-calendar-event`, `--phil-calendar-almanac`, `--phil-calendar-hour-height` |
| Les dates dans les gabarits | `calendar_title`, `calendar_format`, `calendar_time`, `calendar_hour`, `calendar_days`, `calendar_link` |
| Le formulaire d'événement | `EventType`, étendu par une extension de type de formulaire de l'application ; voir [Formulaires et Twig](forms-twig.md) |
| Les choix d'un membre | `CalendarChoiceType` et `_choices.html.twig`, sous une route de l'application qui sait qui est connecté ; voir [Formulaires et Twig](forms-twig.md#les-choix-dun-membre) |
| Le budget | `ExpansionBudget`, un service remis à zéro par `kernel.reset` ; une commande le remet à zéro entre ses unités de travail |

## En dehors du bundle

| Quoi | Où |
|---|---|
| La billetterie : vendre les places d'une occurrence | L'`AttendancePolicy` d'une application, et les paquets de commerce sur lesquels elle s'appuie ; le bundle n'encaisse aucun paiement |
| La fédération : des événements suivis depuis d'autres serveurs, ActivityPub | Un paquet à part, `calendar-activitypub`, une fois qu'une brique ActivityPub partagée existera ; le bundle ne change pas pour elle |
| La synchronisation avec les calendriers des membres : CalDAV, Google, Microsoft | `phil/calendar-sync` |
| Les liens « Ajouter au calendrier » d'une occurrence vers les calendriers de Google, d'Outlook.com ou d'Office 365 | Symfony UX CalendarLink une fois stable ; les flux iCalendar restent ceux du bundle |
