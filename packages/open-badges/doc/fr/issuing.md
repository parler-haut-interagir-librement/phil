<!-- phil-dev source-sha256=201f8723905571b2e00fee4280baafb1fa81c6675180c231821f76427e741cf7 -->
# Émettre

## Les parties

| Type | Ce que c'est |
|---|---|
| `Issuer` | Qui émet : une URL absolue où son profil est publié, un nom et, au besoin, un site Web, une adresse de contact, une image et des appuis. |
| `Achievement` | Ce qui est reconnu : une URL absolue, un nom, une description, le récit de ses critères et, au besoin, une image et des appuis. |
| `Recipient` | À qui : un DID, qui devient l'identifiant du sujet, ou une adresse courriel ou une URL gardée seulement sous forme d'empreinte SHA-256 salée. |

```php
use Phil\OpenBadges\Achievement;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadges\Recipient;

$issuer = new Issuer('https://example.org/issuer', 'Example', 'https://example.org', 'badges@example.org', 'https://example.org/logo.png');
$achievement = new Achievement('https://example.org/badges/speaker', 'Speaker', 'Gave a talk.', 'Give a talk of at least twenty minutes.', 'https://example.org/badges/speaker.png');

$recipient = Recipient::did('did:example:ada');
$recipient = Recipient::email($emailTheyGave, bin2hex(random_bytes(8)));   // salt of 8 characters or more
$recipient = Recipient::url('https://ada.example/', bin2hex(random_bytes(8)));
```

Une adresse courriel est épurée de ses espaces et mise en minuscules avant le calcul de son empreinte. Le sel voyage
avec l'empreinte, dans le titre. Il empêche de retrouver l'adresse dans une table d'empreintes, mais pas de la
confirmer pour qui la connaît.

Les URL et les identifiants sont vérifiés : une URL HTTP est absolue et ne contient aucun caractère qu'un IRI refuse,
un DID a une méthode en minuscules. Une valeur incohérente lance `InvalidBadge`.

## Le titre

```php
use Phil\OpenBadges\CredentialIssuer;

$issuer = new CredentialIssuer($issuerProfile, $signer, 'https://example.org/issuer#key-1');

$credential = $issuer->credential($achievement, $recipient, 'urn:uuid:'.$uuid, $awardedAt, $validUntil, $status, $endorsements);
$token = $issuer->issue($achievement, $recipient, 'urn:uuid:'.$uuid, $awardedAt);
```

- L'identifiant est un `urn:uuid:` en minuscules ou une URL HTTP absolue.
- Le titre est valide à partir du moment donné — celui de l'attribution — et jusqu'au moment donné après celui-ci,
  s'il y en a un. Les deux sont écrits en UTC, à la seconde (`2026-11-07T15:00:00Z`).
- `$status` est un `StatusListEntry`, pour un titre qui peut être révoqué : voyez [Révocation](revocation.md).
- `$endorsements` sont des `EndorsementCredential` signés : voyez [Appuis](endorsements-and-clr.md).

`credential()` retourne le titre non sécurisé, un tableau prêt pour une preuve Data Integrity. `issue()` le retourne
sous forme de JSON Web Token signé par la clé de l'émetteur, dont l'identifiant — le troisième argument du
constructeur — va dans l'en-tête du jeton. Voyez [Sécuriser](securing.md).

Le destinataire demande son titre ; l'application le lui donne sous forme de jeton, de document JSON ou d'image
intégrée ([Intégrer à l'image](baking.md)).
