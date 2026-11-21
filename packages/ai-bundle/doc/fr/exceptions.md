<!-- phil-dev source-sha256=e8f6e1158df3b56a2dce82675ab7b0ff571ad0bf86eaedbee932ab7ea8d73342 -->
# Exceptions

Les exceptions du bundle implémentent `Phil\AiBundle\Domain\Exception\AiException`. Aucune ne contient d'invite, de
réponse ni de clé.

| Exception | Étend | Levée quand |
|---|---|---|
| `Domain\Exception\InvalidAiValue` | `InvalidArgumentException` | Une sorte de tâche, un sujet, un nom de plateforme ou de modèle, un nombre de jetons, un prix, un coût ou une devise est mal formé; une sorte est définie deux fois; un prix est dans une autre devise que la liste |
| `Domain\Exception\AlreadyDecided` | `LogicException` | Une suggestion est décidée une deuxième fois, ou une décision vise une tâche échouée |
| `Application\Exception\UnknownTask` | `LogicException` | Une sorte que la configuration ne définit pas |
| `Application\Exception\MissingPrompt` | `LogicException` | Une sorte sans invite utilisateur dans la locale ni dans ses replis |
| `Application\Exception\NoUserKey` | `RuntimeException` | Sous `require_user`, une tâche sans propriétaire, ou dont le propriétaire n'a aucune clé pour la plateforme |
| `Application\Exception\TaskFailed` | `RuntimeException` | L'appel au modèle a échoué; l'exception du fournisseur est l'exception précédente |
| `Application\Exception\TooManyTasks` | `RuntimeException` | Le sujet exécute déjà autant de tâches en même temps que `quota.concurrency` le permet ; rien n'a été compté ni appelé |
| `Application\Exception\UnknownSuggestion` | `RuntimeException` | Une décision sur un identifiant que le journal ne contient pas |
| `Application\Exception\UnknownPlatform` | `LogicException` | Une plateforme non déclarée, ou la clé d'une personne pour une plateforme sans `user_key_factory`; signalée comme exception précédente de `TaskFailed` |

`run()` et `stream()` laissent aussi passer `Phil\UsageMeter\Exception\QuotaExceeded` quand la réservation de la tâche
n'entre pas. Son `usage` nomme le compteur qui a refusé, la quantité utilisée, la limite et le moment où la fenêtre
libère de la place ; son `requested` est ce que la tâche a tenté de réserver. Rien ne reste réservé.

Exemples de messages :

```text
No task "draft.expand" is defined: add it under phil_ai.tasks.
The task "draft.rewrite" has no prompt "draft.rewrite.user" in the translation domain "phil_ai" for the locale "de".
The task runs only on the person's own key, and they have none for the platform "main".
The task "draft.rewrite" failed at the model (Symfony\AI\Platform\Exception\RateLimitExceededException).
The suggestion "0194f3d2-…" is already accepted.
The subject already runs 3 tasks at once, the most the quota allows.
```

Une configuration mal formée échoue à la construction du conteneur, avec l'`InvalidConfigurationException` de Symfony.
