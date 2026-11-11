# Configuration

An example, with every document on; the defaults are in the tables below.

```yaml
phil_well_known:
    cache_max_age: 3600
    security_txt:
        contact: ['mailto:security@example.com', 'https://example.com/security']
        expires: '2027-06-30T00:00:00Z'
        encryption: ['https://example.com/pgp-key.txt']
        acknowledgments: ['https://example.com/hall-of-fame']
        preferred_languages: [fr, en]
        canonical: ['https://example.com/.well-known/security.txt']
        policy: ['https://example.com/disclosure']
        hiring: ['https://example.com/jobs']
        signer: App\Security\GpgSigner
        expiry_warning_days: 30
    change_password:
        route: account_password
        parameters: { tab: security }
    webfinger:
        resources:
            'acct:contact@example.com':
                aliases: ['https://example.com/']
                properties: { 'http://schema.org/name': Example }
                links:
                    - { rel: 'http://webfinger.net/rel/profile-page', type: text/html, href: 'https://example.com/' }
    nodeinfo:
        path: /nodeinfo/2.1
        software: { name: example, version: 1.4.0, repository: 'https://example.com/code' }
        protocols: [activitypub]
        services: { inbound: [], outbound: [rss2.0] }
        open_registrations: false
        metadata: { nodeName: Example }
        provider: App\Federation\NodeInfo
    did:
        file: '%kernel.project_dir%/config/well-known/did.json'
    did_configuration:
        files: ['%kernel.project_dir%/config/well-known/domain-linkage.jwt']
    atproto:
        did: 'did:plc:ewvi7nxzyoun6zhxrhs64oiz'
        hosts: { 'alice.example.com': 'did:plc:4c6myh2vbevdtl3jb5zn6s7a' }
    apple_app_site_association:
        document:
            applinks: { details: [{ appIDs: [ABCDE12345.com.example.app], components: [{ '/': '/events/*' }] }] }
    asset_links:
        android_apps:
            - package_name: com.example.app
              sha256_cert_fingerprints: ['14:6D:E9:83:C5:73:06:50:D8:EE:B9:95:2F:34:FC:64:16:A0:83:42:E6:1D:BE:A8:8A:04:96:B2:3F:CF:44:E5']
    documents:
        'matrix/server':
            content: '{"m.server": "matrix.example.com:443"}'
            content_type: application/json
            cors: true
```

A section is turned on by any of its keys, by `true`, or by `enabled: true`; `enabled: false` turns it off and keeps
its values, which are still checked.

| Option | Default | Role |
|---|---|---|
| `cache_max_age` | `3600` | The seconds a successful answer may be kept by browsers and shared caches; `0` keeps it in none ([Documents](documents.md#headers)) |

## security_txt

| Option | Default | Role |
|---|---|---|
| `contact` | `[]` | `mailto:`, `tel:` or `https://` URIs; at least one |
| `expires` | `null` | Required: a date such as `2027-06-30` or `2027-06-30T00:00:00Z`, read in UTC without an offset, in the future when the container is built; a relative date such as `+1 year` is refused |
| `encryption` | `[]` | `https://`, `dns:` or `openpgp4fpr:` URIs of the key reports are encrypted with |
| `acknowledgments` | `[]` | `https://` URLs of the page that thanks reporters |
| `preferred_languages` | `[]` | Language tags, written on one line |
| `canonical` | `[]` | `https://` URLs where the file is published |
| `policy` | `[]` | `https://` URLs of the disclosure policy |
| `hiring` | `[]` | `https://` URLs of security jobs |
| `signer` | `null` | The id of a `SecurityTxtSigner` service; the file is served signed by it |
| `expiry_warning_days` | `30` | 1 to 365: the days before `Expires` from which the health check is degraded ([Documents](documents.md#the-expiry-as-a-health-check)) |

No value holds a space or a control character, and none is longer than 2,048 bytes. RFC 9116 advises an `Expires`
less than a year away: a deployment that renews it renews the file. Since the date is checked when the container is
built, a container built before the date and kept after it serves a file that has expired; rebuilding it fails.

## change_password

| Option | Default | Role |
|---|---|---|
| `route` | `null` | The route of the page where a user changes their password |
| `parameters` | `[]` | The parameters of that route |
| `url` | `null` | Or a URL: `https://`, or a path starting with `/` but not `//` |

One of `route` and `url` is required.

## webfinger

| Option | Default | Role |
|---|---|---|
| `resources` | `{}` | Fixed resources, keyed by an `acct:` or `https:` URI, with `subject`, `aliases`, `properties` and `links` (`rel`, `type`, `href`, `template`, `titles`, `properties`) |

A key is read as a resource: its scheme and host are written in lowercase, as queries are. See [WebFinger](webfinger.md).

## nodeinfo

| Option | Default | Role |
|---|---|---|
| `path` | `/nodeinfo/2.1` | Where the 2.1 document is served: an absolute path |
| `software` | `phil`, `0.1.0` | `name`, `version`, `repository` and `homepage`, when no provider tells them |
| `protocols` | `[activitypub]` | Protocols of the 2.1 schema; at least one |
| `services.inbound`, `services.outbound` | `[]` | Services of the 2.1 schema |
| `open_registrations` | `false` | Whether the site takes new users, when no provider tells it |
| `metadata` | `{}` | Free metadata, a map, when no provider tells it |
| `provider` | `null` | The id of a `NodeInfoProvider` service, which then tells the software, the usage, the registrations and the metadata |

## did and apple_app_site_association

| Option | Default | Role |
|---|---|---|
| `document` | `null` | The document, as a map |
| `file` | `null` | Or a JSON file holding it, read when the container is built and watched for changes |

One of them is required. The `id` of the DID document is a `did:web` identifier.

## did_configuration

| Option | Default | Role |
|---|---|---|
| `linked_dids` | `[]` | Domain linkage credentials: JWTs, or JSON-LD credentials as maps |
| `files` | `[]` | Files holding one credential each: a JWT, or a JSON object |

At least one credential is required. The bundle serves the credentials; it neither issues nor verifies them.

## atproto

| Option | Default | Role |
|---|---|---|
| `did` | `null` | The DID of the site's own handle |
| `hosts` | `{}` | The DID of other hosts the site answers for, such as handles on subdomains |

At least one DID is required.

## asset_links

| Option | Default | Role |
|---|---|---|
| `android_apps` | `[]` | `package_name`, `sha256_cert_fingerprints` (32 bytes in uppercase hexadecimal, separated by colons) and `relations`, `delegate_permission/common.handle_all_urls` by default |
| `statements` | `[]` | Other statements, written as they are served |

At least one app or statement is required.

## documents

Keyed by their path under `/.well-known/`, such as `matrix/server`.

| Option | Default | Role |
|---|---|---|
| `content` | `null` | The body |
| `file` | `null` | Or a file holding it, read when the container is built |
| `content_type` | | Required: its media type |
| `cors` | `false` | Whether the browsers of other sites may read it |
