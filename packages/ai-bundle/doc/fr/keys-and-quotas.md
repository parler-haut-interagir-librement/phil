<!-- phil-dev source-sha256=f394aee797767eb8f28f973651a1c53e3096e32ab2c81805b0fd1a24278d0304 -->
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

- **Le compteur de jetons** garde le `reserve` de la tâche, sa taille habituelle, d'avant l'appel jusqu'à son
  règlement.
- **Le compteur de tâches** compte une tâche par tâche.
- **Le budget** est un compteur des dépenses du sujet, en millionièmes de la devise de la liste de prix. Il garde la
  borne de coût de la tâche jusqu'à son règlement. Une offre payante relève la limite ; une offre gratuite peut n'en
  avoir aucune et s'en remettre au compteur de jetons.
- **Le plafond** est le même compteur de dépenses pour toute l'application, compté sur un sujet bien à lui,
  `ceiling_subject`, `phil_ai.application` par défaut : dès que les dépenses du mois l'atteignent, toute tâche est
  refusée jusqu'à la fin de la période ou jusqu'à ce que la limite soit relevée. Chaque tâche écrit sur ce seul
  compteur, qui est donc un point de contention sous forte charge ; un budget par sujet répartit les écritures.
- **La clé personnelle de la personne** n'est pas comptée, à moins que `count_user_keys` soit vrai : la personne paie
  son fournisseur. Une telle tâche ne réserve rien, ne règle rien et ne prend aucune place parmi les tâches en cours en
  même temps.

```yaml
phil_ai:
    usage_meter: 'Phil\UsageMeter\UsageMeter'
    quota:
        tokens_meter: ai_tokens
        tasks_meter: ai_tasks
        budget_meter: ai_budget      # a meter of micros per subject: 5000000 is 5.00 a month
        ceiling_meter: ai_ceiling    # a meter of micros for the application
```

### La réservation

Avant l'appel, `AiTasks` réserve la tâche sur chaque compteur configuré, dans cet ordre fixe :

1. son `reserve` de jetons, sur le compteur de jetons ;
2. sa borne de coût, sur le budget du sujet ;
3. la même borne de coût, sur le sujet du plafond ;
4. une tâche, sur le compteur de tâches.

Chaque étape est un `consume()` de `phil/usage-meter`, atomique, avec l'identifiant de la tâche comme clé
d'idempotence. La réservation est tout ou rien : quand une étape n'entre pas, les étapes déjà faites sont rendues par
`refund()`, puis `QuotaExceeded` est levée et rien n'est appelé. Des tâches lancées au même moment ne passent jamais
toutes sur la même place : la base de données compte chaque incrément contre la limite et refuse ceux qui n'entrent
pas.

### La borne de coût

La borne de coût est ce que la tâche devrait coûter au plus : le prix du modèle, tiré de la liste de prix, appliqué au
`reserve` de la tâche comme jetons lus et à sa plus grande réponse comme jetons écrits, plus le prix par appel du
modèle. La plus grande réponse est l'option `max_tokens` de la tâche quand c'est un entier positif, et son `reserve`
autrement. Avec `reserve: 2000`, `max_tokens: 1024` et un modèle à 3,00 par million de jetons lus et 15,00 par million
écrits, la borne est 2000 × 3 + 1024 × 15 = 21 360 millionièmes, soit 0,02136, arrondie au millionième supérieur.

Un modèle que la liste de prix ne prend pas en compte a une borne de zéro : le budget et le plafond n'exigent alors
que de la place restante.

### Le règlement

Après l'appel, les jetons, le budget et le plafond sont chacun réglés avec ce que la tâche a dépensé :

- quand la tâche a dépensé moins que ce qu'elle a réservé, la différence est rendue par `refund()` sous la clé de la
  tâche ;
- quand elle a dépensé plus, la différence est enregistrée par `record()` sous une seconde clé, `<task id>:adjust`. Une
  tâche plus grande que sa réserve, ou plus chère que sa borne, peut ainsi faire dépasser sa limite au sujet, et la
  tâche suivante est refusée jusqu'à ce que la période libère de la place.

Les deux étapes sont idempotentes : régler deux fois la même tâche ne compte rien de plus. Le compteur de tâches garde
sa tâche.

### Les échecs

Quand l'appel échoue, ou qu'un flux est quitté avant sa fin ou jamais lu, toute la réservation est rendue : la tâche,
ses jetons et sa borne de coût sur le budget et le plafond. Un échec ne coûte rien au sujet, et une seconde
libération ne rend rien de plus.

### Les tâches en cours en même temps

La réservation borne ce qu'un sujet peut dépenser ; `quota.concurrency` borne aussi combien de ses tâches
s'exécutent en même temps, de sorte que la dépense au-delà des bornes (les tâches plus grandes que leur réserve) reste
dans la limite de ce nombre de tâches :

```yaml
framework:
    semaphore: 'lock://default'

phil_ai:
    usage_meter: 'Phil\UsageMeter\UsageMeter'
    quota:
        budget_meter: ai_budget
        concurrency: 3
```

Elle exige `symfony/semaphore`, que le bundle suggère sans l'exiger ; elle est désactivée par défaut. Chaque sujet a
son propre sémaphore, `phil_ai.concurrency.` suivi du hachage XXH128 du sujet ; `quota.semaphore_factory` nomme la
fabrique, `semaphore.factory` de FrameworkBundle par défaut. La place est prise avant la réservation et rendue quand la
tâche se termine (répondue, échouée, refusée par le quota, ou son flux lu jusqu'au bout, quitté ou jamais lu) ; son
temps de vie, 300 secondes, libère la place d'un processus mort avant. Une tâche qui trouve toutes les places prises
lève `TooManyTasks`, et rien n'est compté ni appelé. Le sémaphore s'ajoute à la réservation et ne la remplace jamais.

Les limites viennent des compteurs, ou d'un fournisseur de limites de `phil/usage-meter`. Par exemple, les
fonctionnalités `usage:ai_tokens:<limit>` qu'offre `phil/commerce-subscription-bundle` permettent à une offre payante
de relever le quota.
