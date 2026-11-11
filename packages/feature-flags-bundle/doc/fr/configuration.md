<!-- phil-dev source-sha256=d996e6e3add40a1d5bc97aaba06379bc0ad1af44daeab04a54c2cf0d7b0a5a36 -->
# Référence de la configuration

Tout ce qui se trouve sous la clé `phil_feature_flags`, tel que le déclare `PhilFeatureFlagsBundle::configure()`, et ce
que chaque option branche. Lancez `bin/console config:dump-reference phil_feature_flags` pour voir l'arbre tel que
Symfony l'affiche.

## Exemple annoté

```yaml
# config/packages/phil_feature_flags.yaml
phil_feature_flags:
    # Service id of the resolver of the evaluation context of a request.
    # Default: the service id `Phil\FeatureFlagsBundle\Application\Flag\EvaluationContextResolver`.
    # `phil_feature_flags.context_resolver.anonymous` gives every request an empty context.
    context_resolver: App\Flags\RequestContextResolver

    # The database store. Off by default; `doctrine: true` or any map turns it on.
    doctrine:
        connection: default            # DBAL connection name; default "default"

    # Provider service ids, in order of precedence. Default: the configuration provider, then the
    # database store when `doctrine` is enabled.
    providers:
        - phil_feature_flags.provider.configuration
        - phil_feature_flags.provider.doctrine

    # Service id of the store of active states. Default: the database store when enabled, otherwise none.
    state_store: phil_feature_flags.state_store.doctrine

    # Service id of the store the admin creates and edits flags in. Default: the database store when enabled.
    definition_store: phil_feature_flags.provider.doctrine

    # Service id of the recorder of A/B exposures. Default: none.
    exposure_recorder: App\Flags\ExposureCounter

    # The audited toggle and edit. Off by default; any map turns it on.
    toggle:
        authorizer: App\Flags\PermissionAuthorizer   # default: the ToggleAuthorizer interface id
        auditor: App\Flags\AuditTrailAuditor         # default: the ToggleAuditor interface id

    # The admin screens. Off by default; any map turns them on. They need `toggle`.
    admin:
        actor_provider: App\Flags\OperatorProvider   # default: the ActorProvider interface id
        layout: 'admin/layout.html.twig'             # default: '@PhilFeatureFlags/admin/layout.html.twig'

    # Service id of the PSR-20 clock. Default: "clock".
    clock: clock

    # The flags of the configuration provider, by name.
    flags:
        new-checkout:
            active: true
            rules:
                - { attribute: tenant, members: [tenant-a, tenant-b] }
                - { attribute: tenant, percentage: 20 }
            description: The one-page checkout.
            owner: checkout-team
            kind: release
            expires_on: '2027-03-31'
        checkout-layout:
            active: true
            experiment:
                attribute: tenant
                variants: { one-column: 50, two-columns: 50 }
            kind: experiment
        search.v2: false               # short for { active: false }
        maintenance-banner: true       # short for { active: true }: on for everyone
```

## Clés

### `context_resolver`

| | |
|---|---|
| Type | chaîne (identifiant de service), non vide |
| Valeur par défaut | `Phil\FeatureFlagsBundle\Application\Flag\EvaluationContextResolver` |
| Effet | Deuxième argument de `FlagEvaluator`. Appelé à chaque évaluation, il doit donc rester peu coûteux. |

