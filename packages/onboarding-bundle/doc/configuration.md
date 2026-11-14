# Configuration

```yaml
# config/packages/phil_onboarding.yaml
phil_onboarding:
    store: doctrine               # or "memory", for tests
    entity_manager: default
    current_user: ~               # a CurrentUser service; Symfony Security's user by default
    tour_preference: ~            # a TourPreference service; see below
    answer_grants: ~              # the AnswerGrants service; required when a choice grants a role or a subscription
    kit_subdirectory: ~           # where the recipe Button is; the subdirectory of ux_toolkit when null
    flows:
        author:
            title: onboarding.author.title
            description: onboarding.author.description
            translation_domain: messages
            steps:
                profile:
                    title: onboarding.author.profile
                    description: onboarding.author.profile_help
                    route: app_profile
                    route_parameters: { tab: bio }
                    target: '#profile-form'
                first-page:
                    title: onboarding.author.first_page
                    completion: event
                    event: App\Event\PageWritten
                    skippable: false
                avatar:
                    title: onboarding.author.avatar
                    completion: checker
                    checker: App\Onboarding\AvatarChecker
                    before: [first-page]
                topics:
                    title: onboarding.author.topics
                    completion: question
                    question:
                        multiple: true
                        choices:
                            news: { label: topic.news, subscriptions: [newsletter.news] }
                            events: { label: topic.events, roles: [ROLE_EVENTS] }
```

| Option | Default | |
|---|---|---|
| `store` | `doctrine` | Where the progress is kept: `doctrine`, or `memory` for tests. |
| `entity_manager` | `default` | The Doctrine entity manager of the table. |
| `current_user` | `null` | The `CurrentUser` service naming whose progress a request shows; `SecurityCurrentUser` when null. |
| `tour_preference` | `null` | The `TourPreference` service saying who wants the tours; the setting `onboarding.tours` when `phil/settings-bundle` is installed, `AlwaysTours` otherwise. |
| `answer_grants` | `null` | The `AnswerGrants` service granting the roles and subscriptions of the answers; `NoAnswerGrants` when null, which the container refuses once a choice grants something. |
| `kit_subdirectory` | `null` | Where the recipe `Button` of the kit is, under the components directory; the subdirectory of `ux_toolkit` when null. |
| `flows` | `{}` | The flows, by identifier. |

## A flow

| Key | Default | |
|---|---|---|
| `title` | required | The translation key of the title. |
| `description` | `null` | The translation key of the description. |
| `translation_domain` | `messages` | The domain of the title, the description and the steps' texts. |
| `steps` | `{}` | The steps, by identifier, in their order. |

## A step

| Key | Default | |
|---|---|---|
| `title` | required | The translation key of the title. |
| `description` | `null` | The translation key of the description. |
| `route`, `route_parameters` | `null`, `{}` | The page the step leads to, linked from the checklist and the tour. |
| `target` | `null` | The CSS selector of the element the tour highlights. |
| `completion` | `manual` | `manual`, `event`, `checker` or `question`: see [Flows and steps](flows.md). |
| `event` | `null` | The name of the event that completes the step, usually its class; only for `event`. |
| `checker` | `null` | The service of the `StepChecker`; only for `checker`. |
| `skippable` | `true` | Whether a person may skip the step. |
| `before`, `after` | `[]` | The steps this one comes before or after. |
| `question` | none | Only for `question`: `multiple`, `false` by default, and two to twenty `choices`, by identifier, each with the translation key of its `label` and the `roles` and `subscriptions` it grants. |

Identifiers are made of up to 64 lowercase letters, digits, `_`, `.` and `-`, starting with a letter or a digit. Titles
hold at most 255 characters, descriptions 2 000, selectors 255, none of them a control character. A flow has one to a
hundred steps. A choice, a role and a subscription follow the rules of [a question](flows.md#completion). A mistake
stops the container when it is compiled, with its reason.

Steps may also be declared with attributes, by the application or by other packages, into a flow of the
configuration: see [Flows and steps](flows.md).
