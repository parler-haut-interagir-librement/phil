# Members

Each member is an independent package, published on Packagist and released on its own. The profile requires it at its
latest published minor version, from the release whose fixes the profile relies on.

## `phil/url-bundle` — the addresses people type

- **Constraint:** `^0.1.3`, which boots without `doctrine/dbal`. **Brings:** `phil/url`, `phil/enums`.
- **Role:** an address given in a form is read as a browser reads it — by the WHATWG parser of PHP 8.5 —, normalised,
  and its kind detected: a plain website, a video channel, a social account.
- **Adds:** the form field `TypedUrlType`, the constraint `#[UrlOfType]`, the Doctrine column type, addresses in Twig.
- **Why in this profile:** an address is the first input most public forms take, and the one the other guards read.
- **Needs:** `ext-intl` and `ext-mbstring`; `doctrine/dbal` and DoctrineBundle for the column type alone.

## `phil/safe-http` — the addresses the application fetches

- **Constraint:** `^0.2`. **Brings:** `phil/url`.
- **Role:** an HTTP client over Symfony's that refuses private and reserved networks, checks every redirect again,
  and bounds the time and the size of a response.
- **Adds:** `SafeHttpClient`, `AddressGuard`, `Limits`; a library, with no bundle.
- **Why in this profile:** once an address is accepted, fetching it — for a preview, a feed or a webhook — is where
  server-side request forgery happens.
- **Needs:** `symfony/http-client`, installed with it.

## `phil/rate-limit-bundle` — how often a client may ask

- **Constraint:** `^0.1`. **Brings:** `phil/rate-limit`.
- **Role:** limits declared in the configuration and on controllers, routes and forms; a refused request gets a 429
  answer with the standard RateLimit headers.
- **Adds:** `#[RateLimit]`, the configuration `phil_rate_limit`, the refusal template; and, from `phil/rate-limit`, a
  container that fails to build when a limiter counts in a store each server keeps for itself.
- **Why in this profile:** every other guard is cheaper behind a limit, and a form without one invites brute force.
- **Needs:** `symfony/rate-limiter` and a shared cache pool in production.

## `phil/spam-protection-bundle` — the spam in forms

- **Constraint:** `^0.3`. **Brings:** `phil/spam-protection`, `phil/bot-detection-bundle`, `phil/rate-limit`.
- **Role:** form submissions checked without a captcha, a third party or tracking: a signed token, the filling time,
  a small proof of work and content rules; the browser detectors of `phil/bot-detection-bundle` give a verdict that
  decides how much friction a visitor meets.
- **Adds:** the form option `spam_protection`, the Stimulus controllers.
- **Why in this profile:** public forms are what this profile protects, and spam is their most common abuse.
- **Needs:** `ext-sodium`, `ext-filter`, and in production a Doctrine connection or a lock factory every server
  shares for the tokens already used.

## `phil/upload-scan-bundle` — the files people upload

- **Constraint:** `^0.1.1`, whose quarantine page checks its token without a session. **Brings:** `phil/upload-scan`.
- **Role:** every uploaded file passes through an antivirus as the form is validated: clean, infected or not scanned,
  with a policy for files not scanned; infected files are quarantined, listed for administrators and purged.
- **Adds:** the constraint `#[CleanFile]`, the ClamAV scanner, the quarantine and its page, a purge command.
- **Why in this profile:** a file is the input that reaches other people's devices.
- **Needs:** `ext-sodium` and a ClamAV daemon.

## Packages close to this profile

These packages are not members: they answer other needs, and an application adds them when it has those.

| Package | Why it is not a member |
|---|---|
| `phil/browser-security-bundle` | It guards what the application sends browsers — Content Security Policy, HSTS, framing and cookie rules —, not what it receives. |
| `phil/ip-anonymizer` | Suggested by `phil/rate-limit-bundle` for keys that keep no full address; a privacy choice of the application. |
| `phil/svg-sanitizer`, `phil/css-sanitizer` | They clean markup people write, and come with `phil/markup` in `phil/profile-editorial`. |
