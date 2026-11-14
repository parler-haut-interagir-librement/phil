# Issuing

The bundle registers the builders of `phil/open-badges`, configured with the issuer and its key. An application injects
them by class:

| Service | Use |
|---|---|
| `Phil\OpenBadges\CredentialIssuer` | Builds an `OpenBadgeCredential`; `issue()` returns it as a token. |
| `Phil\OpenBadgesBundle\Application\Securing` | Secures any credential as configured: a `SecuredCredential`, a token or the document with its proof. |
| `Phil\OpenBadges\EndorsementIssuer` | Builds an `EndorsementCredential` the issuer gives another issuer. |
| `Phil\OpenBadges\Clr\ClrIssuer` | Builds a learner record. |
| `Phil\OpenBadgesBundle\Infrastructure\Routing\StatusListUrls` | The status entry of a revocable credential. |
| `Phil\OpenBadges\Issuer`, `Phil\OpenBadges\Contract\Signer` | The issuer's profile and key. |

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

The application gives each revocable credential its own index in one of its lists, and keeps it with what it issued:
revoking the credential is then setting that index in what its `StatusLists` returns (see
[Publishing](publishing.md)).

`secure()` returns a `SecuredCredential`, a value object of two properties:

| Member | Value |
|---|---|
| `content` | The compact JSON Web Token, or the JSON of the document with its proof, slashes and letters unescaped |
| `mediaType` | `application/vc+jwt` for a token, `application/vc` for a document |
| `isToken()` | Whether it is a token |
| `filename()` | `credential.jwt` or `credential.json`, for a download or an attachment |

```php
$secured = $badgeCredentials->forAward($award, $email);

return new Response($secured->content, 200, [
    'Content-Type' => $secured->mediaType,
    'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $secured->filename()),
]);
```

`PngBaker` and `SvgBaker` of `phil/open-badges` bake `content` into the badge's image.
