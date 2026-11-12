<!-- phil-dev source-sha256=cc4de3bd5adf34c19b6be570900aeca04d149da5b60c917d5f0d8579d1de3dc3 -->
# phil/almanac

**Pâques, les phases de la Lune, les saisons, les éclipses, le lever et le coucher du Soleil, et les dates écrites en mots, calculées pour n'importe quelle année.**

phil/almanac calcule des dates : Pâques occidentale et orthodoxe, les phases de la Lune, les équinoxes et les
solstices, les éclipses solaires et lunaires, le Nouvel An chinois, et les dates que les gens écrivent en mots, comme
*2nd monday of october*, *easter - 47 days* ou *saturday on or after 09-15*. Un `Almanac` réunit des définitions
nommées et donne les dates de n'importe quelle période, calculées à la demande et jamais stockées. Il ne connaît les
jours fériés d'aucun pays : une application nomme ses propres dates. Il donne aussi le lever, le coucher, le midi solaire
et les crépuscules civil, nautique et astronomique d'un lieu pour un jour.

La bibliothèque s'adresse à toute application PHP qui montre des jours fériés, des fêtes ou des événements célestes,
comme un calendrier communautaire ou la page des activités d'un club. Le ciel est calculé avec les algorithmes de Jean
Meeus, à la minute près, pour toute année de 1000 à 3000, et le Nouvel An chinois de 1001 à 2999. Aucune table n'est à
tenir à jour. Dans une application Symfony, tournez-vous vers `phil/almanac-bundle`, où les dates viennent de la
configuration ou de la base de données et s'affichent dans Twig. Pour un événement qui se répète simplement selon un
motif fixe, `phil/recurrence` convient mieux.

C'est une bibliothèque PHP simple qui dépend de `phil/recurrence` et de `ext-mbstring`. Chaque genre de date est une
`Rule` qui s'écrit elle-même en texte, que `RuleParser` relit. Une `Definition` donne à une règle une clé, un titre, une
durée et des étiquettes, et l'almanach transforme les définitions en `ComputedDate` pour une période.

## État

| Partie | État |
|---|---|
| Les équinoxes et les solstices, les phases de la Lune, la longitude du Soleil | Implémenté et testé |
| Pâques occidentale et orthodoxe | Implémenté et testé |
| Le Nouvel An chinois, mois intercalaires compris; l'animal et l'élément | Implémenté et testé |
| Les règles lues à partir de texte et réécrites; les décalages | Implémenté et testé |
| Les définitions, les durées et l'almanach d'une période | Implémenté et testé |
| Le lever, le coucher, le midi solaire et les trois crépuscules d'un lieu | Implémenté et testé |
| Les éclipses solaires et lunaires : genre, γ, magnitudes, phases | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis. |
| [Règles](doc/fr/rules.md) | Chaque règle, son texte et ses dates. |
| [L'almanach](doc/fr/almanac.md) | Les définitions, la durée d'une date, les dates d'une période. |
| [Astronomie et calendriers](doc/fr/astronomy.md) | Saisons, Lune, Soleil, lever et crépuscules, éclipses, Pâques, Nouvel An chinois : méthodes, plages, précision. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Comment la bibliothèque est testée. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque. |

## Démarrage rapide

```php
use Phil\Almanac\Almanac;
use Phil\Almanac\Definition;
use Phil\Almanac\Rule\RuleParser;

$almanac = new Almanac([
    new Definition('thanksgiving', 'Thanksgiving', RuleParser::parse('2nd monday of october')),
    new Definition('mardi-gras', 'Mardi Gras', RuleParser::parse('easter - 47 days')),
    new Definition('oktoberfest', 'Oktoberfest', RuleParser::parse('saturday on or after 09-15'), RuleParser::parse('sunday on or after 10-03')),
    new Definition('full-moon', 'Full moon', RuleParser::parse('full moon'), allDay: false),
], new DateTimeZone('America/Toronto'));

foreach ($almanac->between(new DateTimeImmutable('2026-09-01'), new DateTimeImmutable('2026-11-01')) as $date) {
    echo $date->definition->title, ' ', $date->span->start->format('Y-m-d H:i'), "\n";
}
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring`; `phil/recurrence` 0.1, pour ses jours de la semaine.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
