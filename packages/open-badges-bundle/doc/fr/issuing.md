<!-- phil-dev source-sha256=0268df1ee1a9b48c16b5f764bbda697b1998a490bffe09ff0e7d0e8464ac48a7 -->
# Émission

Le bundle enregistre les constructeurs de `phil/open-badges`, configurés avec l'émetteur et sa clé. Une application les
injecte par leur classe :

| Service | Usage |
|---|---|
| `Phil\OpenBadges\CredentialIssuer` | Construit une `OpenBadgeCredential` ; `issue()` la renvoie sous forme de jeton. |
| `Phil\OpenBadgesBundle\Application\Securing` | Sécurise n'importe quelle attestation selon la configuration : une `SecuredCredential`, un jeton ou le document avec sa preuve. |
| `Phil\OpenBadges\EndorsementIssuer` | Construit une `EndorsementCredential` que l'émetteur donne à un autre émetteur. |
| `Phil\OpenBadges\Clr\ClrIssuer` | Construit un relevé d'apprentissage. |
| `Phil\OpenBadgesBundle\Infrastructure\Routing\StatusListUrls` | L'entrée d'état d'une attestation révocable. |
| `Phil\OpenBadges\Issuer`, `Phil\OpenBadges\Contract\Signer` | Le profil et la clé de l'émetteur. |

```php
use Phil\OpenBadges\Achievement;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadges\Recipient;
use Phil\OpenBadgesBundle\Application\SecuredCredential;
use Phil\OpenBadgesBundle\Application\Securing;
use Phil\OpenBadgesBundle\Infrastructure\Routing\StatusListUrls;

final readonly class BadgeCredentials
{
    public function __construct(
        private CredentialIssuer $credentials,
        private Securing $securing,
        private StatusListUrls $statusLists,
    ) {
    }

    public function forAward(Award $award, string $emailTheyGave): SecuredCredential
    {
        $credential = $this->credentials->credential(
            new Achievement($award->badgeUrl, $award->name, $award->description, $award->criteria, $award->imageUrl),
            Recipient::email($emailTheyGave, bin2hex(random_bytes(8))),
            'urn:uuid:'.$award->uuid,
            $award->awardedAt,
            status: $this->statusLists->entry('awards', $award->statusIndex),
        );

        return $this->securing->secure($credential);
    }
}
```

L'application donne à chaque attestation révocable son propre index dans l'une de ses listes, et le conserve avec ce
qu'elle a émis. Révoquer l'attestation revient alors à activer cet index dans ce que renvoient ses `StatusLists` (voir
[Publication](publishing.md)).

`secure()` renvoie une `SecuredCredential`, un objet-valeur de deux propriétés :

| Membre | Valeur |
|---|---|
| `content` | Le jeton Web JSON compact, ou le JSON du document avec sa preuve, barres obliques et lettres non échappées |
| `mediaType` | `application/vc+jwt` pour un jeton, `application/vc` pour un document |
| `isToken()` | Indique si c'est un jeton |
| `filename()` | `credential.jwt` ou `credential.json`, pour un téléchargement ou une pièce jointe |

```php
$secured = $badgeCredentials->forAward($award, $email);

return new Response($secured->content, 200, [
    'Content-Type' => $secured->mediaType,
    'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $secured->filename()),
]);
```

`PngBaker` et `SvgBaker` de `phil/open-badges` intègrent `content` à l'image du badge.
