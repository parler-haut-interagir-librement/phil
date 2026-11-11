<!-- phil-dev source-sha256=1eab543db91233d0d6404e18ad7b01a6bdc47468ab6e4e5a6a9aaa64032883ae -->
# Points d'extension

Le bundle porte ce que sa conception décide et laisse à l'application, au moyen de ports (des interfaces), ce que seule
l'application peut décider : le contexte d'une requête, la permission et la piste d'audit d'un changement, l'opérateur
de l'administration, l'endroit où les expositions sont comptées. Cette page donne le contrat de chaque port, les
invariants qu'un adaptateur doit respecter et un court exemple. Elle se termine par chaque identifiant de service,
étiquette, route, fonction Twig et attribut que déclare le bundle.

Enregistrez un adaptateur comme service et nommez-le dans la configuration (ou laissez l'autowiring donner l'alias de
l'interface à l'implémentation unique, ce qu'attendent les valeurs par défaut de `context_resolver`, `toggle.*` et
`admin.actor_provider`).

| Port | Espace de noms | Requis lorsque |
|---|---|---|
| `EvaluationContextResolver` | `Application\Flag` | Toujours, sauf si `context_resolver` est le résolveur anonyme. |
| `FlagProvider` | `Domain\Flag` | Facultatif : un fournisseur en plus de la configuration et de la base de données. |
| `EnumerableFlagProvider` | `Domain\Flag` | Facultatif : un fournisseur dont les indicateurs apparaissent dans la liste et l'administration. |
| `FlagStateSource` | `Domain\Flag` | Facultatif : un magasin d'états en lecture seule. |
| `WritableFlagStore` | `Application\Toggle` | `toggle` sans le magasin en base de données. |
| `WritableFlagDefinitions` | `Application\Edit` | La création et la modification sans le magasin en base de données. |
| `ToggleAuthorizer` | `Application\Toggle` | `toggle`. |
| `ToggleAuditor` | `Application\Toggle` | `toggle`. |
| `ActorProvider` | `Application\Admin` | `admin`. |
| `ExposureRecorder` | `Application\Flag` | Facultatif : le comptage des expositions A/B. |
| `Rule` | `Domain\Flag\Rule` | Facultatif : les règles de ciblage d'un fournisseur personnalisé. |

Tous les espaces de noms se trouvent sous `Phil\FeatureFlagsBundle\`.

## EvaluationContextResolver

```php
interface EvaluationContextResolver
{
    public function current(): EvaluationContext;
}
```

Construit le contexte de la requête courante : à partir du jeton de sécurité, du locataire actif, du projet visé par la
requête. Il est appelé **à chaque évaluation**, pour qu'un contexte qui change pendant la requête (le pare-feu qui
charge l'utilisateur après une lecture précoce) reçoive ses propres évaluations.

Invariants :

- peu coûteux : il lit le jeton et la requête, et mémoïse toute recherche dont il a besoin (un locataire chargé de la
  base de données), indexée par ce qu'il lit ;

- des identifiants seulement (`EvaluationContext::of(tenant:, project:, subject:)`), `null` pour un attribut absent,
  jamais une chaîne vide (refusée) ;
- ne lance jamais d'exception pour une requête anonyme : retournez un contexte avec des `null`, et les indicateurs
  ciblés sont désactivés ;
- dans un consommateur de messages, lit les champs du message, et non une requête qui n'existe pas.

```php
use Phil\FeatureFlagsBundle\Application\Flag\EvaluationContextResolver;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class RequestContextResolver implements EvaluationContextResolver
{
    public function __construct(private Security $security, private CurrentTenant $tenant) {}

    public function current(): EvaluationContext
    {
        return EvaluationContext::of(
            tenant: $this->tenant->id(),                                   // ?string
            subject: $this->security->getUser()?->getUserIdentifier(),
        );
    }
}
```

Le bundle fournit `AnonymousContextResolver` (service `phil_feature_flags.context_resolver.anonymous`), qui retourne
toujours `EvaluationContext::anonymous()`.

## FlagProvider

```php
interface FlagProvider
{
    public function find(FlagName $name): ?FlagDefinition;
}
```

Une source de définitions. Invariants :

- retourne `null` pour un indicateur qu'il ne connaît pas, pour que le fournisseur suivant réponde ; jamais une
  définition inactive inventée, qui cacherait celle du fournisseur suivant ;
- retourne une définition dont le `name` est le nom demandé ;
- lève `InvalidValue` pour un indicateur qu'il tient dont la définition ne se lit plus comme telle (l'évaluation est
  alors `PARSE_ERROR`), et laisse tout autre échec de sa source remonter en exception (l'évaluation est alors
  `GENERAL`) ; il ne répond jamais `null` pour un indicateur qu'il n'a pas pu lire, ce qui laisserait un fournisseur
  suivant répondre à sa place ;
- est peu coûteux à appeler plusieurs fois dans une requête (l'évaluateur mémoïse par indicateur, le catalogue non) :
  mettez en cache par requête et donnez au service l'étiquette `kernel.reset` s'il lit des données distantes.

Ajoutez-le à `providers` à la position qui lui donne sa priorité.

## EnumerableFlagProvider

```php
interface EnumerableFlagProvider extends FlagProvider
{
    /** @return list<FlagDefinition> */
    public function all(): array;
}
```

Un fournisseur qui peut lister chaque indicateur qu'il connaît : ses indicateurs apparaissent alors dans la commande de
liste et dans la liste de l'administration. `all()` doit concorder avec `find()`. Un service distant qui ne répond qu'un
indicateur à la fois reste un simple `FlagProvider`.

## DiagnosableFlagProvider

```php
interface DiagnosableFlagProvider extends EnumerableFlagProvider
{
    /** @return list<UnreadableFlag> by name */
    public function unreadable(): array;
}
```

Un fournisseur énumérable qui peut aussi nommer les indicateurs qu'il contient sans pouvoir les lire, comme une table
dont les rangées peuvent être modifiées à la main. Pour un tel indicateur, `find()` lance une `InvalidValue`
(l'évaluation est `PARSE_ERROR`), `all()` le laisse de côté, et `unreadable()` retourne un `UnreadableFlag` avec le nom
tel qu'il est stocké et la raison. Le catalogue liste alors l'indicateur à part, à sa place dans la préséance, et la
commande de liste et l'administration l'affichent avec sa raison au lieu de le cacher. `DoctrineFlagDefinitions`
l'implémente.

```php
use Phil\FeatureFlagsBundle\Domain\Flag\EnumerableFlagProvider;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Infrastructure\Configuration\FlagDefinitionFormat;

