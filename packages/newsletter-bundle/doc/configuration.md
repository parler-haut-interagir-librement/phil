# Configuration

The bundle's configuration lives under `phil_newsletter`:

```yaml
# config/packages/phil_newsletter.yaml
phil_newsletter:
    secret: '%env(NEWSLETTER_SECRET)%'
    connection: doctrine.dbal.default_connection
    check_mail_domain: true
    site: default
    sites:
        default: { base_url: 'https://example.com' }
    default_profile: news
    profiles:
        news:
            from: 'news@example.com'
            from_name: 'Example'
            reply_to: 'hello@example.com'
            transport: newsletter
            unsubscribe_mailbox: 'leave@example.com'
            window: { days: [1, 2, 3, 4, 5], from: '09:00', until: '17:00', timezone: 'America/Toronto' }
            daily_cap: 5000
            rate: 120
            domain_rates: { gmail.com: 60, outlook.com: 60 }
    clicks:
        minimum_seconds: 10
    bounces:
        soft_threshold: 3
        verp: { local_part: bounces, domain: 'example.com', separator: '+' }
        imap: { host: 'imap.example.com', port: 993, user: 'bounces@example.com', password: '%env(BOUNCES_PASSWORD)%', folder: INBOX, delete: false }
        remote_event_consumer: mail_provider
    rate_limiter_storage: cache.rate_limiter
    subscription:
        rate_limit:             # "rate_limit: false" turns the limits off
            per_ip: { limit: 10, interval: '1 hour' }
            per_address: { limit: 5, interval: '1 hour' }
            resend_delay: '5 minutes'
    webhooks:
        crm: { url: 'https://crm.example.com/hooks/newsletter', events: [confirmed, unsubscribed] }
    preview_role: ROLE_EDITOR
    approval:
        required: true
        role: ROLE_NEWSLETTER_APPROVER
        four_eyes: true
    circuit_breaker:            # "circuit_breaker: false" turns it off
        minimum: 100
        transport_failure_rate: 10
        bounce_rate: 5
        complaint_rate: 0.3
```

| Option | Default | What it does |
|---|---|---|
| `enabled` | `true` | Registers the bundle's services; `false` registers none. |
| `secret` | `%kernel.secret%` | The key of the signed links, the VERP tags and the suppression hashes: at least 16 bytes, never a placeholder that begins with `change-me` or `changeme`. The keys of the VERP tags and of the suppression hashes are derived from its value at run time, an environment variable included. A new key breaks the links of every message sent. |
| `connection` | `doctrine.dbal.default_connection` | The DBAL connection of the tables. |
| `check_mail_domain` | `true` | Whether the worker that sends the confirmation of a double opt-in asks the DNS first that the address's domain receives mail, and refuses the subscription when it does not; the request never waits on the DNS. |
| `site` | `default` | The site of a standalone application. |
| `sites.<key>.base_url` | — | Each site's base address, before the paths of the links in messages. Without it, the router's context. |
| `default_profile` | `default` | The profile of a list without its own, and of a template sent on its own. |
| `profiles.<name>` | one profile `default` from `newsletter@example.com` | The sender profiles. See [Campaigns](campaigns.md#sender-profiles). |
| `clicks.minimum_seconds` | `10` | A click this soon after its message was sent is a machine's, not counted. |
| `bounces.soft_threshold` | `3` | Soft bounces in a row after which an address counts as bounced. |
| `bounces.verp` | off | The signed return path of each message, so that a bounce names its delivery. |
| `bounces.imap` | off | The bounce mailbox, read over implicit TLS (port 993, RFC 8314; no STARTTLS) every five minutes. |
| `bounces.remote_event_consumer` | `null` | The webhook route name of the mail provider whose events the bundle consumes. |
| `rate_limiter_storage` | `cache.rate_limiter` | The cache pool the pace and the limits of the subscription form are counted in; shared by every worker and web server. |
| `subscription.rate_limit.enabled` | `true` | Limits the public subscription form and the confirmations of a double opt-in; `rate_limit: false` turns the limits off. See [The limits of the form](subscriptions.md#the-limits-of-the-form). |
| `subscription.rate_limit.per_ip.limit`, `.interval` | `10`, `1 hour` | Subscriptions per client address, or IPv6 /64 network, in each interval. |
| `subscription.rate_limit.per_address.limit`, `.interval` | `5`, `1 hour` | Subscriptions per address typed, on each site, subscribed or not, in each interval. |
| `subscription.rate_limit.resend_delay` | `5 minutes` | The delay before another confirmation goes to the same subscriber. |
| `webhooks.<name>` | none | Receivers of the audience events; `events` empty sends all. Needs `phil/standard-webhooks-bundle`. |
| `preview_role` | `ROLE_ADMIN` | The role the preview of the editor answers. |
| `approval.required` | `true` | Whether a campaign is sent, or started at its time, only once approved. See [Approval](campaigns.md#approval). |
| `approval.role` | `ROLE_NEWSLETTER_APPROVER` | The role that approves campaigns. |
| `approval.four_eyes` | `false` | Whether the approver must be someone other than the author of the campaign. |
| `circuit_breaker.enabled` | `true` | Pauses a send run whose transport failures, bounces or complaints climb; `circuit_breaker: false` turns it off. See [Send runs and the circuit breaker](campaigns.md#send-runs-and-the-circuit-breaker). |
| `circuit_breaker.minimum` | `100` | The messages tried, since the run started or was resumed, before the shares are read. |
| `circuit_breaker.transport_failure_rate` | `10` | The share of attempts the mail transport refused, in percent, past which the run pauses. |
| `circuit_breaker.bounce_rate` | `5` | The share of messages sent that bounced, in percent, past which the run pauses. |
| `circuit_breaker.complaint_rate` | `0.3` | The share of messages sent reported as spam, in percent, past which the run pauses. |

Each profile:

| Key | Default | What it does |
|---|---|---|
| `from`, `from_name` | — , `''` | The sender. |
| `reply_to` | `null` | The reply address. |
| `transport` | `null` | A transport of `framework.mailer.transports`, chosen with `X-Transport`; the default transport otherwise. |
| `unsubscribe_mailbox` | `null` | An address added to `List-Unsubscribe` as a `mailto:`. |
| `window` | every day, all day, UTC | The days (1 for Monday to 7) and hours messages go out in. |
| `daily_cap` | `null` | The messages sent at most in a day. |
| `rate` | `120` | Messages per minute to one recipient domain. |
| `domain_rates` | none | Messages per minute to the domains named. |
