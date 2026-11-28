<!-- phil-dev source-sha256=a12826877293b8a22436cdd0c0156c79086b1daafeda840f04989ed1eb87b4a3 -->
# La vie d'un ramassage

## Les états

| État | Atteint par | Occupe une place dans sa plage | A un code |
|---|---|---|---|
| `Preparing` | `Pickups::place()` | Oui | Non |
| `Ready` | `Pickups::ready()` | Oui | Une fois l'avis envoyé |
| `PickedUp` | `Counter::handOver()` | Non | Non : consommé |
| `Expired` | `Expiry::run()` | Non | Non : consommé |

Tout autre passage lève `PickupRefused`, dont la `reason` est `state_<state>`.

## Le passage de la commande

```php
$pickup = $pickups->place('WEB-1042', 'customer-7', $point, $slot, new DateTimeImmutable(), collector: 'Élise Tremblay');
```

Le numéro de commande compte jusqu'à 64 lettres, chiffres, `_`, `.`, `#`, `/` ou `-` ; il ne contient jamais `:`. Le
client est l'identifiant opaque par lequel les demandes de vie privée de l'application désignent les gens. La personne
qui ramasse est celle que le client désigne pour ramasser à sa place, le cas échéant. Le passage refuse un point qui
n'accepte aucune commande (`point_closed`), une commande qui a déjà un ramassage (`already_placed`) et une plage qui a
une raison quelconque (`slot`).

## Prêt, et l'avis

```php
$pickups->ready('WEB-1042', new DateTimeImmutable());
```

Le ramassage est prêt et gardé jusqu'à la fin du délai de garde convenu, compté à partir du début de sa plage, ou à
partir de maintenant quand il est prêt plus tard que cela. `ready()` l'enregistre et envoie le numéro de commande par
`Contract\ReadyNoticeDispatcher` — un `Message\NotifyPickupReady` sur le bus de l'application.

`Message\NotifyPickupReadyHandler` s'exécute dans le *worker*. Il relit le ramassage ; quand il est prêt et que son avis
n'est pas envoyé, il émet un code, enregistre l'empreinte du code, envoie un `ReadyNotice` par `Contract\ReadyNotifier`,
puis marque l'avis comme envoyé. L'avis contient le point convenu, la plage, la fin de la garde, la personne désignée
pour ramasser et l'`IssuedCode`, à montrer en texte avec `display()` et en code QR avec `svg()`. Le code n'existe que
là.

- Un avis qui échoue atteint le bus, qui fait une nouvelle tentative ; la nouvelle tentative émet un nouveau code, qui
  remplace l'empreinte.
- Un message livré deux fois trouve l'avis envoyé et n'envoie rien.
- Un message pour un ramassage qui n'est plus prêt, ou inconnu, ne fait rien.

`Pickups::resend()` oublie le code actuel et envoie l'avis de nouveau : après que le code a été verrouillé par de
mauvaises tentatives, ou quand le client l'a perdu.

## L'arrivée

```php
$first = $pickups->arrive('WEB-1042', ArrivalSource::Manual, new DateTimeImmutable());
```

L'application peut signaler une arrivée d'elle-même (`Automatic`), à partir d'une position que le client partage, et le
client peut appuyer sur « Je suis là » sur la commande (`Manual`). Le premier signal est conservé avec sa source et
retourne `true` ; le personnel est avisé par `Contract\ArrivalNotifier`, une seule fois. Un signal ultérieur retourne
`false` et n'avise personne. Quand le client retire une permission après avoir commandé — la position, les
notifications —, le signal manuel fonctionne toujours et le ramassage suit son cours : aucune étape après le passage de
la commande ne lit une permission.

## Le délai de garde et l'expiration

```php
$expiry = new Expiry($pickupRepository, ExpiryOutcome::Restock, restocker: $restocker);
$expired = $expiry->run(new DateTimeImmutable(), limit: 100);
```

Un ramassage prêt est en retard dès la fin de son délai de garde. `Expiry::run()` lit au plus `limit` ramassages en
retard (de 1 à 1 000), la fin la plus ancienne d'abord, et appelle pour chacun le port que la configuration a choisi :

| `ExpiryOutcome` | Port | Fait |
|---|---|---|
| `CancelAndRefund` | `Contract\ExpiredPickupCanceller` | Le commerce de l'application annule et rembourse la commande. |
| `Restock` | `Contract\ExpiredPickupRestocker` | La logistique de l'application remet les marchandises en stock à l'emplacement convenu, par exemple en libérant la réservation de la commande ; la commande demeure. |

Le constructeur refuse un résultat sans son port. Le port est appelé avant que le ramassage soit enregistré comme
expiré, de sorte qu'une exécution interrompue entre les deux l'appelle de nouveau à l'exécution suivante : les deux
ports sont idempotents, avec le numéro de commande pour clé. Lancez-la depuis une planification, toutes les quelques
minutes.
