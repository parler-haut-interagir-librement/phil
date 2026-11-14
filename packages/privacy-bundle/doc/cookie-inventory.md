# The cookie inventory

Everything a site, and the services it embeds, writes in the visitor's browser is declared once, in
`config/privacy/cookies.yaml`. The banner, the page of the choices and the cookie policy are drawn from it, and a
functional test fails on a response that sets a cookie it does not declare.

```yaml
# config/privacy/cookies.yaml — each consent category to what it writes
session:
    PHPSESSID:
        purpose: { en: Keeps you signed in, fr: Garde votre session ouverte }
        duration: session
analytics:
    '_pk_id.*':
        purpose: { en: Tells one visitor from another, fr: Distingue un visiteur d’un autre }
        duration: P13M
        provider: Matomo
    '_pk_ses.*':
        purpose: { en: Groups the pages of one visit, fr: Regroupe les pages d’une visite }
        duration: PT30M
        provider: Matomo
videos:
    yt-player-bandwidth:
        purpose: { en: Adapts the video to your connection, fr: Adapte la vidéo à votre connexion }
        duration: session
        provider: YouTube
        storage: local_storage
```

| Key | Rule |
|---|---|
| category | A category of `phil_privacy.consent.categories`; any other stops the build, so that nothing is declared under a choice nobody is asked. |
| name | The characters of a cookie name, at most 128. A name that ends with `*` covers a family: `_pk_id.*` covers `_pk_id.1.1fff`. It is a prefix, never a pattern. |
| `purpose` | Required. A text, or texts by locale; the policy shows the reader's locale, then English, then the first. One line of at most 500 characters each. |
| `duration` | Required. `session`, until the browser closes, or an ISO 8601 duration such as `P13M`. |
| `provider` | Who writes it, when it is not the site itself: the name of the service. |
| `storage` | `cookie` (the default), `local_storage` or `session_storage`. |

Any other key stops the build, as does a cookie declared twice in the same storage. The file is parsed once, when the
container is built, and is a resource of the container: change it, and the container is built again. A file that
does not exist declares nothing. Another path goes under `phil_privacy.cookies.inventory`.

The bundle declares its own cookie, the one that keeps the choice (`consent.cookie_name`), for `consent.lifetime`. It
belongs to no category, since no choice can refuse it, and the policy shows it first, under "Your choice".

## The banner, the page and the policy

- The **banner** lists, under "What is written in your browser", each category with the names it writes, and links to
  the policy.
- The **page of the choices** gives each category the names it writes.
- The **cookie policy**, `phil_privacy_cookies` at `/privacy-choices/cookies`, has a table per category: the name,
  who writes it, why, for how long and where. A category that writes nothing says so: the person is asked about it all
  the same. `privacy_cookie_policy()` renders the same tables inside the site's own privacy policy.

Their texts are in the `phil_privacy` domain; override `@PhilPrivacy/cookies/_inventory.html.twig` and
`cookies/policy.html.twig` for your markup.

## A test that fails on an undeclared cookie

```php
use Phil\PrivacyBundle\Infrastructure\Testing\AssertsDeclaredCookies;

final class PagesTest extends WebTestCase
{
    use AssertsDeclaredCookies;

    public function testTheHomePage(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        self::assertResponseSetsDeclaredCookiesOnly($client->getResponse());
    }
}
```

The assertion reads the inventory from the test container and fails with the names it does not declare. A cookie the
response clears is not one it sets. The trait needs PHPUnit and a `KernelTestCase`; `UndeclaredCookies::in()` gives the
same names to any other test framework.

In development, `UndeclaredCookieListener` logs a warning for every response that sets an undeclared cookie, so that a
new one shows before a test is written for it. It is on when `kernel.debug` is, and `cookies.watch` turns it on or off
anywhere; it never changes the response.

The inventory cannot see what a script writes in the browser after the page is loaded. Declare it all the same, under
the category whose consent lets the script run.
