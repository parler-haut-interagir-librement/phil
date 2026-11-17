<!-- phil-dev source-sha256=d2317f2377bf4f8cd3c0d1c7e65ebb54f8c28b001dd98424f0299a769f4834a5 -->
# Messenger

`Messenger\IdempotencyMiddleware` traite un message reçu une fois par clé.

```php
new IdempotencyMiddleware(IdempotencyGuard $guard, bool $compareContent = true)
```

Enregistrez la garde et le middleware comme services, puis ajoutez le middleware à un bus, avant les gestionnaires :

```yaml
framework:
    messenger:
        buses:
            messenger.bus.default:
                middleware:
                    - Phil\Idempotency\Messenger\IdempotencyMiddleware
```

## La clé

- Un `IdempotencyStamp(string $key, string $scope = 'messenger', ?PayloadFingerprint $fingerprint = null)` nomme
  l'intention que réalise le message : deux messages expédiés pour la même intention — un contrôleur réessayé, un
  webhook reçu deux fois — sont traités une fois. L'estampille vérifie sa clé et sa portée à sa création, à
  l'expédition. Les deux sont des identifiants, jamais des données. Une clé qui vient d'un client — l'`Idempotency-Key`
  d'une requête — n'est unique que pour ce client : donnez à l'estampille une portée qui le nomme, le membre, le
  locataire ou le client de l'API, sinon deux clients qui choisissent la même clé pour la même classe de message
  verraient le second message acquitté sans être traité.
- Sans estampille, le `MessageIdStamp` de Messenger est la clé : Messenger le garde d'une nouvelle tentative à l'autre,
  dans le transport des échecs et aux rejeux ; un message livré de nouveau est donc traité une fois.
- Une enveloppe sans l'un ni l'autre passe sans rien changer.

## L'empreinte

Une clé réutilisée avec un autre message est refusée, comme une requête HTTP qui réutilise une clé avec un autre corps.
L'empreinte est la classe du message et son contenu, lus à partir du premier de ces éléments :

1. le `$fingerprint` de l'`IdempotencyStamp`, donné par le producteur qui sait quels champs font l'intention ;
2. `idempotencyFingerprint()`, quand le message implémente `Contract\FingerprintedMessage` ;
3. chaque propriété du message, privées comprises, lue jusqu'à ses scalaires avec la classe de chaque objet : deux
   messages d'une même classe dont les propriétés portent les mêmes valeurs sont une seule intention.

```php
final readonly class RefundPayment implements FingerprintedMessage
{
    public function __construct(
        public string $paymentId,
        public int $amount,
        public \DateTimeImmutable $requestedAt,
    ) {
    }

    public function idempotencyFingerprint(): PayloadFingerprint
    {
        // The moment of the request differs between two dispatches of one refund: it is left out.
        return PayloadFingerprint::ofParts($this->paymentId, (string) $this->amount);
    }
}
```

Un message dont le contenu ne peut pas être lu — une fermeture, une ressource, des octets qui ne sont pas de l'UTF-8,
ou plus de `IdempotencyMiddleware::MAX_DEPTH` (32) niveaux d'objets et de tableaux — et qui ne donne aucune empreinte
est refusé avec `UnrecoverableMessageHandlingException`, dont le message nomme l'interface à implémenter.

`$compareContent` réglé à `false` compare la classe du message seulement : une clé utilisée par deux classes est une
réutilisation, et un second message de la même classe sous la même clé est acquitté quel que soit son contenu. Ne
l'utilisez que lorsque la clé seule nomme l'intention, comme le `MessageIdStamp` de Messenger, qu'une nouvelle livraison
garde avec le même message.

L'estampille garde le SHA-256 hexadécimal, jamais les champs, et le stockage garde l'empreinte seulement.

## Ce qu'il garde

Seulement une enveloppe reçue, qui porte un `ReceivedStamp` : un doublon arrive par un transport, et l'expédition d'un
message asynchrone n'est pas son traitement.

| La clé | Le middleware |
|---|---|
| Nouvelle, ou échouée | Exécute le middleware suivant et les gestionnaires, et garde la clé comme terminée. |
| Terminée | Retourne l'enveloppe sans exécuter les gestionnaires : le message est acquitté. |
| En cours ailleurs | Lève `RecoverableMessageHandlingException`, avec un délai de nouvelle tentative égal aux secondes qui restent à la réservation. |
| Utilisée par une autre classe de message ou un autre contenu | Lève `UnrecoverableMessageHandlingException` : aucune nouvelle tentative. |

Un gestionnaire qui lève une exception marque la clé échouée, pour que la nouvelle tentative que planifie Messenger
l'exécute de nouveau.

Un gestionnaire qui dure plus longtemps que sa réservation, pendant qu'une nouvelle livraison du même message a réservé
la clé, se termine par `ReservationLost` : son issue n'est pas gardée, et la nouvelle tentative que planifie Messenger
trouve l'enregistrement de l'autre livraison — acquitté une fois terminé. Réglez la réservation de la garde plus
longue que le gestionnaire le plus lent.

## Passer de la 0.2

L'empreinte d'un message couvre maintenant son contenu. Une clé enregistrée par la 0.2 porte l'empreinte de la classe
seulement : une nouvelle livraison d'un tel message, durant la rétention, est refusée comme une réutilisation au lieu
d'être acquittée. Laissez les files se vider avant la mise à niveau, ou construisez le middleware avec
`compareContent: false` jusqu'à ce que la rétention des clés enregistrées avant elle soit passée.

Ensuite : [exceptions](exceptions.md).
