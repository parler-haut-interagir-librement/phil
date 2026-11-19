<!-- phil-dev source-sha256=7bbde8d4ca723f521da3731d90a3ce3c1a5843a0fd5d8147c4681fd1c9c2160f -->
# Traductions

Un événement garde ce que toutes les locales partagent (ses heures, sa règle, son lieu, sa page) ainsi que le titre et
la description avec lesquels il a été créé. Ses traductions vivent à côté de lui, une ligne par événement et par locale,
par les traductions de `phil/doctrine-behaviours` : `EventTranslation` porte un titre et une description,
`VenueTranslation` le nom d'un lieu. Les locales, leur graphie et leurs chaînes de repli sont celles de
`phil/locale-dimensions`.

```yaml
phil_calendar:
    translations:
        locales:
            fr_CA: [fr]     # a reader of Canadian French, then of French
            fr: []
            en: []
```

Sans locales, chaque événement est montré dans son propre texte et aucune traduction n'est lue. Une chaîne qui nomme une
locale non servie, ou une même locale deux fois, arrête la construction avec un message sous
`phil_calendar.translations.locales`.

## Écrire

`EventTexts::translate($event, 'en')` donne la traduction anglaise à écrire : celle qui est stockée, ou une nouvelle,
vide, persistée pour le prochain *flush*. Rien n'est copié d'une autre locale : une traduction que personne n'a écrite
ne passe jamais pour une traduction. `translateVenue($venue, 'en')` fait de même pour le nom d'un lieu.

```php
$texts->translate($event, 'en')->write('Game night', 'Bring your games.');
$texts->translateVenue($venue, 'en')->rename('Main hall');
$entityManager->flush();
```

`write()` tient une traduction aux limites de l'événement lui-même (un titre de 1 à 200 caractères, une description de
65 535) ; `rename()` tient le nom d'un lieu à 1 à 200. `locales($event)` donne les locales dans lesquelles un événement
est traduit, pour les variantes de sa page.

## Lire

`EventTexts::of($events, $locale)` lit les traductions d'une liste d'événements et de leurs lieux le long de la chaîne
de la locale (celle de la requête, lue à chaque fois dans le traducteur, lorsqu'on n'en donne pas), en une requête par
genre d'enregistrement. L'`EventTextSet` qu'elle donne a, pour chaque événement, `title()`, `description()`, `place()`
(le libellé de son lieu, ou le lieu dit en mots) et `isTranslated()` ; `venueName()` et `venueLabel()` pour un lieu.

**Une traduction est servie en entier.** La chaîne choisit une ligne, jamais un champ : une traduction française sans
description est montrée sans description, jamais avec celle d'une autre locale. Un événement sans traduction le long de
la chaîne, ou une locale que l'application ne sert pas, est montré dans son propre texte.

Les vues, les affiches, la liste et les données schema.org lisent les textes dans la locale de la requête. Dans Twig,
`calendar_texts(events, locale)` donne l'ensemble, et `calendar_event_schema(event, locale)` et
`calendar_event_json_ld(event, locale)` prennent une locale.

```twig
{% set texts = calendar_texts(events) %}
{% for event in events %}
    <h2>{{ texts.title(event) }}</h2>
{% endfor %}
```

Le flux iCalendar n'a pas de locale de lecteur : il donne chaque événement dans son propre texte.

## Les tables

| Table | Colonnes |
|---|---|
| `phil_calendar_event_translation` | `id`, `event` (l'événement, supprimée avec lui), `locale` (12), `title` (200), `description` (texte) ; unique sur `event` et `locale` |
| `phil_calendar_venue_translation` | `id`, `venue` (le lieu, supprimée avec lui), `locale` (12), `name` (200) ; unique sur `venue` et `locale` |
