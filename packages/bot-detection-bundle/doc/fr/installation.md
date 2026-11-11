<!-- phil-dev source-sha256=12696d90841d698d5f86cc668e7d8060fb320b2b37b3e3a413d81ea162c3fc6f -->
# Installation

## Prérequis

| Prérequis | Contrainte | Raison |
|---|---|---|
| PHP | `>=8.5` | Le code et ses tests utilisent la syntaxe de PHP 8.5 (`new Foo()->bar()` sans parenthèses). |
| `symfony/dependency-injection` | `^8.2` | `PhilBotDetectionBundle` étend `AbstractBundle`. |
| `symfony/config` | `^8.2` | Importé nulle part, mais `AbstractBundle` implémente une interface de `symfony/config`, que `symfony/dependency-injection` n'exige qu'en développement. Sans lui, la classe du bundle ne peut pas se charger. |

Rien d'autre : aucune extension PHP au-delà de celles par défaut, aucun composant HTTP, de cache ou de métriques.
L'adaptateur de compteur qu'écrit l'application apporte ses propres dépendances
([extension-points.md](extension-points.md)).

## Installer

Le bundle est en `0.1.x-dev` (`dev-main` est un alias de `0.1.x-dev`) et n'est pas encore publié sur Packagist. D'ici
là, déclarez un dépôt `path` vers une copie de travail locale, ou un dépôt `vcs` :

```json
{
    "repositories": [
        { "type": "path", "url": "../phil-bundles/packages/bot-detection-bundle" }
    ]
}
```

```bash
composer require phil/bot-detection-bundle:^0.1@dev
```

Les réglages `"minimum-stability": "dev"` et `"prefer-stable": true` du bundle ne s'appliquent que lorsqu'il est le
paquet racine (pour exécuter ses tests seul) ; une application a besoin de `@dev` sur la contrainte, comme ci-dessus, ou
des mêmes réglages de stabilité.

## Enregistrer le bundle

```php
// config/bundles.php
return [
    // ...
    Phil\BotDetectionBundle\PhilBotDetectionBundle::class => ['all' => true],
];
```

**L'enregistrer ne change rien aujourd'hui** : le bundle ne déclare aucun service, aucun paramètre ni aucune clé de
configuration ([configuration.md](configuration.md)). Ses classes fonctionnent par le seul chargeur automatique de
Composer, et son contrôleur Stimulus `verdict` est trouvé par StimulusBundle grâce à `assets/package.json`, que le
bundle soit enregistré ou non. L'enregistrer réserve l'alias de configuration `phil_bot_detection` pour les versions
futures.

Un bundle qui ne fait que réutiliser ses types — `phil/analytics-bundle` utilise `Verdict` et `RobotVerdictCounter` —
n'a pas besoin non plus qu'il soit enregistré.

## La suite

1. Lisez [verdict.md](verdict.md) et [friction.md](friction.md) pour savoir ce qu'est un verdict et ce qu'il peut faire.
2. Écrivez un adaptateur `RobotVerdictCounter` par surface ([extension-points.md](extension-points.md)).
3. Branchez `VerdictHandler` sur chaque surface qui reçoit un verdict ([verdict-handling.md](verdict-handling.md)).
4. Placez le contrôleur `verdict` sur les pages et les formulaires qui envoient un verdict
   ([browser-signals.md](browser-signals.md#placer-le-contrôleur)). Une requête venue d'une surface où il n'est pas
   placé, ou d'un navigateur qui bloque les scripts, ne porte aucun verdict et vaut `Verdict::Unknown` : choisissez la
   politique de friction en conséquence.
