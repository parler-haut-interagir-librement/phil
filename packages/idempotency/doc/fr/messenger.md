<!-- phil-dev source-sha256=fb390e0ec6258698d853c2629bc1b3044346abcec02956b73a5aafc6dbe7c412 -->
# Messenger

`Messenger\IdempotencyMiddleware` traite un message reçu une fois par clé.

```php
new IdempotencyMiddleware(IdempotencyGuard $guard)
```

Enregistrez la garde et l'intergiciel comme services, puis ajoutez l'intergiciel à un bus, avant les gestionnaires :

```yaml
framework:
    messenger:
        buses:
            messenger.bus.default:
                middleware:
                    - Phil\Idempotency\Messenger\IdempotencyMiddleware
```

## La clé

- Un `IdempotencyStamp(string $key, string $scope = 'messenger')` nomme l'intention que réalise le message : deux
  messages expédiés pour la même intention — un contrôleur réessayé, un webhook reçu deux fois — sont traités une fois.
  Le tampon vérifie sa clé et sa portée à sa création, à l'expédition. Les deux sont des identifiants, jamais des
  données. Une clé qui vient d'un client — l'`Idempotency-Key` d'une requête — n'est unique que pour ce client :
  donnez au tampon une portée qui le nomme, le membre, le locataire ou le client de l'API, sinon deux clients qui
  choisissent la même clé pour la même classe de message verraient le second message acquitté sans être traité.
- Sans tampon, le `MessageIdStamp` de Messenger est la clé : Messenger le garde d'une nouvelle tentative à l'autre, dans
  le transport des échecs et aux rejeux ; un message livré de nouveau est donc traité une fois.
- Une enveloppe sans l'un ni l'autre passe sans rien changer.

L'empreinte est la classe du message : une clé utilisée par deux classes est une réutilisation.

## Ce qu'il garde

Seulement une enveloppe reçue, qui porte un `ReceivedStamp` : un doublon arrive par un transport, et l'expédition d'un
message asynchrone n'est pas son traitement.

| La clé | L'intergiciel |
|---|---|
| Nouvelle, ou échouée | Exécute l'intergiciel suivant et les gestionnaires, et garde la clé comme terminée. |
| Terminée | Retourne l'enveloppe sans exécuter les gestionnaires : le message est acquitté. |
| En cours ailleurs | Lève `RecoverableMessageHandlingException`, avec un délai de nouvelle tentative égal aux secondes qui restent à la réservation. |
| Utilisée par une autre classe de message | Lève `UnrecoverableMessageHandlingException` : aucune nouvelle tentative. |

Un gestionnaire qui lève une exception marque la clé échouée, pour que la nouvelle tentative que planifie Messenger
l'exécute de nouveau.

Un gestionnaire qui dure plus longtemps que sa réservation, pendant qu'une nouvelle livraison du même message a réservé
la clé, se termine par `ReservationLost` : son issue n'est pas gardée, et la nouvelle tentative que planifie Messenger
trouve l'enregistrement de l'autre livraison — acquitté une fois terminé. Réglez la réservation de la garde plus
longue que le gestionnaire le plus lent.

Ensuite : [exceptions](exceptions.md).
