<!-- phil-dev source-sha256=3a8887d37148f7f1910ec14ff20dcdf7a2297b0b2d43139e89ed7536b061450d -->
# Configuration

## Le bundle n'a pas encore de configuration

`PhilBotDetectionBundle` étend `AbstractBundle` de Symfony, fixe l'alias de son extension à `phil_bot_detection`, et ne
redéfinit ni `configure()` ni `loadExtension()` :

| Aspect | Aujourd'hui |
|---|---|
| Alias de configuration | `phil_bot_detection` |
| Clés de configuration | Aucune. L'arbre de configuration est vide. |
| Services déclarés | Aucun. Charger l'extension laisse inchangés les identifiants de service du conteneur (le test du bundle le vérifie). |
| Paramètres, étiquettes, passes de compilation | Aucun. |
| Ressources (script du navigateur) | Le contrôleur Stimulus `verdict`, déclaré dans `assets/package.json` et trouvé par StimulusBundle ([browser-signals.md](browser-signals.md)) ; aucune fonction Twig ni ressource propre. |

Les seules formes acceptées sont l'absence de clé, ou une clé vide :

```yaml
# config/packages/phil_bot_detection.yaml — optional; the tree is empty
phil_bot_detection: ~
```

### Erreurs à la construction

Toute clé sous `phil_bot_detection` fait échouer la construction du conteneur
(`Symfony\Component\Config\Definition\Exception\InvalidConfigurationException`) :

```text
Unrecognized option "foo" under "phil_bot_detection". Available options are "".
```

## Ce que l'application branche elle-même

L'application enregistre un `VerdictHandler` par surface qui reçoit un verdict, chacun avec sa politique de friction et
son propre compteur. `FrictionPolicy` se construit par un constructeur nommé : on la crée donc avec une fabrique :

```yaml
# config/services.yaml (application)
services:
    app.bot.policy.robot_only:
        class: Phil\BotDetectionBundle\Domain\Friction\FrictionPolicy
        factory: ['Phil\BotDetectionBundle\Domain\Friction\FrictionPolicy', 'robotOnly']

    app.bot.policy.robot_or_unknown:
        class: Phil\BotDetectionBundle\Domain\Friction\FrictionPolicy
        factory: ['Phil\BotDetectionBundle\Domain\Friction\FrictionPolicy', 'robotOrUnknown']

    # One counter per surface: increment() takes no argument, so the split is in the wiring
    app.bot.counter.sign_up:
        class: App\Bot\CacheRobotVerdictCounter
        arguments: ['bot.robot_verdicts.sign_up']
    app.bot.counter.payment:
        class: App\Bot\CacheRobotVerdictCounter
        arguments: ['bot.robot_verdicts.payment']

    app.bot.handler.sign_up:
        class: Phil\BotDetectionBundle\Application\VerdictHandler
        arguments: ['@app.bot.policy.robot_or_unknown', '@app.bot.counter.sign_up']
    app.bot.handler.payment:
        class: Phil\BotDetectionBundle\Application\VerdictHandler
        arguments: ['@app.bot.policy.robot_only', '@app.bot.counter.payment']
```

### Les décisions qui reviennent à l'application

| Décision | Où elle se prend |
|---|---|
| Si un verdict absent ou mal formé ajoute de la friction, surface par surface | `FrictionPolicy::robotOnly()` ou `FrictionPolicy::robotOrUnknown()` ([friction.md](friction.md)) |
| Ce que signifie une étape de friction sur chaque surface, et le limiteur qui l'absorbe | Le code de l'application qui lit la `Friction` retournée |
| L'adaptateur de compteur par surface, sa fenêtre de temps, l'endroit où le compte est rapporté | Les adaptateurs de `RobotVerdictCounter` ([extension-points.md](extension-points.md)) |
| Le nom du champ de requête qui porte le verdict | Le code de l'application qui appelle `Verdict::fromSubmitted()` |
| Placer le contrôleur `verdict` sur les pages et les formulaires de chaque surface, le servir depuis l'origine de l'application et l'inscrire à l'inventaire des scripts de chaque page | L'application ([browser-signals.md](browser-signals.md#placer-le-contrôleur)) |
| La divulgation dans la politique de confidentialité, et la position juridique sur les signaux du navigateur dans les juridictions desservies | L'application |
