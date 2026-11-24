# phil/email-address-bundle

**Accepts email addresses by a policy per site and per list, suggests the address meant in the browser, and turns
bounces and complaints into decisions, in Symfony.**

phil/email-address-bundle integrates `phil/email-address` and `phil/bounce-parser` with Symfony. The libraries alone
suit an application without Symfony.

- **The constraint on the container.** The library's `DeliverableEmailAddress` gets the container's lists and
  resolver; `AcceptedEmailAddress` applies the policy of the bundle, of the current site and of a list, laid over each
  other: mail server looked up or not, disposable domains, denied domains, the only domains accepted.
- **The email field.** `EmailAddressType` suggests the address meant when its domain is a slip of a known one —
  `ada@gmial.com` — and warns about a role address such as `info@`, without refusing either: in the browser through a
  Stimulus controller and a live region, and without JavaScript as the field's help once the form is submitted.
- **The normalized form.** `EmailAddresses` compares two addresses, keys them by a fingerprint that does not give the
  address back, and tells a role address; it keeps the dots and the `+tag` of the person's address.
- **The bounce rules.** A hard bounce suspends the address; soft bounces count up to a threshold within a window; an
  authenticated complaint unsubscribes, and one no authenticator vouches for goes to review. Each decision is a
  Symfony event, and can go on to Messenger with identifiers only.
- **Three inputs.** The mailer events of a provider's webhook, through Symfony's Webhook and RemoteEvent components;
  a raw message piped by the mail server to a console command; the bounce mailbox, through the `BounceMailbox` port.

## Status

| Part | State |
|---|---|
| `PhilEmailAddressBundle` and its configuration `phil_email_address` | Implemented and tested |
| `DeliverableEmailAddress` on the container, `AcceptedEmailAddress` and the policies per site and per list | Implemented and tested |
| `EmailAddressType`, the route `phil_email_address_domains` and the Stimulus controller | Implemented and tested |
| `EmailAddresses`: normalized form, fingerprint, role addresses | Implemented and tested |
| `BounceRules`, the event `BounceDecided` and `BounceDecidedMessage` | Implemented and tested |
| The webhook consumer and the command `phil:email-address:read-bounce` | Implemented and tested |
| The port `BounceMailbox`, its Maildir adapter and the command `phil:email-address:poll-mailbox` | Implemented and tested |
| Reading an IMAP mailbox | Not in this bundle: fetchmail, getmail or the mail server's local delivery fills the Maildir; `BounceMailbox` leaves room for a separate IMAP package |
| The Flex recipe, in `recipe/0.1` | Prepared |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the routes and the Stimulus controller. |
| [Configuration](doc/configuration.md) | Every option, with an example: policies, DNS, suggestions, bounces. |
| [Bounces](doc/bounces.md) | The bounce rules, their three inputs, the event and the message. |
| [Extension points](doc/extension-points.md) | The services to replace, the events to listen to, and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests, PHP and JavaScript. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```php
// config/bundles.php
Phil\EmailAddressBundle\PhilEmailAddressBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_email_address.yaml
phil_email_address:
    lists:
        staff: { allowed_domains: [example.org] }
    suggestions:
        url: /email-address/domains.json
```

```php
use Phil\EmailAddressBundle\Infrastructure\Form\EmailAddressType;

$builder->add('email', EmailAddressType::class, ['list' => 'staff']);
```

```php
use Phil\EmailAddressBundle\Domain\BounceDecision;
use Phil\EmailAddressBundle\Domain\Event\BounceDecided;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class SuspendBouncedAddresses
{
    public function __invoke(BounceDecided $event): void
    {
        if (BounceDecision::Suspend === $event->decision) {
            // Stop sending to $event->notice->address, or to the recipient $event->notice->recipientId.
        }
    }
}
```

## Requirements

PHP 8.5 or later and Symfony 8.2 or later. The form field needs `symfony/form`, the commands `symfony/console`, the
webhook consumer `symfony/remote-event`, the forwarding of decisions `symfony/messenger`, and the route
`symfony/routing`; the bundle registers each part only when its package is installed.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
