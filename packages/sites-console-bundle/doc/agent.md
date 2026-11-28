# The agent

The agent is the half of the bundle a site runs for the console. It answers four endpoints under the prefix its
routes are imported with:

| Endpoint | Method | What it does |
|---|---|---|
| `status` | `GET` | The site's versions and health. |
| `configuration` | `POST` | Applies common settings, once per push. |
| `update` | `POST` | Brings a package to a version, once per step of a plan. |
| `keys` | `POST` | Stages or retires a console key, once per step of a key rotation. |

Every request must be signed by the console for this site, within the date window, with a nonce not seen before;
otherwise it gets `401` and nothing else happens. Every answer to a verified request is signed by the site. See
[Security model](security-model.md).

## status

```json
{
    "site": "shop",
    "generated_at": "2026-11-20T10:00:00+00:00",
    "php": "8.5.1",
    "symfony": "8.2.0",
    "packages": {"acme/catalog": "1.4.2", "symfony/http-kernel": "v8.2.0"},
    "health": {"status": "up", "checks": [{"name": "database", "status": "up", "summary": ""}]}
}
```

The packages come from `composer.lock`, read on each request: those of `packages`, and of `packages-dev` with
`include_dev`, limited to the patterns of `packages` when given. A missing or malformed lock file gives no package.
The health comes from the checks of `phil/monitoring-bundle` when its bundle is registered, run when the console asks;
without it, it is `unknown`.

## configuration

```json
{"id": "01JD…", "settings": {"mail.footer": "Hello"}}
```

The agent refuses the whole push with `422` when a key is not allowed by `configuration_keys`, and with `501` when the
site has no `ConfigurationApplier`. Otherwise it calls the applier once for that identifier and answers:

```json
{"outcome": "succeeded", "replayed": false, "message": "1 settings applied."}
```

The same push delivered again answers the same outcome with `"replayed": true`, without calling the applier. The same
identifier with other settings is refused with `422`; the same push still being applied gets `409` with `Retry-After`.
An applier that throws gives `"outcome": "failed"`, the error goes to the site's logs, and the push may be delivered
again.

### Writing an applier

```php
use Phil\SitesConsoleBundle\Application\Contract\ConfigurationApplier;
use Phil\SitesConsoleBundle\Domain\ValueObject\Settings;

final readonly class SettingsApplier implements ConfigurationApplier
{
    public function __construct(private SettingsStore $store)
    {
    }

    public function apply(Settings $settings): void
    {
        foreach ($settings->values as $key => $value) {
            $this->store->set($key, $value);
        }
    }
}
```

Name its service in `agent.configuration_applier`. The keys it gets are only those `configuration_keys` allows.

## update

```json
{"id": "01JD…:shop", "package": "acme/catalog", "version": "1.4.3"}
```

The agent calls its `UpdateRunner` once for that identifier and answers its outcome. The bundle never runs Composer:
the runner starts the site's own pipeline — a deployment job, a script on a build server — waits for it within the
console's `update_timeout`, and says how it went. Without a runner, updates get `501`.

```php
use Phil\SitesConsoleBundle\Application\Contract\UpdateRunner;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;

final readonly class PipelineRunner implements UpdateRunner
{
    public function __construct(private Pipeline $pipeline)
    {
    }

    public function run(string $package, string $version): Outcome
    {
        $job = $this->pipeline->update($package, $version);

        return $job->succeeded() ? Outcome::succeeded('Deployed.') : Outcome::failed($job->summary());
    }
}
```

A failed outcome is kept and answered again for the same step; a runner that throws gives a failure that the same step
may retry.

## keys

```json
{"id": "<rotation>:stage:shop", "operation": "stage", "key": "<public key, base64url>"}
```

The console's key rotation posts here; nothing else needs to. `stage` makes the key the next console key the site
accepts, besides the current one, and must be signed with the current key. `retire` stops accepting a key and must be
signed with the key that remains: retiring the current key makes the next one current, and retiring the only key is
refused. A key already staged, already current, or not held changes nothing and succeeds. The answer is an outcome,
as for an update; each identifier is applied once.

The keys are kept in the pool of `agent.key_pool`, or else `agent.cache_pool`, which must be shared by the site's
servers and survive deploys. The configured keys win over the pool once they change. Each change is read back once
written: a change the pool did not keep fails the request, and the console's rotation stops there, with the retired
key still trusted and reported so. See
[Security model](security-model.md#key-rotation).

## Errors

| Status | Signed | When |
|---|---|---|
| `400` | yes | A body that is not the document of the action. |
| `401` | no | A request the console did not sign for this site, now, once. |
| `409` | yes | The same push or step still running; `Retry-After` gives the seconds left. |
| `413` | no | A body larger than 64 KiB, declared by `Content-Length` or read; never more than 64 KiB and one byte is read. |
| `422` | yes | A setting key not allowed, a console key change signed by the wrong key or retiring the only key, or an identifier reused with another body. |
| `501` | yes | No applier, or no runner. |
| `503` | no | A nonce the site could not keep, its pool or its lock store down; `Retry-After` gives 60 seconds. |

Each error body is `{"error": "<message>"}`.
