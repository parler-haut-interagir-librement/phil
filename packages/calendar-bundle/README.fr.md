<!-- phil-dev source-sha256=7764c757ebfdf1ae50bfb02ab9745fc7a8a51d62f52f76fb8dcb7f22c3ffbfa8 -->
# phil/calendar-bundle

**Des événements, récurrents ou non, affichés en mois, en semaine, en jour, en liste ou en affiches.**

phil/calendar-bundle est un calendrier pour une application Symfony. Les événements vivent dans Doctrine avec leur
fuseau horaire, des journées entières, une règle de récurrence et les occurrences qu'elle retire, des organisateurs,
des étiquettes, un lieu et une affiche. Un lieu est soit un lieu propre au calendrier, soit une fiche du répertoire de
l'application. Le bundle donne dans l'ordre les occurrences d'une période, pour un organisateur ou une étiquette, un
formulaire d'événement, et un fichier iCalendar à `/calendar.ics` auquel on s'abonne. Les événements et les lieux sont
traduits dans les locales qu'une application déclare. Les membres participent à une occurrence ou à tout un
événement, dans la limite de sa capacité ; ils proposent des événements qu'un modérateur approuve ; chacun a son propre
calendrier, fait de ce qu'il suit, organise et fréquente — calendriers d'organisateurs, étiquettes, lieux, événements —,
moins ce qu'il masque, avec un flux iCalendar privé à une adresse secrète qu'il peut révoquer. Une occurrence peut avoir
sa propre page, où ses billets se vendent, et le calendrier s'imprime à raison d'un mois par page. Toutes les listes
d'occurrences d'une requête partagent un même budget, de sorte qu'un calendrier de nombreuses règles récurrentes
garde une page dans un temps borné. Les libellés sont en anglais et en français.

Il s'adresse à un site où des organisateurs ou des rédacteurs planifient des événements et où les visiteurs les
parcourent. Le calendrier d'une page est le composant Live Component `PhilCalendar:Calendar`, en cinq vues : mois,
semaine, jour, liste et affiches. La semaine et le jour peuvent partager leurs heures entre des organisateurs, des
lieux ou des étiquettes. Lorsque `phil/almanac` est installé, ses dates s'affichent parmi les événements. Lorsque
`phil/taxonomy-bundle` est installé, les étiquettes sont partagées avec le reste du site. Tournez-vous vers
`phil/recurrence` ou `phil/recurrence-bundle` seuls lorsqu'une application a besoin de règles de récurrence sans tout
un calendrier.

Le bundle s'appuie sur `phil/recurrence` et `phil/recurrence-bundle`, Doctrine ORM et Symfony Form. Symfony UX Live
Component, Stimulus et Twig sont facultatifs. Les vues sont mises en page en PHP et ne savent rien du HTML : les
gabarits du composant ne font que dessiner ce qu'elles calculent, avec les recettes Popover, Badge, Card, Button,
ButtonGroup et Empty du kit PHIL UX, installées dans l'application par le Symfony UX Toolkit. La synchronisation avec
les calendriers des membres — CalDAV, Google, Microsoft — est le travail de `phil/calendar-sync`. Le service `Calendar`
repose sur le port `EventRepository`, avec `DoctrineEventRepository` comme adaptateur.

## État