/** Flags shipped in a JSON file, in the configuration's shape. */
final class JsonFileFlags implements EnumerableFlagProvider
{
    /** @var array<string, FlagDefinition>|null */
    private ?array $flags = null;

    public function __construct(private readonly string $path) {}

    public function find(FlagName $name): ?FlagDefinition
    {
        return $this->load()[$name->value] ?? null;
    }

    public function all(): array
    {
        return array_values($this->load());
    }

    /** @return array<string, FlagDefinition> */
    private function load(): array
    {
        if (null === $this->flags) {
            $this->flags = [];
            foreach (json_decode((string) file_get_contents($this->path), true, 16, \JSON_THROW_ON_ERROR) as $name => $flag) {
                $this->flags[$name] = FlagDefinitionFormat::read(FlagName::of($name), $flag);
            }
        }

        return $this->flags;
    }
}
```

## FlagStateSource

```php
interface FlagStateSource
{
    public function isActive(FlagName $flag): ?bool;
}
```

L'état actif d'un indicateur lorsqu'il peut changer sans déploiement. Invariants :

- ne contient que l'état actif, jamais de règles : l'état remplace le `active` du fournisseur, et les règles restent
  celles du fournisseur ;
- retourne `null` pour un indicateur qu'il ne contient pas, pour que l'état du fournisseur s'applique ;
- l'évaluateur ignore un état pour un indicateur qu'aucun fournisseur ne déclare.

Nommez-le comme `state_store`. Les sources en lecture seule conviennent sans `toggle`.

## WritableFlagStore

```php
interface WritableFlagStore extends FlagStateSource
{
    public function compareAndSet(FlagName $flag, bool $expected, bool $active): bool;
    public function insert(FlagName $flag, bool $active): bool;
}
```

Ce dans quoi écrit une bascule (et l'éditeur, pour l'état initial d'un indicateur créé). Invariants :

- `compareAndSet()` fixe l'état **seulement s'il vaut toujours `$expected`**, de façon atomique (un `UPDATE`
  conditionnel, une comparaison-échange), et retourne `false` lorsque l'état actuel ne vaut plus `$expected` ou que
  l'indicateur n'est pas contenu ;
- `insert()` stocke l'état d'un indicateur que le magasin ne contient pas encore, **seulement s'il ne le contient
  toujours pas**, de façon atomique, et retourne `false` lorsque l'indicateur est déjà contenu ;
- après une écriture, `isActive()` dans le même processus la reflète (videz tout cache par requête à l'écriture) ;
- ne lance d'exception qu'en cas de panne d'infrastructure : une course perdue est un `false`, jamais une exception.

```php
use Phil\FeatureFlagsBundle\Application\Toggle\WritableFlagStore;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;

