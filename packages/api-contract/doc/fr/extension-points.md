<!-- phil-dev source-sha256=71a6b945553b1321164d3f7eae65bb794f6059db15bdab4766f5af2e03947eac -->
# Points d'extension

La bibliothèque définit un port, dont elle ne fournit aucune implémentation, et une interface pour les règles de
vérification, qu'elle implémente dix-sept fois. `phil/api-contract-bundle` apporte aux applications Symfony un
`ObservedDeprecations` appuyé sur un cache, et donne à son vérificateur chaque service qui implémente `OpenApiRule`. Le
stockage de l'idempotence est un port de `phil/idempotency`.

| Port | Côté | Utilisé par |
|---|---|---|
| `Phil\ApiContract\Deprecation\ObservedDeprecations` | Client | Votre journal, votre panneau de profileur et votre commande de vérification. |
| `Phil\ApiContract\Contract\OpenApiRule` | Serveur | `ContractVerifier`, à côté des règles intégrées. |

## `ObservedDeprecations`

L'endroit où un client garde les signaux d'obsolescence qu'il a reçus, point de terminaison par point de terminaison.
L'adaptateur (un pool de cache, une table, un fichier) appartient à l'application, tout comme le sens de « point de
terminaison » : un nom de route, ou une méthode et un modèle de chemin, **jamais une URL qui contient des
identifiants**.

### Méthodes

| Méthode | Contrat |
|---|---|
| `record(string $endpoint, DeprecationSignal $signal, DateTimeImmutable $observedAt): bool` | Enregistre le dernier signal vu sur un point de terminaison. Retourne `true` quand rien n'a été enregistré pour ce point de terminaison depuis `$observedAt` moins un jour : c'est le moment où l'appelant journalise son avertissement, pour que le journal avertisse une fois par point de terminaison et par jour. |
| `all(): array` | `array<string, DeprecationSignal>`, le dernier signal par point de terminaison, pour le panneau du profileur et la commande de vérification. |

### Invariants

- `record()` stocke toujours le signal qu'il reçoit, même quand il retourne `false` : le dernier signal l'emporte.
- La fenêtre d'un jour se mesure à partir du dernier enregistrement de ce point de terminaison, pas à partir de minuit.

### Exemple d'adaptateur (cache PSR-6)

```php
use Phil\ApiContract\Deprecation\{DeprecationSignal, ObservedDeprecations};
use Psr\Cache\CacheItemPoolInterface;

final readonly class CacheObservedDeprecations implements ObservedDeprecations
{
    private const string INDEX = 'observed_deprecations';

    public function __construct(private CacheItemPoolInterface $cache) {}

    public function record(string $endpoint, DeprecationSignal $signal, DateTimeImmutable $observedAt): bool
    {
        $item = $this->cache->getItem(self::INDEX);
        /** @var array<string, array{signal: DeprecationSignal, at: DateTimeImmutable}> $seen */
        $seen = $item->isHit() ? $item->get() : [];

        $previous = $seen[$endpoint]['at'] ?? null;
        $seen[$endpoint] = ['signal' => $signal, 'at' => $observedAt];
        $this->cache->save($item->set($seen));

        return null === $previous || $previous <= $observedAt->modify('-1 day');
    }

    public function all(): array
    {
        $item = $this->cache->getItem(self::INDEX);

        return array_map(static fn (array $entry): DeprecationSignal => $entry['signal'], $item->isHit() ? $item->get() : []);
    }
}
```

Une commande de vérification échoue alors tant qu'un signal enregistré porte un retrait :

```php
$sunset = array_filter($observed->all(), static fn (DeprecationSignal $s): bool => null !== $s->sunsetAt);

return [] === $sunset ? Command::SUCCESS : Command::FAILURE;
```

## `OpenApiRule`

Une règle de la vérification d'un document OpenAPI : une règle du style maison de l'application, ou une règle dont son
API a besoin. Elle est donnée à `ContractVerifier::withBuiltInRules($rule)`, ou à `new ContractVerifier([...])` à côté
des règles que l'application garde.

### Méthodes

| Méthode | Contrat |
|---|---|
| `id(): string` | De 1 à 64 lettres minuscules, chiffres et traits d'union, commençant par une lettre; unique parmi les règles du vérificateur. |
| `level(): RuleLevel` | Le plus bas niveau dont la vérification exécute la règle. |
| `severity(): Severity` | La gravité de chaque constat que produit la règle. |
| `description(): string` | Une phrase : ce que la règle demande au document. |
| `check(OpenApiDocument $document): iterable` | Un `Finding` pour chaque endroit qui enfreint la règle, dans l'ordre du document; les clés sont ignorées. |

### Invariants

- Une règle ne garde aucun état entre deux documents : le vérificateur l'exécute pour chaque document, depuis n'importe
  quelle requête ou n'importe quel *worker*.
- Une règle lit `$document->data` comme une donnée non fiable : une valeur peut manquer ou être de n'importe quel type.
- `Finding::of($this, $location, $message)` prend l'identifiant et la gravité de la règle, de sorte qu'un constat ne
  peut pas contredire sa règle.

### Exemple de règle

```php
use Phil\ApiContract\Contract\OpenApiRule;
use Phil\ApiContract\OpenApi\{Finding, OpenApiDocument, RuleLevel, Severity};

final readonly class PluralCollections implements OpenApiRule
{
    public function id(): string { return 'plural-collections'; }
    public function level(): RuleLevel { return RuleLevel::Strict; }
    public function severity(): Severity { return Severity::Warning; }
    public function description(): string { return 'The first segment of every path is a plural noun.'; }

    public function check(OpenApiDocument $document): iterable
    {
        foreach ($document->operations() as $operation) {
            $first = explode('/', $operation->path)[1] ?? '';

            if ('' !== $first && !str_ends_with($first, 's')) {
                yield Finding::of($this, $operation->pointer, sprintf('"%s" is not plural.', $first));
            }
        }
    }
}
```
