<!-- phil-dev source-sha256=b14f0b7386baae6e2662113d013f94fad4f7846bca069dbc0f620d761c722234 -->
# phil/participant-safety-info

**Garde chiffrés les contacts d'urgence, les allergies et les besoins d'un participant à un événement, lisibles seulement par ses responsables, chaque lecture journalisée.**

phil/participant-safety-info garde ce qu'un participant dit aux organisateurs d'un événement pour que tout se passe en
sécurité : qui appeler en cas d'urgence, les allergies et quoi faire à leur sujet, les besoins pendant l'événement. Le
participant soumet, lit, copie vers un autre événement et efface les siens. Les responsables de l'événement les lisent
pendant sa fenêtre d'accès, de quelques jours avant l'événement jusqu'à sa fin ; un membre de l'équipe ne les lit qu'en
brisant la glace, avec une raison écrite dont les responsables sont avisés. Toute lecture par quelqu'un d'autre est
écrite dans un journal avant que l'information soit ouverte, et le participant voit qui l'a lue. Une fois la fenêtre
fermée, l'information est purgée.

L'information est scellée par le chiffrement d'enveloppe du composant KeyManagement de Symfony : une clé de données
neuve par enregistrement, enveloppée par une clé maîtresse que détient le KMS, l'événement et le participant étant
authentifiés avec le texte, de sorte qu'un enregistrement déplacé vers une autre ligne ne s'ouvre pas. Le magasin ne
voit jamais un texte en clair.

C'est une bibliothèque PHP simple. Le magasin, les rôles, les fenêtres et le journal sont des ports que l'application
implémente. Elle ne garde que ce qui sert à la sécurité pendant l'événement : aucun champ de diagnostic, aucune liste de
médicaments.

## État

| Partie | État |
|---|---|
| L'information de sécurité : jusqu'à trois contacts d'urgence, allergies, besoins, notes, bornés | Implémenté et testé |
| Ce qui revient au participant : soumettre, lire, copier vers un autre événement, effacer, voir qui l'a lue | Implémenté et testé |
| Lecture par les responsables pendant la fenêtre d'accès, journalisée d'abord | Implémenté et testé |
| Bris de glace par l'équipe, avec une raison, journalisé et annoncé | Implémenté et testé |
| Scellement par KeyManagement de Symfony, lié à l'événement et au participant | Implémenté et testé |
| La purge une fois la fenêtre fermée | Implémenté et testé |
| Le magasin, le journal, les rôles et les fenêtres sur une base de données | Ports seulement ; implémentations en mémoire pour les tests |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et la construction du coffre. |
| [Règles d'accès](doc/fr/access-rules.md) | Qui lit quoi, quand, et ce que chaque chemin journalise et annonce. |
| [Chiffrement](doc/fr/encryption.md) | Comment un enregistrement est scellé, ce qui le lie à sa ligne, la clé maîtresse et sa rotation. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports qu'une application implémente, et quand ils sont stables. |
| [Tests](doc/fr/testing.md) | Les tests du paquet et sa cible de fuzzing. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/participant-safety-info
```

```php
use Phil\ParticipantSafetyInfo\EmergencyContact;
use Phil\ParticipantSafetyInfo\SafetyInfo;

$vault->submit('event-42', 'member-7', new SafetyInfo(
    [new EmergencyContact('Alex Tremblay', '+1 514 555-0199', 'sibling')],
    allergies: 'Peanuts: epinephrine pen in the left pocket.',
));

$info = $vault->read('event-42', 'member-7', 'organizer-3'); // a responsible person, during the window
```

## Prérequis

PHP 8.5 ou une version plus récente avec `mbstring` et `sodium` ; `symfony/key-management` 8.2, `psr/clock` et
`psr/event-dispatcher`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
