<!-- phil-dev source-sha256=b63a77a451d327f7622fbe0ea3fbdafccc69330ac92e320ac7d0ac1d0d69e550 -->
# phil/console-bundle

**Des commandes de console Symfony plus sûres**

phil/console-bundle protège les commandes qui ne doivent pas s'exécuter n'importe où, ou pas sans réfléchir.
`#[ForbidEnvironment('prod')]` refuse une commande dans les environnements du noyau indiqués.
`#[ConfirmEnvironment('prod')]` demande le nom de l'environnement avant l'exécution et refuse une exécution non
interactive, sauf si `--force-environment` est fourni. Chaque commande reçoit `--timestamps`, qui place l'heure devant
chaque ligne qu'elle écrit, et, avec Monolog, `--debug-phil`, qui affiche les journaux de débogage des canaux choisis.
Un sommaire de la durée, de la mémoire et du code de sortie est écrit à la fin de la commande, et `lint:console-guards`
énumère les commandes protégées. Rien n'est conservé entre deux commandes : un processus de longue durée exécute chacune
à neuf.

## État

| Partie | État |
|---|---|
| `#[ForbidEnvironment]` et `#[ConfirmEnvironment]` sur les classes, `__invoke()` et les commandes de niveau méthode | Implémenté et testé |
| `--force-environment` pour les exécutions non interactives, code de sortie 77 en cas de refus | Implémenté et testé |
| `--timestamps` en ISO 8601 ou relatif au départ, ou par configuration | Implémenté et testé |
| `--debug-phil` avec les canaux Monolog | Implémenté et testé |
| Le sommaire de la durée et de la mémoire | Implémenté et testé |
| `lint:console-guards` en tableau ou en JSON | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, le bundle, le mode *worker*. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Protéger les commandes](doc/fr/guards.md) | Les deux attributs, la confirmation, le code de sortie, la commande de vérification. |
| [Sortie](doc/fr/output.md) | Les horodatages, les journaux de débogage, le sommaire. |
| [Points d'extension](doc/fr/extension-points.md) | L'interrupteur de débogage, la jauge de mémoire, l'horloge. |
| [Tests](doc/fr/testing.md) | Les tests du bundle et son banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque classe du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Phil\ConsoleBundle\PhilConsoleBundle::class => ['all' => true],
```

```php
use Phil\ConsoleBundle\Domain\Attribute\ConfirmEnvironment;
use Phil\ConsoleBundle\Domain\Attribute\ForbidEnvironment;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('app:database:reset')]
#[ForbidEnvironment('prod', 'staging')]
final class ResetDatabase
{
    public function __invoke(): int
}

#[AsCommand('app:customers:anonymize')]
final class AnonymizeCustomers
{
    #[ConfirmEnvironment('prod')]
    public function __invoke(): int
}
```

```sh
bin/console app:customers:anonymize --timestamps=relative --debug-phil=doctrine -v
bin/console app:customers:anonymize --no-interaction --force-environment
bin/console lint:console-guards
```

## Prérequis

PHP 8.5 ou plus récent et Symfony 8.2 ou plus récent ; Monolog pour `--debug-phil`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
