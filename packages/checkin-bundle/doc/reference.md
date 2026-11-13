# Reference

| Class | Role |
|---|---|
| `PhilCheckinBundle` | The bundle: configuration, prepended CSRF token and assets, `RESOLVER_TAG`, `CATALOG_TAG`, `SCAN`. |
| `Application\Contract\AdmissionRules` | The rule of each checkpoint, from the configuration or the application; `ruleFor()`. |
| `Application\Contract\CredentialAttributes` | What the rules read of a credential as `attributes`; `of()`. |
| `Application\Event\CheckInRecorded` | A scan the endpoints recorded, online or synchronised: identifiers, direction, outcome, `offline`, `conflict`. |
| `Infrastructure\DependencyInjection\CheckinServices` | Registers the services of the configuration. |
| `Infrastructure\DependencyInjection\CsrfProtectionPass` | Stops a container without CSRF protection. |
| `Infrastructure\DependencyInjection\CredentialCatalogPass` | Turns the offline services off without a catalog named or tagged. |
| `Infrastructure\Catalog\CredentialCatalogs` | The tagged catalogs asked together, in their order. |
| `Infrastructure\Doctrine\DoctrineCheckInLedger` | The ledger in `phil_checkin_scan`; `configureSchema()`. |
| `Infrastructure\Doctrine\CheckInSchemaListener` | Adds the table to the schema the ORM generates. |
| `Infrastructure\Http\CheckinController` | The scan, manifest and synchronisation endpoints. |
| `Infrastructure\Http\CheckinRequest` | Whether a request comes from the desk controller; `cookieName()`. |
| `Infrastructure\Http\DeskConfig` | The desk controller's configuration for a checkpoint; `for()`. |
| `Infrastructure\Http\Routes` | The names of the routes. |
| `Infrastructure\Rules\CheckpointRules` | Evaluates a checkpoint's rule with ExpressionLanguage; `has()`, `admits()`, `lint()`. |
| `Infrastructure\Rules\CheckpointDesks` | The desk and the manifest issuer of a checkpoint, with its rule; `desk()`, `manifests()`. |
| `Infrastructure\Rules\RuledCredentials` | The resolver and catalog as a checkpoint's rule sees them. |
| `Infrastructure\Rules\ConfiguredAdmissionRules` | The rules of `rules.checkpoints`. |
| `Infrastructure\Rules\NoCredentialAttributes` | No attributes, without `rules.attributes`. |

| Service | Class |
|---|---|
| `Phil\CheckinBundle\Infrastructure\Rules\CheckpointDesks` | The desk and manifests of each checkpoint, on the tagged resolvers, with its rule. |
| `Phil\CheckinBundle\Infrastructure\Rules\CheckpointRules` | The evaluator of the rules. |
| `Phil\CheckinBundle\Application\Contract\AdmissionRules` | Alias of `ConfiguredAdmissionRules`, or of `rules.provider`. |
| `Phil\CheckinBundle\Application\Contract\CredentialAttributes` | Alias of `NoCredentialAttributes`, or of `rules.attributes`. |
| `Phil\Checkin\CredentialResolvers` | The tagged resolvers asked together. |
| `Phil\CheckinBundle\Infrastructure\Catalog\CredentialCatalogs` | The tagged catalogs asked together, when `catalog` is null and one is tagged. |
| `Phil\Checkin\AdmissionPolicy` | The policy of `policy`. |
| `Phil\Checkin\Contract\CheckInLedger` | Alias of the ledger of `store`. |
| `Phil\Checkin\Offline\OfflineSync` | With a catalog, named or tagged, only. |
| `Phil\CheckinBundle\Infrastructure\Http\DeskConfig` | For the pages of the desk. |

| Script | Role |
|---|---|
| `assets/src/checkin_desk_controller.js` | The Stimulus desk controller. |
| `assets/src/lib/store.js` | The device's store: IndexedDB, `localStorage` or memory; what `localStorage` kept moved to IndexedDB. |
| `assets/src/lib/offline.js` | The manifest, its fingerprints and the journal. |
| `assets/src/lib/decide.js` | The admission policy, for a device offline. |
| `assets/src/lib/csrf.js` | The double-submitted CSRF token. |
| `assets/src/lib/i18n.js` | The labels in English and French. |
