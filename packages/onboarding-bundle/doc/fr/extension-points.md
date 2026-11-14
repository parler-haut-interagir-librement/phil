<!-- phil-dev source-sha256=adc18a79c68d829ed061cf1913beb501c8f1897bd25f066b4aa0f769a059bf54 -->
# Points d'extension

## `CurrentUser`

Le port qui dit à qui s'adresse la requête courante, dans `Phil\OnboardingBundle\Application\Contract` : l'identifiant
sous lequel sa progression est gardée, ou null pour un visiteur. `SecurityCurrentUser` lit l'identifiant de
l'utilisateur de Symfony Security ; une autre connexion nomme son propre service sous `phil_onboarding.current_user`.

```php
interface CurrentUser
{
    public function id(): ?string;
}
```

## `StepChecker`

Dit si un utilisateur a fait une étape, à partir des données de l'application. Une étape nomme son service sous
`checker`, ou un vérificateur déclare sa propre étape avec `AsOnboardingStep`. Le conteneur s'arrête quand le service
n'implémente pas le port. Voyez [Parcours et étapes](flows.md#accomplissement).

```php
interface StepChecker
{
    public function isComplete(string $user, string $flow, string $step): bool;
}
```

## `NamesOnboardingUser`

Implémentée par un événement de l'application qui accomplit les étapes d'une autre personne que l'utilisateur de la
requête courante : un événement réparti par un *worker*, ou qui porte sur quelqu'un d'autre.

```php
interface NamesOnboardingUser
{
    public function onboardingUser(): ?string;
}
```

## `AnswerGrants`

Ce que l'application accorde à un utilisateur qui a répondu à une question : les rôles et les abonnements des choix qu'il
a retenus, dans une `Answer` — l'utilisateur, le parcours, l'étape, les choix, les rôles et les abonnements, chacun une
fois. Le bundle n'écrit jamais lui-même un rôle ou un abonnement ; il appelle le port avant d'enregistrer l'étape comme
faite, de sorte qu'une exception laisse la question à répondre de nouveau. La même réponse peut arriver deux fois,
quand deux requêtes se disputent ou que le parcours est recommencé : un accord est idempotent. `NoAnswerGrants` n'accorde
rien, pour les questions dont les choix n'accordent rien ; le conteneur s'arrête quand un choix accorde quelque chose et
que `phil_onboarding.answer_grants` ne nomme aucun service.

```php
interface AnswerGrants
{
    public function grant(Answer $answer): void;
}

final readonly class TopicGrants implements AnswerGrants
{
    public function __construct(private Members $members, private Newsletters $newsletters) {}

    public function grant(Answer $answer): void
    {
        $this->members->addRoles($answer->user, $answer->roles);
        $this->newsletters->subscribe($answer->user, $answer->subscriptions);
    }
}
```

## `TourPreference`

Dit si un utilisateur veut les visites guidées. `AlwaysTours` répond oui à tout le monde ; avec
`phil/settings-bundle`, `SettingsTourPreference` lit le réglage `onboarding.tours` de l'utilisateur. Une application
nomme le sien sous `phil_onboarding.tour_preference`.

```php
interface TourPreference
{
    public function wantsTours(string $user): bool;
}
```

## Les déclarations

`AsOnboardingFlow` et `AsOnboardingStep` déclarent des parcours et des étapes depuis n'importe quel paquet ou depuis
l'application. Un paquet peut aussi ajouter la configuration de `phil_onboarding` en tête, dans son propre bundle :

```php
public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
{
    $builder->prependExtensionConfig('phil_onboarding', ['flows' => ['gallery' => [
        'title' => 'onboarding.gallery.title',
        'translation_domain' => 'gallery',
        'steps' => ['first-album' => ['title' => 'onboarding.gallery.first_album']],
    ]]]);
}
```

## Le stockage

`ProgressRepository`, dans `Phil\OnboardingBundle\Domain\Repository`, est le port de l'endroit où vit la progression ;
le bundle est fourni avec des stockages Doctrine et en mémoire. Un autre stockage garde une progression par
utilisateur et par parcours, et lance `ProgressNotSaved` quand une autre requête a gardé la même progression en
premier.

## Les gabarits et les événements

Les gabarits se redéfinissent comme ceux de tout bundle. Les événements décrits dans [Événements](events.md) permettent
d'agir après un changement sans modifier le bundle.

Les boutons sont la copie qu'a l'application de la recette `Button`, installée par le Symfony UX Toolkit : la modifier
les modifie, et `kit_subdirectory` dit où elle se trouve.

Aucun service du bundle ne garde d'état entre les requêtes. Un `CurrentUser`, un `StepChecker`, un `AnswerGrants` ou un
`TourPreference` de l'application n'en garde pas non plus, ou le remet à zéro avec `kernel.reset`.
