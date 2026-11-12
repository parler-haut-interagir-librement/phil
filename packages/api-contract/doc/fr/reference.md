<!-- phil-dev source-sha256=1d23b3151edd661e92eb636ec7044d9eae5a84b71a671deab4a633a987a9e9ee -->
# Référence

Chaque type public de la bibliothèque, par sujet. Les espaces de noms sont relatifs à `Phil\ApiContract`. Tous les
objets-valeurs sont immuables (`final readonly`), et les constructeurs sont privés derrière des constructeurs nommés,
sauf indication contraire.

## Problem

| Type | Rôle | État |
|---|---|---|
| `ProblemDetails` | Un document de problème RFC 9457 : `create()` strict pour le serveur, `fromArray()` tolérant pour le client, `toArray()`, `hasType()`, `isBlank()`, les constantes `MEDIA_TYPE` et `BLANK_TYPE`. | Implémenté et testé |

## Deprecation

| Type | Rôle | État |
|---|---|---|
| `DeprecationSignal` | `Deprecation`, `Sunset` et `Link: rel="deprecation"` : `of()` et `toHeaders()` pour le serveur, `fromHeaders()` pour le client, `isDeprecatedAt()`, `isSunsetAt()`, `noticeInDays()`. | Implémenté et testé |
| `LinkHeader` | `firstWithRelation()` : la première cible d'une relation de lien parmi les lignes `Link` (sous-ensemble de la RFC 8288). | Implémenté et testé |
| `ObservedDeprecations` | Port : l'enregistrement par le client des signaux par point de terminaison, `record()` et `all()`. | Port seulement |

## Http

| Type | Rôle | État |
|---|---|---|
| `HttpDate` | `format()` en IMF-fixdate en UTC; `parse()` des trois formes de la RFC 9110, `null` pour tout le reste. | Implémenté et testé |

## TolerantReading

| Type | Rôle | État |
|---|---|---|
| `TolerantEnum` | `withDefault()` pour un champ ordinaire, `authoritative()` pour un champ d'autorité; strict, sans conversion. | Implémenté et testé |

## OpenApi

| Type | Rôle | État |
|---|---|---|
| `ContractVerifier` | Vérifie un `OpenApiDocument` à un `RuleLevel` : `withBuiltInRules()`, `builtInRules()`, `without()`, `rulesAt()`, `verify()`; la liste publique `rules`. Les identifiants de règle sont uniques et bien formés. | Implémenté et testé |
| `OpenApiDocument` | Un document lu pour la vérification : `fromJson()`, `fromArray()`, borné par `MAX_BYTES`, `MAX_DEPTH` et `MAX_VALUES`; `operations()`, `at()` pour une référence locale, `resolve()` pour une chaîne de références, `nodes()`, `pointer()`; la constante `METHODS`. | Implémenté et testé |
| `Operation` | Une opération : `path`, `method`, `pointer`, `operation`, `pathItem`; `parameters()` et `responses()` avec leurs références suivies. Constructeur public. | Implémenté et testé |
| `RuleLevel` | Enum `Minimal`, `Recommended`, `Strict`; `includes()`. | Implémenté et testé |
| `Severity` | Enum `Error`, `Warning`; `reaches()`. | Implémenté et testé |
| `Finding` | L'identifiant de la règle, la gravité, le pointeur JSON et le message, les deux derniers gardés imprimables et bornés à `MAX_LENGTH`; `of()`, `toArray()`. Constructeur public. | Implémenté et testé |
| `Report` | Le niveau et les constats; `fails()`, `reaching()`, `toArray()`. Constructeur public. | Implémenté et testé |

## OpenApi\Rule

Chaque règle est une classe `final readonly` avec un constructeur public et une constante `ID`; voyez
[Vérification OpenAPI](openapi-verification.md#les-règles) pour ce que chacune demande.

| Niveau | Règles |
|---|---|
| Minimal | `OpenApiVersion`, `Info`, `Paths`, `OperationResponses`, `PathParameters`, `OperationIdUnique`, `LocalReferences` |
| Recommended | `NoVersionInPath`, `SuccessResponse`, `JsonContent` (avec le `isJson()` statique), `ProblemDetails`, `DeprecationSunset`, `OperationId` |
| Strict | `ExtensibleEnum`, `ErrorResponses`, `OperationDescription`, `SecurityRequirement` |

## Contract

| Type | Rôle | État |
|---|---|---|
| `OpenApiRule` | Interface d'une règle de vérification : `id()`, `level()`, `severity()`, `description()`, `check()`. | Interface |

## Exception

| Type | Rôle | État |
|---|---|---|
| `ApiContractException` | Interface marqueur qui étend `Throwable` : attrapez tout ce que lève la bibliothèque avec un seul `catch`. | Implémenté |
| `InvalidContractValue` | `InvalidArgumentException` : un objet-valeur a refusé sa propre construction; `because(string)`. | Implémenté et testé |
| `UnknownAuthorityValue` | `UnexpectedValueException` : une valeur inconnue dans un champ d'autorité; `for(class-string, mixed)`, message limité à 64 octets et échappé. | Implémenté et testé |
| `UnreadableDocument` | `InvalidArgumentException` : un document OpenAPI qui ne peut pas du tout être vérifié; `because(string)`. | Implémenté et testé |

## Hors de la bibliothèque

- Un service, une configuration, un écouteur d'événements, un décorateur de client HTTP ou une commande console
  Symfony, un adaptateur pour `ObservedDeprecations` et la lecture d'un fichier YAML : `phil/api-contract-bundle` les
  apporte.
- La comparaison de deux versions d'un document, et la vérification du trafic réel contre l'une d'elles : la
  vérification lit un seul document.
- Les clés, les empreintes et les stockages d'idempotence : `phil/idempotency`.
- La moitié de la lecture tolérante qui concerne les champs inconnus, qui appartient au désérialiseur.
