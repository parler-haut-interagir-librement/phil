<!-- phil-dev source-sha256=dcad3ff7bc7fa7eb99fc6071d87adaede0b9a92ff195e5ff1e1f46b5a9c38a4f -->
# Messenger

## Quand l'outbox propre à Messenger convient

Symfony Messenger 8.2 fournit un outbox et un inbox qui lui sont propres :

- l'option `outbox` d'un transport stocke les messages dans un transport d'outbox, à l'intérieur de la transaction de
  base de données en cours, et les transmet à leur transport cible quand le transport d'outbox est consommé ;
- l'option `inbox` consigne les messages traités dans une table, dans la transaction de leurs *handlers*, de sorte
  qu'un message livré de nouveau n'est pas traité deux fois.

**Employez-les quand le code répartit déjà des messages de Messenger** dans une transaction Doctrine : les *handlers*
d'une application, un bundle bâti sur Messenger. Ils n'exigent ni codec ni relais propres.

**Employez phil/outbox quand une bibliothèque ne connaît rien de Messenger** : une bibliothèque PHP simple qui écrit par
son propre entrepôt et informe des écouteurs PSR-14, dont les événements doivent survivre à un échec entre son écriture
et sa répartition. phil/outbox garde aussi dans l'ordre les enregistrements d'une clé d'ordre, et ses enregistrements
morts et sa rétention dans une seule table.

Les deux se rejoignent : le pont ci-dessous relaie les événements de l'outbox vers un bus, dont l'acheminement, les
transports, les nouvelles tentatives et l'inbox s'appliquent alors à eux.

## Le pont vers un bus

```yaml
phil_outbox:
    messenger:
        enabled: true
        bus: messenger.default_bus
```

Le relais répartit chaque événement reconstruit vers le bus au lieu du répartiteur PSR-14. Un enregistrement est livré
une fois que le bus a accepté son événement : traité par un *handler* synchrone, ou envoyé à son transport. Un
*handler* qui échoue, ou un transport qui refuse le message, laisse l'enregistrement être essayé de nouveau après le
recul.

Un message envoyé par un transport porte ce que son codec a reconstruit : des identifiants seulement, comme doivent le
faire les messages de Messenger. `RecordedEvent` porte l'identifiant de l'enregistrement, qu'un *handler* conserve pour
traiter un enregistrement une seule fois ; avec un transport asynchrone, activez aussi l'`inbox` du transport, afin
qu'un message que le transport livre de nouveau soit traité une seule fois.
