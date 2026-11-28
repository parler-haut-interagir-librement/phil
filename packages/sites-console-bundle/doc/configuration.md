# Configuration

Every option, with its default. Both halves are off until `enabled: true`, or until their section is given.

```yaml
phil_sites_console:
    clock: clock                          # service id of the PSR-20 clock

    console:
        enabled: false
        private_key: ~                    # required: the console's Ed25519 key, base64 of a 32-byte seed or 64-byte secret
        next_private_key: ~               # the key phil:sites-console:keys:rotate moves the sites to
        store: doctrine                   # "doctrine", or "memory" for tests
        entity_manager: default           # the Doctrine entity manager of the tables
        http_client: http_client          # service id of the HTTP client the agents are reached with
        allow_insecure_urls: false        # http and localhost agents, for development and tests only
        timeout: 10.0                     # seconds a status or a push may take (1 to 120)
        update_timeout: 600.0             # seconds the update of one site may take (1 to 3600)
        watched_packages: ['phil/*', 'symfony/http-kernel']   # compared across sites; * stands for any characters
        expected_versions: {}             # the lowest version a package should run, such as { 'vendor/package': '1.4.2' }
        snapshot_retention_days: 90       # what phil:sites-console:purge keeps; the latest of each site stays
        roles:
            viewer: ROLE_SITES_CONSOLE_VIEWER       # reads the screens
            operator: ROLE_SITES_CONSOLE_OPERATOR   # pulls, delivers pushes, runs update plans
        layout: '@PhilSitesConsole/layout.html.twig'   # a template with the blocks "title" and "content"
        kit_subdirectory: ~               # the subdirectory of the kit's recipes; the ux_toolkit one when null

    agent:
        enabled: false
        site_id: ~                        # required: this site's identifier in the console
        private_key: ~                    # required: this site's Ed25519 key
        console_public_key: ~             # required: the console's public key
        console_next_public_key: ~        # a second console key accepted during a rotation, given by deploy
        lock_file: '%kernel.project_dir%/composer.lock'
        include_dev: false                # report the development packages too
        packages: []                      # the packages reported, by pattern; all when empty
        configuration_keys: []            # the setting keys a push may carry, by pattern; none when empty
        configuration_applier: ~          # service id of a ConfigurationApplier; pushes answer 501 without one
        update_runner: ~                  # service id of an UpdateRunner; updates answer 501 without one
        health: auto                      # "monitoring", "none", or "auto": monitoring when its bundle is registered
        cache_pool: cache.app             # shared by the site's servers: nonces and idempotency keys
        key_pool: ~                       # the console keys a rotation stages and retires; cache_pool when null
        lock_factory: lock.factory        # shared by the site's servers: makes the nonces and the idempotency atomic
        max_clock_skew: 300               # seconds a request's date may differ from the site's clock (1 to 3600)
```

## The console

`private_key` is required when the console is on, and refused at boot otherwise. Keep it in a secret:
`'%env(SITES_CONSOLE_PRIVATE_KEY)%'`.

`watched_packages` chooses the columns of the versions screen and the packages whose versions raise an alert when a
site runs an older one than another site. `expected_versions` adds a floor of its own: a site below it raises an
alert even when no other site is newer. Keys are package names, values are versions; both are checked at boot.

`next_private_key` is the key a rotation moves the sites to; `phil:sites-console:keys:rotate` prints one when it is
missing. The console signs with it once every site holds it, and keeps signing with it until the rotation is
finished; then move it to `private_key` and remove it. Keep it in a secret too:
`'%env(SITES_CONSOLE_NEXT_PRIVATE_KEY)%'`. The rotation is described in [the security model](security-model.md#key-rotation).

`allow_insecure_urls` lets a site be registered on `http` or on `localhost`. Leave it off in production: requests are
signed, but their content — versions, settings — travels in clear over `http`.

## The agent

`site_id`, `private_key` and `console_public_key` are required when the agent is on. `site_id` follows the rule of the
console's identifiers: 1 to 64 lowercase letters, digits and inner dashes, starting with a letter.

`configuration_keys` is an allow-list: a push that carries any other key is refused whole, with `422`. Without
`configuration_applier`, pushes are refused with `501` whatever the keys; without `update_runner`, updates are.

`console_next_public_key` is a second console key the agent accepts, for a rotation prepared by deploy rather than
pushed by the console; a rotation pushed by the console needs no change here. `key_pool` keeps the keys such a
rotation stages and retires: it must be shared by the site's servers and survive deploys — Redis or a database, not a
pool emptied by `cache:clear`. The keys of the configuration win over that pool once they change, so after a rotation,
set `console_public_key` to the new key at the next deploy.

`packages` limits what `status` reports, by pattern such as `vendor/*`. The console only compares what the sites
report.

## Example

```yaml
# The console
phil_sites_console:
    console:
        enabled: true
        private_key: '%env(SITES_CONSOLE_PRIVATE_KEY)%'
        watched_packages: ['acme/*', 'symfony/http-kernel']
        expected_versions:
            symfony/http-kernel: 8.2.1
        roles:
            viewer: ROLE_ADMIN
            operator: ROLE_SUPER_ADMIN

# A site
phil_sites_console:
    agent:
        enabled: true
        site_id: shop
        private_key: '%env(SITES_CONSOLE_AGENT_KEY)%'
        console_public_key: '%env(SITES_CONSOLE_PUBLIC_KEY)%'
        packages: ['acme/*', 'symfony/*']
        configuration_keys: ['mail.*', 'banner.text']
        configuration_applier: App\Console\SettingsApplier
        update_runner: App\Console\PipelineRunner
        cache_pool: cache.shared
        key_pool: cache.persistent
```
