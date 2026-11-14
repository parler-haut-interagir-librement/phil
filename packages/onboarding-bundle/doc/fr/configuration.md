<!-- phil-dev source-sha256=30191c60dfe61194dbf766976447650cdfa13c525adbfdc84397cdcbfb966452 -->
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

| Option | Valeur par défaut | |
|---|---|---|
| `store` | `doctrine` | Où la progression est gardée : `doctrine`, ou `memory` pour les tests. |
| `entity_manager` | `default` | Le gestionnaire d'entités Doctrine de la table. |
| `current_user` | `null` | Le service `CurrentUser` qui dit de qui une requête montre la progression ; `SecurityCurrentUser` quand il est nul. |
| `tour_preference` | `null` | Le service `TourPreference` qui dit qui veut les visites ; le réglage `onboarding.tours` quand `phil/settings-bundle` est installé, `AlwaysTours` sinon. |
| `answer_grants` | `null` | Le service `AnswerGrants` qui accorde les rôles et les abonnements des réponses ; `NoAnswerGrants` quand il est nul, que le conteneur refuse dès qu'un choix accorde quelque chose. |
| `kit_subdirectory` | `null` | Où se trouve la recette `Button` du kit, sous le répertoire des composants ; le sous-répertoire de `ux_toolkit` quand il est nul. |
| `flows` | `{}` | Les parcours, par identifiant. |

## Un parcours

| Clé | Valeur par défaut | |
|---|---|---|
| `title` | obligatoire | La clé de traduction du titre. |
| `description` | `null` | La clé de traduction de la description. |
| `translation_domain` | `messages` | Le domaine du titre, de la description et des textes des étapes. |
| `steps` | `{}` | Les étapes, par identifiant, dans leur ordre. |

## Une étape

| Clé | Valeur par défaut | |
|---|---|---|
| `title` | obligatoire | La clé de traduction du titre. |
| `description` | `null` | La clé de traduction de la description. |
| `route`, `route_parameters` | `null`, `{}` | La page où mène l'étape, liée depuis la liste de contrôle et la visite. |
| `target` | `null` | Le sélecteur CSS de l'élément que la visite met en évidence. |
| `completion` | `manual` | `manual`, `event`, `checker` ou `question` : voyez [Parcours et étapes](flows.md). |
| `event` | `null` | Le nom de l'événement qui accomplit l'étape, d'habitude sa classe ; seulement pour `event`. |
| `checker` | `null` | Le service du `StepChecker` ; seulement pour `checker`. |
| `skippable` | `true` | Si une personne peut passer l'étape. |
| `before`, `after` | `[]` | Les étapes avant ou après lesquelles celle-ci vient. |
| `question` | aucune | Seulement pour `question` : `multiple`, `false` par défaut, et de deux à vingt `choices`, par identifiant, chacun avec la clé de traduction de son `label` et les `roles` et `subscriptions` qu'il accorde. |

Les identifiants comptent jusqu'à 64 lettres minuscules, chiffres, `_`, `.` et `-`, et commencent par une lettre ou un
chiffre. Les titres comptent au plus 255 caractères, les descriptions 2 000 et les sélecteurs 255, sans aucun
caractère de contrôle. Un parcours compte d'une à cent étapes. Un choix, un rôle et un abonnement suivent les règles d'[une question](flows.md#accomplissement). Une erreur arrête le
conteneur à sa compilation, avec sa raison.

Les étapes peuvent aussi être déclarées par des attributs, par l'application ou par d'autres paquets, dans un parcours
de la configuration : voyez [Parcours et étapes](flows.md).
