<!-- phil-dev source-sha256=63c9dc47864043685e376d1f1db30656ead962b62f622ceae3f24baa2207c0e1 -->
# Référence

| Classe ou fichier | Nature | Rôle |
|---|---|---|
| `Phil\OnboardingBundle\PhilOnboardingBundle` | bundle | La configuration `phil_onboarding` ; le mapping, le jeton CSRF, les ressources et le réglage ajoutés en tête |
| `Phil\OnboardingBundle\Application\Onboarding` | `final readonly class` | Le point d'entrée : progression, accomplir, cocher, répondre, passer, ranger, recommencer, oublier |
| `Phil\OnboardingBundle\Application\FlowCatalog` | `final readonly class` | Les parcours déclarés : `fromDeclarations()`, `get()`, `has()`, `all()`, `onEvent()`, `grantsAnything()` |
| `Phil\OnboardingBundle\Application\FlowProgress` | `final readonly class` | Un parcours, tel qu'un utilisateur y en est : `done()`, `total()`, `percent()`, `current()`, `isFinished()`, `isActive()` |
| `Phil\OnboardingBundle\Application\StepProgress` | `final readonly class` | Une étape, telle qu'un utilisateur y en est : `state`, `at`, `isTickable()`, `isAnswerable()`, `isSkippable()` |
| `Phil\OnboardingBundle\Application\EventCompletion` | `final readonly class` | L'écouteur des événements qui accomplissent des étapes |
| `Phil\OnboardingBundle\Application\AlwaysTours` | `final readonly class` | Tout le monde veut les visites |
| `Phil\OnboardingBundle\Application\Answer` | `final readonly class` | Une réponse : les choix retenus, les rôles et les abonnements qu'ils accordent |
| `Phil\OnboardingBundle\Application\NoAnswerGrants` | `final readonly class` | N'accorde rien |
| `Phil\OnboardingBundle\Application\Contract\CurrentUser`, `StepChecker`, `AnswerGrants`, `TourPreference`, `NamesOnboardingUser` | interface | Les ports de l'application |
| `Phil\OnboardingBundle\Application\Event\StepCompleted`, `QuestionAnswered`, `StepSkipped`, `FlowFinished`, `FlowDismissed`, `FlowReset` | `final readonly class` | Les événements, faits d'identifiants |
| `Phil\OnboardingBundle\Application\Exception\UnknownFlow`, `StepRefused` | `final class` | Un parcours ou une étape inconnus ; une étape qu'une personne ne peut ni cocher, ni répondre, ni passer |
| `Phil\OnboardingBundle\Domain\Flow\FlowDefinition`, `StepDefinition` | `final readonly class` | Un parcours et ses étapes |
| `Phil\OnboardingBundle\Domain\Flow\Question`, `Choice` | `final readonly class` | La question d'une étape et ses choix |
| `Phil\OnboardingBundle\Domain\Flow\Ordering` | `final class` | L'ordre des étapes, `before` et `after` |
| `Phil\OnboardingBundle\Domain\Flow\Identifier` | `final class` | Les règles des identifiants et des textes |
| `Phil\OnboardingBundle\Domain\Flow\Completion`, `StepState` | enum | `manual`, `event`, `checker`, `question` ; `pending`, `completed`, `skipped` |
| `Phil\OnboardingBundle\Domain\Attribute\AsOnboardingFlow`, `AsOnboardingStep` | attribut | Les déclarations par attributs |
| `Phil\OnboardingBundle\Domain\Model\Progress` | entité | Où en est un utilisateur dans un parcours, dans `phil_onboarding_progress` |
| `Phil\OnboardingBundle\Domain\Repository\ProgressRepository` | interface | L'endroit où vit la progression |
| `Phil\OnboardingBundle\Domain\Exception\OnboardingException` | interface | Toutes les exceptions du bundle |
| `Phil\OnboardingBundle\Domain\Exception\InvalidFlow`, `InvalidProgress`, `InvalidAnswer`, `ProgressNotSaved` | `final class` | Une déclaration refusée ; un identifiant d'utilisateur refusé ; une réponse refusée ; une progression gardée en premier par une autre requête |
| `Phil\OnboardingBundle\Infrastructure\Doctrine\DoctrineProgressRepository` | `final readonly class` | La progression avec Doctrine |
| `Phil\OnboardingBundle\Infrastructure\Doctrine\BinaryKeysListener` | `final readonly class` | Les identifiants en `utf8mb4_bin` sur MySQL et MariaDB, à l'événement `loadClassMetadata` de l'ORM |
| `Phil\OnboardingBundle\Infrastructure\Memory\InMemoryProgressRepository` | `final class` | La progression en mémoire |
| `Phil\OnboardingBundle\Infrastructure\Http\OnboardingController`, `OnboardingRequest`, `Routes` | `final class` | Les points de terminaison, leur vérification CSRF et leurs noms |
| `Phil\OnboardingBundle\Infrastructure\Privacy\OnboardingPrivacy` | `final readonly class` | La progression d'un utilisateur dans l'exportation et l'effacement de `phil/privacy-bundle` |
| `Phil\OnboardingBundle\Infrastructure\Security\SecurityCurrentUser` | `final readonly class` | L'utilisateur courant de Symfony Security |
| `Phil\OnboardingBundle\Infrastructure\Settings\SettingsTourPreference` | `final readonly class` | Le réglage `onboarding.tours` de `phil/settings-bundle` |
| `Phil\OnboardingBundle\Infrastructure\Twig\OnboardingView`, `OnboardingExtension` | `final class` | Ce que les gabarits affichent ; les fonctions Twig, dont `phil_onboarding_kit()` |
| `Phil\OnboardingBundle\Infrastructure\Twig\KitComponents` | `final readonly class` | Le nom d'une recette du kit sous le sous-répertoire des composants du Toolkit |
| `Phil\OnboardingBundle\Infrastructure\Twig\ChecklistComponent`, `TourComponent` | `final class` | `PhilOnboarding:Checklist`, `PhilOnboarding:Tour` |
| `Phil\OnboardingBundle\Infrastructure\DependencyInjection\OnboardingServices`, `OnboardingPass`, `KitSubdirectoryPass` | `final class` | Les services ; les déclarations par attributs, les vérificateurs, les accords et les écouteurs ; le sous-répertoire des recettes |
| `config/routes.php` | routes | Les points de terminaison |
| `templates/_checklist.html.twig`, `_tour.html.twig`, `_question.html.twig`, `components/*.html.twig` | gabarits | La liste de contrôle, la visite, le formulaire d'une question et leurs composants |
| `assets/src/tour_controller.js`, `assets/styles/onboarding.css` | ressources | Le contrôleur Stimulus et la feuille de style |
| `translations/phil_onboarding.en.php`, `phil_onboarding.fr.php` | traductions | Anglais et français |
