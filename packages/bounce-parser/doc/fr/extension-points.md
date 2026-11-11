<!-- phil-dev source-sha256=caa6e46cc837d18fd43b7d79d2ddeedfdcb8c5eb207c17e09012ef41440c25e4 -->
# Points d'extension

La bibliothèque est du PHP simple et ne déclare aucun service. Ce que choisit une application passe par un port et des
arguments.

## `Contract\ReportAuthenticator`

| Méthode | Contrat |
|---|---|
| `isAuthentic(Mime\Part $message, FeedbackReport $report): bool` | Dit si un rapport de plainte vient d'une boucle de rétroaction à laquelle l'application est abonnée ; ne garde aucun état entre les appels |

`Auth\AuthenticationResults` lit les `Authentication-Results` du serveur de l'application ; `Auth\TrustEveryReport` fait
confiance à tous. Un adaptateur de l'application peut vérifier lui-même la signature DKIM, ou comparer l'IP d'envoi aux
plages publiées de ses boucles de rétroaction — voir [Rapports de plainte](feedback-reports.md).

## Arguments

| Point | Comment |
|---|---|
| Ce qu'aucune norme ne décrit | `Rule\Rules` et ses règles, ordonnées par nom — voir [Règles](rules.md) |
| Retrouver le message envoyé | Un `Verp` pour les chemins de retour, un champ propre à l'application lu avec `OriginalMessage::header()` — voir [Retrouver le message](finding-the-message.md) |
| Les bornes du travail | `Limits` — voir [MIME](mime.md) |
| Ce à quoi mène chaque genre | La `BounceAction` du rapport, que l'application associe à ses compteurs, à sa liste de suppression et à sa file de révision |
| Attraper les erreurs de la bibliothèque | Chaque exception implémente `Exception\BounceParserException` ; l'analyse d'un message n'en lève aucune |
