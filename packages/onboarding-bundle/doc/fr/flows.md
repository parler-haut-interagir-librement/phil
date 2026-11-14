<!-- phil-dev source-sha256=b7a01e55477828a3aa06a86c34b192bab85c633d63b62d5eb47d859a8f508a94 -->
# Parcours et étapes

## Déclarer

Un parcours se déclare dans la configuration — voyez [Configuration](configuration.md) — ou par des attributs sur une
classe que le conteneur configure automatiquement :

```php
use Phil\OnboardingBundle\Domain\Attribute\AsOnboardingFlow;
use Phil\OnboardingBundle\Domain\Attribute\AsOnboardingStep;
use Phil\OnboardingBundle\Domain\Flow\Completion;

#[AsOnboardingFlow('author', 'onboarding.author.title')]
#[AsOnboardingStep('author', 'profile', 'onboarding.author.profile', route: 'app_profile', target: '#profile-form')]
#[AsOnboardingStep('author', 'first-page', 'onboarding.author.first_page', Completion::Event, event: PageWritten::class)]
final class AuthorOnboarding
{
}
```

Une étape peut joindre un parcours déclaré ailleurs : par la configuration, par l'application ou par un autre paquet.
Un parcours ne peut pas être déclaré deux fois. Le conteneur s'arrête sur une étape dont le parcours n'est pas déclaré.

## Ordre

Les étapes d'un parcours viennent dans l'ordre de leur déclaration — celles de la configuration d'abord, puis celles
des attributs —, à moins qu'elles ne demandent à venir `before` ou `after` d'autres étapes. Les étapes qu'une étape
attend sont alors placées juste avant elle, dans leur ordre de déclaration. Une étape non déclarée est ignorée : un
paquet peut ainsi placer son étape à côté de celle d'un paquet qui n'est peut-être pas installé. Un cycle arrête le
conteneur.

## Accomplissement

| Accomplissement | Fait quand | Qui peut la cocher |
|---|---|---|
| `manual` | la personne la coche dans la liste de contrôle ou la dépasse dans la visite ; ou l'application appelle `complete()` | la personne |
| `event` | l'événement que l'étape nomme est réparti ; ou l'application appelle `complete()` | personne |
| `checker` | le `StepChecker` que l'étape nomme répond oui quand la progression est lue ; ou l'application appelle `complete()` | personne |
| `question` | la personne répond à sa question dans la liste de contrôle ou la visite ; ou l'application appelle `complete()` | la personne, en y répondant |

**Un événement** accomplit son étape pour l'utilisateur qu'il nomme quand il implémente `NamesOnboardingUser`, et pour
l'utilisateur de la requête courante sinon. Un événement qui ne nomme personne n'accomplit rien. L'écouteur ignore une
progression qui ne peut pas être enregistrée : l'action de l'application n'échoue donc jamais à cause de l'accueil.

```php
final readonly class PageWritten implements NamesOnboardingUser
{
    public function __construct(public string $author) {}

    public function onboardingUser(): ?string
    {
        return $this->author;
    }
}
```

**Un vérificateur** lit les données de l'application. Il est interrogé chaque fois que la progression d'un utilisateur
est lue, pour chacune de ses étapes pas encore accomplies, sauf si le parcours est rangé. Il doit répondre vite : une
requête indexée, un compte en cache. Une classe qui implémente `StepChecker` et déclare sa propre étape avec
`Completion::Checker` sans `checker` la vérifie elle-même :

```php
#[AsOnboardingStep('author', 'avatar', 'onboarding.author.avatar', Completion::Checker)]
final readonly class AvatarChecker implements StepChecker
{
    public function __construct(private Members $members) {}

    public function isComplete(string $user, string $flow, string $step): bool
    {
        return $this->members->hasAvatar($user);
    }
}
```

**Une question** demande à la personne quelque chose dont l'application tire parti de la réponse : ses intérêts, son
niveau, la partie du site pour laquelle elle est venue. Son titre la pose ; elle offre de deux à vingt choix, dont la
personne en retient un, ou autant qu'elle veut avec `multiple`. Chaque choix peut nommer des rôles et des abonnements,
comme l'application les nomme :

