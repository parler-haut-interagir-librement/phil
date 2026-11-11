<!-- phil-dev source-sha256=adc23e79a390869d24033064e24e116e00e302b699decea9bca446f2b66b3761 -->
# phil/checkin

**L'enregistrement à l'entrée d'un événement ou d'un lieu : un code lu à une porte, décidé aussitôt, enregistré une
fois, en ligne ou hors ligne.**

phil/checkin est le noyau que partagent tous les genres d'enregistrement. Un appareil du personnel lit un code à un
point de contrôle — une porte, une barrière, une salle, une séance d'un programme — ; le code est résolu en son titre
d'accès — un billet, une inscription, une carte de membre, un insigne — ; une politique d'admission décide, d'après la
fréquentation du titre, si son détenteur entre ; et la décision est inscrite dans un registre que partagent tous les
appareils, refus compris, sous l'identifiant propre à la lecture, de sorte qu'une lecture envoyée deux fois n'est
inscrite qu'une fois.

Il s'adresse à toute application qui fait entrer des gens : un congrès qui vérifie les billets à plusieurs portes, un
club qui compte les visites de ses membres, un cours qui prend les présences, un lieu qui laisse sortir et rentrer les
gens. Ce qu'est un titre d'accès appartient à l'application, par un port ; la façon dont un code est porté — imprimé,
tapé, dans un code QR, signé — appartient aux paquets qui étendent le noyau.

Les appareils continuent d'enregistrer les entrées quand le réseau tombe. Un manifeste hors ligne donne à un appareil
les titres d'accès de son point de contrôle sous des empreintes à clé de leurs codes, jamais les codes eux-mêmes.
L'appareil décide avec la même politique, tient un journal et le renvoie une fois en ligne, où chaque lecture est
décidée de nouveau, inscrite avec la décision que le détenteur a reçue à la porte, et signalée comme conflit quand le
registre décide autrement.

C'est une bibliothèque PHP simple dont les seuls prérequis sont `psr/clock` et l'extension sodium.

## État

| Partie | État |
|---|---|
| Identifiants, codes et leurs empreintes, titres d'accès, fréquentation, enregistrements | Implémenté et testé |
| La politique d'admission : ouverture avec une fenêtre anticipée, fermeture, entrées, anti-retour, sortie et nouvelle entrée | Implémenté et testé |
| `CheckInDesk` : résoudre, décider, inscrire une fois par identifiant de lecture, décider de nouveau après une course | Implémenté et testé |
| Manifestes hors ligne d'empreintes à clé, lecture des journaux, synchronisation avec conflits | Implémenté et testé |
| Un registre en mémoire pour les tests | Implémenté et testé |
| Un bundle Symfony : un registre Doctrine, les points de terminaison, le contrôleur Stimulus du guichet avec son journal hors ligne | Implémenté dans `phil/checkin-bundle` |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, la construction du guichet. |
| [Enregistrer les entrées](doc/fr/checking-in.md) | Les codes, les titres d'accès, la politique d'admission, la fréquentation, le guichet et ses courses. |
| [Hors ligne](doc/fr/offline.md) | Les manifestes, les empreintes, les journaux, la synchronisation et ses conflits. |
| [Points d'extension](doc/fr/extension-points.md) | Les résolveurs, le registre et le catalogue de l'application. |
| [Exceptions](doc/fr/exceptions.md) | `CheckinException` et ses exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, le seuil de mutation, la cible de fuzzing et le banc d'essai, et comment tester un enregistrement. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/checkin
```

```php
use Phil\Checkin\AdmissionPolicy;
use Phil\Checkin\CheckInDesk;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\CredentialCode;
use Phil\Checkin\DeviceId;
use Phil\Checkin\ScanOutcome;

$desk = new CheckInDesk($tickets, $ledger, $clock, new AdmissionPolicy(earlySeconds: 1800));

$result = $desk->scan(CredentialCode::of($scanned), CheckpointId::of('main-door'), DeviceId::of('door-tablet-2'));

if (ScanOutcome::Admitted === $result->outcome) {
    // green light, and $result->credential->label for the staff
}
```

`$tickets` est le `CredentialResolver` de l'application et `$ledger` son `CheckInLedger` : voir [Points
d'extension](doc/fr/extension-points.md).

## Prérequis

PHP 8.5 ou plus récent, l'extension sodium et `psr/clock`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
