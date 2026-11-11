<!-- phil-dev source-sha256=ec71a26b3b85283ff0ec00a97761e540d9ce6a8042952f04bed9a0ff02824367 -->
# phil/upload-scan

**Chaque fichier téléversé passe par un antivirus avant que quiconque puisse l'ouvrir.**

phil/upload-scan analyse les fichiers qu'une application reçoit (manuscrits, images, pièces jointes) derrière un seul
port, `Scanner` : un fichier entre, un `ScanResult` sort. Le résultat est sain ; infecté, avec le nom de la signature
qui a correspondu ; ou une erreur, avec la raison pour laquelle le fichier n'a pas pu être analysé jusqu'au bout. Une
`ScanPolicy` en fait une décision : un fichier sain est accepté, un fichier infecté est rejeté, et un fichier qui n'a
pas pu être analysé est rejeté, ou accepté et signalé, selon le choix de l'application.

Il est fourni avec `ClamdScanner`, qui parle lui-même le protocole de clamd, le démon de ClamAV : le fichier est
transmis en flux par un socket Unix ou TCP, par morceaux (`zINSTREAM`). clamd peut ainsi s'exécuter dans un autre
conteneur et ne lit jamais le disque de l'application. L'analyseur borne ce qu'il envoie par une limite de taille, et
chaque lecture et chaque écriture par un délai d'attente. Quand clamd est arrêté, il répond par un résultat d'erreur au
lieu de lancer une exception. `NullScanner` et `FakeScanner`, qui trouve la chaîne de test EICAR, remplacent clamd dans
les tests.

C'est une bibliothèque PHP simple sans dépendance : aucun client ClamAV tiers, aucune extension
au-delà de celles que PHP construit par défaut. Un analyseur ne garde rien entre deux fichiers : une seule instance sert
donc chaque requête d'un *worker* de longue durée.

## État

| Partie | État |
|---|---|
| Le port `Scanner`, `ScanResult`, `ScanPolicy` et ses deux réponses à une erreur | Implémenté et testé |
| `ClamdScanner` : `INSTREAM` par morceaux sur un socket Unix ou TCP, limite de taille, délais d'attente, `PING`, `VERSION` | Implémenté et testé |
| Le lecteur des réponses de clamd, soumis au fuzzing | Implémenté et testé |
| `NullScanner` et `FakeScanner` (EICAR et aiguilles au choix du test) | Implémenté et testé |
| Les sessions (`IDSESSION`) et plusieurs fichiers sur une même connexion | Pas commencé : une connexion par fichier garde l'analyseur sans état |
| Un bundle Symfony : configuration, une contrainte `#[CleanFile]`, quarantaine, événements, nouvelles analyses, intégrations | Implémenté dans `phil/upload-scan-bundle` |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, construction d'un analyseur. |
| [Analyse](doc/fr/scanning.md) | Le port, les résultats, la politique et ses décisions. |
| [clamd](doc/fr/clamd.md) | `ClamdScanner`, le protocole qu'il parle, la limite de taille, les délais d'attente, la sonde. |
| [Points d'extension](doc/fr/extension-points.md) | Un autre antivirus derrière `Scanner`, une autre façon de joindre clamd. |
| [Tests](doc/fr/testing.md) | Les tests, la cible de fuzzing et le banc d'essai de la bibliothèque ; `NullScanner` et `FakeScanner` dans les tests de l'application. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/upload-scan
```

```php
use Phil\UploadScan\Clamd\ClamdScanner;
use Phil\UploadScan\OnScanError;
use Phil\UploadScan\ScanPolicy;

$scanner = ClamdScanner::at('tcp://clamav:3310');
$policy = new ScanPolicy(OnScanError::Reject);

$result = $scanner->scanPath($uploadedFile);

if (!$policy->decide($result)->keeps()) {
    // refused: $result->signature names the threat, or $result->reason why it was not scanned
}
```

## Prérequis

PHP 8.5 ou plus récent ; clamd, pour `ClamdScanner`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