```yaml
phil_onboarding:
    answer_grants: App\Onboarding\TopicGrants
    flows:
        member:
            title: onboarding.member.title
            steps:
                topics:
                    title: onboarding.member.topics
                    completion: question
                    question:
                        multiple: true
                        choices:
                            news: { label: topic.news, subscriptions: [newsletter.news] }
                            events: { label: topic.events, roles: [ROLE_EVENTS], subscriptions: [newsletter.events] }
                            quiet: { label: topic.quiet }
```

Le bundle n'écrit jamais un rôle ou un abonnement. À une réponse, il les remet à l'`AnswerGrants` de l'application — voyez
[Points d'extension](extension-points.md#answergrants) — et enregistre l'étape comme faite une fois l'accord revenu, de
sorte qu'un accord qui échoue laisse la question à répondre de nouveau. Le conteneur s'arrête quand un choix nomme un
rôle ou un abonnement et qu'aucun `answer_grants` n'est nommé. Un choix, un rôle et un abonnement sont faits de lettres,
de chiffres, de `_`, `.`, `:` et `-` ; un choix accorde au plus vingt de chaque.

Une question reçoit une réponse une fois : une étape répondue ou passée est refusée avec `StepRefused` jusqu'à ce que le
parcours soit recommencé, et y répondre alors remet les mêmes accords une fois de plus, ce qu'un accord supporte sans
mal. Reprendre un rôle relève de l'application.

## Lire et modifier la progression

`Onboarding` est le point d'entrée. Chaque méthode prend l'identifiant de l'utilisateur, celui que donne `CurrentUser`.

```php
$progress = $onboarding->progress('0192f3c4-member-1842', 'author');   // FlowProgress

$progress->done();       // the steps completed or skipped
$progress->total();
$progress->percent();    // rounded down, 0 to 100
$progress->current();    // the first pending StepProgress, or null
$progress->isFinished(); // no step pending
$progress->isActive();   // neither finished nor dismissed

foreach ($progress->steps as $step) {
    $step->step;         // the StepDefinition
    $step->state;        // StepState::Pending, Completed or Skipped
    $step->at;           // when it was completed or skipped
}
```

| Méthode | |
|---|---|
| `progress($user, $flow)` | Où en est l'utilisateur ; interroge les vérificateurs et enregistre ce qu'ils disent fait. |
| `complete($user, $flow, $step)` | Enregistre une étape accomplie, quel que soit son mode d'accomplissement, pour le code de l'application. |
| `markDone($user, $flow, $step)` | Enregistre une étape cochée par la personne ; refuse avec `StepRefused` une étape accomplie par un événement, un vérificateur ou une réponse. |
| `answer($user, $flow, $step, $choices)` | Remet les accords des choix retenus à `AnswerGrants`, puis enregistre l'étape comme faite ; refuse avec `StepRefused` une étape sans question, ou qui n'est plus en attente, et avec `InvalidAnswer` des choix que la question ne peut accepter. |
| `skip($user, $flow, $step)` | Enregistre une étape en attente comme passée ; refuse avec `StepRefused` une étape qui ne peut pas être passée. Une étape accomplie le reste. |
| `dismiss($user, $flow)` | Range le parcours : sa liste de contrôle et sa visite ne s'affichent plus. |
| `reset($user, $flow)` | Recommence le parcours, toutes les étapes en attente ; les vérificateurs sont interrogés de nouveau à la lecture suivante. |
| `forget($user)` | Retire toute la progression de l'utilisateur, pour effacer son compte. |

Quand `phil/privacy-bundle` est installé, `OnboardingPrivacy` se joint aux exportations et aux effacements d'une
personne, la personne concernée étant l'identifiant de l'utilisateur. L'exportation indique où elle en est dans chaque
parcours, étape par étape, et l'effacement appelle `forget()`.

Un parcours ou une étape inconnus lancent `UnknownFlow` ; un identifiant d'utilisateur vide ou de plus de 320 octets,
`InvalidProgress` ; une réponse qui ne retient rien, un choix que la question n'offre pas, le même choix deux fois ou
plusieurs là où un seul est demandé, `InvalidAnswer` ; une progression qu'une autre requête a gardée en premier,
`ProgressNotSaved`. Chaque exception
implémente `OnboardingException`.

Un parcours est terminé quand aucune étape n'est en attente : une étape ajoutée à un parcours qu'un utilisateur a
terminé réapparaît dans sa liste de contrôle. `FlowFinished` est réparti une seule fois, la première fois que toutes
les étapes sont faites depuis le début du parcours.
