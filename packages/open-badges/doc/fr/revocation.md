<!-- phil-dev source-sha256=d4e5d16a30568b458ec9cddf1fa46b22902d3693e34b81ee7968c4d25f7ba793 -->
# Révocation

Un titre émis par erreur ou obtenu par fraude est révoqué par une **Bitstring Status List** : l'émetteur publie une
liste de bits, un par titre, et active le bit d'un titre révoqué. Le titre dit où se trouve son bit.

## L'entrée que porte un titre

```php
use Phil\OpenBadges\Status\StatusListEntry;

$status = new StatusListEntry('https://example.org/status/1', $index);            // revocation
$status = new StatusListEntry('https://example.org/status/2', $index, 'suspension');

$issuer->credential($achievement, $recipient, $id, $awardedAt, status: $status);
```

L'application donne à chaque titre son propre index dans une liste, et le garde. L'entrée devient le
`credentialStatus` du titre, une `BitstringStatusListEntry`.

## La liste

```php
use Phil\OpenBadges\Status\StatusList;
use Phil\OpenBadges\Status\StatusListIssuer;

$list = StatusList::of($revokedIndexes);       // at least 131,072 bits
$credential = new StatusListIssuer($issuerProfile)->credential('https://example.org/status/1', $list, 'revocation', $now, $until);
```

La liste contient au moins 131 072 bits, pour ne pas révéler combien de titres ont été émis, et au plus 16 777 216, en
multiple de 8. Son premier index est le bit le plus à gauche du premier octet. Elle est publiée compressée en GZIP, en
base 64 URL multibase (`u…`), dans un `BitstringStatusListCredential` que l'émetteur sécurise comme tout titre et sert
à l'URL de la liste. Un `validUntil` court indique aux vérificateurs combien de temps ils peuvent la garder.

## Lire un état

```php
$revoked = new StatusListIssuer($issuerProfile)->applies($verified->status(), $verifiedStatusListCredential);
```

`applies()` prend le titre de la liste d'état, vérifié par l'appelant. Elle vérifie qu'il s'agit de la liste de
l'entrée et qu'elle a été émise par l'émetteur, décode la liste — en refusant une liste qui gonfle au-delà de ses
bornes — et lit le bit.
