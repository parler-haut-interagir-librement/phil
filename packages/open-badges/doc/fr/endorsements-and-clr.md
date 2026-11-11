<!-- phil-dev source-sha256=669c8d7b3ab83211e4eb0bb57f31bc9369870a0047d8b8bfb087f8c75185029e -->
# Appuis et relevés d'apprentissage

## Appuis

Un appui est le soutien d'un tiers à une réalisation, à un émetteur ou à un titre : un organisme partenaire, un
éditeur, un ordre professionnel. L'auteur de l'appui construit et signe un `EndorsementCredential` :

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

L'émetteur de ce qui est appuyé porte l'appui signé : `new Achievement(..., endorsements: [$signed])`,
`new Issuer(..., endorsements: [$signed])`, ou les `$endorsements` d'un titre. Un document avec sa preuve va dans
`endorsement`, un jeton dans `endorsementJwt` ; tout le reste lance `InvalidBadge`.

## Comprehensive Learner Record 2.0

Un relevé d'apprentissage réunit les titres d'un apprenant dans un seul relevé que l'émetteur signe :

```php
use Phil\OpenBadges\Clr\ClrIssuer;

$record = new ClrIssuer($issuerProfile)->credential($learner, [$securedDocument, $token], 'Record of Ada', 'urn:uuid:'.$uuid, $now);
$signed = new EddsaRdfc2022()->sign($record, $signer, $method, $now);
```

C'est un `ClrCredential` dont le `ClrSubject` est l'apprenant et dont le `verifiableCredential` contient de 1 à 200
titres : un document avec sa preuve Data Integrity tel quel, un jeton sous forme d'`EnvelopedVerifiableCredential` dont
l'identifiant est `data:application/vc+jwt,` suivi du jeton. `partial: true` indique qu'il ne contient qu'une partie
des titres de l'apprenant. Le relevé est sécurisé comme tout titre, et chaque titre intégré garde sa propre preuve.
