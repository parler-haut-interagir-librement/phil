# Requests without an account

A person who has no account — a subscriber to a newsletter, someone who wrote through a form, a buyer of a ticket — has
the same rights as a member: a copy of what the site keeps about them, and its erasure. The bundle lets them ask through
the site, proving with a link sent to their address that the address is theirs.

```yaml
phil_privacy:
    public_origin: '%env(APP_PUBLIC_ORIGIN)%'   # https://example.org
    guest_requests:
        enabled: true
        from: 'Privacy <privacy@example.org>'
```

It is off until `enabled` is true. Once on, nothing in it is optional: the build fails without `symfony/rate-limiter`,
without a sender of the links, a store of the requests, the lock or the clock, rather than open a form that sends mail
to anyone without its limits.

## The steps

1. The person opens `phil_privacy_guest_request` (`/privacy-choices/request`), chooses a copy or an erasure, and gives
   their address.
2. The site answers the same page whatever the address: "if this address can receive mail, a link is on its way". It
   sends the link to the address, normalized — the domain in lowercase, the part before the @ as written.
3. The link opens `phil_privacy_guest_confirm` (`/privacy-choices/request/{id}?token=…`), a page that says what will
   happen and asks to confirm. **Opening the link does nothing**: only the form of that page acts, so that a mail
   scanner that follows links changes nothing.
4. The form downloads the archive of what the site keeps about the address
   ([export-and-erasure.md](export-and-erasure.md#the-archive-of-an-access-answer)), or erases it from every module and
   says so. The link then no longer works.

An erasure that a module could not complete keeps the request: the page says to try again in a moment, with the same
link, until it expires; erasers are idempotent.

## The guards

- **No enumeration.** The form answers the same page whether the address is known or not. What the link finds is
  decided only once it is opened, by the owner of the mailbox.
- **A limit per client**, counted before anything is read, an invalid address included: `per_client`, 5 an hour by
  default, keyed on the client's IP. Over it, the form answers `429` with `Retry-After`.
- **A limit per address**: `per_address`, 3 a day by default. Over it, the link is not sent and the page does not say
  so: the limit protects a mailbox from being filled by someone else.
- **No address or IP in the limiter's storage.** Both limits are keyed on an HMAC under a key derived from the
  application secret, in the cache pool of `guest_requests.cache` (`cache.app`).
- **A link works once, for a short time.** Its secret is 256 random bits; only its SHA-256 is kept, and it is compared
  in constant time. It expires after `link_lifetime` (`PT1H`). An expired request is removed when its link is opened,
  and at each new request.
- **The address is sealed at rest.** The table `phil_privacy_guest_request` keeps it encrypted with libsodium under a
  key derived from the application secret, only as long as the request.
- **One execution at a time.** An export or an erasure runs under a lock named after the request, read again once the
  lock is held. The lock lives 300 seconds and is renewed before each module and before the request is removed: an
  execution whose lock was taken by another stops, keeps the request, and answers as a link that no longer opens it
  ([Locks and long work](export-and-erasure.md#locks-and-long-work)).
- **No secret in a referrer or a queue.** The pages of the link send `Referrer-Policy: no-referrer` and are never kept by
  a cache; the mailer sender sends at once through the mailer's transports, never through a message queue where the
  link would be written down.
- **The link points to the site, whatever the request named.** See [Where the link points](#where-the-link-points).
- The forms carry the stateless CSRF token of the bundle.

## Who the address is

The modules export and erase by `DataSubject`. `GuestSubjects` says which identifier a verified address is: by default
the address itself (`EmailGuestSubjects`), for modules that index the records of people without an account on their
address. An application whose modules index them otherwise names its own service under `guest_requests.subjects`.

```php
final readonly class Subscribers implements GuestSubjects
{
    public function subjectOf(string $email): DataSubject
    {
        return DataSubject::fromString('subscriber:'.hash('sha256', $email));
    }
}
```

## The link

`MailerGuestLinkSender` renders `@PhilPrivacy/guest/email.txt.twig` — its block `subject` is the subject — and sends it
from `guest_requests.from`. Another way of sending is a `GuestLinkSender` named under `guest_requests.sender`; it keeps
the secret out of any log or queue.

| Key | Default | Effect |
|---|---|---|
| `enabled` | `false` | Turns the requests on. While off, their pages answer `404`. |
| `link_lifetime` | `PT1H` | How long a link works. |
| `from` | `null` | The sender of the links; required by the mailer sender. |
| `sender` | `null` | Service id of a `GuestLinkSender`. |
| `subjects` | `null` | Service id of a `GuestSubjects`. |
| `store` | `null` | Service id of a `GuestRequestStore`; `null`, the table of the bundle. |
| `cache` | `cache.app` | The cache pool the limits are counted in. |
| `per_client` | `{ limit: 5, interval: '1 hour' }` | The limit per client. |
| `per_address` | `{ limit: 3, interval: '1 day' }` | The limit per address. |

## Where the link points

The link is an absolute address, and a request names its own host in its `Host` header. Without care, someone asks a
link for another person's address while naming their own host: the person receives a genuine link that leads to that
host, and one click hands over its secret. `MailerGuestLinkSender` therefore builds the link on `public_origin`, the
canonical address of the site, through `GuestLinkOrigin`: the router's context names that origin — scheme, host and
port — while the link is generated, and the request's context comes back at once; the base path of the application is
kept. The origin is read by `phil/url` when the kernel boots: an `http` or `https` address with a host name, no IP
address, no path, query or fragment; `localhost` only in debug mode.

Without `public_origin`, the link takes the host of the request, which `framework.trusted_hosts` must bound:

```yaml
framework:
    trusted_hosts: ['^example\.org$']
```

A request for another host is then refused with `400`. In the `prod` environment, while the requests are on with the
mailer sender, the bundle refuses to boot when neither `public_origin` nor `framework.trusted_hosts` is set. A
`GuestLinkSender` of the application's own builds its link itself, and owes the same care.

The pages and the e-mail are in the `phil_privacy` domain, in English and French; override the templates under
`templates/bundles/PhilPrivacy/guest/` for your markup.
