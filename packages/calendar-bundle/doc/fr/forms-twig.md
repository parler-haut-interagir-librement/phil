<!-- phil-dev source-sha256=15745ba96055bd6d350d15ae28c0452bc0b76b55b1c1688a92585520428740bc -->
# Formulaires et Twig

## `EventType`

Le formulaire d'un événement, vers un `EventDraft` : titre, début et fin (`datetime-local`), journées entières, fuseau
horaire, la règle (le `RecurrenceRuleType` de `phil/recurrence-bundle`), lieu, page (`https://` ajouté s'il manque),
image, le lieu parmi ceux du calendrier, par leur libellé, description, étiquettes séparées par des virgules, et
places (la capacité, de 1 à 1 000 000, ou vide pour ne pas limiter). Un nouvel événement commence dans le
`default_timezone` configuré. Les libellés viennent du domaine `phil_calendar`.

```php
$form = $this->createForm(EventType::class, EventDraft::of($event));
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    $calendar->apply($event, $form->getData());
}
```

`EventDraft` porte les contraintes du validateur : un titre de 200 caractères au plus, un début, une fin, un fuseau
horaire IANA, une page avec un domaine de premier niveau.

## Twig

| Nom | Genre | Ce qu'il donne |
|---|---|---|
| `calendar_occurrences(from, through, hostKind, hostReference, tag)` | fonction | `Calendar::occurrences()` |
| `occurrence\|occurrence_when(locale)` | filtre | Quand une occurrence a lieu, dans la langue de la requête sauf si on en donne une |

```twig
{{ occurrence|occurrence_when }}
{# mardi 10 novembre 2026, 19 h 00 – 22 h 00 #}
{# vendredi 10 juillet 2026 – dimanche 12 juillet 2026 #}
```

Un jour donne ses heures, plusieurs jours leurs dates et leurs heures, des journées entières leurs dates seules.

## schema.org

| Fonction | Donne |
|---|---|
| `calendar_event_schema(event, locale)` | L'événement sous la forme d'un `Event` de schema.org, son nom, sa description et son lieu dans la locale (celle de la requête par défaut) : dates (dates seules pour une journée entière), lieu (son lieu, ou le lieu en mots), page, statut, mots-clés et son `Schedule` |
| `calendar_event_json_ld(event, locale)` | Le même événement en JSON-LD : un nœud du graphe de la page avec phil/structured-data-bundle, sinon son propre élément `<script type="application/ld+json">` |
| `calendar_texts(events, locale)` | L'`EventTextSet` d'un événement ou d'une liste : `title()`, `description()`, `place()` dans la locale ; voir [Traductions](translations.md) |
| `calendar_personal(kind, reference, from, through)` | Les occurrences propres à un membre pour une période ; voir [Membres](members.md#le-calendrier-dun-membre) |
| `calendar_choices(kind, reference)` | Ce qu'un membre suit et masque, un `MemberChoices` ; voir [plus bas](#les-choix-dun-membre) |
| `calendar_attendance(occurrence)`, `calendar_places_left(occurrence)` | Combien participent à une occurrence, ou à un événement en entier ; les places restantes, null sans capacité |
| `calendar_attends(occurrence, kind, reference)` | Si un membre y participe |

Avec phil/structured-data-bundle installé, `calendar_event_json_ld` n'imprime rien : il ajoute l'événement au graphe de
la page, que `structured_data()` imprime en un seul élément script avec le site, la page et les autres nœuds. Produisez
le rendu de `structured_data()` après les événements, à la fin du corps. Sans ce bundle, chaque événement imprime son
propre élément, dont le JSON écrit `<`, `>` et `&` sous forme d'échappements, pour qu'aucune valeur ne puisse le fermer.

`calendar_event_schema` donne les données seules, pour un gabarit qui compose son propre document.

## Les choix d'un membre

`CalendarChoiceType` est le formulaire d'un choix, vers un `CalendarChoiceDraft` : la source — organisateur, étiquette,
lieu ou événement —, sa valeur, et si elle est suivie ou masquée, deux boutons radio. Le brouillon vérifie la valeur
selon sa source ; `CalendarChoices::apply($member, $draft)` la conserve.

```php
$form = $this->createForm(CalendarChoiceType::class);
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    $choices->apply($member, $form->getData());
}
```

`@PhilCalendar/calendar/_choices.html.twig` dessine les choix d'un membre dans une carte du kit PHIL UX : ceux qui sont
suivis, puis ceux qui sont masqués, chacun en un badge. Il prend `choices`, issu de `calendar_choices()`, et trois
variables facultatives : `form`, la vue d'un `CalendarChoiceType`, dessinée dans le pied de la carte ; `forget_route`,
une route qui prend `source` et `value` par `POST`, pour un bouton à côté de chaque badge ; et `forget_token`, envoyé
comme `_token` avec lui.

```twig
{{ include('@PhilCalendar/calendar/_choices.html.twig', {
    choices: calendar_choices('member', app.user.id),
    form: form,
    forget_route: 'app_calendar_forget',
    forget_token: csrf_token('calendar_choice'),
}) }}
```

Il dessine les recettes Card, Badge et Button ; les classes `phil-calendar-choices__*` sont stylées dans `calendar.css`.