La valeur par défaut est le nom de l'interface : l'application enregistre donc un service sous cet identifiant (un alias
d'autowiring suffit) ou nomme le sien. `phil_feature_flags.context_resolver.anonymous` (`AnonymousContextResolver`) est
toujours enregistré : chaque contexte est vide, donc un indicateur avec des règles ou une expérience est désactivé pour
tout le monde, et seuls les indicateurs sans règles sont activés. Voyez
[flags-and-evaluation.md](flags-and-evaluation.md).

### `doctrine`

| Clé | Type | Valeur par défaut | Effet |
|---|---|---|---|
| `doctrine` | booléen ou table (`canBeEnabled`) | désactivé | `true`, `~` ou une table non vide l'active ; `false` le désactive. |
| `doctrine.enabled` | booléen | `false` | Fixé par les formes ci-dessus. |
| `doctrine.connection` | chaîne, non vide | `default` | La connexion DBAL ; le bundle fait référence au service `doctrine.dbal.<connection>_connection`. |

Lorsqu'il est activé, le bundle enregistre :

- `phil_feature_flags.provider.doctrine` : `DoctrineFlagDefinitions`, avec l'étiquette `kernel.reset`, un fournisseur et
  le magasin de définitions modifiable ;
- `phil_feature_flags.state_store.doctrine` : `DoctrineFlagStore`, avec l'étiquette `kernel.reset`, le magasin
  modifiable des états actifs ;
- `FlagSchemaListener`, avec l'étiquette `doctrine.event_listener` sur `postGenerateSchema` pour cette connexion.

Il change aussi les valeurs par défaut de `providers`, `state_store` et `definition_store`. Voyez
[database-store.md](database-store.md).

### `providers`

| | |
|---|---|
| Type | liste de chaînes (identifiants de service), chacune non vide |
| Valeur par défaut | `[phil_feature_flags.provider.configuration]`, plus `phil_feature_flags.provider.doctrine` lorsque `doctrine` est activé |
| Effet | Premier argument de `FlagEvaluator` et de `FlagCatalog`, dans cet ordre. |

Le premier fournisseur qui connaît un indicateur donne sa définition entière ; une liste vide signifie la valeur par
défaut. Le catalogue indexe les fournisseurs par leur identifiant de service, ce que la liste et l'administration
affichent comme **Source**. Chaque service implémente `FlagProvider` ; implémentez `EnumerableFlagProvider` pour
apparaître dans la liste. Nommer `phil_feature_flags.provider.doctrine` sans activer `doctrine` fait échouer la
compilation du conteneur pour service manquant. Si vous omettez `phil_feature_flags.provider.configuration`, les `flags`
de la configuration ne sont jamais lus (le fournisseur reste enregistré).

### `state_store`

| | |
|---|---|
| Type | chaîne (identifiant de service) ou `null` |
| Valeur par défaut | `null`, ou `phil_feature_flags.state_store.doctrine` lorsque `doctrine` est activé |
| Effet | Troisième argument de `FlagEvaluator` et deuxième de `FlagCatalog` ; le magasin où écrivent `FlagToggler` et `FlagEditor`. |

Sans `toggle`, n'importe quel `FlagStateSource` convient. Avec `toggle`, ce doit être un `WritableFlagStore`.

### `definition_store`

| | |
|---|---|
| Type | chaîne (identifiant de service) ou `null` |
| Valeur par défaut | `null`, ou `phil_feature_flags.provider.doctrine` lorsque `doctrine` est activé |
| Effet | Le `WritableFlagDefinitions` où écrit l'éditeur. Lorsque `toggle` est activé, il enregistre `FlagEditor` ; les écrans de création et de modification de l'administration n'existent qu'alors. |

L'administration n'affiche le lien de modification que pour les indicateurs dont la source dans le catalogue est
exactement cet identifiant de service : le magasin de définitions devrait donc aussi faire partie des `providers`.

### `exposure_recorder`

| | |
|---|---|
| Type | chaîne (identifiant de service) ou `null` |
| Valeur par défaut | `null` |
| Effet | Quatrième argument de `FlagEvaluator` : un `ExposureRecorder` informé de l'indicateur et de la variante une fois par indicateur et par contexte, dans chaque requête. |

Voyez [experiments.md](experiments.md).

### `toggle`

| Clé | Type | Valeur par défaut | Effet |
|---|---|---|---|
| `toggle` | booléen ou table (`canBeEnabled`) | désactivé | Active `FlagToggler` (et `FlagEditor` lorsqu'il y a un magasin de définitions). |
| `toggle.authorizer` | chaîne (identifiant de service), non vide | `Phil\FeatureFlagsBundle\Application\Toggle\ToggleAuthorizer` | Autorise chaque bascule, création et modification. |
| `toggle.auditor` | chaîne (identifiant de service), non vide | `Phil\FeatureFlagsBundle\Application\Toggle\ToggleAuditor` | Écrit la ligne d'audit de chaque changement. |

Enregistre `FlagToggler` (autorisateur, magasin d'états, auditeur, horloge, `FlagCatalog`) et, lorsque
`definition_store` est défini, `FlagEditor` (autorisateur, magasin de définitions, magasin d'états, auditeur, horloge,
`FlagCatalog`). Voyez [toggles-and-edits.md](toggles-and-edits.md).

### `admin`

| Clé | Type | Valeur par défaut | Effet |
|---|---|---|---|
| `admin` | booléen ou table (`canBeEnabled`) | désactivé | Enregistre `FlagAdminController` (public, `controller.service_arguments`). |
| `admin.actor_provider` | chaîne (identifiant de service), non vide | `Phil\FeatureFlagsBundle\Application\Admin\ActorProvider` | Nomme l'opérateur d'une requête. |
| `admin.layout` | chaîne, non vide | `@PhilFeatureFlags/admin/layout.html.twig` | Le gabarit Twig qu'étend chaque écran d'administration ; il définit les blocs `title` et `content`. |

Le contrôleur reçoit aussi les services `twig` et `router`. Les routes ne sont pas chargées automatiquement : importez
`@PhilFeatureFlagsBundle/config/routes/admin.php` ([installation.md](installation.md), [admin.md](admin.md)).

### `clock`

| | |
|---|---|
| Type | chaîne (identifiant de service), non vide |
| Valeur par défaut | `clock` (l'horloge PSR-20 qu'enregistre FrameworkBundle lorsque `symfony/clock` est installé) |
| Effet | Donnée au magasin en base de données (`updated_at`), à la bascule et à l'éditeur (horodatages d'audit), ainsi qu'à l'administration et à la liste (expiration). |

### `flags`

Une table de nom d'indicateur vers définition, lue par `ConfiguredFlagProvider` (service
`phil_feature_flags.provider.configuration`). Les clés ne sont pas normalisées : les tirets restent des tirets.

| Clé | Type | Valeur par défaut | Contraintes |
|---|---|---|---|
| *(nom)* | table, ou booléen | — | Des segments ASCII en minuscules séparés par `.`, `_` ou `-` ; au plus 100 octets. Un booléen `b` est un raccourci pour `{ active: b }`. |
| `active` | booléen | `false` | Un indicateur est inactif sauf s'il est déclaré actif. |
| `rules` | liste de tables | `[]` | Actif sans règles, l'indicateur est activé pour tous les contextes ; avec des règles, il est activé lorsqu'une règle correspond. |
| `rules[].attribute` | chaîne, requise | — | `tenant`, `project` ou `subject`. |
| `rules[].members` | liste de chaînes | `[]` | Des identifiants non vides. Exactement un de `members` ou `percentage`. |
| `rules[].percentage` | entier ou `null` | `null` | De 0 à 100. |
| `experiment` | table ou `null` | `null` | `attribute` (`tenant`, `project` ou `subject`) et `variants`, une table de nom de variante vers poids entier ; au moins deux variantes, des poids de 0 à 100 dont la somme vaut 100. |
| `description` | chaîne ou `null` | `null` | Sans espaces aux extrémités ; au plus 500 caractères ; une valeur vide signifie aucune. |
| `owner` | chaîne ou `null` | `null` | Sans espaces aux extrémités ; au plus 100 caractères ; une valeur vide signifie aucun. |
| `kind` | chaîne ou `null` | `null` | `release`, `experiment`, `ops` ou `permission`. |
| `expires_on` | chaîne ou `null` | `null` | `YYYY-MM-DD`. Une date YAML sans guillemets (lue comme un horodatage) est reconvertie en sa date UTC. |

Les noms de variante suivent l'alphabet des noms d'indicateur, au plus 50 octets. Un indicateur nommé
`circuit-open.<key>` n'accepte ni règle ni expérience. Voyez [targeting.md](targeting.md),
[experiments.md](experiments.md) et [metadata-and-expiry.md](metadata-and-expiry.md).

Pour chaque circuit déclaré à `phil/circuit-breaker-bundle`, le fournisseur de configuration déclare aussi
`circuit-open.<key>`, inactif, sans règles, de genre `ops`, avec la description `Forces the circuit "<key>" open.`, sauf
si `flags` déclare lui-même ce nom. Voyez [circuit-breakers.md](circuit-breakers.md).

## Services enregistrés dans toute configuration

| Identifiant de service | Classe | Étiquettes |
|---|---|---|
| `phil_feature_flags.provider.configuration` | `ConfiguredFlagProvider` (fabrique `fromConfiguration`) | — |
| `phil_feature_flags.context_resolver.anonymous` | `AnonymousContextResolver` | — |
| `Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator` | `FlagEvaluator` | `kernel.reset` (méthode `reset`) |
| `Phil\FeatureFlagsBundle\Infrastructure\Profiler\FeatureFlagsDataCollector` | `FeatureFlagsDataCollector` | `data_collector` (`id` `phil_feature_flags`) |
| `Phil\FeatureFlagsBundle\Application\Flag\FlagCatalog` | `FlagCatalog` | — |
| `Phil\FeatureFlagsBundle\Infrastructure\Console\ListFlagsCommand` | `ListFlagsCommand` | `console.command` |
| `Phil\FeatureFlagsBundle\Infrastructure\Http\FeatureGateListener` | `FeatureGateListener` | `kernel.event_listener` sur `kernel.controller` |
| `Phil\FeatureFlagsBundle\Infrastructure\Routing\FlagRoutingCondition` | `FlagRoutingCondition` | `routing.condition_service` (alias `feature_flags`) |
| `Phil\FeatureFlagsBundle\Infrastructure\Twig\FeatureFlagsExtension` | `FeatureFlagsExtension` | `twig.attribute_extension`, `twig.runtime` |
| `Phil\FeatureFlagsBundle\Application\Circuit\FlagCircuitOverride` | `FlagCircuitOverride` | `phil_circuit_breaker.override` |

Les services conditionnels sont listés avec leur option ci-dessus ; l'inventaire complet se trouve dans
[extension-points.md](extension-points.md#identifiants-de-service). Tous les services sont privés sauf
`FlagAdminController` ; ceux qui portent le nom d'une classe peuvent être injectés automatiquement par type.

## Validation à la construction

Chaque indicateur est analysé pendant la construction du conteneur : une entrée mal formée fait donc échouer
`cache:clear` plutôt que la première requête. Les erreurs sont des `InvalidConfigurationException` :

| Message (forme) | Cause |
|---|---|
| `phil_feature_flags.flags.<name>: <reason>` | Un indicateur, une règle, une expérience ou des métadonnées mal formés, p. ex. `phil_feature_flags.flags.new-checkout: A percentage is between 0 and 100; got 120.` |
| `phil_feature_flags.flags.<name>: "<name>" is not a flag name: …` | Un nom hors de l'alphabet, ou de plus de 100 octets. |
| `phil_feature_flags.flags.circuit-open.<key>: A flag that forces a circuit open takes no rule and no experiment: …` | Des règles ou une expérience sur un indicateur d'ouverture forcée. |
| `phil_feature_flags.admin needs the toggle: every change it makes is authorized and audited there.` | `admin` activé sans `toggle`. |
| `phil_feature_flags.toggle needs a state_store: a toggle writes to the store the evaluator reads.` | `toggle` activé sans `state_store` ni `doctrine`. |
| `phil_feature_flags.<path> must be a service id.` | Une option d'identifiant de service donnée sous une autre forme qu'une chaîne (un entier, un booléen), p. ex. `phil_feature_flags.toggle.authorizer must be a service id.` |
| `phil_feature_flags.providers must be a list of service ids.` | Une entrée qui n'est pas une chaîne dans `providers`. |

L'arbre de configuration de Symfony ajoute ses propres erreurs avant celles-ci (un `attribute` manquant dans une règle,
un `percentage` non entier, une chaîne vide là où `cannotBeEmpty()` s'applique). Un identifiant de service qui n'existe
pas est signalé par le compilateur du conteneur, pas par le bundle.
