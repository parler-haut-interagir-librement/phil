<!-- phil-dev source-sha256=7ef12fb7eab918a384bfc7456c10db5ede89974028c8b08c75c79162cb2933e6 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `PhilCalendarBundle` | `final class`, `AbstractBundle` | Alias `phil_calendar` ; la configuration, le mappage, les services |
| `Domain\Model\Event` | entité | Un événement, sa règle, ses dates ajoutées, ses exceptions et ses étiquettes ; ses occurrences |
| `Domain\Model\EventHost` | entité | Qui tient un événement |
| `Domain\Model\Venue` | entité | L'endroit où les événements ont lieu : un lieu propre ou une fiche du répertoire |
| `Domain\ValueObject\Occurrence` | `final readonly class` | Une occurrence d'un événement |
| `Domain\ValueObject\Member` | `final readonly class` | Une personne de l'application, `kind:reference` |
| `Domain\Model\EventTranslation`, `VenueTranslation` | entité | Le titre et la description d'un événement, le nom d'un lieu, dans une locale |
| `Domain\Model\Attendance` | entité | Un membre qui participe à une occurrence, ou à toutes |
| `Domain\Exception\AttendanceRefused` | exception | Une participation refusée, et sa `reason` |
| `Domain\Repository\AttendanceRepository` | interface | Le port des participations |
| `Domain\Model\CalendarChoice`, `Domain\Choice\ChoiceSource` | entité, énumération | Une source qu'un membre suit ou masque : un organisateur, une étiquette, un lieu, un événement |
| `Domain\Model\PersonalFeedToken` | entité | L'empreinte du secret du flux d'un membre |
| `Domain\Repository\CalendarChoiceRepository`, `PersonalFeedTokenRepository` | interface | Les ports des choix et des secrets de flux |
| `Domain\Exception\InvalidChoice` | exception | Un choix qui ne peut pas être |
| `Application\EventTexts`, `EventTextSet` | `final readonly class` | Les événements et les lieux dans une locale : écrits, et lus pour une liste |
| `Application\Attendees`, `AttendancePolicy` | `final readonly class`, interface | Inscrire, retirer, compter, énumérer ; l'admission par l'application |
| `Application\PersonalCalendar` | `final readonly class` | Le calendrier propre à un membre : ce auquel il participe, ce qu'il organise, ce qu'il suit, moins ce qu'il masque |
| `Application\CalendarChoices`, `MemberChoices`, `CalendarChoiceDraft` | `final readonly class`, valeur, `final class` | Ce que les membres suivent et masquent ; les choix d'un membre lus une fois ; un choix tel qu'un formulaire le remplit |
| `Application\PersonalFeeds` | `final readonly class` | Les secrets des flux des membres : émis, révoqués, lus |
| `Application\ExpansionBudget` | `final class` | Ce qu'une requête déroule au plus, partagé par chaque appel, remis à zéro entre les requêtes |
| `Application\Proposals`, `ProposalWorkflow` | `final readonly class`, `final class` | Les propositions des membres, et leur machine à états `phil_calendar_proposal` |
| `Application\PrintableCalendar` | `final readonly class` | Le calendrier imprimé, un mois par page, par `phil/paged-media` |
| `Domain\Exception\InvalidEvent` | exception | Un événement impossible |
| `Domain\Repository\EventRepository`, `VenueRepository` | interface | Les ports |
| `Application\Calendar` | `final readonly class` | Planifier, modifier, supprimer, organiser, les occurrences d'une période ; rafraîchir les étiquettes |
| `Application\EventTagging` | interface | Le port vers une taxonomie qui porte les étiquettes des événements |
| `Application\EventDraft`, `VenueDraft` | `final class` | Un événement, un lieu, tels que les remplit un formulaire |
| `Application\Venues` | `final readonly class` | Créer, modifier, supprimer et énumérer des lieux ; le lieu d'une fiche |
| `Application\ICalendar` | `final class` | Les événements, ou des occurrences à part, sous forme de fichier iCalendar |
| `Application\View\CalendarView`, `Period` | enum, `final readonly class` | Les vues, et la période que l'une d'elles montre |
| `Application\View\Item`, `ItemSource`, `EventItems`, `AlmanacItems`, `Items` | valeur, interface, sources | Ce qu'une vue montre, et d'où cela vient |
| `Application\View\MonthGrid`, `MonthWeek`, `Bar`, `Lanes` | mise en page | La grille du mois |
| `Application\View\TimeGrid`, `TimeColumn`, `TimedBlock`, `Resources`, `CalendarResource` | mise en page | La semaine et le jour par heure, partagés ou non |
| `Application\View\Agenda`, `AgendaGroup`, `DaySpan` | mise en page | La liste groupée par jours, et les jours en mots |
| `Application\View\CalendarViews`, `HostLabels` | service, port | Les vues d'une page ; les noms que l'application donne à ses organisateurs |
| `Application\View\AlmanacImport` | `final readonly class` | Les dates de l'almanach d'une année en événements, une seule fois |
| `Infrastructure\Doctrine\DoctrineEventRepository`, `DoctrineVenueRepository`, `DoctrineAttendanceRepository`, `DoctrineCalendarChoiceRepository`, `DoctrinePersonalFeedTokenRepository` | dépôt | Les ports sur Doctrine ORM ; participants comptés et énumérés, les lignes d'un membre comptées et oubliées, avec DBAL |
| `Infrastructure\Form\EventType`, `VenueType`, `CalendarChoiceType`, `TagsTransformer` | types de formulaire, transformateur de données | Les formulaires d'événement, de lieu et de choix |
| `Infrastructure\Http\CalendarFeedController` | contrôleur | `calendar.ics` |
| `Infrastructure\Http\ICalendarResponse` | réponse | Un fichier iCalendar |
| `Infrastructure\Http\PersonalFeedController` | contrôleur | `personal/{token}.ics`, le flux propre à un membre |
| `Infrastructure\Privacy\CalendarPrivacy` | adaptateur | La part d'un membre dans une exportation et un effacement de `phil/privacy-bundle` |
| `Infrastructure\Twig\CalendarExtension` | extension Twig | `calendar_occurrences`, `occurrence_when`, `calendar_event_schema`, `calendar_event_json_ld`, `calendar_texts`, `calendar_personal`, `calendar_choices`, `calendar_attendance`, `calendar_places_left`, `calendar_attends`, `calendar_days`, `calendar_title`, `calendar_format`, `calendar_time`, `calendar_hour`, `calendar_link` |
| `Infrastructure\Twig\Components\CalendarComponent` | Live Component | `PhilCalendar:Calendar` |
| `Infrastructure\Console\ImportAlmanacCommand` | commande de console | `phil:calendar:import-almanac` |
| `Infrastructure\Taxonomy\TaxonomyEventTagging` | adaptateur | Les étiquettes des événements dans `phil/taxonomy-bundle`, ressource `phil_calendar_event` |
| `Infrastructure\Console\RefreshTagsCommand` | commande de console | `phil:calendar:refresh-tags` |
| `templates/components/Calendar.html.twig`, `templates/calendar/*` | gabarits | Le composant et ses vues ; les choix d'un membre, `_choices` |
| `assets/src/calendar_controller.js` | contrôleur Stimulus | Le clavier dans la grille du mois ; `moved()` |
| `assets/styles/calendar.css` | feuille de style | L'apparence des vues, sur les jetons de PHIL UX |
