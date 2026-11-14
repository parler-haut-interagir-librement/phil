# Changelog

All notable changes to `phil/onboarding-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-14

### Added

- `PhilOnboardingBundle`, configured under `phil_onboarding`: the store (`doctrine` or `memory`), the entity manager,
  the current user, the tour preference, the grants of the answers, the subdirectory of the kit's recipes and the
  flows; the Doctrine mapping, the stateless CSRF token, the assets and
  the setting `onboarding.tours` of `phil/settings-bundle` prepended.
- `FlowDefinition` and `StepDefinition`: one to a hundred steps, each with its identifier, the translation keys of
  its title and description, its route and its parameters, the CSS selector of its target, its `Completion` — manual,
  event, checker or question —, whether it may be skipped, and its place `before` or `after` other steps, kept by `Ordering`.
- `Question` and `Choice`: two to twenty choices, one or several picked, each granting up to twenty roles and twenty
  subscriptions named as the application names them; `InvalidAnswer` for an answer the question cannot take.
- `AsOnboardingFlow` and `AsOnboardingStep`, the declarations of attributes, a question among them; a checker that
  declares its own step checks it.
- `FlowCatalog`, built from the configuration and the attributes, checked when the container is compiled: an
  identifier malformed, a step twice, a flow missing, a checker that is not one, an order in a circle, a choice that
  grants something without an `AnswerGrants` of the application stop it.
- `Progress`, the entity of where a user stands in a flow, keyed by user identifier and flow, with each step's
  `StepState` and time, the start, the finish and the dismissal, to the second in UTC; `ProgressRepository`, with
  Doctrine and in-memory stores, and `ProgressNotSaved` for a progress kept first by another request.
- `Onboarding`: `progress()`, which asks the checkers, `complete()`, `markDone()`, `answer()`, which hands the grants
  of the choices to the application before it records the step, `skip()`, `dismiss()`, `reset()` and `forget()`;
  `FlowProgress` and `StepProgress`; `Answer`.
- The ports `CurrentUser`, with `SecurityCurrentUser`; `StepChecker`; `AnswerGrants`, with `NoAnswerGrants`, through
  which the application grants roles and subscriptions the bundle never writes; `TourPreference`, with `AlwaysTours` and
  `SettingsTourPreference`; `NamesOnboardingUser`, for an event that names its user.
- `EventCompletion`, the listener of the events that complete steps.
- `OnboardingPrivacy`: a user's progress in the export and the erasure of `phil/privacy-bundle`.
- The events `StepCompleted`, `QuestionAnswered`, `StepSkipped`, `FlowFinished`, `FlowDismissed` and `FlowReset`,
  made of identifiers.
- `OnboardingController` and `config/routes.php`: tick, answer, skip, dismiss and reset, checked by `OnboardingRequest`
  against the stateless CSRF token `phil_onboarding`, answered in JSON or by a redirection.
- `OnboardingView`, the Twig functions `onboarding_progress()`, `onboarding_checklist()`, `onboarding_tour()` and
  `phil_onboarding_kit()`, and the components `PhilOnboarding:Checklist` and `PhilOnboarding:Tour`, whose buttons are
  the recipe `Button` of the kit, named by `KitComponents` and `KitSubdirectoryPass` after the components
  subdirectory of the Symfony UX Toolkit or the option `kit_subdirectory`; the form of a question, its choices in a
  `<fieldset>`.
- The Stimulus controller `tour`: the targets highlighted in turn, next, previous, skip and end, the arrows and
  Escape, the focus kept, a status for screen readers, reduced motion; its stylesheet `onboarding.css`.
- The translations in English and French.
- `OnboardingException`, implemented by `InvalidFlow`, `InvalidProgress`, `InvalidAnswer`, `ProgressNotSaved`,
  `UnknownFlow` and `StepRefused`.
- The Flex recipe in `recipe/0.1`: the bundle registered, `config/packages/phil_onboarding.yaml` with no flow and
  `config/routes/phil_onboarding.yaml` with the endpoints of the checklist and the tour.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
