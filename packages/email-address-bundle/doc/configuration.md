# Configuration

The bundle's configuration lives under `phil_email_address`. Every option has a default; the example sets most of
them:

```yaml
# config/packages/phil_email_address.yaml
phil_email_address:
    # The bundle's policy.
    check_mail_server: false
    allow_disposable: false
    known_top_level_domain: true
    international_local_part: false
    denied_domains: [spam.example]
    allowed_domains: []
    disposable_domains: [throwaway.example]
    role_local_parts: ~
    fingerprint_key: '%kernel.secret%'

    # The policy of each site, then of each list, laid over the one above.
    current_site: App\Site\CurrentSite
    sites:
        shop: { check_mail_server: true }
    lists:
        staff: { allowed_domains: [example.org] }
        newsletter: { denied_domains: [competitor.example] }

    dns:
        resolver: ~
        cache: cache.app
        ttl: 3600

    suggestions:
        domains: ~
        url: /email-address/domains.json

    bounces:
        enabled: true
        soft_bounce_threshold: 3
        soft_bounce_window: P30D
        history: cache.app
        authserv_ids: [mx.example.net]
        feedback_domains: [fbl.example.com]
        authenticator: ~
        verp:
            enabled: true
            local_part: bounces
            domain: example.net
            separator: '+'
            key: '%env(BOUNCE_VERP_KEY)%'
        remote_event_consumers: [mailer_provider]
        recipient_metadata: recipient
        message_bus: messenger.default_bus
        mailbox: ~
        maildir: '%kernel.project_dir%/var/mail/bounces'
```

## The policy

| Option | Default | What it does |
|---|---|---|
| `check_mail_server` | `false` | Looks up a mail server of the domain through the configured resolver; a domain whose lookup fails is not refused. |
| `allow_disposable` | `false` | Accepts the domains that give disposable mailboxes. |
| `known_top_level_domain` | `true` | Refuses a domain whose top-level domain the root zone does not delegate. |
| `international_local_part` | `false` | Accepts international characters before the @ (RFC 6531). |
| `denied_domains` | `[]` | Domains refused, with their subdomains. |
| `allowed_domains` | `[]` | The only domains accepted, with their subdomains; an empty list accepts every domain. |
| `disposable_domains` | `[]` | Disposable domains added to the list the library imports. |
| `role_local_parts` | the bundle's list | The parts before the @ that name a role — warned about, never refused. |
| `fingerprint_key` | `%kernel.secret%` | The secret the key of the address fingerprints is derived from, by HKDF-SHA256 under `phil_email_address.fingerprint`: the fingerprints never match a hash another usage of the same secret keeps for the address. |

`sites` and `lists` take the same options but `disposable_domains`, `role_local_parts` and `fingerprint_key`. Each
option they leave out, or set to `null`, keeps the value of the policy under them: a site's is laid over the bundle's,
a list's over the site's. Denied domains add up. `allowed_domains` is the most specific policy's that names it: a
list's empty list accepts every domain again.

`current_site` is the id of a service implementing `CurrentSite`, whose key picks the policy of `sites`; without it,
no site's policy applies. A form or a constraint that names a list `lists` does not hold throws `UnknownEmailList`.

The policy applies to `AcceptedEmailAddress` and to `EmailAddressType`. The library's `DeliverableEmailAddress`, used
directly, keeps the options its attribute gives and gets only the container's lists and resolver.

## The DNS

| Option | Default | What it does |
|---|---|---|
| `dns.resolver` | `null` | The id of a service implementing `MailDomainResolver`; `null` asks the system resolver through `dns_get_record()`. |
| `dns.cache` | `cache.app` | The cache pool that keeps the answers; `null` keeps none. A failed lookup is kept 60 seconds. |
| `dns.ttl` | `3600` | How long an answer is kept, in seconds. |

## The suggestions

| Option | Default | What it does |
|---|---|---|
| `suggestions.domains` | the library's list | The domains suggested, in lowercase ASCII, most used first; the server and the browser use the same list. |
| `suggestions.url` | `null` | The URL of the route `phil_email_address_domains`; `null` leaves `EmailAddressType` without its Stimulus controller. |

## The bounces

| Option | Default | What it does |
|---|---|---|
| `bounces.enabled` | `true` | Registers the bounce rules and their inputs; `false` registers none. |
| `bounces.soft_bounce_threshold` | `3` | The soft bounces within the window that suspend an address. |
| `bounces.soft_bounce_window` | `P30D` | The sliding window soft bounces are counted in, a positive ISO 8601 duration. A notice is also decided once within it. |
| `bounces.history` | `cache.app` | The cache pool that keeps the notices decided and the soft bounces. It must keep its items until they expire and be shared by every server that receives bounces. |
| `bounces.authserv_ids` | `[]` | The names your mail server writes its `Authentication-Results` under. |
| `bounces.feedback_domains` | `[]` | The DKIM signing domains of the feedback loops you subscribed to. With `authserv_ids`, an abuse report signed by one of them is authenticated. |
| `bounces.authenticator` | `null` | The id of a service implementing `ReportAuthenticator`, in place of the two options above. |
| `bounces.verp` | disabled | The return path the messages are sent with; a tag signed with `key` names the recipient. `domain` is required when it is enabled. |
| `bounces.remote_event_consumers` | `[]` | The names of the webhooks, under `framework.webhook.routing`, whose mailer events the rules consume. |
| `bounces.recipient_metadata` | `null` | The key of the message metadata that holds the recipient identifier, in the events of a webhook. |
| `bounces.message_bus` | `null` | The id of a message bus each decision is sent on to, as a `BounceDecidedMessage` without the address. |
| `bounces.mailbox` | `null` | The id of a service implementing `BounceMailbox`, which `phil:email-address:poll-mailbox` reads. |
| `bounces.maildir` | `null` | A Maildir the mail server delivers bounces to, read in place of `mailbox`. |

Without `authserv_ids` and `feedback_domains`, nor an `authenticator`, every abuse report goes to review: anyone can
write one to the bounce mailbox.
