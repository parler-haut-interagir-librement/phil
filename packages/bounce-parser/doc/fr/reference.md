<!-- phil-dev source-sha256=c66866d5d21c1524fa333f22be9822cd5d87548e16453acf6b80dd6c5fcf6e35 -->
# Référence

| Type | Rôle |
|---|---|
| `BounceParser` | Lit un message de la boîte de rebonds et retourne un `BounceReport` |
| `BounceReport` | Ce que dit un message : format, genre, action, destinataires, rapport de plainte, message original, étiquette VERP, règle |
| `ReportFormat` | Comment le message a été reconnu |
| `BounceKind` | Ce que le message dit du message envoyé |
| `BounceAction` | Ce que l'application devrait faire, et `for()` l'action de chaque genre |
| `Recipient` | Un destinataire que nomme un rapport, et `kindOfFailure()` |
| `DeliveryAction` | L'`Action` d'un destinataire d'une notification d'état de remise |
| `StatusCode` | Un code d'état étendu du système de courriel |
| `FeedbackReport` | Les champs d'un rapport de plainte |
| `FeedbackType` | Le `Feedback-Type` d'un rapport de plainte |
| `OriginalMessage` | Les champs d'en-tête du message que concerne un rapport |
| `Verp` | Les chemins de retour d'enveloppe variables |
| `Limits` | Les bornes du travail sur un message |
| `Rule\Rule` | Une règle : un motif sur un champ, un genre, une action, une portée |
| `Rule\Rules` | Les règles dans leur ordre |
| `Rule\Field` | Le texte qu'examine une règle |
| `Rule\RuleScope` | Les messages sur lesquels une règle est essayée |
| `Contract\ReportAuthenticator` | Le port qui se porte garant des rapports de plainte |
| `Auth\AuthenticationResults` | Se porte garant par les `Authentication-Results` du serveur de l'application |
| `Auth\TrustEveryReport` | Se porte garant de tout rapport |
| `Mime\MimeParser` | Lit un message et sa structure MIME |
| `Mime\Part` | Un message ou l'une de ses parties |
| `Mime\Headers` | Les champs d'en-tête d'une partie |
| `Exception\BounceParserException` | L'interface de chaque exception de la bibliothèque |
| `Exception\InvalidLimits`, `Exception\InvalidRule`, `Exception\InvalidVerp` | Une configuration refusée |
| `Mime\PartBudget`, `Reader\*` | Les parties qui restent à lire et les lectures de chaque format : hors de l'API publique |
