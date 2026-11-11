<!-- phil-dev source-sha256=801f7e9fa19be427c6beacf834ee6b2c4f51dd04383cece6c3697e2cf866e3ef -->
# Mots, Twig et stockage

## `RuleDescription`

```php
$description->describe(RecurrenceRule::parse('FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE;COUNT=10'), 'fr');
// "Toutes les 2 semaines le lundi et le mercredi, 10 fois"
```

Les phrases sont des messages ICU du domaine `phil_recurrence` — pluriels, ordinaux, *le 1er*, *the 2nd* —, et ICU
écrit les jours, les mois et les dates de fin dans la langue donnée ou celle du traducteur. L'application change les
mots en remplaçant les messages. Un formateur est construit une seule fois par langue, motif et fuseau horaire, puis
conservé : une liste d'événements écrit chacune de ses règles sans construire un formateur pour chaque nom de jour ou de
mois. Jusqu'à 64 formateurs sont conservés, puis reconstruits : les langues et les fuseaux tirés des requêtes ne peuvent
ainsi pas faire grossir la mémoire d'un *worker* qui vit d'une requête à l'autre.

Une heure de fin, `UNTIL=20270101T045959Z`, est un moment : `describe()` prend en troisième argument le fuseau horaire
de la première occurrence de la récurrence, et écrit le jour où la fin tombe dans ce fuseau — *until 31 December 2026*
à Toronto. Sans lui, la fin est écrite dans son propre fuseau, UTC pour une règle lue à partir d'un texte. Un jour de
fin est écrit tel quel.

## Twig

| Nom | Nature | Donne |
|---|---|---|
| `rrule_text(locale = null, timezone = null)` | filtre | La règle en mots ; vide pour `null`. `timezone` — un fuseau, son nom, ou la première occurrence elle-même — indique où une heure de fin devient un jour |
| `rrule_occurrences(start, rule, limit = 10, from = null)` | fonction | Les occurrences à partir d'une première, à partir d'un moment ; la première seule sans règle |

## `phil_rrule`

Avec DoctrineBundle, le type de colonne `phil_rrule` : un `RecurrenceRule` dans un `VARCHAR(255)`, tel que l'écrit la
RFC 5545, sa fin en UTC ou sous forme de jour, pour qu'il se relise à l'identique. Une valeur qui n'est pas une règle
est refusée à l'écriture ; un texte qui n'est pas une règle, à la lecture.

```php
#[ORM\Column(type: 'phil_rrule', nullable: true)]
private ?RecurrenceRule $rule = null;
```

## schema.org

| Fonction | Donne |
|---|---|
| `rrule_schema(start, rule)` | La récurrence sous la forme d'un `Schedule` de schema.org, ou `null` |

Les données s'intègrent dans un document JSON-LD imprimé par le gabarit :

```twig
<script type="application/ld+json">{{ data|json_encode(constant('JSON_UNESCAPED_SLASHES') b-or constant('JSON_UNESCAPED_UNICODE') b-or constant('JSON_HEX_TAG'))|raw }}</script>
```

`JSON_HEX_TAG` écrit `<` et `>` sous forme d'échappements : aucune valeur ne peut ainsi fermer l'élément script.
