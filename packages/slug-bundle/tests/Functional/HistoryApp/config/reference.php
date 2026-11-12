<?php

// This file is auto-generated and is for apps only. Bundles SHOULD NOT rely on its content.

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Config\Loader\ParamConfigurator as Param;

/**
 * This class provides array-shapes for configuring the services and bundles of an application.
 *
 * Services declared with the config() method below are autowired and autoconfigured by default.
 *
 * This is for apps only. Bundles SHOULD NOT use it.
 *
 * Example:
 *
 *     ```php
 *     // config/services.php
 *     namespace Symfony\Component\DependencyInjection\Loader\Configurator;
 *
 *     return App::config([
 *         'services' => [
 *             'App\\' => [
 *                 'resource' => '../src/',
 *             ],
 *         ],
 *     ]);
 *     ```
 *
 * @psalm-type ImportsConfig = list<string|array{
 *     resource: string,
 *     type?: string|null,
 *     ignore_errors?: bool|'not_found',
 * }>
 * @psalm-type ParametersConfig = array<string, scalar|\UnitEnum|array<scalar|\UnitEnum|array<mixed>|Param|null>|Param|null>
 * @psalm-type ArgumentsType = list<mixed>|array<string, mixed>
 * @psalm-type CallType = array<string, ArgumentsType>|array{0:string, 1?:ArgumentsType, 2?:bool}|array{method:string, arguments?:ArgumentsType, returns_clone?:bool}
 * @psalm-type TagsType = list<string|array<string, array<string, mixed>>> // arrays inside the list must have only one element, with the tag name as the key
 * @psalm-type CallbackType = string|array{0:string|ReferenceConfigurator,1:string}|\Closure|ReferenceConfigurator|ExpressionConfigurator
 * @psalm-type DeprecationType = array{package: string, version: string, message?: string}
 * @psalm-type DefaultsType = array{
 *     public?: bool,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     autowire?: bool,
 *     autoconfigure?: bool,
 *     bind?: array<string, mixed>,
 * }
 * @psalm-type InstanceofType = array{
 *     shared?: bool,
 *     lazy?: bool|string,
 *     public?: bool,
 *     factory?: CallbackType,
 *     properties?: array<string, mixed>,
 *     configurator?: CallbackType,
 *     calls?: list<CallType>,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     autowire?: bool,
 *     bind?: array<string, mixed>,
 *     constructor?: string,
 * }
 * @psalm-type DefinitionType = array{
 *     class?: string,
 *     file?: string,
 *     parent?: string,
 *     shared?: bool,
 *     synthetic?: bool,
 *     lazy?: bool|string,
 *     public?: bool,
 *     abstract?: bool,
 *     deprecated?: DeprecationType,
 *     factory?: CallbackType,
 *     configurator?: CallbackType,
 *     arguments?: ArgumentsType,
 *     properties?: array<string, mixed>,
 *     calls?: list<CallType>,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     decorates?: string,
 *     decorates_tag?: string,
 *     decoration_within?: string|list<string>,
 *     decoration_around?: string|list<string>,
 *     decoration_inner_name?: string,
 *     decoration_priority?: int,
 *     decoration_on_invalid?: 'exception'|'ignore'|null,
 *     autowire?: bool,
 *     autoconfigure?: bool,
 *     bind?: array<string, mixed>,
 *     constructor?: string,
 *     from_callable?: CallbackType,
 * }
 * @psalm-type AliasType = string|array{
 *     alias: string,
 *     public?: bool,
 *     deprecated?: DeprecationType,
 * }
 * @psalm-type PrototypeType = array{
 *     resource: string,
 *     namespace?: string,
 *     exclude?: string|list<string>,
 *     parent?: string,
 *     shared?: bool,
 *     lazy?: bool|string,
 *     public?: bool,
 *     abstract?: bool,
 *     deprecated?: DeprecationType,
 *     factory?: CallbackType,
 *     arguments?: ArgumentsType,
 *     properties?: array<string, mixed>,
 *     configurator?: CallbackType,
 *     calls?: list<CallType>,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     autowire?: bool,
 *     autoconfigure?: bool,
 *     bind?: array<string, mixed>,
 *     constructor?: string,
 * }
 * @psalm-type StackType = array{
 *     stack: list<DefinitionType|AliasType|PrototypeType|array<class-string, ArgumentsType|null>>,
 *     public?: bool,
 *     deprecated?: DeprecationType,
 *     decorates?: string,
 *     decorates_tag?: string,
 *     decoration_within?: string|list<string>,
 *     decoration_around?: string|list<string>,
 *     decoration_inner_name?: string,
 *     decoration_priority?: int,
 *     decoration_on_invalid?: 'exception'|'ignore'|null,
 * }
 * @psalm-type ServicesConfig = array{
 *     _defaults?: DefaultsType,
 *     _instanceof?: array<class-string, InstanceofType>,
 *     ...<string, DefinitionType|AliasType|PrototypeType|StackType|ArgumentsType|null>
 * }
 * @psalm-type ExtensionType = array<mixed>|scalar|\UnitEnum|Param|null
 * @psalm-type RouterConfig = bool|array{
 *     enabled?: bool|Param, // Default: false
 *     resource?: scalar|Param|null, // Default: null
 *     type?: scalar|Param|null,
 *     default_uri?: scalar|Param|null, // The default URI used to generate URLs in a non-HTTP context. // Default: null
 *     http_port?: scalar|Param|null, // Default: 80
 *     https_port?: scalar|Param|null, // Default: 443
 *     strict_requirements?: scalar|Param|null, // Set to true to throw an exception when a parameter does not match the requirements. Set to false to disable exceptions when a parameter does not match the requirements (and return null instead). Set to null to disable parameter checks against requirements. Defaults to true in debug mode and to null otherwise.
 *     utf8?: bool|Param, // Default: true
 * }
 * @psalm-type CacheConfig = array{
 *     prefix_seed?: scalar|Param|null, // Used to namespace cache keys when using several apps with the same shared backend. // Default: "_%kernel.project_dir%.%kernel.container_class%"
 *     app?: scalar|Param|null, // App related cache pools configuration. Cannot be combined with "default_provider". // Default: "cache.adapter.filesystem"
 *     system?: scalar|Param|null, // System related cache pools configuration. // Default: "cache.adapter.system"
 *     directory?: scalar|Param|null, // Default: "%kernel.share_dir%/pools/app"
 *     default_provider?: scalar|Param|null, // DSN of the backend to use for "cache.app"; the adapter is deduced from it. Replaces "app", which cannot be set alongside it.
 *     default_psr6_provider?: scalar|Param|null,
 *     default_redis_provider?: scalar|Param|null, // Default: "redis://localhost"
 *     default_valkey_provider?: scalar|Param|null, // Default: "valkey://localhost"
 *     default_memcached_provider?: scalar|Param|null, // Default: "memcached://localhost"
 *     default_doctrine_dbal_provider?: scalar|Param|null, // Default: "database_connection"
 *     default_pdo_provider?: scalar|Param|null, // Default: null
 *     default_mongodb_provider?: scalar|Param|null, // Default: "mongodb://localhost/app"
 *     pools?: array<string, array{ // Default: []
 *         adapters?: Param|string|list<scalar|Param|null>,
 *         tags?: scalar|Param|null, // Default: null
 *         public?: bool|Param, // Default: false
 *         default_lifetime?: scalar|Param|null, // Default lifetime of the pool.
 *         provider?: scalar|Param|null, // Overwrite the setting from the default provider for this adapter.
 *         early_expiration_message_bus?: scalar|Param|null,
 *         clearer?: scalar|Param|null,
 *         marshaller?: scalar|Param|null, // The marshaller service to use for this pool.
 *     }>,
 * }
 * @psalm-type AssetConfig = bool|array{
 *     enabled?: bool|Param, // Default: true
 *     strict_mode?: bool|Param, // Throw an exception if an entry is missing from the manifest.json. // Default: false
 *     version_strategy?: scalar|Param|null, // Default: null
 *     version?: scalar|Param|null, // Default: null
 *     version_format?: scalar|Param|null, // Default: "%%s?%%s"
 *     json_manifest_path?: scalar|Param|null, // Default: null
 *     base_path?: scalar|Param|null, // Default: ""
 *     base_urls?: Param|string|list<scalar|Param|null>,
 *     packages?: array<string, array{ // Default: []
 *         strict_mode?: bool|Param, // Throw an exception if an entry is missing from the manifest.json. // Default: false
 *         version_strategy?: scalar|Param|null, // Default: null
 *         version?: scalar|Param|null,
 *         version_format?: scalar|Param|null, // Default: null
 *         json_manifest_path?: scalar|Param|null, // Default: null
 *         base_path?: scalar|Param|null, // Default: ""
 *         base_urls?: Param|string|list<scalar|Param|null>,
 *     }>,
 * }
 * @psalm-type SerializerConfig = bool|array{
 *     enabled?: bool|Param, // Default: true
 *     enable_attributes?: bool|Param, // Default: true
 *     name_converter?: scalar|Param|null,
 *     circular_reference_handler?: scalar|Param|null,
 *     max_depth_handler?: scalar|Param|null,
 *     mapping?: array{
 *         paths?: list<scalar|Param|null>,
 *     },
 *     default_context?: array<string, mixed>,
 *     named_serializers?: array<string, array{ // Default: []
 *         name_converter?: scalar|Param|null,
 *         default_context?: array<string, mixed>,
 *         include_built_in_normalizers?: bool|Param, // Whether to include the built-in normalizers // Default: true
 *         include_built_in_encoders?: bool|Param, // Whether to include the built-in encoders // Default: true
 *     }>,
 * }
 * @psalm-type ValidationConfig = bool|array{
 *     enabled?: bool|Param, // Default: true
 *     enable_attributes?: bool|Param, // Default: true
 *     static_method?: Param|string|list<scalar|Param|null>,
 *     translation_domain?: scalar|Param|null, // Default: "validators"
 *     email_validation_mode?: "html5"|"html5-allow-no-tld"|"strict"|Param, // Default: "html5"
 *     mapping?: array{
 *         paths?: list<scalar|Param|null>,
 *     },
 *     not_compromised_password?: bool|array{
 *         enabled?: bool|Param|null, // When disabled, compromised passwords will be accepted as valid. Defaults to false when "framework.test" is enabled, to true otherwise. // Default: null
 *         endpoint?: scalar|Param|null, // API endpoint for the NotCompromisedPassword Validator. // Default: null
 *     },
 *     disable_translation?: bool|Param, // Default: false
 *     property_metadata_existence_check?: bool|Param, // When enabled, validateProperty() and validatePropertyValue() throw an exception if no metadata is found for the given property. // Default: false
 *     auto_mapping?: array<string, array{ // Default: []
 *         services?: list<scalar|Param|null>,
 *     }>,
 * }
 * @psalm-type TranslationConfig = bool|array{
 *     enabled?: bool|Param, // Default: true
 *     fallbacks?: Param|string|list<scalar|Param|null>,
 *     logging?: bool|Param, // Default: false
 *     formatter?: scalar|Param|null, // Default: "translator.formatter.default"
 *     cache_dir?: scalar|Param|null, // Default: "%kernel.cache_dir%/translations"
 *     default_path?: scalar|Param|null, // The default path used to load translations. // Default: "%kernel.project_dir%/translations"
 *     paths?: list<scalar|Param|null>,
 *     pseudo_localization?: bool|array{
 *         enabled?: bool|Param, // Default: false
 *         accents?: bool|Param, // Default: true
 *         expansion_factor?: float|Param, // Default: 1.0
 *         brackets?: bool|Param, // Default: true
 *         parse_html?: bool|Param, // Default: false
 *         localizable_html_attributes?: list<scalar|Param|null>,
 *     },
 *     providers?: array<string, array{ // Default: []
 *         dsn?: scalar|Param|null,
 *         domains?: array<string, scalar|Param|null>,
 *         locales?: list<scalar|Param|null>,
 *     }>,
 *     globals?: array<string, Param|string|array{ // Default: []
 *         value?: mixed,
 *         message?: string|Param,
 *         parameters?: array<string, scalar|Param|null>,
 *         domain?: string|Param,
 *     }>,
 * }
 * @psalm-type WebLinkConfig = bool|array{ // Web links configuration
 *     enabled?: bool|Param, // Default: true
 * }
 * @psalm-type LockConfig = Param|bool|string|array{
 *     enabled?: bool|Param, // Default: true
 *     resources?: Param|string|array<string, Param|string|list<mixed>>,
 * }
 * @psalm-type MessengerConfig = bool|array{
 *     enabled?: bool|Param, // Default: true
 *     routing?: array<string, Param|string|list<scalar|Param|null>>,
 *     serializer?: array{
 *         default_serializer?: scalar|Param|null, // Service id to use as the default serializer for the transports. // Default: "messenger.transport.native_php_serializer"
 *         signing_secret?: Param|string|list<scalar|Param|null>,
 *         symfony_serializer?: array{
 *             service?: scalar|Param|null, // Service id of the Symfony serializer behind the messenger.transport.symfony_serializer service, e.g. "serializer.api" for the named serializer "api". // Default: null
 *             format?: scalar|Param|null, // Serialization format for the messenger.transport.symfony_serializer service (which is not the serializer used by default). // Default: "json"
 *             context?: array<string, mixed>,
 *         },
 *     },
 *     transports?: array<string, Param|string|array{ // Default: []
 *         dsn?: scalar|Param|null,
 *         serializer?: scalar|Param|null, // Service id of a custom serializer to use. // Default: null
 *         sign?: bool|Param, // Whether to sign every message sent to this transport with its trust level, and to refuse any message received from it that is unsigned, or signed as unverified when this is not a failure transport. Handlers with the "sign" option require it on the transports their messages come from. // Default: false
 *         claim_check?: array{
 *             cache_pool?: scalar|Param|null, // Service id of the dedicated cache pool used to store claims. Pools declared under "framework.cache.pools" must define a "default_lifetime".
 *             max_size?: int|Param, // Maximum encoded message size in bytes before using a claim check.
 *         },
 *         options?: array<string, mixed>,
 *         failure_transport?: scalar|Param|null, // Transport name to send failed messages to (after all retries have failed). // Default: null
 *         outbox?: scalar|Param|null, // Name or DSN of the transport that stores the messages inside the current database transaction; consume that transport to forward them to this one. A value that is not the name of a configured transport is a DSN, used to create the "orders.outbox" transport for an "orders" transport. // Default: null
 *         inbox?: bool|array{ // Records the handled messages in a database table, in the transaction of their handlers, so that a redelivered message is not handled twice. Needs the "identity_stamps" option and a middleware that runs the handlers in a transaction.
 *             enabled?: bool|Param, // Default: false
 *             connection?: scalar|Param|null, // Name of the Doctrine DBAL connection of that transaction, the default connection when not set. // Default: null
 *             table_name?: scalar|Param|null, // Default: "messenger_inbox"
 *             retention?: int|Param, // Number of seconds a record is kept, which must exceed the time the transport can take to redeliver a message. // Default: 604800
 *         },
 *         retry_strategy?: Param|string|array{
 *             service?: scalar|Param|null, // Service id to override the retry strategy entirely. // Default: null
 *             max_retries?: int|Param, // Default: 3
 *             delay?: int|Param, // Time in ms to delay (or the initial value when multiplier is used). // Default: 1000
 *             multiplier?: float|Param, // If greater than 1, delay will grow exponentially for each retry: this delay = (delay * (multiple ^ retries)). // Default: 2
 *             max_delay?: int|Param, // Max time in ms that a retry should ever be delayed (0 = infinite). // Default: 0
 *             jitter?: float|Param, // Randomness to apply to the delay (between 0 and 1). // Default: 0.1
 *         },
 *         cooldown_strategy?: Param|bool|string|array{ // Stops consuming this transport for a while after its messages fail. With the default strategy, each worker counts its own failures.
 *             enabled?: bool|Param, // Default: false
 *             service?: scalar|Param|null, // Service id to override the cooldown strategy entirely. // Default: null
 *             failure_threshold?: int|Param, // Number of failures in a row that start a cooldown. // Default: 5
 *             delay?: int|Param, // Duration in ms of the first cooldown (or the initial value when multiplier is used). // Default: 1000
 *             multiplier?: float|Param, // If greater than 1, the cooldown will grow exponentially for each further failure in a row: this delay = (delay * (multiplier ^ (failures - failure_threshold))). // Default: 2
 *             max_delay?: int|Param, // Max duration in ms of a cooldown (0 = infinite). // Default: 60000
 *             jitter?: float|Param, // Randomness to apply to the duration (between 0 and 1). // Default: 0.1
 *         },
 *         rate_limiter?: scalar|Param|null, // Rate limiter name to use when processing messages. // Default: null
 *         priority?: int|Param, // Order in which "messenger:consume --all" consumes this transport, higher comes first. // Default: 0
 *     }>,
 *     failure_transport?: scalar|Param|null, // Transport name to send failed messages to (after all retries have failed). // Default: null
 *     stop_worker_on_signals?: Param|int|string|list<scalar|Param|null>,
 *     reject_redelivered_messages?: bool|Param, // Whether redeliveries should be rejected and retried through a new message instead of being handled directly. This mostly makes sense for AMQP, which redelivers messages that were neither acknowledged nor rejected. Disabling it avoids losing a message when the retry or the failure transport is unreachable, at the risk of a redelivery loop that blocks the queue. // Default: true
 *     deduplication?: array{
 *         lock_factory?: scalar|Param|null, // The service ID of the lock factory used to deduplicate messages, "lock.factory" when not set. // Default: null
 *     },
 *     identity_stamps?: bool|Param, // Adds a message id and a causation id to dispatched messages, and a correlation id at the start of each flow. // Default: false
 *     default_bus?: scalar|Param|null, // Default: null
 *     buses?: array<string, array{ // Default: {"messenger.bus.default":{"default_middleware":{"enabled":true,"allow_no_handlers":false,"allow_no_senders":true},"middleware":[],"messages":[],"unwrap_exceptions":false}}
 *         default_middleware?: Param|bool|string|array{
 *             enabled?: bool|Param, // Default: true
 *             allow_no_handlers?: bool|Param, // Default: false
 *             allow_no_senders?: bool|Param, // Default: true
 *         },
 *         middleware?: Param|string|list<Param|string|array{ // Default: []
 *             id?: scalar|Param|null,
 *             arguments?: list<mixed>,
 *         }>,
 *         messages?: Param|string|list<scalar|Param|null>,
 *         unwrap_exceptions?: bool|Param, // Whether the application gets the exception of the failing handler instead of a HandlerFailedException when it dispatches a message. // Default: false
 *     }>,
 * }
 * @psalm-type WorkflowConfig = bool|array{
 *     enabled?: bool|Param, // Default: true
 *     workflows?: array<string, array{ // Default: []
 *         audit_trail?: bool|array{
 *             enabled?: bool|Param, // Default: false
 *         },
 *         type?: "workflow"|"state_machine"|Param, // Default: "state_machine"
 *         marking_store?: array{
 *             type?: "method"|Param,
 *             property?: scalar|Param|null,
 *             service?: scalar|Param|null,
 *         },
 *         supports?: Param|string|list<scalar|Param|null>,
 *         definition_validators?: list<scalar|Param|null>,
 *         support_strategy?: scalar|Param|null,
 *         initial_marking?: \BackedEnum|Param|string|list<scalar|Param|null>,
 *         events_to_dispatch?: null|list<string|Param>,
 *         places?: Param|string|list<Param|string|array{ // Default: []
 *             name?: scalar|Param|null,
 *             metadata?: array<string, mixed>,
 *         }>,
 *         transitions?: list<array{ // Default: []
 *             name?: string|Param,
 *             guard?: string|Param, // An expression to block the transition.
 *             from?: \BackedEnum|Param|string|list<Param|string|array{ // Default: []
 *                 place?: string|Param,
 *                 weight?: int|Param, // Default: 1
 *             }>,
 *             to?: \BackedEnum|Param|string|list<Param|string|array{ // Default: []
 *                 place?: string|Param,
 *                 weight?: int|Param, // Default: 1
 *             }>,
 *             weight?: int|Param, // Default: 1
 *             metadata?: array<string, mixed>,
 *         }>,
 *         metadata?: array<string, mixed>,
 *     }>,
 * }
 * @psalm-type HtmlSanitizerConfig = bool|array{
 *     enabled?: bool|Param, // Default: true
 *     sanitizers?: array<string, array{ // Default: []
 *         default_action?: "drop"|"block"|"allow"|Param, // Defines how the sanitizer must behave by default.
 *         allow_safe_elements?: bool|Param, // Allows "safe" elements and attributes. // Default: false
 *         allow_static_elements?: bool|Param, // Allows all static elements and attributes from the W3C Sanitizer API standard. // Default: false
 *         allow_elements?: array<string, mixed>,
 *         block_elements?: Param|string|list<string|Param>,
 *         drop_elements?: Param|string|list<string|Param>,
 *         allow_attributes?: array<string, mixed>,
 *         drop_attributes?: array<string, mixed>,
 *         force_attributes?: array<string, array<string, string|Param>>,
 *         force_https_urls?: bool|Param, // Transforms URLs using the HTTP scheme to use the HTTPS scheme instead. // Default: false
 *         allowed_link_schemes?: Param|string|list<string|Param>,
 *         allowed_link_hosts?: Param|null|string|list<string|Param>,
 *         allow_relative_links?: bool|Param, // Allows relative URLs to be used in links href attributes. // Default: false
 *         allowed_media_schemes?: Param|string|list<string|Param>,
 *         allowed_media_hosts?: Param|null|string|list<string|Param>,
 *         allow_relative_medias?: bool|Param, // Allows relative URLs to be used in media source attributes (img, audio, video, ...). // Default: false
 *         with_attribute_sanitizers?: Param|string|list<string|Param>,
 *         without_attribute_sanitizers?: Param|string|list<string|Param>,
 *         max_input_length?: int|Param, // The maximum length allowed for the sanitized input. // Default: 0
 *     }>,
 * }
 * @psalm-type TypeInfoConfig = bool|array{
 *     enabled?: bool|Param, // Default: true
 *     aliases?: array<string, scalar|Param|null>,
 * }
 * @psalm-type PropertyAccessConfig = bool|array{ // Property access configuration
 *     enabled?: bool|Param, // Default: true
 *     magic_call?: bool|Param, // Default: false
 *     magic_get?: bool|Param, // Default: true
 *     magic_set?: bool|Param, // Default: true
 *     throw_exception_on_invalid_index?: bool|Param, // Default: false
 *     throw_exception_on_invalid_property_path?: bool|Param, // Default: true
 *     wildcard_reads?: bool|Param, // Enables reading every element of a collection through a "[*]" wildcard. // Default: false
 * }
 * @psalm-type PropertyInfoConfig = bool|array{ // Property info configuration
 *     enabled?: bool|Param, // Default: true
 *     with_constructor_extractor?: bool|Param, // Registers the constructor extractor. // Default: true
 * }
 * @psalm-type UidConfig = bool|array{
 *     enabled?: bool|Param, // Default: true
 *     default_uuid_version?: 7|6|4|1|Param, // Default: 7
 *     name_based_uuid_version?: 5|3|Param, // Default: 5
 *     name_based_uuid_namespace?: scalar|Param|null,
 *     time_based_uuid_version?: 7|6|1|Param, // Default: 7
 *     time_based_uuid_node?: scalar|Param|null,
 *     uuid47_secret?: scalar|Param|null, // A high-entropy secret used by the "uuid47_transformer" service. Defaults to the "kernel.secret" parameter; the service is not registered when neither is defined. // Default: null
 * }
 * @psalm-type SchedulerConfig = bool|array{
 *     enabled?: bool|Param, // Default: true
 *     use_messenger_routing?: bool|Param|null, // Whether scheduled messages are routed to the Messenger senders configured for their class, as any other dispatched message is. A class with no sender configured still runs in the scheduler worker, and a "transports" option set on a task still wins. // Default: null
 *     schedules?: array<string, array{ // Default: []
 *         stateful?: bool|Param, // Whether the schedule keeps its state in the "cache.scheduler" pool and, when locks are enabled, runs in only one worker at a time. // Default: false
 *         cache_pool?: scalar|Param|null, // The cache pool that stores the state of the schedule, so that a restarted worker resumes where the previous one stopped. // Default: null
 *         lock_factory?: scalar|Param|null, // The service ID of the lock factory used to run the schedule in only one worker at a time. // Default: null
 *         process_only_last_missed_run?: bool|Param, // Whether a task that missed several runs runs only once instead of once per missed run. // Default: false
 *     }>,
 * }
 * @psalm-type AssetMapperConfig = bool|array{
 *     enabled?: bool|Param, // Default: true
 *     paths?: Param|string|array<string, scalar|Param|null>,
 *     excluded_patterns?: list<scalar|Param|null>,
 *     exclude_dotfiles?: bool|Param, // If true, any files starting with "." will be excluded from the asset mapper. // Default: true
 *     server?: bool|Param, // If true, a "dev server" will return the assets from the public directory (true in "debug" mode only by default). // Default: "%kernel.debug%"
 *     public_prefix?: scalar|Param|null, // The public path where the assets will be written to (and served from when "server" is true). // Default: "/assets/"
 *     metadata_dir?: scalar|Param|null, // The directory where "asset-map:compile" writes manifest.json, importmap.json and entrypoint.*.json. These files are read by PHP only, so they do not need to be exposed to the browser. // Default: null
 *     missing_import_mode?: "strict"|"warn"|"ignore"|Param|null, // Behavior if an asset cannot be found when imported from JavaScript or CSS files - e.g. "import './non-existent.js'". "strict" means an exception is thrown, "warn" means a warning is logged, "ignore" means the import is left as-is. Defaults to "strict" in debug mode and to "warn" otherwise. // Default: null
 *     extensions?: array<string, scalar|Param|null>,
 *     importmap_path?: scalar|Param|null, // The path of the importmap.php file. // Default: "%kernel.project_dir%/importmap.php"
 *     importmap_polyfill?: scalar|Param|null, // The importmap name that will be used to load the polyfill. Set to false to disable. // Default: "es-module-shims"
 *     importmap_entries?: "all"|"reachable"|Param, // Which entries end up in the rendered importmap: "all" of them, or only the ones "reachable" from the rendered entrypoints (their eager and lazy import chains) plus the polyfill. // Default: "all"
 *     importmap_script_attributes?: array<string, scalar|Param|null>,
 *     importmap_integrity_algorithms?: list<"sha256"|"sha384"|"sha512"|Param>,
 *     vendor_dir?: scalar|Param|null, // The directory to store JavaScript vendors. // Default: "%kernel.project_dir%/assets/vendor"
 *     minimum_release_age?: int|Param, // Minimum age in seconds a package version must have to be considered when checking for updates (0 disables the check). Enabling it makes update checks download the full npm metadata document, which is larger than the abbreviated one. // Default: 0
 *     precompress?: bool|array{ // Precompress assets with Brotli, Zstandard and gzip.
 *         enabled?: bool|Param, // Default: false
 *         formats?: list<scalar|Param|null>,
 *         extensions?: list<scalar|Param|null>,
 *     },
 * }
 * @psalm-type RateLimiterConfig = bool|array{
 *     enabled?: bool|Param, // Default: true
 *     builder?: array{ // Configuration for the RateLimiterBuilder service.
 *         lock_factory?: scalar|Param|null, // The service ID of the lock factory to use with the RateLimiterBuilder. // Default: "auto"
 *         cache_pool?: scalar|Param|null, // The cache pool to use with RateLimiterBuilder. // Default: "cache.rate_limiter"
 *         storage_service?: scalar|Param|null, // The service ID of a custom storage implementation, this precedes any configured "cache_pool". // Default: null
 *     },
 *     limiters?: array<string, array{ // Default: []
 *         lock_factory?: scalar|Param|null, // The service ID of the lock factory used by this limiter (or null to disable locking). // Default: "auto"
 *         cache_pool?: scalar|Param|null, // The cache pool to use for storing the current limiter state. // Default: "cache.rate_limiter"
 *         storage_service?: scalar|Param|null, // The service ID of a custom storage implementation, this precedes any configured "cache_pool". // Default: null
 *         policy?: "fixed_window"|"token_bucket"|"sliding_window"|"compound"|"no_limit"|Param, // The algorithm to be used by this limiter.
 *         limiters?: Param|string|array<string, array{ // Default: []
 *             key?: scalar|Param|null, // The key to pass to this limiter, instead of the one passed to the compound limiter's create() method. // Default: null
 *         }>,
 *         limit?: int|Param, // The maximum allowed hits in a fixed interval or burst.
 *         interval?: scalar|Param|null, // Configures the fixed interval if "policy" is set to "fixed_window" or "sliding_window". The value must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent).
 *         rate?: array{ // Configures the fill rate if "policy" is set to "token_bucket".
 *             interval?: scalar|Param|null, // Configures the rate interval. The value must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent).
 *             amount?: int|Param, // Amount of tokens to add each interval. // Default: 1
 *         },
 *         anchor_at?: scalar|Param|null, // Aligns the "fixed_window" policy to a calendar (e.g. "2024-01-05 00:00:00 UTC" combined with `interval: 1 month` resets the counter on the 5th of each month). UTC if not specified. // Default: null
 *     }>,
 * }
 * @psalm-type HttpClientConfig = bool|array{
 *     enabled?: bool|Param, // Default: true
 *     max_host_connections?: int|Param, // The maximum number of connections to a single host.
 *     recorder?: bool|array{ // Record HTTP exchanges into HAR files and replay them in tests (see RecorderHttpClient).
 *         enabled?: bool|Param|null, // Defaults to true when "framework.test" is enabled, to false otherwise. // Default: null
 *         redactor?: scalar|Param|null, // Service id of a RedactorInterface; when set, "redact" and "redact_except" are ignored. // Default: null
 *         redact?: list<scalar|Param|null>,
 *         redact_except?: list<scalar|Param|null>,
 *     },
 *     default_options?: array{
 *         vars?: array<string, mixed>,
 *         headers?: array<string, mixed>,
 *         max_redirects?: int|Param, // The maximum number of redirects to follow.
 *         http_version?: scalar|Param|null, // The default HTTP version, typically 1.1 or 2.0, leave to null for the best version.
 *         resolve?: array<string, scalar|Param|null>,
 *         proxy?: scalar|Param|null, // The URL of the proxy to pass requests through or null for automatic detection.
 *         no_proxy?: scalar|Param|null, // A comma separated list of hosts that do not require a proxy to be reached.
 *         timeout?: float|Param, // The idle timeout, defaults to the "default_socket_timeout" ini parameter.
 *         max_duration?: float|Param, // The maximum execution time for the request+response as a whole.
 *         max_connect_duration?: float|Param, // The maximum duration allowed for DNS + TCP + TLS connection; a value lower than or equal to 0 means unlimited.
 *         bindto?: scalar|Param|null, // A network interface name, IP address, a host name or a UNIX socket to bind to.
 *         verify_peer?: bool|Param, // Indicates if the peer should be verified in a TLS context.
 *         verify_host?: bool|Param, // Indicates if the host should exist as a certificate common name.
 *         cafile?: scalar|Param|null, // A certificate authority file.
 *         capath?: scalar|Param|null, // A directory that contains multiple certificate authority files.
 *         local_cert?: scalar|Param|null, // A PEM formatted certificate file.
 *         local_pk?: scalar|Param|null, // A private key file.
 *         passphrase?: scalar|Param|null, // The passphrase used to encrypt the "local_pk" file.
 *         ciphers?: scalar|Param|null, // A list of TLS ciphers separated by colons, commas or spaces (e.g. "RC3-SHA:TLS13-AES-128-GCM-SHA256"...).
 *         peer_fingerprint?: array{ // Associative array: hashing algorithm => hash(es).
 *             sha1?: mixed,
 *             pin-sha256?: mixed,
 *             md5?: mixed,
 *         },
 *         crypto_method?: scalar|Param|null, // The minimum version of TLS to accept; must be one of STREAM_CRYPTO_METHOD_TLSv*_CLIENT constants.
 *         extra?: array<string, mixed>,
 *         rate_limiter?: scalar|Param|null, // Rate limiter name to use for throttling requests. // Default: null
 *         caching?: bool|array{ // Caching configuration.
 *             enabled?: bool|Param, // Default: false
 *             cache_pool?: string|Param, // The taggable cache pool to use for storing the responses. // Default: "cache.http_client"
 *             shared?: bool|Param, // Indicates whether the cache is shared (public) or private. // Default: true
 *             max_ttl?: int|Param, // The maximum TTL (in seconds) allowed for cached responses. // Default: 86400
 *         },
 *         retry_failed?: bool|array{
 *             enabled?: bool|Param, // Default: false
 *             base_uris?: Param|string|list<string|Param>,
 *             retry_strategy?: scalar|Param|null, // service id to override the retry strategy. // Default: null
 *             http_codes?: Param|int|string|array<string, array{ // Default: []
 *                 code?: int|Param,
 *                 methods?: Param|string|list<string|Param>,
 *             }>,
 *             max_retries?: int|Param, // Default: 3
 *             delay?: int|Param, // Time in ms to delay (or the initial value when multiplier is used). // Default: 1000
 *             multiplier?: float|Param, // If greater than 1, delay will grow exponentially for each retry: delay * (multiple ^ retries). // Default: 2
 *             max_delay?: int|Param, // Max time in ms that a retry should ever be delayed (0 = infinite). // Default: 0
 *             jitter?: float|Param, // Randomness in percent (between 0 and 1) to apply to the delay. // Default: 0.1
 *         },
 *     },
 *     mock_response_factory?: scalar|Param|null, // `true` to always return empty 200 responses, or the id of the service to use to generate mock responses - which should be either an invokable or an iterable.
 *     scoped_clients?: array<string, Param|string|array{ // Default: []
 *         scope?: scalar|Param|null, // The regular expression that the request URL must match before adding the other options. When none is provided, the base URI is used instead.
 *         base_uri?: scalar|Param|null, // The URI to resolve relative URLs, following rules in RFC 3985, section 2.
 *         auth_basic?: scalar|Param|null, // An HTTP Basic authentication "username:password".
 *         auth_bearer?: scalar|Param|null, // A token enabling HTTP Bearer authorization.
 *         auth_ntlm?: scalar|Param|null, // A "username:password" pair to use Microsoft NTLM authentication (requires the cURL extension).
 *         query?: array<string, scalar|Param|null>,
 *         mock_response_factory?: scalar|Param|null, // `true` to always return empty 200 responses, `false` to disable mocking, or the id of the service to use to generate mock responses (invokable or iterable).
 *         headers?: array<string, mixed>,
 *         max_redirects?: int|Param, // The maximum number of redirects to follow.
 *         http_version?: scalar|Param|null, // The default HTTP version, typically 1.1 or 2.0, leave to null for the best version.
 *         resolve?: array<string, scalar|Param|null>,
 *         proxy?: scalar|Param|null, // The URL of the proxy to pass requests through or null for automatic detection.
 *         no_proxy?: scalar|Param|null, // A comma separated list of hosts that do not require a proxy to be reached.
 *         timeout?: float|Param, // The idle timeout, defaults to the "default_socket_timeout" ini parameter.
 *         max_duration?: float|Param, // The maximum execution time for the request+response as a whole.
 *         max_connect_duration?: float|Param, // The maximum duration allowed for DNS + TCP + TLS connection; a value lower than or equal to 0 means unlimited.
 *         bindto?: scalar|Param|null, // A network interface name, IP address, a host name or a UNIX socket to bind to.
 *         verify_peer?: bool|Param, // Indicates if the peer should be verified in a TLS context.
 *         verify_host?: bool|Param, // Indicates if the host should exist as a certificate common name.
 *         cafile?: scalar|Param|null, // A certificate authority file.
 *         capath?: scalar|Param|null, // A directory that contains multiple certificate authority files.
 *         local_cert?: scalar|Param|null, // A PEM formatted certificate file.
 *         local_pk?: scalar|Param|null, // A private key file.
 *         passphrase?: scalar|Param|null, // The passphrase used to encrypt the "local_pk" file.
 *         ciphers?: scalar|Param|null, // A list of TLS ciphers separated by colons, commas or spaces (e.g. "RC3-SHA:TLS13-AES-128-GCM-SHA256"...).
 *         peer_fingerprint?: array{ // Associative array: hashing algorithm => hash(es).
 *             sha1?: mixed,
 *             pin-sha256?: mixed,
 *             md5?: mixed,
 *         },
 *         crypto_method?: scalar|Param|null, // The minimum version of TLS to accept; must be one of STREAM_CRYPTO_METHOD_TLSv*_CLIENT constants.
 *         extra?: array<string, mixed>,
 *         rate_limiter?: scalar|Param|null, // Rate limiter name to use for throttling requests. // Default: null
 *         caching?: bool|array{ // Caching configuration.
 *             enabled?: bool|Param, // Default: false
 *             cache_pool?: string|Param, // The taggable cache pool to use for storing the responses. // Default: "cache.http_client"
 *             shared?: bool|Param, // Indicates whether the cache is shared (public) or private. // Default: true
 *             max_ttl?: int|Param, // The maximum TTL (in seconds) allowed for cached responses. // Default: 86400
 *         },
 *         retry_failed?: bool|array{
 *             enabled?: bool|Param, // Default: false
 *             base_uris?: Param|string|list<string|Param>,
 *             retry_strategy?: scalar|Param|null, // service id to override the retry strategy. // Default: null
 *             http_codes?: Param|int|string|array<string, array{ // Default: []
 *                 code?: int|Param,
 *                 methods?: Param|string|list<string|Param>,
 *             }>,
 *             max_retries?: int|Param, // Default: 3
 *             delay?: int|Param, // Time in ms to delay (or the initial value when multiplier is used). // Default: 1000
 *             multiplier?: float|Param, // If greater than 1, delay will grow exponentially for each retry: delay * (multiple ^ retries). // Default: 2
 *             max_delay?: int|Param, // Max time in ms that a retry should ever be delayed (0 = infinite). // Default: 0
 *             jitter?: float|Param, // Randomness in percent (between 0 and 1) to apply to the delay. // Default: 0.1
 *         },
 *     }>,
 * }
 * @psalm-type MailerConfig = bool|array{
 *     enabled?: bool|Param, // Default: true
 *     message_bus?: scalar|Param|null, // The message bus to use. Defaults to the default bus if the Messenger component is installed. // Default: null
 *     dsn?: scalar|Param|null, // Default: null
 *     transports?: array<string, Param|string|array{ // Default: []
 *         dsn?: scalar|Param|null,
 *         rate_limiter?: scalar|Param|null, // Rate limiter name used to limit the number of messages sent through this transport; when the limit is exceeded, sending fails with a RateLimitExceededException. // Default: null
 *     }>,
 *     envelope?: array{ // Mailer Envelope configuration
 *         sender?: scalar|Param|null,
 *         recipients?: Param|string|list<scalar|Param|null>,
 *         allowed_recipients?: Param|string|list<scalar|Param|null>,
 *     },
 *     headers?: array<string, Param|string|array{ // Default: []
 *         value?: mixed,
 *     }>,
 *     tracking?: array{ // Default open/click tracking for every message that does not carry an explicit "X-Track" header; null keeps each provider's default. An "X-Track" entry in the "headers" option wins over this one.
 *         opens?: true|false|Param|null, // Default: null
 *         clicks?: true|false|Param|null, // Default: null
 *     },
 *     dkim_signer?: bool|array{ // DKIM signer configuration
 *         enabled?: bool|Param, // Default: false
 *         key?: scalar|Param|null, // Key content, or path to key (in PEM format with the `file://` prefix) // Default: ""
 *         domain?: scalar|Param|null, // Default: ""
 *         select?: scalar|Param|null, // Default: ""
 *         passphrase?: scalar|Param|null, // The private key passphrase // Default: ""
 *         options?: array<string, mixed>,
 *     },
 *     smime_signer?: bool|array{ // S/MIME signer configuration
 *         enabled?: bool|Param, // Default: false
 *         key?: scalar|Param|null, // Path to key (in PEM format) // Default: ""
 *         certificate?: scalar|Param|null, // Path to certificate (in PEM format without the `file://` prefix) // Default: ""
 *         passphrase?: scalar|Param|null, // The private key passphrase // Default: null
 *         extra_certificates?: scalar|Param|null, // Default: null
 *         sign_options?: int|Param, // Default: null
 *     },
 *     smime_encrypter?: bool|array{ // S/MIME encrypter configuration
 *         enabled?: bool|Param, // Default: false
 *         repository?: scalar|Param|null, // S/MIME certificate repository service. This service shall implement the `Symfony\Component\Mailer\EventListener\SmimeCertificateRepositoryInterface`. // Default: ""
 *         certificates?: array<string, scalar|Param|null>,
 *         on_missing_certificate?: "send_unencrypted"|"fail"|"encrypt"|"skip"|Param, // Default behavior when a recipient has no S/MIME certificate: "send_unencrypted" (send the message unencrypted, deprecated since 8.2), "fail" (throw an exception), "encrypt" (encrypt for the recipients that have a certificate, the others receive an unreadable message), "skip" (encrypt for the recipients that have a certificate and drop the others from the envelope; note that "mailer.envelope.recipients" is applied afterwards and overrides that list). Can be overridden per message by setting the "X-SMime-Encrypt" header to one of these values. // Default: "send_unencrypted"
 *         encrypt_for_sender?: bool|Param, // Also encrypt for the sender, when a certificate is available for its address, so that the sender can read the messages it sent. // Default: false
 *         cipher?: int|Param, // A set of algorithms used to encrypt the message // Default: null
 *     },
 *     pgp_signer?: bool|array{ // PGP/MIME signer configuration
 *         enabled?: bool|Param, // Default: false
 *         secret_key?: scalar|Param|null, // Path to the secret key (ASCII armored format without the `file://` prefix) // Default: ""
 *         public_key?: scalar|Param|null, // Path to the public key (ASCII armored format without the `file://` prefix) // Default: null
 *         passphrase?: scalar|Param|null, // The secret key passphrase // Default: null
 *         binary?: scalar|Param|null, // Path to the GnuPG binary // Default: "gpg"
 *         digest_algorithm?: "SHA224"|"SHA256"|"SHA384"|"SHA512"|Param, // The digest algorithm used to sign the message // Default: "SHA512"
 *     },
 *     pgp_encrypter?: bool|array{ // PGP/MIME encrypter configuration
 *         enabled?: bool|Param, // Default: false
 *         repository?: scalar|Param|null, // Service or class implementing `Symfony\Component\Mailer\EventListener\PgpPublicKeyRepositoryInterface` to provide recipient PGP public keys. // Default: ""
 *         keys?: array<string, scalar|Param|null>,
 *         binary?: scalar|Param|null, // Path to the GnuPG binary // Default: "gpg"
 *         cipher_algorithm?: "AES"|"AES192"|"AES256"|"TWOFISH"|"CAMELLIA128"|"CAMELLIA192"|"CAMELLIA256"|Param, // The cipher algorithm used to encrypt the message // Default: "AES256"
 *         timeout?: float|Param, // Timeout in seconds for the GPG process (null for no timeout) // Default: 60.0
 *         hide_recipients?: bool|Param, // Hide every recipient's key ID in the encrypted message (gpg --hidden-recipient). Recipients listed in the Bcc header are always hidden regardless of this option; set it to true to also hide To and Cc recipients. // Default: false
 *         on_missing_key?: "fail"|"encrypt"|"skip"|Param, // Default behavior when a recipient has no PGP public key: "fail" (throw an exception), "encrypt" (encrypt for the recipients that have a key, the others receive an unreadable message), "skip" (encrypt for the recipients that have a key and drop the others from the envelope; note that "mailer.envelope.recipients" is applied afterwards and overrides that list). The message is never sent unencrypted. Can be overridden per message by setting the "X-Pgp-Encrypt" header to one of these values. // Default: "fail"
 *         encrypt_for_sender?: bool|Param, // Also encrypt for the sender, when a public key is available for its address, so that the sender can read the messages it sent. // Default: false
 *     },
 * }
 * @psalm-type NotifierConfig = bool|array{
 *     enabled?: bool|Param, // Default: true
 *     message_bus?: scalar|Param|null, // The message bus to use. Defaults to the default bus if the Messenger component is installed. // Default: null
 *     chatter_transports?: array<string, scalar|Param|null>,
 *     texter_transports?: array<string, scalar|Param|null>,
 *     notification_on_failed_messages?: bool|Param, // Default: false
 *     channel_policy?: array<string, Param|string|list<scalar|Param|null>>,
 *     admin_recipients?: list<array{ // Default: []
 *         email?: scalar|Param|null,
 *         phone?: scalar|Param|null, // Default: ""
 *     }>,
 * }
 * @psalm-type KeyManagementConfig = Param|bool|string|array{
 *     enabled?: bool|Param, // Default: true
 *     default_client?: scalar|Param|null, // Name of the default client (must match an entry of "clients"); inferred when only one client is registered. // Default: null
 *     clients?: Param|string|array<string, Param|string|array{ // Default: []
 *         dsn?: string|Param,
 *         members?: array<string, scalar|Param|null>,
 *         retired?: list<scalar|Param|null>,
 *     }>,
 *     store?: array{ // Data key store: payloads then refer to a stored data key instead of carrying it, so the KMS is reached once per key and per process, and that key can be rewrapped under another provider without rewriting a payload.
 *         connection?: scalar|Param|null, // Service id of the DBAL connection holding the table. // Default: "doctrine.dbal.default_connection"
 *         client?: scalar|Param|null, // Name of the client wrapping the data keys this store creates (must match an entry of "clients"); defaults to the default client. // Default: null
 *         allowed_clients?: list<scalar|Param|null>,
 *         key_id?: scalar|Param|null, // Master key wrapping the data keys this store creates (backend-specific: alias, ARN, key URL, ...).
 *         secret?: Param|string|list<scalar|Param|null>,
 *         table?: scalar|Param|null, // Name of the table holding the wrapped data keys. // Default: "key_management_data_keys"
 *         read_self_contained?: bool|Param, // Whether a payload carrying its own wrapped data key is still read, through the default client. On while migrating to the stored format, off afterwards: such a payload resolves no row, so nothing the store checks applies to it. // Default: false
 *         bind_wrapping_context?: bool|Param, // Whether the backend authenticates the reference and the scope of each data key as it wraps it. Off by default: not every backend accepts authenticated data, and turning it on needs the stored keys rewrapped. // Default: false
 *         max_age?: mixed, // Seconds after which the current root key of a scope is retired. The default is 30 days; 0 forces rotation on every call and null disables retirement. // Default: 2592000
 *     },
 *     doctrine_dbal?: array{
 *         types?: array<string, array{ // Default: []
 *             type?: string|Param, // Doctrine type converting the value before it is encrypted.
 *             key?: string|Param, // Master key encrypting the values, or the scope of their data key when "store" is configured.
 *         }>,
 *     },
 * }
 * @psalm-type FrameworkConfig = array{
 *     secret?: scalar|Param|null, // A secret that must not change. The options that sign data default to it and take a list of secrets to rotate theirs.
 *     http_method_override?: bool|Param, // Set true to enable support for the '_method' request parameter to determine the intended HTTP method on POST requests. // Default: false
 *     allowed_http_method_override?: null|list<string|Param>,
 *     trust_x_sendfile_type_header?: scalar|Param|null, // Set true to enable support for xsendfile in binary file responses. // Default: "%env(bool:default::SYMFONY_TRUST_X_SENDFILE_TYPE_HEADER)%"
 *     ide?: scalar|Param|null, // Deprecated: Setting the "framework.ide.ide" configuration option is deprecated, use the "SYMFONY_IDE" env var instead. // Default: null
 *     test?: bool|Param,
 *     default_locale?: scalar|Param|null, // Default: "en"
 *     set_locale_from_accept_language?: bool|Param, // Whether to use the Accept-Language HTTP header to set the Request locale (only when the "_locale" request attribute is not passed). // Default: false
 *     set_content_language_from_locale?: bool|Param, // Whether to set the Content-Language HTTP header on the Response using the Request locale. // Default: false
 *     enabled_locales?: list<scalar|Param|null>,
 *     trusted_hosts?: Param|string|list<scalar|Param|null>,
 *     trusted_proxies?: mixed, // Default: ["%env(default::SYMFONY_TRUSTED_PROXIES)%"]
 *     trusted_headers?: Param|string|list<scalar|Param|null>,
 *     error_controller?: scalar|Param|null, // Default: "error_controller"
 *     handle_all_throwables?: bool|Param, // HttpKernel will handle all kinds of \Throwable. // Default: true
 *     csrf_protection?: bool|array{
 *         enabled?: scalar|Param|null, // Default: null
 *         stateless_token_ids?: list<scalar|Param|null>,
 *         check_header?: scalar|Param|null, // Whether to check the CSRF token in a header in addition to a cookie when using stateless protection. // Default: false
 *         cookie_name?: scalar|Param|null, // The name of the cookie to use when using stateless protection. // Default: "csrf-token"
 *     },
 *     form?: bool|array{ // Form configuration
 *         enabled?: bool|Param, // Default: true
 *         csrf_protection?: bool|array{
 *             enabled?: scalar|Param|null, // Default: null
 *             token_id?: scalar|Param|null, // Default: null
 *             field_name?: scalar|Param|null, // Default: "_token"
 *             field_attr?: array<string, scalar|Param|null>,
 *         },
 *     },
 *     http_cache?: bool|array{ // HTTP cache configuration
 *         enabled?: bool|Param, // Default: false
 *         debug?: bool|Param, // Default: "%kernel.debug%"
 *         trace_level?: "none"|"short"|"full"|Param,
 *         trace_header?: scalar|Param|null,
 *         cache_status?: scalar|Param|null, // Enables the RFC 9211 "Cache-Status" response header and names this cache in it, e.g. "Symfony". No header is added when null.
 *         default_ttl?: int|Param,
 *         private_headers?: list<scalar|Param|null>,
 *         skip_response_headers?: list<scalar|Param|null>,
 *         allow_reload?: bool|Param,
 *         allow_revalidate?: bool|Param,
 *         stale_while_revalidate?: int|Param,
 *         stale_if_error?: int|Param,
 *         terminate_on_cache_hit?: bool|Param, // Deprecated: Setting the "framework.http_cache.terminate_on_cache_hit.terminate_on_cache_hit" configuration option is deprecated. It will be removed in version 9.0.
 *     },
 *     esi?: bool|array{ // ESI configuration
 *         enabled?: bool|Param, // Default: false
 *     },
 *     ssi?: bool|array{ // SSI configuration
 *         enabled?: bool|Param, // Default: false
 *     },
 *     fragments?: bool|array{ // Fragments configuration
 *         enabled?: bool|Param, // Default: false
 *         hinclude_default_template?: scalar|Param|null, // Deprecated: Setting the "framework.fragments.hinclude_default_template.hinclude_default_template" configuration option is deprecated. It will be removed in version 9.0. // Default: null
 *         path?: scalar|Param|null, // Default: "/_fragment"
 *     },
 *     uri_signer?: array{ // URI signer configuration
 *         secret?: Param|string|list<scalar|Param|null>,
 *         expiration?: int|Param, // Default expiration of signed URIs, in seconds. // Default: null
 *     },
 *     profiler?: bool|array{ // Profiler configuration
 *         enabled?: bool|Param, // Default: false
 *         collect?: bool|Param, // Default: true
 *         collect_parameter?: scalar|Param|null, // The name of the parameter to use to enable or disable collection on a per request basis. // Default: null
 *         only_exceptions?: bool|Param, // Default: false
 *         only_main_requests?: bool|Param, // Default: false
 *         excluded_paths?: Param|string|list<scalar|Param|null>,
 *         excluded_http_codes?: Param|int|string|list<Param|string|list<scalar|Param|null>>,
 *         dsn?: scalar|Param|null, // Default: "file:%kernel.cache_dir%/profiler"
 *         collect_serializer_data?: true|Param, // Deprecated: Setting the "framework.profiler.collect_serializer_data.collect_serializer_data" configuration option is deprecated. It will be removed in version 9.0. // Default: true
 *     },
 *     workflows?: WorkflowConfig,
 *     router?: RouterConfig,
 *     assets?: AssetConfig,
 *     asset_mapper?: AssetMapperConfig,
 *     translator?: TranslationConfig,
 *     validation?: ValidationConfig,
 *     serializer?: SerializerConfig,
 *     property_access?: PropertyAccessConfig,
 *     type_info?: TypeInfoConfig,
 *     property_info?: PropertyInfoConfig,
 *     cache?: CacheConfig,
 *     web_link?: WebLinkConfig,
 *     lock?: LockConfig,
 *     semaphore?: mixed,
 *     messenger?: MessengerConfig,
 *     scheduler?: SchedulerConfig,
 *     http_client?: HttpClientConfig,
 *     mailer?: MailerConfig,
 *     notifier?: NotifierConfig,
 *     rate_limiter?: RateLimiterConfig,
 *     uid?: UidConfig,
 *     html_sanitizer?: HtmlSanitizerConfig,
 *     webhook?: mixed,
 *     remote_event?: mixed,
 *     json_streamer?: mixed,
 *     session?: bool|array{ // Session configuration
 *         enabled?: bool|Param, // Default: false
 *         storage_factory_id?: scalar|Param|null, // Defaults to "session.storage.factory.mock_file" when "framework.test" is true, to "session.storage.factory.native" otherwise. // Default: null
 *         handler_id?: scalar|Param|null, // Defaults to using the native session handler, or to the native *file* session handler if "save_path" is not null.
 *         name?: scalar|Param|null,
 *         cookie_lifetime?: scalar|Param|null,
 *         cookie_path?: scalar|Param|null,
 *         cookie_domain?: scalar|Param|null,
 *         cookie_secure?: true|false|"auto"|Param, // Default: "auto"
 *         cookie_httponly?: bool|Param, // Default: true
 *         cookie_samesite?: null|"lax"|"strict"|"none"|Param, // Default: "lax"
 *         use_cookies?: bool|Param,
 *         gc_divisor?: scalar|Param|null,
 *         gc_probability?: scalar|Param|null,
 *         gc_maxlifetime?: scalar|Param|null,
 *         save_path?: scalar|Param|null, // Defaults to "%kernel.cache_dir%/sessions" if the "handler_id" option is not null.
 *         metadata_update_threshold?: int|Param, // Seconds to wait between 2 session metadata updates. // Default: 0
 *         isolate_attributes?: bool|Param, // Whether to deep-clone the values read from and passed to session attributes, so that only values passed to set() are saved. // Default: false
 *     },
 *     request?: bool|array{ // Request configuration
 *         enabled?: bool|Param, // Default: false
 *         formats?: array<string, Param|string|list<scalar|Param|null>>,
 *         serializer?: scalar|Param|null, // Service id of the serializer that maps request payloads and query strings to controller arguments with #[MapRequestPayload] and #[MapQueryString], e.g. "serializer.api" for the named serializer "api". // Default: null
 *     },
 *     response?: array{ // Response configuration
 *         serializer?: scalar|Param|null, // Service id of the serializer that serializes the values returned by controllers with #[Serialize], e.g. "serializer.api" for the named serializer "api". // Default: null
 *     },
 *     php_errors?: array{ // PHP errors handling configuration
 *         log?: mixed, // Use the application logger instead of the PHP logger for logging PHP errors. // Default: true
 *         throw?: bool|Param|null, // Throw PHP errors as \ErrorException instances. Enabled by default when debug is enabled. // Default: null
 *     },
 *     exceptions?: array<string, array{ // Default: []
 *         log_level?: scalar|Param|null, // The level of log message. Null to let Symfony decide. // Default: null
 *         status_code?: scalar|Param|null, // The status code of the response. Null or 0 to let Symfony decide. // Default: null
 *         log_channel?: scalar|Param|null, // The channel of log message. Null to let Symfony decide. // Default: null
 *     }>,
 *     disallow_search_engine_index?: bool|Param|null, // Enabled by default when debug is enabled. // Default: null
 *     secrets?: bool|array{
 *         enabled?: bool|Param, // Default: true
 *         vault_directory?: scalar|Param|null, // Default: "%kernel.project_dir%/config/secrets/%kernel.runtime_environment%"
 *         local_dotenv_file?: scalar|Param|null, // Default: "%kernel.project_dir%/.env.%kernel.environment%.local"
 *         decryption_env_var?: scalar|Param|null, // Default: "base64:default::SYMFONY_DECRYPTION_SECRET"
 *     },
 * }
 * @psalm-type DoctrineConfig = array{
 *     dbal?: array{
 *         default_connection?: scalar|Param|null,
 *         types?: array<string, Param|string|array{ // Default: []
 *             class?: scalar|Param|null,
 *         }>,
 *         driver_schemes?: array<string, scalar|Param|null>,
 *         connections?: array<string, array{ // Default: []
 *             url?: scalar|Param|null, // A URL with connection information; any parameter value parsed from this string will override explicitly set parameters
 *             dbname?: scalar|Param|null,
 *             host?: scalar|Param|null, // Defaults to "localhost" at runtime.
 *             port?: scalar|Param|null, // Defaults to null at runtime.
 *             user?: scalar|Param|null, // Defaults to "root" at runtime.
 *             password?: scalar|Param|null, // Defaults to null at runtime.
 *             dbname_suffix?: scalar|Param|null, // Adds the given suffix to the configured database name, this option has no effects for the SQLite platform
 *             application_name?: scalar|Param|null,
 *             charset?: scalar|Param|null,
 *             path?: scalar|Param|null,
 *             memory?: bool|Param,
 *             unix_socket?: scalar|Param|null, // The unix socket to use for MySQL
 *             persistent?: bool|Param, // True to use as persistent connection for the ibm_db2 driver
 *             protocol?: scalar|Param|null, // The protocol to use for the ibm_db2 driver (default to TCPIP if omitted)
 *             service?: bool|Param, // True to use SERVICE_NAME as connection parameter instead of SID for Oracle
 *             servicename?: scalar|Param|null, // Overrules dbname parameter if given and used as SERVICE_NAME or SID connection parameter for Oracle depending on the service parameter.
 *             sessionMode?: scalar|Param|null, // The session mode to use for the oci8 driver
 *             server?: scalar|Param|null, // The name of a running database server to connect to for SQL Anywhere.
 *             default_dbname?: scalar|Param|null, // Override the default database (postgres) to connect to for PostgreSQL connection.
 *             sslmode?: scalar|Param|null, // Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL.
 *             sslrootcert?: scalar|Param|null, // The name of a file containing SSL certificate authority (CA) certificate(s). If the file exists, the server's certificate will be verified to be signed by one of these authorities.
 *             sslcert?: scalar|Param|null, // The path to the SSL client certificate file for PostgreSQL.
 *             sslkey?: scalar|Param|null, // The path to the SSL client key file for PostgreSQL.
 *             sslcrl?: scalar|Param|null, // The file name of the SSL certificate revocation list for PostgreSQL.
 *             pooled?: bool|Param, // True to use a pooled server with the oci8/pdo_oracle driver
 *             MultipleActiveResultSets?: bool|Param, // Configuring MultipleActiveResultSets for the pdo_sqlsrv driver
 *             instancename?: scalar|Param|null, // Optional parameter, complete whether to add the INSTANCE_NAME parameter in the connection. It is generally used to connect to an Oracle RAC server to select the name of a particular instance.
 *             connectstring?: scalar|Param|null, // Complete Easy Connect connection descriptor, see https://docs.oracle.com/database/121/NETAG/naming.htm.When using this option, you will still need to provide the user and password parameters, but the other parameters will no longer be used. Note that when using this parameter, the getHost and getPort methods from Doctrine\DBAL\Connection will no longer function as expected.
 *             driver?: scalar|Param|null, // Default: "pdo_mysql"
 *             auto_commit?: bool|Param,
 *             schema_filter?: scalar|Param|null,
 *             logging?: bool|Param, // Default: true
 *             profiling?: bool|Param, // Default: true
 *             profiling_collect_backtrace?: bool|Param, // Enables collecting backtraces when profiling is enabled // Default: false
 *             profiling_collect_schema_errors?: bool|Param, // Enables collecting schema errors when profiling is enabled // Default: true
 *             server_version?: scalar|Param|null,
 *             idle_connection_ttl?: int|Param, // Default: 600
 *             driver_class?: scalar|Param|null,
 *             wrapper_class?: scalar|Param|null,
 *             keep_replica?: bool|Param,
 *             options?: array<string, mixed>,
 *             mapping_types?: array<string, scalar|Param|null>,
 *             default_table_options?: array<string, scalar|Param|null>,
 *             schema_manager_factory?: scalar|Param|null, // Default: "doctrine.dbal.default_schema_manager_factory"
 *             result_cache?: scalar|Param|null,
 *             replicas?: array<string, array{ // Default: []
 *                 url?: scalar|Param|null, // A URL with connection information; any parameter value parsed from this string will override explicitly set parameters
 *                 dbname?: scalar|Param|null,
 *                 host?: scalar|Param|null, // Defaults to "localhost" at runtime.
 *                 port?: scalar|Param|null, // Defaults to null at runtime.
 *                 user?: scalar|Param|null, // Defaults to "root" at runtime.
 *                 password?: scalar|Param|null, // Defaults to null at runtime.
 *                 dbname_suffix?: scalar|Param|null, // Adds the given suffix to the configured database name, this option has no effects for the SQLite platform
 *                 application_name?: scalar|Param|null,
 *                 charset?: scalar|Param|null,
 *                 path?: scalar|Param|null,
 *                 memory?: bool|Param,
 *                 unix_socket?: scalar|Param|null, // The unix socket to use for MySQL
 *                 persistent?: bool|Param, // True to use as persistent connection for the ibm_db2 driver
 *                 protocol?: scalar|Param|null, // The protocol to use for the ibm_db2 driver (default to TCPIP if omitted)
 *                 service?: bool|Param, // True to use SERVICE_NAME as connection parameter instead of SID for Oracle
 *                 servicename?: scalar|Param|null, // Overrules dbname parameter if given and used as SERVICE_NAME or SID connection parameter for Oracle depending on the service parameter.
 *                 sessionMode?: scalar|Param|null, // The session mode to use for the oci8 driver
 *                 server?: scalar|Param|null, // The name of a running database server to connect to for SQL Anywhere.
 *                 default_dbname?: scalar|Param|null, // Override the default database (postgres) to connect to for PostgreSQL connection.
 *                 sslmode?: scalar|Param|null, // Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL.
 *                 sslrootcert?: scalar|Param|null, // The name of a file containing SSL certificate authority (CA) certificate(s). If the file exists, the server's certificate will be verified to be signed by one of these authorities.
 *                 sslcert?: scalar|Param|null, // The path to the SSL client certificate file for PostgreSQL.
 *                 sslkey?: scalar|Param|null, // The path to the SSL client key file for PostgreSQL.
 *                 sslcrl?: scalar|Param|null, // The file name of the SSL certificate revocation list for PostgreSQL.
 *                 pooled?: bool|Param, // True to use a pooled server with the oci8/pdo_oracle driver
 *                 MultipleActiveResultSets?: bool|Param, // Configuring MultipleActiveResultSets for the pdo_sqlsrv driver
 *                 instancename?: scalar|Param|null, // Optional parameter, complete whether to add the INSTANCE_NAME parameter in the connection. It is generally used to connect to an Oracle RAC server to select the name of a particular instance.
 *                 connectstring?: scalar|Param|null, // Complete Easy Connect connection descriptor, see https://docs.oracle.com/database/121/NETAG/naming.htm.When using this option, you will still need to provide the user and password parameters, but the other parameters will no longer be used. Note that when using this parameter, the getHost and getPort methods from Doctrine\DBAL\Connection will no longer function as expected.
 *                 ...<string, mixed>
 *             }>,
 *             ...<string, mixed>
 *         }>,
 *         ...<string, mixed>
 *     },
 *     orm?: array{
 *         default_entity_manager?: scalar|Param|null,
 *         enable_native_lazy_objects?: bool|Param, // Deprecated: The "enable_native_lazy_objects" option is deprecated and will be removed in DoctrineBundle 4.0, as native lazy objects are now always enabled. // Default: true
 *         controller_resolver?: bool|array{
 *             enabled?: bool|Param, // Default: true
 *             auto_mapping?: bool|Param, // Deprecated: The "doctrine.orm.controller_resolver.auto_mapping.auto_mapping" option is deprecated and will be removed in DoctrineBundle 4.0, as it only accepts `false` since 3.0. // Set to true to enable using route placeholders as lookup criteria when the primary key doesn't match the argument name // Default: false
 *             evict_cache?: bool|Param, // Set to true to fetch the entity from the database instead of using the cache, if any // Default: false
 *         },
 *         entity_managers?: array<string, array{ // Default: []
 *             query_cache_driver?: Param|string|array{
 *                 type?: scalar|Param|null, // Default: null
 *                 id?: scalar|Param|null,
 *                 pool?: scalar|Param|null,
 *             },
 *             metadata_cache_driver?: Param|string|array{
 *                 type?: scalar|Param|null, // Default: null
 *                 id?: scalar|Param|null,
 *                 pool?: scalar|Param|null,
 *             },
 *             result_cache_driver?: Param|string|array{
 *                 type?: scalar|Param|null, // Default: null
 *                 id?: scalar|Param|null,
 *                 pool?: scalar|Param|null,
 *             },
 *             entity_listeners?: array{
 *                 entities?: array<string, array{ // Default: []
 *                     listeners?: array<string, array{ // Default: []
 *                         events?: list<array{ // Default: []
 *                             type?: scalar|Param|null,
 *                             method?: scalar|Param|null, // Default: null
 *                         }>,
 *                     }>,
 *                 }>,
 *                 ...<string, mixed>
 *             },
 *             connection?: scalar|Param|null,
 *             class_metadata_factory_name?: scalar|Param|null, // Default: "Doctrine\\ORM\\Mapping\\ClassMetadataFactory"
 *             default_repository_class?: scalar|Param|null, // Default: "Doctrine\\ORM\\EntityRepository"
 *             auto_mapping?: scalar|Param|null, // Default: false
 *             naming_strategy?: scalar|Param|null, // Default: "doctrine.orm.naming_strategy.default"
 *             quote_strategy?: scalar|Param|null, // Default: "doctrine.orm.quote_strategy.default"
 *             typed_field_mapper?: scalar|Param|null, // Default: "doctrine.orm.typed_field_mapper.default"
 *             entity_listener_resolver?: scalar|Param|null, // Default: null
 *             fetch_mode_subselect_batch_size?: scalar|Param|null,
 *             repository_factory?: scalar|Param|null, // Default: "doctrine.orm.container_repository_factory"
 *             schema_ignore_classes?: list<scalar|Param|null>,
 *             validate_xml_mapping?: bool|Param, // Set to "true" to opt-in to the new mapping driver mode that was added in Doctrine ORM 2.14 and will be mandatory in ORM 3.0. See https://github.com/doctrine/orm/pull/6728. // Default: false
 *             second_level_cache?: array{
 *                 region_cache_driver?: Param|string|array{
 *                     type?: scalar|Param|null, // Default: null
 *                     id?: scalar|Param|null,
 *                     pool?: scalar|Param|null,
 *                 },
 *                 region_lock_lifetime?: scalar|Param|null, // Default: 60
 *                 log_enabled?: bool|Param, // Default: true
 *                 region_lifetime?: scalar|Param|null, // Default: 3600
 *                 enabled?: bool|Param, // Default: true
 *                 factory?: scalar|Param|null,
 *                 regions?: array<string, array{ // Default: []
 *                     cache_driver?: Param|string|array{
 *                         type?: scalar|Param|null, // Default: null
 *                         id?: scalar|Param|null,
 *                         pool?: scalar|Param|null,
 *                     },
 *                     lock_path?: scalar|Param|null, // Default: "%kernel.cache_dir%/doctrine/orm/slc/filelock"
 *                     lock_lifetime?: scalar|Param|null, // Default: 60
 *                     type?: scalar|Param|null, // Default: "default"
 *                     lifetime?: scalar|Param|null, // Default: null
 *                     service?: scalar|Param|null,
 *                     name?: scalar|Param|null,
 *                 }>,
 *                 loggers?: array<string, array{ // Default: []
 *                     name?: scalar|Param|null,
 *                     service?: scalar|Param|null,
 *                 }>,
 *             },
 *             hydrators?: array<string, scalar|Param|null>,
 *             mappings?: array<string, Param|bool|string|array{ // Default: []
 *                 mapping?: scalar|Param|null, // Default: true
 *                 type?: scalar|Param|null,
 *                 dir?: scalar|Param|null,
 *                 alias?: scalar|Param|null,
 *                 prefix?: scalar|Param|null,
 *                 is_bundle?: bool|Param,
 *             }>,
 *             dql?: array{
 *                 string_functions?: array<string, scalar|Param|null>,
 *                 numeric_functions?: array<string, scalar|Param|null>,
 *                 datetime_functions?: array<string, scalar|Param|null>,
 *             },
 *             filters?: array<string, Param|string|array{ // Default: []
 *                 class?: scalar|Param|null,
 *                 enabled?: bool|Param, // Default: false
 *                 parameters?: array<string, mixed>,
 *                 ...<string, mixed>
 *             }>,
 *             identity_generation_preferences?: array<string, scalar|Param|null>,
 *         }>,
 *         resolve_target_entities?: array<string, scalar|Param|null>,
 *         ...<string, mixed>
 *     },
 * }
 * @psalm-type PhilSlugConfig = array{
 *     default?: scalar|Param|null, // The name of the slugger used when none is named: the one autowired as Slugger. // Default: "default"
 *     sluggers?: array<string, array{ // Default: []
 *         locale?: scalar|Param|null, // The locale of the transliteration — "de" writes ü as ue —; null for the generic one. // Default: null
 *         max_length?: int|Param, // The longest slug, cut at the last hyphen that fits. // Default: 255
 *         leading_words?: list<scalar|Param|null>,
 *     }>,
 *     history?: bool|array{ // The history of former slugs in the table phil_slug_history, through doctrine/dbal: "phil_slug.history", autowired as SlugHistory.
 *         enabled?: bool|Param, // Default: false
 *         connection?: scalar|Param|null, // The DBAL connection of the table. // Default: "default"
 *         clock?: scalar|Param|null, // Service id of the PSR-20 clock of the recorded changes. // Default: "clock"
 *     },
 * }
 * @psalm-type ConfigType = array{
 *     imports?: ImportsConfig,
 *     parameters?: ParametersConfig,
 *     services?: ServicesConfig,
 *     router?: RouterConfig,
 *     cache?: CacheConfig,
 *     asset?: AssetConfig,
 *     serializer?: SerializerConfig,
 *     validation?: ValidationConfig,
 *     translation?: TranslationConfig,
 *     web_link?: WebLinkConfig,
 *     lock?: LockConfig,
 *     messenger?: MessengerConfig,
 *     workflow?: WorkflowConfig,
 *     html_sanitizer?: HtmlSanitizerConfig,
 *     type_info?: TypeInfoConfig,
 *     property_access?: PropertyAccessConfig,
 *     property_info?: PropertyInfoConfig,
 *     uid?: UidConfig,
 *     scheduler?: SchedulerConfig,
 *     asset_mapper?: AssetMapperConfig,
 *     rate_limiter?: RateLimiterConfig,
 *     http_client?: HttpClientConfig,
 *     mailer?: MailerConfig,
 *     notifier?: NotifierConfig,
 *     key_management?: KeyManagementConfig,
 *     framework?: FrameworkConfig,
 *     doctrine?: DoctrineConfig,
 *     phil_slug?: PhilSlugConfig,
 *     "when@test"?: array{
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         router?: RouterConfig,
 *         cache?: CacheConfig,
 *         asset?: AssetConfig,
 *         serializer?: SerializerConfig,
 *         validation?: ValidationConfig,
 *         translation?: TranslationConfig,
 *         web_link?: WebLinkConfig,
 *         lock?: LockConfig,
 *         messenger?: MessengerConfig,
 *         workflow?: WorkflowConfig,
 *         html_sanitizer?: HtmlSanitizerConfig,
 *         type_info?: TypeInfoConfig,
 *         property_access?: PropertyAccessConfig,
 *         property_info?: PropertyInfoConfig,
 *         uid?: UidConfig,
 *         scheduler?: SchedulerConfig,
 *         asset_mapper?: AssetMapperConfig,
 *         rate_limiter?: RateLimiterConfig,
 *         http_client?: HttpClientConfig,
 *         mailer?: MailerConfig,
 *         notifier?: NotifierConfig,
 *         key_management?: KeyManagementConfig,
 *         framework?: FrameworkConfig,
 *         doctrine?: DoctrineConfig,
 *         phil_slug?: PhilSlugConfig,
 *     },
 *     ...<string, ExtensionType|array{ // extra keys must follow the when@%env% pattern or match an extension alias
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         ...<string, ExtensionType>,
 *     }>
 * }
 */
