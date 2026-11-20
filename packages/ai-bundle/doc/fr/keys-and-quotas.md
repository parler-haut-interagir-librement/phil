<!-- phil-dev source-sha256=52b3c4656b663a044e14db4519e1e9d6b2779a0a7382c54f3790bc09ffe774a1 -->
# Clés et quotas

## Quelle clé

La tâche d'une personne, soit un `TaskRequest` avec un `owner`, s'exécute avec une clé choisie par `key_policy` :

| Politique | Comportement |
|---|---|
| `application` | Toujours la clé de l'application; les clés de la personne ne sont jamais lues. |
| `prefer_user` | La clé personnelle de la personne si elle en a une pour la plateforme, sinon celle de l'application. |
| `require_user` | Seulement la clé personnelle : sans elle, ou pour une tâche sans propriétaire, `NoUserKey`. |

Une tâche sans propriétaire s'exécute toujours avec la clé de l'application, sauf sous `require_user`.

## La clé de la personne

Les clés des personnes vivent dans `phil/user-secrets-bundle`, scellées au repos, et seule une tâche exécutée pour la
personne les lit. Enregistrez ce bundle, puis nommez pour chaque plateforme le secret qui contient la clé d'une
personne et la fabrique qui construit la plateforme avec elle :

```yaml
phil_ai:
    platforms:
        main:
            platform: 'app.ai_platform'
            user_key_factory: 'App\Ai\MainPlatformOnUserKey'
            secret: 'main-key'
```

```php
use Phil\AiBundle\Infrastructure\SymfonyAi\UserKeyPlatformFactory;
use Symfony\AI\Platform\Bridge\TheProvider\PlatformFactory;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class MainPlatformOnUserKey implements UserKeyPlatformFactory
{
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    public function create(#[\SensitiveParameter] string $apiKey): PlatformInterface
    {
        return PlatformFactory::create($apiKey, $this->httpClient);
    }
}
```

La personne range sa clé une fois, par le bundle des secrets :

```php
$secrets->store(Owner::fromString('member-7'), SecretName::fromString('main-key'), ServiceName::fromString('main'), ApiKey::of($submittedKey));
```

Chaque tâche la lit avec le motif `AI task "<kind>"`, qui va à la piste d'audit des secrets. Un secret absent ou
révoqué équivaut à aucune clé. Un secret qui ne s'ouvre pas, ou qui contient un jeton OAuth, échoue avec l'exception du
bundle des secrets. Le bundle ne journalise jamais la clé, ne l'inscrit jamais au journal et ne la garde jamais.

## Quotas

Les quotas sont comptés par `phil/usage-meter`, par sujet et par période, au moyen d'un service `UsageMeter` que
construit l'application. Ses compteurs, ses périodes, ses limites et son stockage appartiennent à l'application :

```yaml
services:
    app.ai_meters:
        class: Phil\UsageMeter\Meters
        arguments:
            - !service { class: Phil\UsageMeter\Meter, arguments: ['ai_tokens', !service { class: Phil\UsageMeter\Period\CalendarPeriod, factory: ['Phil\UsageMeter\Period\CalendarPeriod', 'month'] }, !service { class: Phil\UsageMeter\Limit, factory: ['Phil\UsageMeter\Limit', 'of'], arguments: [200000] }] }
    Phil\UsageMeter\UsageMeter:
        arguments: ['@app.ai_meters', '@Phil\UsageMeter\Dbal\DbalUsageStore', '@clock']

phil_ai:
    usage_meter: 'Phil\UsageMeter\UsageMeter'
    quota:
        tokens_meter: ai_tokens
```

- **Le compteur de jetons** est vérifié avant une tâche. Le sujet doit avoir de la place pour le `reserve` de la
  tâche, sa taille habituelle; sinon, `QuotaExceeded` est levée et rien n'est appelé. Après l'appel, les jetons
  consommés sont enregistrés, avec l'identifiant de la tâche comme clé d'idempotence. Une tâche plus grande que sa
  réserve peut faire dépasser sa limite au sujet; la tâche suivante est alors refusée jusqu'à ce que la période libère
  de la place.
- **Le compteur de tâches** consomme une tâche avant que chacune commence, de façon atomique, avec l'identifiant de la
  tâche comme clé d'idempotence. Quand l'appel échoue ensuite, ou qu'un flux est quitté avant sa fin, le `refund()` de
  `phil/usage-meter` rend cette tâche : un échec ne coûte rien au sujet.
- **Le budget** est un compteur des dépenses du sujet, en millionièmes de la devise de la liste de prix : le sujet doit
  avoir de la place avant une tâche, et le coût de la tâche est enregistré après elle. Une offre payante relève la
  limite ; une offre gratuite peut n'en avoir aucune et s'en remettre au compteur de jetons.
- **Le plafond** est le même compteur de dépenses pour toute l'application, compté sur un sujet bien à lui,
  `ceiling_subject`, `phil_ai.application` par défaut : dès que les dépenses du mois l'atteignent, toute tâche est
  refusée jusqu'à la fin de la période ou jusqu'à ce que la limite soit relevée. Chaque tâche s'enregistre sur ce seul
  compteur, qui est donc un point de contention sous forte charge ; un budget par sujet répartit les écritures.
- **La clé personnelle de la personne** n'est pas comptée, à moins que `count_user_keys` soit vrai : la personne paie
  son fournisseur.

```yaml
phil_ai:
    usage_meter: 'Phil\UsageMeter\UsageMeter'
    quota:
        tokens_meter: ai_tokens
        tasks_meter: ai_tasks
        budget_meter: ai_budget      # a meter of micros per subject: 5000000 is 5.00 a month
        ceiling_meter: ai_ceiling    # a meter of micros for the application
```

Un budget ou un plafond vérifie qu'il reste de la place, non que la prochaine tâche y entre : le coût d'une tâche n'est
connu qu'après elle, et la dernière tâche d'une période peut donc faire dépasser la limite aux dépenses, d'une tâche au
plus par requête en cours à ce moment-là.

Les limites viennent des compteurs, ou d'un fournisseur de limites de `phil/usage-meter`. Par exemple, les
fonctionnalités `usage:ai_tokens:<limit>` qu'offre `phil/commerce-subscription-bundle` permettent à une offre payante
de relever le quota.
