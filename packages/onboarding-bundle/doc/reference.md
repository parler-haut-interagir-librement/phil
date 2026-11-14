# Reference

| Class or file | Kind | Role |
|---|---|---|
| `Phil\OnboardingBundle\PhilOnboardingBundle` | bundle | The configuration `phil_onboarding`; the mapping, the CSRF token, the assets and the setting prepended |
| `Phil\OnboardingBundle\Application\Onboarding` | `final readonly class` | The entry point: progress, complete, mark done, answer, skip, dismiss, reset, forget |
| `Phil\OnboardingBundle\Application\FlowCatalog` | `final readonly class` | The declared flows: `fromDeclarations()`, `get()`, `has()`, `all()`, `onEvent()`, `grantsAnything()` |
| `Phil\OnboardingBundle\Application\FlowProgress` | `final readonly class` | A flow as a user stands in it: `done()`, `total()`, `percent()`, `current()`, `isFinished()`, `isActive()` |
| `Phil\OnboardingBundle\Application\StepProgress` | `final readonly class` | A step as a user stands on it: `state`, `at`, `isTickable()`, `isAnswerable()`, `isSkippable()` |
| `Phil\OnboardingBundle\Application\EventCompletion` | `final readonly class` | The listener of the events that complete steps |
| `Phil\OnboardingBundle\Application\AlwaysTours` | `final readonly class` | Everyone wants the tours |
| `Phil\OnboardingBundle\Application\Answer` | `final readonly class` | An answer: the choices picked, the roles and the subscriptions they grant |
| `Phil\OnboardingBundle\Application\NoAnswerGrants` | `final readonly class` | Grants nothing |
| `Phil\OnboardingBundle\Application\Contract\CurrentUser`, `StepChecker`, `AnswerGrants`, `TourPreference`, `NamesOnboardingUser` | interface | The ports of the application |
| `Phil\OnboardingBundle\Application\Event\StepCompleted`, `QuestionAnswered`, `StepSkipped`, `FlowFinished`, `FlowDismissed`, `FlowReset` | `final readonly class` | The events, made of identifiers |
| `Phil\OnboardingBundle\Application\Exception\UnknownFlow`, `StepRefused` | `final class` | An unknown flow or step; a step a person may not tick, answer or skip |
| `Phil\OnboardingBundle\Domain\Flow\FlowDefinition`, `StepDefinition` | `final readonly class` | A flow and its steps |
| `Phil\OnboardingBundle\Domain\Flow\Question`, `Choice` | `final readonly class` | The question of a step and its choices |
| `Phil\OnboardingBundle\Domain\Flow\Ordering` | `final class` | The order of the steps, `before` and `after` |
| `Phil\OnboardingBundle\Domain\Flow\Identifier` | `final class` | The rules of the identifiers and texts |
| `Phil\OnboardingBundle\Domain\Flow\Completion`, `StepState` | enum | `manual`, `event`, `checker`, `question`; `pending`, `completed`, `skipped` |
| `Phil\OnboardingBundle\Domain\Attribute\AsOnboardingFlow`, `AsOnboardingStep` | attribute | The declarations of attributes |
| `Phil\OnboardingBundle\Domain\Model\Progress` | entity | Where a user stands in a flow, in `phil_onboarding_progress` |
| `Phil\OnboardingBundle\Domain\Repository\ProgressRepository` | interface | Where the progress lives |
| `Phil\OnboardingBundle\Domain\Exception\OnboardingException` | interface | Every exception of the bundle |
| `Phil\OnboardingBundle\Domain\Exception\InvalidFlow`, `InvalidProgress`, `InvalidAnswer`, `ProgressNotSaved` | `final class` | A declaration refused; a user identifier refused; an answer refused; a progress kept first by another request |
| `Phil\OnboardingBundle\Infrastructure\Doctrine\DoctrineProgressRepository` | `final readonly class` | The progress with Doctrine |
| `Phil\OnboardingBundle\Infrastructure\Memory\InMemoryProgressRepository` | `final class` | The progress in memory |
| `Phil\OnboardingBundle\Infrastructure\Http\OnboardingController`, `OnboardingRequest`, `Routes` | `final class` | The endpoints, their CSRF check and their names |
| `Phil\OnboardingBundle\Infrastructure\Privacy\OnboardingPrivacy` | `final readonly class` | A user's progress in the export and the erasure of `phil/privacy-bundle` |
| `Phil\OnboardingBundle\Infrastructure\Security\SecurityCurrentUser` | `final readonly class` | The current user of Symfony Security |
| `Phil\OnboardingBundle\Infrastructure\Settings\SettingsTourPreference` | `final readonly class` | The setting `onboarding.tours` of `phil/settings-bundle` |
| `Phil\OnboardingBundle\Infrastructure\Twig\OnboardingView`, `OnboardingExtension` | `final class` | What the templates show; the Twig functions, `phil_onboarding_kit()` among them |
| `Phil\OnboardingBundle\Infrastructure\Twig\KitComponents` | `final readonly class` | The name of a recipe of the kit under the Toolkit's components subdirectory |
| `Phil\OnboardingBundle\Infrastructure\Twig\ChecklistComponent`, `TourComponent` | `final class` | `PhilOnboarding:Checklist`, `PhilOnboarding:Tour` |
| `Phil\OnboardingBundle\Infrastructure\DependencyInjection\OnboardingServices`, `OnboardingPass`, `KitSubdirectoryPass` | `final class` | The services; the declarations of attributes, the checkers, the grants and the listeners; the subdirectory of the recipes |
| `config/routes.php` | routes | The endpoints |
| `templates/_checklist.html.twig`, `_tour.html.twig`, `_question.html.twig`, `components/*.html.twig` | templates | The checklist, the tour, the form of a question and their components |
| `assets/src/tour_controller.js`, `assets/styles/onboarding.css` | assets | The Stimulus controller and the stylesheet |
| `translations/phil_onboarding.en.php`, `phil_onboarding.fr.php` | translations | English and French |