/** Active states in Redis, with atomic primitives. */
final readonly class RedisFlagStore implements WritableFlagStore
{
    private const string LUA_CAS = <<<'LUA'
        if redis.call('HGET', KEYS[1], ARGV[1]) == ARGV[2] then redis.call('HSET', KEYS[1], ARGV[1], ARGV[3]) return 1 end
        return 0
        LUA;

    public function __construct(private \Redis $redis) {}

    public function isActive(FlagName $flag): ?bool
    {
        $value = $this->redis->hGet('flags', $flag->value);

        return false === $value ? null : '1' === $value;
    }

    public function compareAndSet(FlagName $flag, bool $expected, bool $active): bool
    {
        return 1 === $this->redis->eval(self::LUA_CAS, ['flags', $flag->value, $expected ? '1' : '0', $active ? '1' : '0'], 1);
    }

    public function insert(FlagName $flag, bool $active): bool
    {
        return true === $this->redis->hSetNx('flags', $flag->value, $active ? '1' : '0');
    }
}
```

## WritableFlagDefinitions

```php
interface WritableFlagDefinitions
{
    public function findRevised(FlagName $flag): ?RevisedDefinition;
    public function create(FlagDefinition $definition): bool;
    public function replace(FlagDefinition $definition, int $revision): bool;
}
```

Les définitions qu'une administration crée et modifie, habituellement aussi l'un des fournisseurs de l'évaluateur (il
devrait alors être listé dans `providers`, et c'est le `definition_store`). Invariants :

- `findRevised()` retourne la définition stockée avec sa révision (au moins 1), ou `null` ; il lit la valeur actuelle,
  pas un cache par requête, et peut lancer `InvalidValue` pour une rangée qui ne se lit plus ;
- `create()` stocke la révision 1 et retourne `false` lorsqu'un indicateur de ce nom est déjà contenu, de façon
  atomique ;
- `replace()` n'écrit **que si la révision vaut toujours `$revision`**, l'incrémente, et retourne `false` lorsqu'elle a
  changé ou que l'indicateur a disparu, de façon atomique ;
- une écriture vide tout cache par requête que garde le côté fournisseur.

`DoctrineFlagDefinitions` est l'implémentation de référence ([database-store.md](database-store.md)).

## ToggleAuthorizer

```php
interface ToggleAuthorizer
{
    /** @throws ToggleRefused */
    public function authorize(ToggleRequest $request): void;

    /** @throws ToggleRefused */
    public function authorizeEdit(EditRequest $request): void;
}
```

Décide si un acteur peut changer un indicateur. Appelé en premier, avant toute lecture. Invariants :

- retourne normalement pour autoriser ; lance `ToggleRefused::forFlag($flag, $why)` pour refuser (`$why` est montré à
  l'opérateur dans l'administration) ;
- n'a aucun effet de bord : un changement refusé n'écrit et n'audite rien.

`ToggleRequest` porte `flag`, `active` (l'état demandé), `actor`, `reason` ; `EditRequest` porte `definition`, `actor`,
`reason`, `revision` (`null` pour une création), avec `flag()` et `isCreation()`.

```php
use Phil\FeatureFlagsBundle\Application\Toggle\ToggleAuthorizer;
use Phil\FeatureFlagsBundle\Application\Circuit\FlagCircuitOverride;
use Phil\FeatureFlagsBundle\Domain\Edit\EditRequest;
use Phil\FeatureFlagsBundle\Domain\Exception\ToggleRefused;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleRequest;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class PermissionAuthorizer implements ToggleAuthorizer
{
    public function __construct(private Security $security) {}

    public function authorize(ToggleRequest $request): void
    {
        if (!$this->security->isGranted('ROLE_FLAG_OPERATOR')) {
            throw ToggleRefused::forFlag($request->flag, 'the operator lacks the flag permission.');
        }

        if (str_starts_with($request->flag->value, FlagCircuitOverride::FORCE_OPEN_FLAG_PREFIX)
            && !$this->security->isGranted('ROLE_CIRCUIT_OPERATOR')) {
            throw ToggleRefused::forFlag($request->flag, 'forcing a circuit needs the circuit permission.');
        }
    }

    public function authorizeEdit(EditRequest $request): void
    {
        if (!$this->security->isGranted('ROLE_FLAG_EDITOR')) {
            throw ToggleRefused::forFlag($request->flag(), 'the operator lacks the edit permission.');
        }
    }
}
```

## ToggleAuditor

```php
interface ToggleAuditor
{
    public function record(ToggleRecord $record): void;
    public function recordEdit(EditRecord $record): void;
}
```

Écrit la ligne d'audit d'un changement **avant** que le changement soit écrit. Invariants :

- lance une exception lorsqu'il ne peut pas écrire la ligne : le changement n'est alors pas appliqué ;
- la ligne peut survivre à une écriture qui échoue ensuite, à moins que l'appelant exécute le changement dans une seule
  transaction partagée avec le magasin ([toggles-and-edits.md](toggles-and-edits.md#transactions)) ;
- conservez ce que porte l'enregistrement : l'acteur, l'indicateur, l'avant et l'après, la raison, l'heure.

```php
use Phil\FeatureFlagsBundle\Application\Toggle\ToggleAuditor;
use Phil\FeatureFlagsBundle\Domain\Edit\EditRecord;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleRecord;
use Phil\FeatureFlagsBundle\Infrastructure\Configuration\FlagDefinitionFormat;

