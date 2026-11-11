# Endorsements and learner records

## Endorsements

An endorsement is a third party's support for an achievement, an issuer or a credential: a partner organization, a
publisher, a professional body. The endorser builds and signs an `EndorsementCredential`:

```php
use Phil\OpenBadges\EndorsementIssuer;

$endorsement = new EndorsementIssuer($partnerProfile)->credential(
    'https://example.org/badges/speaker',     // what is endorsed: a URL, a urn:uuid: or a DID
    'Endorsement of the Speaker badge',
    'We recognize the talks this badge rewards.',
    'urn:uuid:'.$uuid,
    $now,
);

$signed = new EddsaRdfc2022()->sign($endorsement, $partnerSigner, $partnerMethod, $now);   // or a JwtSigner
```

The issuer of the endorsed thing carries the signed endorsement: `new Achievement(..., endorsements: [$signed])`,
`new Issuer(..., endorsements: [$signed])`, or the `$endorsements` of a credential. A document with its proof goes in
`endorsement`, a token in `endorsementJwt`; anything else raises `InvalidBadge`.

## Comprehensive Learner Record 2.0

A learner record gathers a learner's credentials in one record the issuer signs:

```php
use Phil\OpenBadges\Clr\ClrIssuer;

$record = new ClrIssuer($issuerProfile)->credential($learner, [$securedDocument, $token], 'Record of Ada', 'urn:uuid:'.$uuid, $now);
$signed = new EddsaRdfc2022()->sign($record, $signer, $method, $now);
```

It is a `ClrCredential` whose `ClrSubject` is the learner and whose `verifiableCredential` holds from 1 to 200
credentials: a document with its Data Integrity proof as it is, a token as an `EnvelopedVerifiableCredential` whose
identifier is `data:application/vc+jwt,` and the token. `partial: true` says it holds only some of the learner's
credentials. The record is secured like any credential, and each embedded credential keeps its own proof.
