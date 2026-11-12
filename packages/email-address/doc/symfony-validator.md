# Symfony Validator

`Validator\DeliverableEmailAddress` is a constraint of the Symfony Validator that runs `EmailValidator` on a property
or a getter: the syntax, the top-level domain, the application's domains, the disposable domains and, when asked, the
mail server of the domain. It names the first problem found.

```php
use Phil\EmailAddress\Validator\DeliverableEmailAddress;
use Symfony\Component\Validator\Constraints\NotBlank;

final class SignUp
{
    #[NotBlank]
    #[DeliverableEmailAddress(checkMailServer: true, deniedDomains: ['competitor.example'])]
    public string $email = '';
}
```

`null` and the empty string are valid, as for every constraint of Symfony: add `NotBlank` to require a value. A value
is a string, a `Stringable` or an `EmailAddress`; anything else throws `UnexpectedValueException`.

## Options

| Option | Default | Effect |
|---|---|---|
| `internationalLocalPart` | `false` | Accepts a part before the @ outside ASCII (RFC 6531) |
| `quotedLocalPart` | `false` | Accepts a quoted part before the @ |
| `ipLiteral` | `false` | Accepts an IP literal such as `ada@[192.0.2.1]` |
| `knownTopLevelDomain` | `true` | Refuses a domain under a top-level domain the root zone does not delegate |
| `allowDisposable` | `false` | Accepts the domains of disposable mailboxes |
| `checkMailServer` | `false` | Asks the DNS whether a server receives mail for the domain |
| `deniedDomains` | `[]` | Domains refused, with their subdomains |
| `allowedDomains` | `[]` | The only domains accepted, with their subdomains; none accepts every domain |

The DNS is off by default: it makes a validation wait on the network, and a test or a form that validates on every
keystroke does not want it. Turn it on where an address must receive mail before anything else happens, at sign-up.

## Messages and codes

| Problem | Message option | Code constant |
|---|---|---|
| The syntax | `message` | `SYNTAX_ERROR` |
| An unknown top-level domain | `unknownTopLevelDomainMessage` | `UNKNOWN_TOP_LEVEL_DOMAIN_ERROR` |
| A denied domain | `deniedDomainMessage` | `DENIED_DOMAIN_ERROR` |
| A domain not allowed | `domainNotAllowedMessage` | `DOMAIN_NOT_ALLOWED_ERROR` |
| A disposable domain | `disposableMessage` | `DISPOSABLE_ERROR` |
| No mail server | `noMailServerMessage` | `NO_MAIL_SERVER_ERROR` |
| A null MX | `refusesMailMessage` | `REFUSES_MAIL_ERROR` |

Every message receives four parameters: `{{ value }}`, the value; `{{ domain }}`, its domain in Unicode, empty when
the syntax fails; `{{ reason }}`, the `SyntaxError` described, empty for the other problems; and `{{ suggestion }}`,
the address the person probably meant, or an empty string. A form shows the suggestion as a question, "Did you mean
…?", and never replaces the value with it.

The messages are in English and pass through the `validators` domain of the Translator like those of Symfony: an
application translates them in its own `translations/validators.<locale>.xlf`. The library ships no catalogue, as it
registers nothing in a container.

## The validator

`Validator\DeliverableEmailAddressValidator` is built without arguments when the Validator creates it outside a
container: it then reads the bundled lists once, on the first value that needs them, and asks `dns_get_record()`. An
application that declares it as a service, tagged `validator.constraint_validator`, passes its own lists, resolver and
suggester — a resolver with a cache, a merged list of disposable domains — as [Installation](installation.md) shows.

It keeps the lists it read, the same for every request, and nothing of one value for the next: one instance serves
every request of a long-running worker.
