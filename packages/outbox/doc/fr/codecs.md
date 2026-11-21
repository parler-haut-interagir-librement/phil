<!-- phil-dev source-sha256=b356e5fe44a81f1e94840c99b289c069ccb42765fa36a4edb1ecdd5cfd9cbc32 -->
# Codecs

Un codec reconstruit les événements de certains types à partir de leurs enregistrements, pour que le relais les
répartisse. Les types que les codecs reconstruisent forment la liste d'autorisation de l'outbox : un type sans codec
n'est jamais enregistré, et un enregistrement dont le type a perdu son codec est mort aussitôt au lieu d'être relayé.

```php
use Phil\Outbox\Contract\EventCodec;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\Payload;

final readonly class SetEventsCodec implements EventCodec
{
    public function __construct(private SetDefinitions $definitions) {}

    public function types(): array
    {
        return ['sets.set_completed'];
    }

    public function decode(OutboxRecord $record, Payload $payload): SetCompleted
    {
        $definition = $this->definitions->find($payload->string('set'))
            ?? throw UndeliverableRecord::because('The set is gone.');

        return new SetCompleted($record->id->value, $definition, $payload->int('holder'));
    }
}
```

`Codecs` les rassemble et refuse deux codecs d'un même type, ou un type de forme différente.

## Ce que fait un codec

- Il lit les identifiants de la charge utile avec `Payload::string()`, `int()`, `get()` et `has()` ; une valeur
  manquante ou d'un mauvais type lance `InvalidRecord`, et l'enregistrement est mort aussitôt.
- Il peut relire le reste de l'événement là où sa bibliothèque le conserve.
- Il lance `UndeliverableRecord` quand l'enregistrement ne peut jamais redevenir un événement, par exemple quand l'état
  qu'il nomme a disparu pour de bon : le relais déplace aussitôt l'enregistrement parmi les enregistrements morts. Sa
  raison est conservée comme dernière erreur de l'enregistrement : des identifiants seulement, jamais de
  renseignements personnels ([La dernière erreur](housekeeping.md#la-dernière-erreur)).
- Il lance `ForgottenRecord` quand l'état que l'enregistrement nomme a été effacé exprès, par exemple une demande
  purgée après qu'une personne a demandé à être oubliée : le relais marque l'enregistrement écarté, aucun écouteur ne
  le reçoit, et il n'est jamais mort. Un enregistrement écarté laisse passer les enregistrements suivants de sa clé
  d'ordre, et il est purgé avec les enregistrements livrés.
- Toute autre exception est une tentative échouée : le relais essaie de nouveau l'enregistrement après le délai de son
  recul.
- Il n'exécute jamais `unserialize()` de PHP : un enregistrement contient du JSON d'identifiants, relu sous les mêmes
  bornes qu'une nouvelle charge utile.
- Il place l'identifiant de l'enregistrement dans l'événement quand ses écouteurs doivent distinguer une nouvelle
  livraison d'un nouvel événement.

## `RecordedEvent`

`RecordedEventCodec` reconstruit les enregistrements des types qu'on lui donne sous forme de `RecordedEvent`, sans
classe d'événement propre : l'identifiant, le type, la charge utile, la clé d'ordre, l'occurrence et la tentative de
l'enregistrement. Un écouteur de `RecordedEvent` lit `$event->type` et les identifiants dont il a besoin.

```php
$codecs = new Codecs([new RecordedEventCodec(['quiz.completed', 'quiz.perfect_score'])]);
```
