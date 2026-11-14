# Tracked emails

An email can tell its sender that it was opened — an invisible image loaded from the sender's server — and which of its
links were clicked — each link passing through the sender first. Both read something from the recipient's device and
tie it to a person, so they wait for that person's consent. The bundle asks each member, on their preferences page, and
answers the code that sends the emails.

**Off by default.** A site that does not track its emails asks nothing; a site that does turns the consent on:

```yaml
phil_privacy:
    email_tracking:
        enabled: true
        version: '1'          # the version of the text the member reads
```

## What the member sees

The preferences page ([preferences.md](preferences.md)) gains a section with two boxes, one per purpose, both unticked
until the member ticks them:

- count when I open an email;
- follow the links I click in an email.

The form posts to `phil_privacy_email_tracking` (`/email-tracking`), with the stateless CSRF token of the bundle, and
comes back to the page with a `303`. A box left unticked refuses: withdrawing is the same form, as simple as granting.
The page shows the version of the text and the date of the last choice.

## What the sender asks

`EmailTrackingConsents` is a public service. The code that sends an email asks it, with the member's data subject
identifier, before it turns on a pixel or redirected links:

```php
use Phil\PrivacyBundle\Application\EmailTracking\EmailTrackingConsents;

$opens = $this->consents->opens($memberId);
$clicks = $this->consents->clicks($memberId);
```

Each answer is no unless the member chose yes under the current version of the text: a member who never chose, an
identifier no member can have (empty, too long, with whitespace), or a choice made under an earlier version all answer
no. Each question reads one row by its primary key; nothing is kept between requests.

With `phil/notifications-bundle`, whose digests ask its `EmailTrackingConsent` port, the application delegates to it:

```php
use Phil\NotificationsBundle\Application\Contract\EmailTrackingConsent;
use Phil\PrivacyBundle\Application\EmailTracking\EmailTrackingConsents;

final readonly class MemberEmailTracking implements EmailTrackingConsent
{
    public function __construct(private EmailTrackingConsents $consents) {}

    public function opens(string $recipient): bool
    {
        return $this->consents->opens($recipient);
    }

    public function clicks(string $recipient): bool
    {
        return $this->consents->clicks($recipient);
    }
}
```

```yaml
phil_notifications:
    digest:
        tracking_consent: App\Privacy\MemberEmailTracking
```

The recipient of a notification must then be the member's data subject identifier, the one `DataSubjectUser` gives.

## The version of the text

A member agreed to a text. When the text changes — another purpose, another processor —, change `version`: every
earlier choice stops counting, nothing is tracked, and the page asks again with both boxes unticked. A version is
letters, digits, `.`, `_` and `-`, at most 32, starting with a letter or a digit; anything else fails the build.

## The record

The table `phil_privacy_email_tracking`, added to the schema the ORM generates, keeps one row per member: the subject,
the version, `opens`, `clicks`, and the date of the choice in a `datetime_utc_immutable` column. The last choice replaces
the one before; a choice that refuses both stays, as the dated proof of the withdrawal. Another store is an
`EmailTrackingStore` named under `email_tracking.store` ([extension-points.md](extension-points.md#emailtrackingstore)).

Turned on, the consent needs a store and the clock; a missing one fails the build with a `LogicException` that names it.

## Export and erasure

`EmailTrackingConsents` is also the module `email_tracking` of the member's rights: their export holds
`email_tracking.json` with the version, `opens`, `clicks` and the date in UTC, and their erasure deletes the row. The
erasure is idempotent, as every module's must be ([export-and-erasure.md](export-and-erasure.md)).
