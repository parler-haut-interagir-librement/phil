<!-- phil-dev source-sha256=3a9cae10ca75d442a66016ce442966d3cc47c96b193f7e1d8b4c272bf491fd4a -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `RecurrenceRule` | `final readonly class` | Une règle RFC 5545 : lecture, vérification, écriture |
| `Recurrence` | `final readonly class` | Une règle à partir de sa première occurrence : occurrences, la suivante, exceptions, dates ajoutées |
| `RecurrenceDates` | `final readonly class` | Une ligne de dates `RDATE` ou `EXDATE` : lecture, vérification, écriture |
| `Frequency` | enum adossée à une chaîne | `YEARLY`, `MONTHLY`, `WEEKLY`, `DAILY` |
| `Weekday` | enum adossée à une chaîne | De `MO` à `SU`, et leurs numéros ISO |
| `WeekdayNum` | `final readonly class` | Un jour de la semaine de `BYDAY`, avec son rang |
| `Exception\RecurrenceException` | interface | Chaque exception de la bibliothèque |
| `Exception\InvalidRule` | `final class`, `InvalidArgumentException` | Une règle ou une ligne de dates impossible, ou qui n'est pas lue |

## schema.org

`SchemaOrg::of(Recurrence)` donne un `Schedule` : date et heure de début, `repeatFrequency` sous forme de durée
ISO 8601 (`P2W`), `byDay` (`https://schema.org/Tuesday`, ou `2TU` pour un ordinal), `byMonthDay`, `byMonth`,
`repeatCount`, `endDate` (une heure de fin écrite comme le jour où elle tombe dans le fuseau horaire de la première
occurrence), `exceptDate`, `scheduleTimezone`. Un `Schedule` n'a pas de propriété pour les dates ajoutées : elles sont
laissées de côté. Le tableau a un `@type` et pas de `@context` : il est fait pour être
intégré dans un document JSON-LD plus large.

| Type | Nature | Rôle |
|---|---|---|
| `SchemaOrg` | `final class` | `of(Recurrence)` : un `Schedule` de schema.org, pour l'`eventSchedule` d'un `Event` |