final readonly class TableAuditor implements ToggleAuditor
{
    public function __construct(private \Doctrine\DBAL\Connection $db) {}

    public function record(ToggleRecord $r): void
    {
        $this->db->insert('flag_audit', [
            'flag' => $r->flag->value, 'actor' => $r->actor, 'reason' => $r->reason->value,
            'change' => json_encode(['active' => [$r->before, $r->after]]), 'at' => $r->at->format(DATE_ATOM),
        ]);
    }

    public function recordEdit(EditRecord $r): void
    {
        $this->db->insert('flag_audit', [
            'flag' => $r->flag->value, 'actor' => $r->actor, 'reason' => $r->reason->value,
            'change' => json_encode([
                'before' => null === $r->before ? null : FlagDefinitionFormat::write($r->before),
                'after' => FlagDefinitionFormat::write($r->after),
            ]),
            'at' => $r->at->format(DATE_ATOM),
        ]);
    }
}
```

## ActorProvider

```php
interface ActorProvider
{
    public function current(): ?string;
}
```

Qui utilise les écrans d'administration : l'identifiant opaque que l'application donne à l'opérateur, tiré de sa propre
authentification, ou `null` lorsque personne n'est authentifié (chaque écran répond alors 403). L'identifiant est
inscrit dans les lignes d'audit et ne doit pas être blanc. Exemple dans
[admin.md](admin.md#lopérateur-et-le-pare-feu).

## ExposureRecorder

```php
interface ExposureRecorder
{
    public function record(FlagName $flag, VariantName $variant): void;
}
```

Informé qu'une variante a été montrée : une fois par indicateur et par contexte dans chaque requête, à la première
évaluation qui donne une variante. Invariants :

- reçoit l'indicateur et la variante, rien d'autre, et ne doit pas tenter d'y rattacher un contexte : une exposition est
  un compte ajouté à un agrégat, jamais un registre de qui a vu quoi ;
- est appelé à l'intérieur de l'évaluation : gardez-le rapide, et ne laissez pas une panne d'infrastructure briser la
  page (attrapez et journalisez, ou mettez en tampon et videz plus tard).

Exemple dans [experiments.md](experiments.md#expositions).

## Rule

```php
interface Rule
{
    public function matches(FlagName $flag, EvaluationContext $context): bool;
    public function matchReason(): EvaluationReason;
    public function reads(): TargetingAttribute;
}
```

Une règle de ciblage. Invariants : une règle qui ne peut pas lire l'attribut dont elle a besoin ne correspond pas
(fermeture par défaut) ; `reads()` nomme l'unique attribut du contexte qu'elle lit, ce qui permet à une condition de
route de distinguer un indicateur qu'elle peut évaluer avant l'authentification d'un indicateur qu'elle ne peut pas
évaluer ; `matches()` est déterministe pour un indicateur et un contexte ; `matchReason()` vaut `TARGETING_MATCH` pour
une règle qui sélectionne des contextes et `SPLIT` pour une règle qui en admet une part. Les règles personnalisées
fonctionnent dans les définitions construites par les fournisseurs de l'application, mais ne peuvent pas être écrites
par `FlagDefinitionFormat` (la configuration, le magasin en base de données et l'administration ne connaissent que
`MemberOf` et `PercentageRollout`).

## Identifiants de service

| Identifiant de service | Classe | Enregistré | Étiquettes |
|---|---|---|---|
| `phil_feature_flags.provider.configuration` | `Infrastructure\Configuration\ConfiguredFlagProvider` | toujours | — |
| `phil_feature_flags.context_resolver.anonymous` | `Application\Flag\AnonymousContextResolver` | toujours | — |
| `Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator` | `FlagEvaluator` | toujours | `kernel.reset` |
| `Phil\FeatureFlagsBundle\Application\Flag\FlagCatalog` | `FlagCatalog` | toujours | — |
| `Phil\FeatureFlagsBundle\Infrastructure\Console\ListFlagsCommand` | `ListFlagsCommand` | toujours | `console.command` |
| `Phil\FeatureFlagsBundle\Infrastructure\Http\FeatureGateListener` | `FeatureGateListener` | toujours | `kernel.event_listener` (`kernel.controller`) |
| `Phil\FeatureFlagsBundle\Infrastructure\Routing\FlagRoutingCondition` | `FlagRoutingCondition` | toujours | `routing.condition_service` (alias `feature_flags`) |
| `Phil\FeatureFlagsBundle\Infrastructure\Twig\FeatureFlagsExtension` | `FeatureFlagsExtension` | toujours | `twig.attribute_extension`, `twig.runtime` |
| `phil_feature_flags.provider.doctrine` | `Infrastructure\Doctrine\DoctrineFlagDefinitions` | `doctrine` | `kernel.reset` |
| `phil_feature_flags.state_store.doctrine` | `Infrastructure\Doctrine\DoctrineFlagStore` | `doctrine` | `kernel.reset` |
| `Phil\FeatureFlagsBundle\Infrastructure\Doctrine\FlagSchemaListener` | `FlagSchemaListener` | `doctrine` | `doctrine.event_listener` (`postGenerateSchema`, la connexion) |
| `Phil\FeatureFlagsBundle\Application\Toggle\FlagToggler` | `FlagToggler` | `toggle` | — |
| `Phil\FeatureFlagsBundle\Application\Edit\FlagEditor` | `FlagEditor` | `toggle` et un magasin de définitions | — |
| `Phil\FeatureFlagsBundle\Infrastructure\Http\FlagAdminController` | `FlagAdminController` | `admin` | `controller.service_arguments` ; public |
| `Phil\FeatureFlagsBundle\Application\Circuit\FlagCircuitOverride` | `FlagCircuitOverride` | toujours | `phil_circuit_breaker.override` |

Les identifiants sous forme de chaîne sont aussi des constantes de `PhilFeatureFlagsBundle` : `CONFIGURATION_PROVIDER`,
`DOCTRINE_PROVIDER`, `DOCTRINE_STATE_STORE`, `ANONYMOUS_CONTEXT_RESOLVER` ; `DEFAULT_LAYOUT` est la mise en page
d'administration par défaut. Chaque service est privé sauf le contrôleur d'administration ; injectez-les par type (ceux
qui portent le nom d'une classe s'injectent automatiquement) ou par identifiant.

## Routes

| Nom | Chemin (sous le préfixe d'importation) | Méthodes |
|---|---|---|
| `phil_feature_flags_admin_list` | `/` | GET |
| `phil_feature_flags_admin_create` | `/_new` | GET, POST |
| `phil_feature_flags_admin_show` | `/{name}` | GET |
| `phil_feature_flags_admin_toggle` | `/{name}/toggle` | POST |
| `phil_feature_flags_admin_edit` | `/{name}/edit` | GET, POST |

Chargées à partir de `@PhilFeatureFlagsBundle/config/routes/admin.php` ([admin.md](admin.md)).

## Fonctions Twig

| Fonction | Retourne |
|---|---|
| `feature_enabled(string flag)` | `bool` |
| `feature_variant(string flag)` | `?string` |

## Service de condition de routage

`service('feature_flags')` dans une condition de route, avec `enabled(string $flag): bool` et
`variant(string $flag): ?string`. Un indicateur avec une règle ou une expérience sur le sujet ou le locataire lance
`Infrastructure\Routing\FlagNotRoutable` ([placement.md](placement.md#dans-le-routage--la-condition-de-route)).

## Attribut

`#[Phil\FeatureFlagsBundle\Infrastructure\Http\IsFeatureEnabled(string $flag, ?string $variant = null)]`, sur une classe
ou une méthode de contrôleur, répétable ([placement.md](placement.md)).

## Commande de console

`phil:feature-flags:list [--expired] [--fail-on-expired]` ([console.md](console.md)).

## Espace de noms Twig

`@PhilFeatureFlags`, qui contient `admin/layout.html.twig`, `admin/list.html.twig`, `admin/show.html.twig` et
`admin/form.html.twig`.