| Partie | État |
|---|---|
| `Event`, `EventHost`, `Occurrence` et le port du dépôt | Implémenté et testé |
| `Calendar` : planifier, modifier, supprimer, organiser, les occurrences d'une période | Implémenté et testé |
| Les étiquettes dans `phil/taxonomy-bundle` : `EventTagging`, `TaxonomyEventTagging`, `phil:calendar:refresh-tags` | Implémenté et testé |
| `DoctrineEventRepository` | Implémenté et testé |
| `EventType` et `EventDraft` | Implémenté et testé |
| `ICalendar` (RFC 5545) et le flux `calendar.ics` | Implémenté et testé |
| Le composant `PhilCalendar:Calendar`, ses gabarits, son contrôleur Stimulus et sa feuille de style | Implémenté et testé |
| Les dates de l'almanach parmi les événements, et en événements : `AlmanacItems`, `AlmanacImport`, `phil:calendar:import-almanac` | Implémenté et testé |
| Twig : `calendar_occurrences`, `occurrence_when` | Implémenté et testé |
| Les traductions des événements et des lieux : `EventTranslation`, `VenueTranslation`, `EventTexts` | Implémenté et testé |
| Les participants et la capacité : `Attendance`, `Attendees`, `AttendancePolicy`, `DoctrineAttendanceRepository` | Implémenté et testé |
| Le calendrier d'un membre, `PersonalCalendar` ; les propositions et leur flux de travail, `Proposals` | Implémenté et testé |
| Ce qu'un membre suit et masque, `CalendarChoices`, son formulaire et son gabarit ; son flux à une adresse secrète, `PersonalFeeds` et `phil_calendar_personal_ics` | Implémenté et testé |
| Les données d'un membre dans l'exportation et l'effacement de `phil/privacy-bundle`, `CalendarPrivacy` | Implémenté et testé |
| Le budget d'une requête, `ExpansionBudget` : occurrences et événements récurrents lus, l'arrêt consigné | Implémenté et testé |
| Une page propre à une occurrence ; le calendrier imprimé, `PrintableCalendar` | Implémenté et testé |
| Des vues mises en page en PHP : les périodes, la grille du mois, la semaine et le jour par heure partagée entre des ressources, la liste groupée par jours, les affiches, les jours en mots | Implémenté et testé |
| Le contrôleur et la feuille de style associés à AssetMapperBundle, le contrôleur chargé paresseusement | Implémenté et testé |
| La billetterie : vendre les places d'une occurrence | Hors du paquet : l'`AttendancePolicy` d'une application et les paquets de commerce |
| La fédération avec d'autres serveurs, ActivityPub | Hors du paquet : `calendar-activitypub`, un paquet à part à venir |
| Les liens « Ajouter au calendrier » d'une occurrence | En attente d'un Symfony UX CalendarLink stable ; les flux iCalendar sont ceux du bundle |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement, la configuration, les tables. |
| [Événements](doc/fr/events.md) | `Event`, ses occurrences, ses organisateurs et ses étiquettes ; `Calendar` et le budget d'une requête. |
| [Formulaires et Twig](doc/fr/forms-twig.md) | `EventType`, `EventDraft`, la fonction et le filtre Twig. |
| [Traductions](doc/fr/translations.md) | Les événements et les lieux dans les locales déclarées, écrits et lus. |
| [Participants, propositions et calendriers de membres](doc/fr/members.md) | La participation et la capacité, la participation payante, le calendrier d'un membre, ce qu'il suit et masque, son flux et ses données, les propositions et leur flux de travail. |
| [Le calendrier imprimé](doc/fr/printing.md) | Un mois par page en PDF. |
| [Vues](doc/fr/views.md) | Les périodes, les éléments et leurs sources, le mois, la semaine et le jour, la liste ; le composant, son apparence et son accessibilité ; l'almanach. |
| [Le flux](doc/fr/feeds.md) | Les routes et les fichiers iCalendar : celui du calendrier et celui d'un membre. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```sh
composer require phil/calendar-bundle
```

```php
$event = $calendar->schedule($draft);          // an EventDraft, from EventType
$calendar->host($event, 'association', '42');

foreach ($calendar->occurrences($from, $through, 'association', '42') as $occurrence) {
    // $occurrence->event, $occurrence->start, $occurrence->end
}
```

```twig
<twig:PhilCalendar:Calendar view="month" />

{% for occurrence in calendar_occurrences(from, through) %}
    {{ occurrence.event.title }} — {{ occurrence|occurrence_when }}
{% endfor %}
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-intl`, `ext-mbstring` et `ext-sodium` ; `phil/recurrence`, `phil/recurrence-bundle`, `phil/url`,
`phil/doctrine-behaviours` et `phil/locale-dimensions` 0.1 ; Doctrine ORM 3.7 et DBAL 4.5 ; les composants dependency
injection, config, form, HTTP foundation, UID, validator et workflow de Symfony 8.2 ; l'horloge de PSR-20 et le journaliseur de PSR-3. Twig,
Symfony UX Live Component avec Twig Component et le bundle Stimulus avec les recettes du kit PHIL UX, AssetMapper,
la console, `phil/almanac`, `phil/paged-media`, `phil/privacy-bundle` et `phil/taxonomy-bundle` sont facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