final class App
{
    /**
     * @param ConfigType $config
     *
     * @psalm-return ConfigType
     */
    public static function config(array $config): array
    {
        /** @var ConfigType $config */
        $config = AppReference::config($config);

        return $config;
    }
}

namespace Symfony\Component\Routing\Loader\Configurator;

/**
 * This class provides array-shapes for configuring the routes of an application.
 *
 * Example:
 *
 *     ```php
 *     // config/routes.php
 *     namespace Symfony\Component\Routing\Loader\Configurator;
 *
 *     return Routes::config([
 *         'controllers' => [
 *             'resource' => 'routing.controllers',
 *         ],
 *     ]);
 *     ```
 *
 * @psalm-type RouteConfig = array{
 *     path: string|array<string,string>,
 *     controller?: string,
 *     template?: string,
 *     redirect_to_route?: string,
 *     redirect_to?: string,
 *     methods?: string|list<string>,
 *     requirements?: array<string,string>,
 *     defaults?: array<string,mixed>,
 *     options?: array<string,mixed>,
 *     host?: string|array<string,string>,
 *     schemes?: string|list<string>,
 *     condition?: string,
 *     add_condition?: string,
 *     locale?: string,
 *     format?: string,
 *     utf8?: bool,
 *     stateless?: bool,
 *     firewall?: string,
 * }
 * @psalm-type ImportConfig = array{
 *     resource: string,
 *     type?: string,
 *     exclude?: string|list<string>,
 *     prefix?: string|array<string,string>,
 *     name_prefix?: string,
 *     trailing_slash_on_root?: bool,
 *     controller?: string,
 *     methods?: string|list<string>,
 *     requirements?: array<string,string>,
 *     defaults?: array<string,mixed>,
 *     options?: array<string,mixed>,
 *     host?: string|array<string,string>,
 *     schemes?: string|list<string>,
 *     condition?: string,
 *     add_condition?: string,
 *     locale?: string,
 *     format?: string,
 *     utf8?: bool,
 *     stateless?: bool,
 *     firewall?: string,
 * }
 * @psalm-type AliasConfig = array{
 *     alias: string,
 *     deprecated?: array{package:string, version:string, message?:string},
 * }
 * @psalm-type RoutesConfig = array{
 *     "when@test"?: array<string, RouteConfig|ImportConfig|AliasConfig>,
 *     ...<string, RouteConfig|ImportConfig|AliasConfig>
 * }
 */
final class Routes
{
    /**
     * @param RoutesConfig $config
     *
     * @psalm-return RoutesConfig
     */
    public static function config(array $config): array
    {
        return $config;
    }
}
