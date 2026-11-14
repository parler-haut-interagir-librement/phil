# Export and erasure from the preferences

A member exercises their rights from the page `phil_privacy_preferences`: download everything the site holds about them,
as one ZIP archive with a readable `README.txt` and one JSON file per module
([export-and-erasure.md](export-and-erasure.md#the-archive-of-an-access-answer)), and delete their account. **Both are
on by default**, on every site. When the application turns it on, the same page asks the member whether the emails sent
to them may be tracked ([email-tracking.md](email-tracking.md)).

The bundle mounts its pages itself, in front of the application's routes, under `pages.prefix` (`/privacy-choices`):

| Route | Path | Methods |
|---|---|---|
| `phil_privacy_preferences` | `/` | `GET` |
| `phil_privacy_export` | `/export` | `POST` |
| `phil_privacy_email_tracking` | `/email-tracking` | `POST`; a `404` until `email_tracking.enabled` |
| `phil_privacy_erasure` | `/erasure` | `GET`, `POST` |
| `phil_privacy_erasure_cancel` | `/erasure/cancel` | `POST` |
| `phil_privacy_consent` | `/consent` | `GET` |
| `phil_privacy_consent_decide` | `/consent` | `POST` |
| `phil_privacy_cookies` | `/cookies` | `GET` |
| `phil_privacy_guest_request` | `/request` | `GET`, `POST` |
| `phil_privacy_guest_confirm` | `/request/{id}` | `GET`, `POST` |

To mount them elsewhere, set `pages.auto_mount: false` and import `@PhilPrivacyBundle/config/routes.php`. No address
carries anything about a person: the link of a request made without an account carries a random identifier and a
single-use secret.

The preferences must be behind a firewall, for the bundle to know who is asking. The consent, the cookie policy and the
requests without an account ([requests-without-an-account.md](requests-without-an-account.md)) are open to everyone.

## The member

`CurrentDataSubject` says who the current member is. By default it is the signed-in user, whose class implements
`DataSubjectUser`:

```php
final class User implements UserInterface, DataSubjectUser
{
    public function dataSubjectIdentifier(): string
    {
        return $this->id->toRfc4122();
    }
}
```

A user class that does not is refused rather than read from `getUserIdentifier()`, which is often an e-mail address.
Another source is a service named in `pages.current_subject`.

## The confirmation

The confirmation of a deletion uses the sudo mode of Symfony: `IS_AUTHENTICATED_RECENTLY`, on the page and on the form.
A session that has not proved itself recently is sent to the firewall's `re_authentication_entry_point`, and comes back
to the page. Without an entry point the request is refused with a `403`: configure one.

```yaml
security:
    firewalls:
        main:
            re_authentication_entry_point: form_login
```

Forms are protected by the stateless CSRF token `phil_privacy`. An export is one at a time per member.

## Turning a right off

> [!CAUTION]
> **Leave both rights on.** Turning one off takes a legal right away from every member of the site at once: they can no
> longer obtain their data, or no longer delete their account, without writing to you, and you must then answer each
> request by hand within the legal delay.

**This is a destructive gesture**, and the bundle treats it as a code host treats the deletion of a repository: there is
no boolean. The configuration holds the full sentence, and anything else stops the build.

```yaml
phil_privacy:
    rights:
        export:
            disable: 'I understand that members of this site will no longer be able to export their personal data from their preferences'
        erasure:
            disable: 'I understand that members of this site will no longer be able to erase their account and personal data from their preferences'
```

A right turned off raises a warning that frames the standard error at every container build, and a red `DANGER` line
before every console command. Its routes answer `404` and its section leaves the page. The commands `phil:privacy:export`
and `phil:privacy:erase` remain, for the person who answers requests by hand.
