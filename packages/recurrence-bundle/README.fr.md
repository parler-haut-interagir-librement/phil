<!-- phil-dev source-sha256=c6a52fda21b927a01d901c11e8cfa8641b6e1a7e2c8e72e20d071b12d9969a59 -->
# phil/recurrence-bundle

**Un formulaire pour les événements qui se répètent, une colonne pour enregistrer la règle, et la règle en mots.**

phil/recurrence-bundle intègre `phil/recurrence` à une application Symfony. `RecurrenceRuleType` est un formulaire pour
les règles que les gens fixent : tous les tant de jours, de semaines, de mois ou d'années, certains jours de la
semaine, un jour du mois ou le énième jour de la semaine, certains mois, jusqu'à une date ou pour un nombre de fois. Le
type de colonne `phil_rrule` enregistre une règle avec Doctrine. Dans Twig, `rrule_text` écrit une règle en mots, comme
*Tous les mois le deuxième mardi*, `rrule_occurrences` liste ses dates, et `rrule_schema` la donne en données
schema.org pour le JSON-LD. Les libellés et les phrases existent en anglais et en français.

Il s'adresse à une application où un rédacteur ou un organisateur planifie des événements récurrents par un formulaire
et où les visiteurs lisent quand ils ont lieu. Le formulaire contient les règles que les gens fixent vraiment. Une règle
qui dépasse ses champs, comme une règle avec des positions dans l'ensemble, se lit tout de même dans la bibliothèque. Le
formulaire en montre ce qu'il peut en contenir, et la rend entière quand il est soumis sans changement. Tournez-vous
vers `phil/recurrence` seul hors de Symfony, ou quand aucun formulaire ni stockage n'est nécessaire.
`phil/calendar-bundle` construit un calendrier complet sur ce bundle.

Le formulaire se branche sur Symfony Form par un transformateur de données vers un `RecurrenceRule` et à partir de
celui-ci. Un petit contrôleur Stimulus montre les rangées de la fréquence et de la fin choisies, et désactive les autres
pour qu'elles ne soient pas envoyées. Les phrases sont des messages ICU du domaine `phil_recurrence`, et ICU nomme les
jours et les mois dans la langue du lecteur.

## État

| Partie | État |
|---|---|
| `RecurrenceRuleType` et le contrôleur Stimulus `recurrence` | Implémenté et testé |
| `RuleDescription` : règles en mots, messages ICU | Implémenté et testé |
| Le type de colonne `phil_rrule` | Implémenté et testé |
| Twig : le filtre `rrule_text`, les fonctions `rrule_occurrences` et `rrule_schema` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et enregistrement. |
| [Formulaires](doc/fr/forms.md) | `RecurrenceRuleType`, ce qu'il contient, son contrôleur Stimulus. |
| [Mots, Twig et stockage](doc/fr/words-twig-storage.md) | `RuleDescription`, le filtre et les fonctions Twig, `phil_rrule`, schema.org. |
| [Points d'extension](doc/fr/extension-points.md) | Ce que l'application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```sh
composer require phil/recurrence-bundle
```

```php
#[ORM\Column(type: 'phil_rrule', nullable: true)]
private ?RecurrenceRule $rule = null;

$builder->add('rule', RecurrenceRuleType::class, ['required' => false]);
```

```twig
{{ event.rule|rrule_text }}
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-intl` ; `phil/recurrence` 0.1 ; l'injection de dépendances, les formulaires et
l'OptionsResolver de Symfony 8.2, ainsi que les contrats de traduction, avec un traducteur qui lit les messages ICU.
Doctrine DBAL et Twig sont facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
