<!-- phil-dev source-sha256=8bcad523355412a1bc133b9bd9918a41f9a8a459d92530848d5cee6bdbe6e678 -->
# Guide d'adoption

Ce guide fait passer à l'outbox une bibliothèque qui écrit son état puis répartit un événement PSR-14.

## Avant : l'événement perdu après l'écriture

```php
public function complete(Holder $holder, Set $set): void
{
    $this->completions->add($holder, $set);                         // committed
    $this->events->dispatch(new SetCompleted($holder->id, $set));   // may never run
}
```

Quand le répartiteur ou un écouteur lance une exception, ou que le processus s'arrête, après l'écriture, l'achèvement
est conservé et rien n'informe de nouveau les écouteurs. Une reprise propre à chaque bibliothèque — un indicateur
« livré », une nouvelle tentative planifiée, son propre recul — règle une bibliothèque et laisse les autres.

## Après : l'événement enregistré avec l'écriture

```php
public function __construct(
    private CompletionStore $completions,
    private Outbox $outbox,
    private Transactions $transactions,
) {}

public function complete(Holder $holder, Set $set): void
{
    $this->transactions->run(function () use ($holder, $set): void {
        $this->completions->add($holder, $set);
        $this->outbox->record(
            'sets.set_completed',
            ['set' => $set->code, 'holder' => $holder->id],
            orderingKey: 'holder:'.$holder->id,
            idempotencyKey: 'completion:'.$set->code.':'.$holder->id,
        );
    });
}
```

Et un codec reconstruit l'événement pour le relais :

```php
final readonly class SetEventsCodec implements EventCodec
{
    public function types(): array
    {
        return ['sets.set_completed'];
    }

    public function decode(OutboxRecord $record, Payload $payload): SetCompleted
    {
        return new SetCompleted($record->id->value, $payload->int('holder'), $payload->string('set'));
    }
}
```

## Les étapes

1. **Prendre le port.** La bibliothèque prend `Phil\Outbox\Contract\Outbox` au lieu du répartiteur PSR-14 pour les
   événements qui suivent une écriture. Elle garde le répartiteur pour les événements qui ne suivent rien de persisté.
2. **Enregistrer dans la transaction.** L'enregistrement va dans la transaction de l'écriture, sur la même connexion :
   avec phil/outbox-bundle, la table de l'outbox est sur la connexion des tables propres à la bibliothèque.
3. **Nommer le type.** Un type par événement, stable, préfixé par la bibliothèque : `sets.set_completed`.
4. **Conserver des identifiants.** La charge utile contient ce qui nomme l'événement — l'ensemble, le titulaire —,
   jamais les données que l'événement porte, que le codec relit, ni des renseignements personnels.
5. **Choisir les clés.** Une clé d'ordre quand les écouteurs ont besoin des événements d'un agrégat dans l'ordre ; une
   clé d'idempotence quand l'écriture elle-même peut s'exécuter deux fois. Une clé se construit avec des identifiants,
   comme la charge utile, jamais avec des renseignements personnels.
6. **Fournir un codec.** La bibliothèque fournit le codec de ses types ; un bundle l'enregistre, avec l'étiquette
   `phil_outbox.codec` ou par configuration automatique.
7. **Faire porter à l'événement l'identifiant de l'enregistrement**, et dire aux écouteurs, dans la documentation de la
   bibliothèque, qu'ils sont idempotents : le relais livre au moins une fois.
8. **Retirer la reprise propre à la bibliothèque** : l'indicateur de livraison, la nouvelle tentative planifiée et le
   recul de la bibliothèque disparaissent, ceux du relais les remplacent.

## Un exemple complet : les achèvements d'une collection d'ensembles

Une bibliothèque d'ensembles à collectionner informe ses écouteurs quand un titulaire complète un ensemble, afin
qu'une récompense soit accordée. Elle marquait auparavant un achèvement livré une fois son événement réparti sans
exception, conservait les titulaires dont la livraison avait échoué, et les réessayait selon un horaire avec un recul
qui lui était propre ; un entrepôt sans répartiteur marquait l'achèvement livré sans que personne soit informé.

Avec l'outbox, enregistrer un achèvement et son enregistrement forment une seule transaction : l'entrepôt des
achèvements et la table de l'outbox partagent la connexion de la bibliothèque. Le type d'événement est
`sets.set_completed`, sa charge utile le code et la version de l'ensemble et la référence du titulaire ; la clé
d'ordre est le titulaire, de sorte que les paliers d'un titulaire sont communiqués dans l'ordre où ils ont été
atteints ; la clé d'idempotence est l'achèvement, de sorte qu'un achèvement vérifié deux fois est communiqué une seule
fois. Le codec de la bibliothèque relit l'achèvement par ces identifiants et reconstruit l'événement `SetCompleted`,
avec l'identifiant de l'enregistrement, que l'écouteur de récompense conserve pour n'accorder une récompense qu'une
fois. Les livraisons en attente, leur recul et le rapport des livraisons qui échouent sans cesse reviennent au relais :
les enregistrements morts et leur dernière erreur remplacent le rapport, et un exploitant les essaie de nouveau une
fois l'écouteur corrigé.
