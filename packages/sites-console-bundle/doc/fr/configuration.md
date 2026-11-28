<!-- phil-dev source-sha256=1d817425875851137d2774717a0abce7e60b59a7a59e8d74240731e8fbd221a2 -->
# Configuration

Chaque option, avec sa valeur par défaut. Les deux moitiés sont désactivées tant que `enabled: true` n'est pas donné,
ou que leur section n'est pas fournie.

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

## La console

`private_key` est obligatoire quand la console est activée, et refusée au démarrage sinon. Gardez-la dans un secret :
`'%env(SITES_CONSOLE_PRIVATE_KEY)%'`.

`watched_packages` choisit les colonnes de l'écran des versions et les paquets dont les versions lèvent une alerte
quand un site en exécute une plus ancienne qu'un autre site. `expected_versions` ajoute un plancher propre : un site
en dessous lève une alerte même si aucun autre site n'est plus récent. Les clés sont des noms de paquets, les valeurs
des versions ; les deux sont vérifiées au démarrage.

`next_private_key` est la clé vers laquelle une rotation déplace les sites ; `phil:sites-console:keys:rotate` en affiche
une quand elle manque. La console signe avec elle dès que chaque site la détient, et continue de signer avec elle
jusqu'à la fin de la rotation ; déplacez-la alors vers `private_key` et supprimez-la. Gardez-la aussi dans un secret :
`'%env(SITES_CONSOLE_NEXT_PRIVATE_KEY)%'`. La rotation est décrite dans
[le modèle de sécurité](security-model.md#rotation-des-cles).

`allow_insecure_urls` permet d'enregistrer un site en `http` ou sur `localhost`. Laissez-la désactivée en production :
les requêtes sont signées, mais leur contenu — versions, réglages — circule en clair sur `http`.

## L'agent

`site_id`, `private_key` et `console_public_key` sont obligatoires quand l'agent est activé. `site_id` suit la règle
des identifiants de la console : de 1 à 64 lettres minuscules, chiffres et tirets intérieurs, en commençant par une
lettre.

`configuration_keys` est une liste de clés permises : une poussée qui porte une autre clé est refusée en entier, avec
`422`. Sans `configuration_applier`, les poussées sont refusées avec `501` quelles que soient les clés ; sans
`update_runner`, les mises à jour le sont.

`console_next_public_key` est une seconde clé de la console que l'agent accepte, pour une rotation préparée par le
déploiement plutôt que poussée par la console ; une rotation poussée par la console n'exige aucun changement ici.
`key_pool` garde les clés qu'une telle rotation dépose et retire : il doit être partagé par les serveurs du site et
survivre aux déploiements — Redis ou une base de données, pas un pool vidé par `cache:clear`. Les clés de la
configuration l'emportent sur ce pool dès qu'elles changent ; après une rotation, donnez donc à `console_public_key` la
nouvelle clé au déploiement suivant.

`packages` limite ce que `status` rapporte, par motif comme `vendor/*`. La console ne compare que ce que les sites
rapportent.

## Exemple

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
