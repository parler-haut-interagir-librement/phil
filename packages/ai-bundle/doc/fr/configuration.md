<!-- phil-dev source-sha256=93b9ab5f2887f4efb1631f604b9e20cfe143eabc0a7a500058e6d7364d1a9522 -->
# Configuration

```yaml
phil_ai:
    platforms:
        main:
            platform: 'app.ai_platform'            # required: a Symfony AI PlatformInterface service
            user_key_factory: 'app.ai_user_platform' # a UserKeyPlatformFactory service, for the person's own key
            secret: 'main-key'                     # the person's secret in phil/user-secrets-bundle
    currency: USD
    prices:
        the-model: { input: 3000000, output: 15000000 }
        the-image-model: { input: 0, output: 0, per_call: 3000 }
    tasks:
        draft.rewrite:
            platform: main
            model: 'the-model'
            options: { max_tokens: 1024, temperature: 0.7 }
            reserve: 2000
            output: text                           # text, structured, image or speech
            structure: ~                           # the class of a structured output
            tools:
                look_up: { description: 'Looks a rule up.', parameters: { type: object } }
            purpose: 'Proposes a rewrite of a passage the author chose.'
    key_policy: prefer_user
    usage_meter: 'Phil\UsageMeter\UsageMeter'
    quota:
        tokens_meter: ai_tokens
        tasks_meter: ai_tasks
        count_user_keys: false
        budget_meter: ai_budget
        ceiling_meter: ai_ceiling
        ceiling_subject: phil_ai.application
    translation_domain: phil_ai
    store: doctrine
    entity_manager: default
```

| Option | Valeur par défaut | Signification |
|---|---|---|
| `platforms` | aucune | Les plateformes par nom : jusqu'à 64 lettres minuscules, chiffres, `_` ou `-`, en commençant par une lettre. |
| `platforms.*.platform` | obligatoire | Le service de la plateforme avec la clé de l'application. |
| `platforms.*.user_key_factory` | `null` | Le service qui construit la plateforme avec la clé personnelle d'une personne; sans lui, la plateforme n'accepte aucune clé de personne. |
| `platforms.*.secret` | `null` | Le nom du secret, dans `phil/user-secrets-bundle`, qui contient la clé d'une personne pour cette plateforme. Exige `user_key_factory`. |
| `currency` | `USD` | Le code ISO 4217 des prix et des coûts du journal. |
| `prices` | aucune | Par modèle, `input` et `output` en millionièmes de la devise par million de jetons : 3,00 s'écrit `3000000` ; `per_call`, ce que coûte chaque appel quels que soient ses jetons, 0 par défaut. Un modèle sans prix coûte 0. |
| `tasks` | aucune | Par sorte, la racine de ses invites : la plateforme, parmi `platforms`; le modèle; les options transmises telles quelles à la plateforme; et `reserve`, les jetons qui doivent rester dans le quota du sujet avant que la tâche commence, 1 ou plus, 1 par défaut. |
| `tasks.*.output` | `text` | Ce que la tâche demande : `text`, `structured`, `image` ou `speech`. Voir [Sorties, flux continu et outils](outputs.md). |
| `tasks.*.structure` | `null` | La classe dans laquelle une sortie structurée est lue ; obligatoire pour `structured`, refusée autrement. |
| `tasks.*.tools` | aucun | Les outils que le modèle peut proposer, par nom (de 1 à 64 lettres, chiffres, `_` ou `-`), chacun avec sa `description` et le schéma JSON de ses `parameters`. Jamais exécutés par le bundle ; refusés pour les images et la parole. |
| `tasks.*.purpose` | `''` | À quoi sert la tâche, en une phrase, pour le registre des usages. |
| `key_policy` | `prefer_user` | `application`, `prefer_user` ou `require_user`; voir [Clés et quotas](keys-and-quotas.md). `require_user` exige `phil/user-secrets-bundle`. |
| `usage_meter` | `null` | Le service de `phil/usage-meter` qui compte les quotas. Obligatoire dès qu'un compteur de quota est nommé. |
| `quota.tokens_meter` | `null` | Le compteur de jetons. |
| `quota.tasks_meter` | `null` | Le compteur de tâches. |
| `quota.count_user_keys` | `false` | Indique si les tâches exécutées avec la clé personnelle d'une personne comptent dans les quotas. |
| `quota.budget_meter` | `null` | Le compteur des dépenses du sujet, en millionièmes de la devise. |
| `quota.ceiling_meter` | `null` | Le compteur des dépenses de toute l'application, en millionièmes de la devise. |
| `quota.ceiling_subject` | `phil_ai.application` | Le sujet sur lequel le plafond est compté. |
| `translation_domain` | `phil_ai` | Le domaine de traduction des invites. |
| `store` | `doctrine` | L'endroit où le journal est gardé : `doctrine`, ou `memory` pour les tests. |
| `entity_manager` | `default` | Le gestionnaire d'entités Doctrine du journal. |

La configuration est vérifiée à la construction du conteneur. Sont refusés avec l'`InvalidConfigurationException` de
Symfony : une tâche sur une plateforme non déclarée, un secret sans fabrique, un compteur de quota, de budget ou de
plafond sans compteur d'utilisation, une structure sans `structured` ou l'inverse, un outil sur une tâche d'image ou de
parole, et `require_user` sans `phil/user-secrets-bundle`.

## Services

| Service | Public | Rôle |
|---|---|---|
| `Phil\AiBundle\Application\AiTasks` | oui | Exécute les tâches et enregistre les décisions |
| `Phil\AiBundle\Domain\Repository\JournalRepository` | oui (alias) | Le journal |
| `Phil\AiBundle\Application\Port\Completions` | oui (alias) | Les modèles, par Symfony AI; un test le remplace |
| `Phil\AiBundle\Application\Port\UserKeys` | non (alias) | Les clés de la personne, ou aucune |
| `Phil\AiBundle\Application\AiNotice` | oui | Les avis qu'une personne parle à une IA, et que ce qu'elle voit a été généré |
| `Phil\AiBundle\Application\AiUsageRegister` | oui | Les usages de l'IA de l'application, tirés de ses tâches |
| `Phil\AiBundle\Infrastructure\Privacy\AiJournalPrivacy` | non | Le module du journal de `phil/privacy-bundle`, quand il est installé |
| `Phil\AiBundle\Infrastructure\Replicate\ReplicatePlatformFactory` | non | La plateforme d'images Replicate sur un jeton, quand un client HTTP est installé |
