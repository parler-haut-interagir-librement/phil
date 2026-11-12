<!-- phil-dev source-sha256=108883af73da11dbbe7664c9450aeda6ed841972d6260ad37c1ab5cebd62f086 -->
# Tests

## Comment la bibliothèque est testée

| Test | Couvre |
|---|---|
| `tests/Unit/FlowTest.php` | Les identifiants et libellés acceptés et refusés |
| `tests/Unit/Internal/PoissonTailTest.php` | Les queues exactes, une moyenne de mille sans annulation à zéro, la borne de Chernoff au-delà de 10 000 |
| `tests/Unit/BrokenFlowRuleTest.php` | Les parcours achalandés, rares et silencieux, les baisses, le rythme par jour de la semaine, l'étendue la plus calme, le seuil de signification à sa limite, les réglages et historiques refusés |
| `tests/Unit/BrokenFlowWatchTest.php` | Le dernier jour UTC entier, l'alerte le jour où un parcours s'interrompt et pas après, le rétablissement, un silence devenu le rythme, l'ordre des parcours |
| `tests/Unit/Notifier/NotifierAlertSenderTest.php` | Le sujet, le contenu et l'importance de chaque alerte, avec et sans destinataires |

Chaque classe de test déclare `#[CoversClass]` pour les types qu'elle couvre. Les tests n'utilisent ni conteneur, ni base
de données, ni réseau ; l'horloge est une `MockClock`.

## Lancer les tests

Depuis la racine du monodépôt PHIL :

```bash
vendor/bin/phpunit packages/broken-flow-alerts/tests
```

Installée seule, comme la voit un projet qui la requiert :

```bash
composer install
vendor/bin/phpunit --no-configuration tests
```

## Tester un adaptateur

Un `CompletionHistory` se teste contre les compteurs qu'il lit : un jour sans aboutissement est un `0`, le premier compte
est le jour de `$firstDay`, et la longueur est exactement `$days`. Une surveillance sur un historique scénarisé et un
expéditeur qui enregistre teste ce que l'application fait des alertes :

```php
$history = new class implements CompletionHistory {
    public function dailyCompletions(Flow $flow, DateTimeImmutable $firstDay, int $days): array
    {
        return [...array_fill(0, $days - 1, 20), 0];             // twenty a day, then none
    }
};

$alerts = new BrokenFlowWatch($history, $sender, new MockClock('2026-11-12 06:00 UTC'))->check([Flow::of('checkout')]);
// one Broken alert for 2026-11-11
```
