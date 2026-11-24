# Reference

Every public type of the package, with its role.

## The bundle

| Type | Role |
|---|---|
| `Phil\EmailAddressBundle\PhilEmailAddressBundle` | The bundle: its configuration `phil_email_address` and its services. |

## Addresses and policies

| Type | Role |
|---|---|
| `Phil\EmailAddressBundle\Application\EmailAddresses` | The normalized form of an address, the comparison of two, the fingerprint, and whether an address names a role. |
| `Phil\EmailAddressBundle\Application\EmailPolicy` | What a form accepts of an address; laid over another policy with `over()`, turned into the library's constraint with `constraint()`. |
| `Phil\EmailAddressBundle\Application\EmailPolicies` | The policy in force: the bundle's, the current site's and a list's, laid over each other. |
| `Phil\EmailAddressBundle\Application\Contract\CurrentSite` | The site the current request belongs to, whose key picks the site's policy. |
| `Phil\EmailAddressBundle\Application\Exception\UnknownEmailList` | A form or a constraint names a list the configuration does not hold. |
| `Phil\EmailAddressBundle\Domain\ValueObject\AddressFingerprint` | A keyed hash of an address's normalized form, carried instead of the address. |

## Forms, validation and HTTP

| Type | Role |
|---|---|
| `Phil\EmailAddressBundle\Infrastructure\Validator\Attribute\AcceptedEmailAddress` | The constraint of an address the policy in force accepts; option `list`. |
| `Phil\EmailAddressBundle\Infrastructure\Validator\AcceptedEmailAddressValidator` | Checks it through the library's `DeliverableEmailAddress`, in the current context. |
| `Phil\EmailAddressBundle\Infrastructure\Form\EmailAddressType` | The email field: the constraint, the Stimulus controller, the suggestion or warning as help once submitted. Options `list`, `accepted`, `suggest`. |
| `Phil\EmailAddressBundle\Infrastructure\Http\EmailDomainsController` | The route `phil_email_address_domains`: the known domains, the top-level slips and the role addresses, in JSON. |
| `Phil\EmailAddressBundle\Infrastructure\Dns\CachedMailDomainResolver` | Keeps the answers of another `MailDomainResolver` in a cache pool. |

## Bounces

| Type | Role |
|---|---|
| `Phil\EmailAddressBundle\Domain\NoticeKind` | What a notice says: `Hard`, `Soft`, `Complaint`, `Unsubscribe`, `Review`. |
| `Phil\EmailAddressBundle\Domain\NoticeSource` | Where it came from: `Message`, `Mailbox`, `Webhook`. |
| `Phil\EmailAddressBundle\Domain\BounceDecision` | What the rules decided: `Suspend`, `Count`, `Unsubscribe`, `Review`, `Duplicate`. |
| `Phil\EmailAddressBundle\Domain\ValueObject\BounceNotice` | One address a bounce speaks of, its kind, its report, its authentication and its recipient identifier. |
| `Phil\EmailAddressBundle\Domain\Event\BounceDecided` | The event of a decision, with its notice and the soft bounces counted. |
| `Phil\EmailAddressBundle\Domain\Contract\BounceHistoryRepository` | What the rules remember: the notices decided and the soft bounces. |
| `Phil\EmailAddressBundle\Application\Bounces\BounceRules` | Decides a notice and dispatches the event. |
| `Phil\EmailAddressBundle\Application\Bounces\BounceNotices` | Turns a report of the bounce parser, or an event of a webhook, into notices. |
| `Phil\EmailAddressBundle\Application\Bounces\BounceIngestor` | Parses a raw message and has the rules decide its notices. |
| `Phil\EmailAddressBundle\Application\Bounces\MailboxPoller` | Reads the bounce mailbox, ingests each message and acknowledges it. |
| `Phil\EmailAddressBundle\Application\Contract\BounceMailbox` | The port of the bounce mailbox. |
| `Phil\EmailAddressBundle\Application\MailboxMessage` | One message of the mailbox: its identifier and its raw text. |
| `Phil\EmailAddressBundle\Application\Message\BounceDecidedMessage` | A decision sent on through Messenger, with identifiers only. |
| `Phil\EmailAddressBundle\Infrastructure\Cache\CacheBounceHistoryRepository` | The history in a cache pool, every key a hash. |
| `Phil\EmailAddressBundle\Infrastructure\InMemory\InMemoryBounceHistoryRepository` | The history in memory, for tests. |
| `Phil\EmailAddressBundle\Infrastructure\Mailbox\MaildirBounceMailbox` | The bounce mailbox as a Maildir. |
| `Phil\EmailAddressBundle\Infrastructure\RemoteEvent\MailerEventConsumer` | Consumes the mailer events of a provider's webhook. |
| `Phil\EmailAddressBundle\Infrastructure\Messenger\ForwardBounceDecision` | Sends each decision on as a `BounceDecidedMessage`. |
| `Phil\EmailAddressBundle\Infrastructure\Console\ReadBounceCommand` | `phil:email-address:read-bounce [file]`: a raw message from a file or the standard input. |
| `Phil\EmailAddressBundle\Infrastructure\Console\PollMailboxCommand` | `phil:email-address:poll-mailbox [--limit=50]`: the bounce mailbox. |

## Services

| Service | Class |
|---|---|
| `Phil\EmailAddress\Validator\DeliverableEmailAddressValidator` | The library's validator, with the container's lists, resolver and suggester. |
| `Phil\EmailAddress\Contract\MailDomainResolver` | `CachedMailDomainResolver` over `DnsGetRecordResolver`, or the configured resolver. |
| `Phil\EmailAddress\TypoSuggester` | The suggester, with the configured domains. |
| `phil_email_address.disposable_domains`, `phil_email_address.top_level_domains` | The library's lists, with the configured disposable domains. |
| `Phil\BounceParser\BounceParser` | The parser, with the configured return path and authenticator. |
| `Phil\EmailAddressBundle\Domain\Contract\BounceHistoryRepository` | `CacheBounceHistoryRepository` over `bounces.history`. |

## The Stimulus controller

`phil--email-address-bundle--email-address`, on the field. Values: `url`, the route's URL; `suggestion`, the message
with `%address%`; `use`, the label of the button that takes the suggestion; `role`, the warning. Action: `check`, on
`blur`. Events: `hint` with what it found — `{kind: 'suggestion', address}`, `{kind: 'role'}` or `null` —, `applied`
with the address taken, `failed` with the error when the lists cannot be read.
