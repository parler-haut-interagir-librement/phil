# Publishing

The routes of `config/routes.php` serve what anyone needs to verify the issuer's credentials. Each answer is JSON or a
token, open to every origin (`Access-Control-Allow-Origin: *`), and cacheable by browsers and shared caches.

| Route | Path under the prefix | Answers |
|---|---|---|
| `phil_open_badges_issuer` | `/issuer` | The issuer's profile document, `application/ld+json`: its `Profile`, and its Ed25519 key as a `Multikey` verification method it asserts with. |
| `phil_open_badges_issuer_keys` | `/issuer/keys` | Its JSON Web Key Set, `application/jwk-set+json`, the key's `kid` being its verification method. |
| `phil_open_badges_status_list` | `/status/{list}` | The `BitstringStatusListCredential` of the list, secured as configured. |

The profile and the keys may be kept `max_age` seconds, carry an `ETag`, and are a `304` to a request that holds them.
An RSA key has no Multikey: the profile then lists no verification method, and the keys are found in the key set.

## Status lists

A list is named by the application — `awards`, `certificates-2026` —: letters, digits, `_` and `-`, at most 64
characters. Its content comes from the application's `StatusLists`:

```php
use Phil\OpenBadgesBundle\Application\Contract\StatusLists;
use Phil\OpenBadgesBundle\Application\StatusListContent;

final readonly class AwardStatusLists implements StatusLists
{
    public function find(string $list): ?StatusListContent
    {
        return 'awards' === $list ? new StatusListContent($this->awards->revokedStatusIndexes()) : null;
    }
}
```

A list the application does not have is a `404`. Each answer is built from the content at that moment: the list of
`status_lists.size` bits, its purpose (`revocation` by default, or `suspension`), valid from now for
`status_lists.max_age` seconds and cacheable as long. A revocation is therefore seen by verifiers within that time.
The list is secured like the issuer's credentials: a token (`application/vc+jwt`) or a document with its proof
(`application/vc`).

`StatusListUrls::listOf()` answers which of the application's lists a status entry points to: it reads the entry's
URL with `phil/url`, as a browser reads it, takes the last segment of its path as the list's name, and keeps that
name only when the route gives it exactly that URL. Another host, another spelling of the path, a query or a fragment
is not one of the application's lists. `allow_localhost` lets these URLs name `localhost`, as it does the issuer's
addresses.
