<!-- phil-dev source-sha256=0ca0bfa8dc49aa70fb48b1de5f4594a871977391784f54b27e75037ac042551e -->
# Protéger les commandes

## Interdire un environnement

`#[ForbidEnvironment]` nomme les environnements du noyau où une commande refuse de s'exécuter :

```php
#[AsCommand('app:database:reset')]
#[ForbidEnvironment('prod', 'staging')]
final class ResetDatabase
{
    public function __invoke(): int
}
```

En `prod`, la commande ne s'exécute pas : la console affiche `The command "app:database:reset" cannot run in the "prod"
environment.` et se termine avec le code 77. Aucune option ne lève le refus, pas même `--force-environment`.

## Demander une confirmation

`#[ConfirmEnvironment]` demande le nom de l'environnement avant l'exécution de la commande :

```php
#[AsCommand('app:customers:anonymize')]
#[ConfirmEnvironment('prod')]
final class AnonymizeCustomers
```

```text
The command "app:customers:anonymize" runs in the "prod" environment. Type "prod" to continue: prod
```

| Situation | Résultat |
|---|---|
| La réponse est le nom de l'environnement, sans compter les espaces autour | La commande s'exécute |
| Une autre réponse, une réponse vide ou la fin de l'entrée | Refus, code de sortie 77 |
| Une exécution non interactive (`--no-interaction`, un cron, une tâche d'intégration continue) | Refus, code de sortie 77 |
| `--force-environment` | La commande s'exécute sans rien demander ; une ligne sur la sortie d'erreur l'indique |

Sans environnement, `#[ConfirmEnvironment]` demande une confirmation dans tous les environnements. La question et l'avis
sont écrits sur la sortie d'erreur, afin que la sortie standard reste celle de la commande.

## Où les attributs sont lus

Les deux attributs sont répétables, et les environnements de chaque occurrence s'additionnent. Ils sont lus :

- sur la classe d'une commande invocable et sur sa méthode `__invoke()` ;
- sur la classe d'une commande de niveau méthode et sur sa méthode ;
- sur la classe d'une commande qui étend `Command` et sur sa méthode `__invoke()` lorsqu'elle en a une.

```php
#[ConfirmEnvironment('prod')]
final class Operations
{
    #[AsCommand('app:cache:rebuild')]
    public function rebuild(): int

    #[AsCommand('app:data:wipe')]
    #[ForbidEnvironment('prod')]
    public function wipe(): int
}
```

Une commande paresseuse est chargée pour lire ses attributs, ce qui n'arrive que lorsqu'elle s'exécute ou qu'elle est
énumérée.

## Les gardes et les autres écouteurs

Les gardes s'exécutent dans l'écouteur de `console.command` du bundle. Un refus est levé sous la forme d'un
`EnvironmentRefused` : la console en produit le rendu comme une erreur, répartit `console.error` et `console.terminate`,
et se termine avec son code, 77 (`EX_NOPERM` de `sysexits.h`).

## Énumérer les commandes protégées

```sh
bin/console lint:console-guards
bin/console lint:console-guards --format=json
```

```text
 ------------------------- -------------- -----------------
  Command                   Forbidden in   Confirmation in
 ------------------------- -------------- -----------------
  app:customers:anonymize                  prod
  app:database:reset        prod, staging
 ------------------------- -------------- -----------------
```

Une confirmation dans tous les environnements est affichée `*`. La commande échoue, avec le code 1, lorsqu'une commande
demande une confirmation dans un environnement où elle est interdite : l'un des deux attributs est alors erroné.
