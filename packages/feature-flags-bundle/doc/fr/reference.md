<!-- phil-dev source-sha256=a718840942fc65daa006c5c374f0bdf0b045e9730ce2864d386ea227d48f25cd -->
# Référence

L'API publique par couche. Toutes les classes se trouvent sous `Phil\FeatureFlagsBundle\`. **État** : *implémenté*
(testé dans `tests/Unit` ou `tests/Functional`), *port* (une interface que l'application implémente ou peut
implémenter), *branché* (enregistré par le bundle à partir de la configuration).

`Domain` ne dépend que de `phil/ddd-bundle` (pour `InvalidValue` et `DomainError`) ; `Application` ajoute `psr/clock` ;
`Infrastructure` ajoute Doctrine DBAL et ORM, le noyau HTTP et le routage, la console et Twig.

## Domain\Flag

| Type | Rôle | État |
|---|---|---|
| `FlagName` | Le nom d'un indicateur : des segments ASCII en minuscules séparés par `.`, `_`, `-`, au plus 100 octets. | implémenté |
| `FlagDefinition` | Un indicateur tel qu'un fournisseur le connaît : état actif, règles, expérience, métadonnées ; `evaluate()`, `withActive()`, `readsAnyOf()`. | implémenté |
| `Evaluation` | Le résultat pour un indicateur et un contexte : activé, raison, code d'erreur, variante ; `notFound()`, `failed()`. | implémenté |
| `EvaluationReason` | `STATIC`, `TARGETING_MATCH`, `SPLIT`, `DEFAULT`, `DISABLED`, `ERROR` (vocabulaire d'OpenFeature). | implémenté |
| `EvaluationErrorCode` | `FLAG_NOT_FOUND`, `PARSE_ERROR`, `GENERAL` (vocabulaire d'OpenFeature). | implémenté |
| `EvaluationContext` | Les identifiants de locataire, de projet et de sujet, chacun nullable ; `of()`, `anonymous()`, `get()`. | implémenté |
| `TargetingAttribute` | `tenant`, `project`, `subject`. | implémenté |
| `Bucket` | Le compartiment stable 0–99 : `of()` pour les déploiements progressifs, `forVariant()` pour les expériences (`murmur3a`). | implémenté |
| `Percentage` | Un pourcentage entier 0–100 ; `admits(Bucket)`. | implémenté |
| `Experiment` | Des variantes pondérées sur un attribut ; `assign()`. | implémenté |
| `Variant` | Une branche et son poids 0–100. | implémenté |
| `VariantName` | Le nom d'une variante, au plus 50 octets. | implémenté |
| `FlagMetadata` | Description, responsable, genre, date d'expiration ; `isExpiredAt()`. | implémenté |
| `FlagKind` | `release`, `experiment`, `ops`, `permission`. | implémenté |
| `FlagProvider` | Une source de définitions : `find()`. | port |
| `EnumerableFlagProvider` | Un fournisseur qui peut lister ses indicateurs : `all()`. | port |
| `DiagnosableFlagProvider` | Un fournisseur énumérable qui liste aussi les indicateurs qu'il ne peut pas lire : `unreadable()`. | port |
| `UnreadableFlag` | Un indicateur qu'un fournisseur contient sans pouvoir le lire : le nom tel qu'il est stocké et la raison. | implémenté |
| `FlagStateSource` | Une source d'états actifs : `isActive()`. | port |

## Domain\Flag\Rule

| Type | Rôle | État |
|---|---|---|
| `Rule` | Une règle de ciblage : `matches()`, `matchReason()`, `reads()`. | port |
| `MemberOf` | Correspond lorsqu'un attribut fait partie d'un ensemble explicite ; raison `TARGETING_MATCH`. | implémenté |
| `PercentageRollout` | Correspond à une part stable des valeurs d'un attribut ; raison `SPLIT`. | implémenté |

## Domain\Toggle

| Type | Rôle | État |
|---|---|---|
| `ToggleReason` | Une raison obligatoire, débarrassée des blancs Unicode aux extrémités, d'au plus 1 000 caractères ; `BLANK` est la classe des caractères blancs. | implémenté |
| `ToggleRequest` | Un acteur qui demande de rendre un indicateur actif ou inactif, avec une raison. | implémenté |
| `ToggleRecord` | La ligne d'audit d'une bascule appliquée. | implémenté |

## Domain\Edit

| Type | Rôle | État |
|---|---|---|
| `EditRequest` | Un acteur qui demande de créer un indicateur (sans révision) ou d'en changer un (avec la révision lue). | implémenté |
| `EditRecord` | La ligne d'audit d'une création ou d'une modification appliquée, avec les définitions avant et après. | implémenté |
| `RevisedDefinition` | Une définition stockée et sa révision (au moins 1). | implémenté |

## Domain\Exception

| Type | Rôle | État |
|---|---|---|
| `ToggleRefused` | L'autorisateur a refusé un changement. | implémenté |
| `UnknownFlag` | Une bascule ou une modification a nommé un indicateur qui ne peut pas être changé. | implémenté |
| `ToggleConflict` | Une bascule a perdu une course. | implémenté |
| `EditConflict` | Une révision périmée, une écriture perdue ou un nom déjà déclaré. | implémenté |

Toutes implémentent `Phil\DddBundle\Domain\Exception\DomainError`.

## Application\Flag

| Type | Rôle | État |
|---|---|---|
| `FlagEvaluator` | Évalue les indicateurs une fois par requête et par contexte à partir de fournisseurs ordonnés, d'un magasin d'états et d'un enregistreur d'expositions ; `definition()`, `reset()`. | implémenté, branché (`kernel.reset`) |
| `FlagCatalog` | La configuration actuelle : `entries()`, `unreadable()`, `lookup()`, `find()`. | implémenté, branché |
| `CatalogEntry` | Un indicateur avec sa définition déclarée, sa source et son état stocké ; `effective()`. | implémenté |
| `UnreadableEntry` | Un indicateur que le fournisseur qui le déclare ne peut pas lire : nom, raison, source. | implémenté |
| `AnonymousContextResolver` | Un contexte sans attributs. | implémenté, branché |
| `EvaluationContextResolver` | Construit le contexte de la requête courante. | port |
| `ExposureRecorder` | Compte les expositions A/B par indicateur et par variante. | port |

## Application\Toggle

| Type | Rôle | État |
|---|---|---|
| `FlagToggler` | La bascule auditée : autoriser, lire, auditer, écrire (comparaison-échange ou première insertion). | implémenté, branché avec `toggle` |
| `ToggleAuthorizer` | Décide des bascules, des créations et des modifications. | port |
| `ToggleAuditor` | Écrit la ligne d'audit de chaque changement. | port |
| `WritableFlagStore` | Un magasin d'états où écrit une bascule : `compareAndSet()`, `insert()`. | port |

## Application\Edit

| Type | Rôle | État |
|---|---|---|
| `FlagEditor` | La création et la modification auditées, conditionnelles à la révision. | implémenté, branché avec `toggle` et un magasin de définitions |
| `WritableFlagDefinitions` | Les définitions où écrit l'administration : `findRevised()`, `create()`, `replace()`. | port |

## Application\Admin

| Type | Rôle | État |
|---|---|---|
| `ActorProvider` | L'opérateur des écrans d'administration. | port |

## Application\Circuit

| Type | Rôle | État |
|---|---|---|
| `FlagCircuitOverride` | Le `CircuitOverride` de `phil/circuit-breaker` : force l'ouverture d'un circuit tant que `circuit-open.<key>` est activé ; `flagFor()`, `FORCE_OPEN_FLAG_PREFIX`. | implémenté, branché et étiqueté `phil_circuit_breaker.override` |

## Infrastructure\Configuration

| Type | Rôle | État |
|---|---|---|
| `ConfiguredFlagProvider` | Les indicateurs de `phil_feature_flags.flags`, plus l'indicateur d'ouverture forcée de chaque circuit ; énumérable. | implémenté, branché |
| `FlagDefinitionFormat` | La forme de tableau unique d'un indicateur, lue à partir de la configuration, des rangées de la base de données et des formulaires d'administration, et réécrite dans les deux derniers. | implémenté |

## Infrastructure\Doctrine

| Type | Rôle | État |
|---|---|---|
| `DoctrineFlagDefinitions` | Les indicateurs créés dans l'administration, dans `phil_feature_flags_definition` : fournisseur diagnosticable (une rangée illisible donne `PARSE_ERROR` et est listée avec sa raison) et définitions modifiables. | implémenté, testé sur SQLite, MySQL et PostgreSQL, branché avec `doctrine` |
| `DoctrineFlagStore` | Les états actifs dans `phil_feature_flags_state`. | implémenté, testé, branché avec `doctrine` |
| `FlagSchemaListener` | Ajoute les tables au schéma généré par l'ORM. | implémenté, testé, branché avec `doctrine` |
| `FlagTables` | Les noms des tables et `configure(Schema)`. | implémenté |

## Infrastructure\Http

| Type | Rôle | État |
|---|---|---|
| `IsFeatureEnabled` | Attribut de contrôleur : 404 tant que l'indicateur est désactivé ou hors de la variante. | implémenté |
| `FeatureGateListener` | Fait respecter `IsFeatureEnabled` sur `kernel.controller`. | implémenté, branché |
| `FlagAdminController` | Les écrans d'administration : `list`, `show`, `toggle`, `create`, `edit`. | implémenté, branché avec `admin` |
| `SameOriginGuard` | Refuse les envois de formulaire intersites selon `Sec-Fetch-Site` ou `Origin`. | implémenté |

## Infrastructure\Routing, Twig, Console

| Type | Rôle | État |
|---|---|---|
| `Routing\FlagRoutingCondition` | `service('feature_flags')` dans les conditions de route : `enabled()`, `variant()` ; refuse un indicateur qui cible des sujets ou des locataires. | implémenté, branché |
| `Routing\FlagNotRoutable` | Lancée par une condition de route qui lit un indicateur ciblant des sujets ou des locataires. | implémenté |
| `Twig\FeatureFlagsExtension` | `feature_enabled()` et `feature_variant()`. | implémenté, branché |
| `Console\ListFlagsCommand` | `phil:feature-flags:list`. | implémenté, branché |

## Infrastructure\DependencyInjection

| Type | Rôle | État |
|---|---|---|
| `ForceOpenFlagsPass` | Donne au fournisseur de configuration les clés des circuits de `phil/circuit-breaker-bundle`, pour que chacun déclare son indicateur d'ouverture forcée. | implémenté, ajouté par le bundle |

## Bundle

| Type | Rôle | État |
|---|---|---|
| `PhilFeatureFlagsBundle` | L'arbre de configuration (alias `phil_feature_flags`), la validation à la construction, le câblage ; les constantes d'identifiants de service. | implémenté, testé |

## Ressources

| Chemin | Rôle |
|---|---|
| `config/routes/admin.php` | Les cinq routes d'administration. |
| `templates/admin/layout.html.twig` | La mise en page par défaut (blocs `title` et `content`). |
| `templates/admin/list.html.twig`, `show.html.twig`, `form.html.twig` | Les écrans d'administration. |
