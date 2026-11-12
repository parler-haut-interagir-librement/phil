<!-- phil-dev source-sha256=c899d0f5b4f7ceb52223d55696c061adca6d80abb11ef9e502ec548dcdc1e288 -->
# Points d'extension

Deux contrats, sous `Phil\BrokenFlowAlerts\Contract`, laissent à l'application ce qu'elle seule sait : où les
aboutissements sont comptés, et comment une alerte atteint quelqu'un.

| Contrat | Méthode | Sert à | Adaptateur dans la bibliothèque |
|---|---|---|---|
| `CompletionHistory` | `dailyCompletions(Flow, DateTimeImmutable, int): list<int>` | Les aboutissements d'un parcours, par jour UTC | aucun |
| `AlertSender` | `send(FlowAlert): void` | Prévenir quelqu'un | `Notifier\NotifierAlertSender` |

## `CompletionHistory`

```php
interface CompletionHistory
{
    /** @return list<int> one count per UTC day, oldest first, exactly $days of them */
    public function dailyCompletions(Flow $flow, DateTimeImmutable $firstDay, int $days): array;
}
```

Invariants :

- **Exactement `$days` comptes**, le premier pour le jour UTC de `$firstDay`. Un jour sans aboutissement est un `0`,
  jamais une entrée manquante : la surveillance refuse un historique d'une autre longueur.
- **Des nombres entiers à partir de zéro.** La règle refuse un compte négatif.
- **Lecture seule.** L'historique lit des compteurs que tient l'application ; il ne les écrit jamais, et ne lit rien d'un
  visiteur.

Un adaptateur sur des commandes, dans le code de l'application avec Doctrine DBAL :

```php
final readonly class OrderCompletionHistory implements CompletionHistory
{
    public function __construct(private Connection $connection) {}

    public function dailyCompletions(Flow $flow, DateTimeImmutable $firstDay, int $days): array
    {
        $last = $firstDay->modify(sprintf('+%d days', $days));
        $perDay = $this->connection->fetchAllKeyValue(
            'SELECT DATE(paid_at), COUNT(*) FROM orders WHERE channel = ? AND paid_at >= ? AND paid_at < ? GROUP BY DATE(paid_at)',
            [$flow->id, $firstDay->format('Y-m-d'), $last->format('Y-m-d')],
        );

        $counts = [];

        for ($i = 0; $i < $days; ++$i) {
            $counts[] = (int) ($perDay[$firstDay->modify(sprintf('+%d days', $i))->format('Y-m-d')] ?? 0);
        }

        return $counts;
    }
}
```

Son `paid_at` est stocké en UTC, pour qu'un jour de la requête soit un jour UTC. `phil/analytics-bundle` fournit un
adaptateur sur ses compteurs d'objectifs, `GoalCompletionHistory`.

## `AlertSender`

```php
interface AlertSender
{
    public function send(FlowAlert $alert): void;
}
```

`Notifier\NotifierAlertSender` est celui de la bibliothèque ([watch.md](watch.md#notifications)). Un adaptateur de
l'application peut traduire le texte, choisir des destinataires par parcours, ou ouvrir un incident :

```php
final readonly class TranslatedAlertSender implements AlertSender
{
    public function __construct(private NotifierInterface $notifier, private TranslatorInterface $translator) {}

    public function send(FlowAlert $alert): void
    {
        $this->notifier->send(new Notification($this->translator->trans('flow.'.$alert->kind->value, [
            '%flow%' => $alert->flow->label,
            '%since%' => $alert->since,
        ])));
    }
}
```

Une exception lancée par un expéditeur arrête le passage : les parcours qui le suivent ne sont pas vérifiés. Un
expéditeur qui ne doit pas arrêter les autres attrape ses propres échecs.
