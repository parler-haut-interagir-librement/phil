<!-- phil-dev source-sha256=df62797ad9eb550dfe57098f855fd8f2fd022ad46f37d009fc6a76d257b2f877 -->
# Codes et comptoir

## Le code

`PickupCodes` émet un code de huit caractères de la base 32 de Crockford — `0123456789ABCDEFGHJKMNPQRSTVWXYZ`, sans I,
L, O ni U —, chacun tiré par `random_int()` : quarante bits. `IssuedCode::display()` l'affiche en deux groupes,
`7KQ2-M9XD`.

La lecture d'un code pardonne ce que les gens saisissent : la casse, les espaces et les tirets sont ignorés, `O` se lit
`0`, `I` et `L` se lisent `1`. `PickupCodes::normalize()` donne les huit caractères, ou `null`.

Seule l'empreinte est conservée : `hash_hmac('sha256', order."\n".code, key)`, 64 chiffres hexadécimaux, où `key` est
`hash_hkdf('sha256', secret, 32, 'phil_local_pickup.code_digest')`. Elle est liée à la commande, de sorte qu'un code ne
vaut rien pour une autre commande, et elle est calculée avec une clé dérivée du secret, de sorte qu'une copie de la base
de données seule ne permet pas d'essais hors ligne, et qu'aucun autre usage du même secret ne donne les mêmes
empreintes. `PickupCodes::matches()` compare les empreintes avec `hash_equals()`.

## Le code QR

`IssuedCode::payload()` est le numéro de commande, un deux-points et le code : `WEB-1042:7KQ2M9XD`. `qrCode()` l'encode
avec phil/qr-code au niveau de correction d'erreur demandé (moyen par défaut), et `svg()` le dessine avec un titre pour
les lecteurs d'écran. Le texte d'un lecteur de codes se relit avec `ScannedCode::parse()`, ou directement dans une
requête avec `HandOverRequest::scanned()` ; une charge utile de plus de 97 octets, sans deux-points, ou sans code après
le dernier deux-points est refusée avec `InvalidPickup`.

## Le comptoir

```php
$counter = new Counter($pickupRepository, new PickupCodes($secret), $handOverLog, maxAttempts: 5);

$handOver = $counter->handOver(new HandOverRequest(
    order: 'WEB-1042',
    code: '7kq2 m9xd',
    station: 'counter-1',
    staff: 'staff-3',
    at: new DateTimeImmutable(),
    collector: 'Élise Tremblay',
));
```

| Situation | Résultat |
|---|---|
| Aucun ramassage pour la commande | `UnknownPickup` |
| Le ramassage n'est pas prêt | `PickupRefused`, raison `state_<state>` |
| Le code a subi `maxAttempts` mauvaises tentatives | `CodeLocked` |
| Le ramassage est prêt mais son avis n'est pas envoyé | `PickupRefused`, raison `no_code` |
| Un mauvais code | La tentative est comptée et enregistrée ; `CodeRefused` avec `attemptsLeft`, ou `CodeLocked` à la dernière |
| Le bon code | Le ramassage est marqué ramassé, son code consommé, le `HandOver` écrit au journal et retourné |

La limite de tentatives va de 1 à 20, cinq par défaut. Un code verrouillé le reste jusqu'à ce que `Pickups::resend()`
en envoie un nouveau, ce qui recommence le compte.

## Quelqu'un qui ramasse à la place du client

Le client peut désigner une personne en passant la commande ; l'avis montre le nom. Au comptoir, le personnel vérifie
qui est là et passe son nom comme `collector`. Le `HandOver` conserve alors le nom de la personne qui ramasse et le nom
désigné côte à côte, et `byThirdParty()` vaut `true`, de sorte qu'une vérification ultérieure puisse comparer ce que le
personnel a vu à ce que le client avait demandé. Sans `collector`, le client a ramassé lui-même et aucun nom n'est
conservé.

## Les caisses

`Contract\PickupDesk` est le port qu'appellent à la fois un écran de comptoir et une caisse de point de vente ;
`Counter` l'implémente. La caisse d'un kiosque de congrès remet une commande avec l'identifiant de la caisse comme
`station` et le caissier comme `staff`. Le côté de la caisse — lire le code dans l'écran de vente et appeler le port —
relève du point de vente ; cette bibliothèque n'en dépend pas.
