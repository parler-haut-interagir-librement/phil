# Configuration

Every option of `phil_identity_provider_google`, with its default.

```yaml
phil_identity_provider_google:
    enabled: true
    client_id: ~                    # required
    client_secret: ~                # required
    hosted_domains: []
    profile: false
    prompt: select_account
    clock_skew: 60
    attempt_lifetime: 600
    cache_pool: cache.app
    http_client: ~
    clock: clock
    risc:
        enabled: false
        audiences: []
        max_age: 604800
        hold_hijacked: true
        holds_pool: ~
```

| Option | Default | What it does |
|---|---|---|
| `enabled` | `true` | Registers the bundle's services. |
| `client_id` | required | The client ID of the application at Google, such as `1234-abc.apps.googleusercontent.com`. |
| `client_secret` | required | Its client secret, read from Symfony secrets: `'%env(GOOGLE_CLIENT_SECRET)%'`. It is sent to Google's token endpoint by HTTP Basic, and nowhere else. |
| `hosted_domains` | `[]` | The Google Workspace domains accepted, in lowercase. Empty, any Google account signs in, consumer accounts included. With domains, an account whose `hd` claim is not one of them is refused; the `hd` parameter of the authorization request is the domain, or `*` for several, which only narrows Google's account chooser. |
| `profile` | `false` | Whether the person's name is asked for, with the scope `profile`, and handed to the bundle. Only the address is asked for by default. |
| `prompt` | `select_account` | The `prompt` sent to Google: `none`, `consent`, `select_account`, or `default` to send none. |
| `clock_skew` | `60` | The seconds Google's clock may differ from the application's, 0 to 300, for the times of the ID token. |
| `attempt_lifetime` | `600` | The seconds a sign-in waits for Google to send the person back, 60 to 3600. |
| `cache_pool` | `cache.app` | The cache pool of Google's discovery documents and keys, and of the identifiers of the security events received. |
| `http_client` | none | The HTTP client Google is called with; phil/safe-http wraps it. phil/safe-http's own by default. |
| `clock` | `clock` | The PSR-20 clock of the time checks. |
| `risc.enabled` | `false` | Whether the endpoint receives Google's security events; it answers 404 otherwise. |
| `risc.audiences` | `[]` | The client IDs the events may be meant for; the client ID by default. |
| `risc.max_age` | `604800` | The seconds a security event token may be old, 60 to 30 days; its identifier is kept as long to refuse a replay. |
| `risc.hold_hijacked` | `true` | Whether a Google account disabled as hijacked may not sign in until Google enables it again. |
| `risc.holds_pool` | the cache pool | The PSR-6 pool of the held accounts. It must keep what it is given: see [Cross-Account Protection](risc.md#held-accounts). |

## What is cached

| Entry | Lifetime |
|---|---|
| Google's discovery document | 24 hours |
| The configuration document of Cross-Account Protection | 24 hours |
| Google's keys | As long as Google's `Cache-Control` says, 30 days at most, one hour when it says nothing; fetched again when a token names a key the cache does not hold, at most once a minute |
| The identifier of a security event token received | `risc.max_age` |
| A held account | Until Google enables it again |
