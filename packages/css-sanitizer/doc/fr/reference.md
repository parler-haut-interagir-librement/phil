<!-- phil-dev source-sha256=0fb665c9016fbecc08870ddfcba296c9d28395b18d488da7dad9f1385409dfe2 -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `Phil\CssSanitizer\CssSanitizer` | `final readonly class` | Feuilles de style et attributs `style` assainis selon une politique |
| `Phil\CssSanitizer\Policy` | `final readonly class` | Ce qu'une feuille de style peut contenir, son réécrivain, sa portée et ses limites |
| `Phil\CssSanitizer\AtRuleKind` | enum | Les règles @ qu'une politique peut permettre : media, supports, page, font-face, import |
| `Phil\CssSanitizer\Sanitized` | `final readonly class` | Ce qui a été gardé, en CSS et en valeurs, et ce qui a été retiré |
| `Phil\CssSanitizer\Removal` | `final readonly class` | Un retrait : sa raison, son sujet et sa ligne |
| `Phil\CssSanitizer\RemovalReason` | enum | Pourquoi quelque chose a été retiré, et sa description |
| `Phil\CssSanitizer\UrlRewriter` | interface | Ce que devient chaque URL, ou `null` pour la refuser |
| `Phil\CssSanitizer\UrlKind` | enum | Ce que charge une URL : une image, une police ou une feuille de style |
| `Phil\CssSanitizer\Url\RefuseUrls` | `final readonly class` | Refuse toutes les URL |
| `Phil\CssSanitizer\Url\AllowedFiles` | `final readonly class` | Fait correspondre des noms aux URL publiques des fichiers permis |
| `Phil\CssSanitizer\Url\CallbackRewriter` | `final readonly class` | Un réécrivain écrit sous forme de fermeture |
| `Phil\CssSanitizer\Serializer` | `final class` | Le CSS réécrit à partir de valeurs, de déclarations et de feuilles de style |
| `Phil\CssSanitizer\Sheet\StyleSheet` | `final readonly class` | Les règles `@import` et les autres règles gardées d'une feuille de style |
| `Phil\CssSanitizer\Sheet\ImportRule` | `final readonly class` | Un `@import` gardé : son URL réécrite et ses conditions |
| `Phil\CssSanitizer\Sheet\StyleRule` | `final readonly class` | Une règle de style gardée : ses sélecteurs, ses déclarations et ses règles imbriquées |
| `Phil\CssSanitizer\Sheet\GroupRule` | `final readonly class` | Une règle @ gardée, avec son prélude, ses déclarations et ses règles |
| `Phil\CssSanitizer\Syntax\Tokenizer` | `final class` | L'analyseur lexical de CSS Syntax Level 3 |
| `Phil\CssSanitizer\Syntax\Parser` | `final readonly class` | L'analyseur de CSS Syntax Level 3 |
| `Phil\CssSanitizer\Syntax\TokenType` | enum | Les genres de jetons |
| `Phil\CssSanitizer\Syntax\ComponentValue` | interface | Un jeton, un bloc simple ou une fonction |
| `Phil\CssSanitizer\Syntax\Token` | `final readonly class` | Un jeton aux échappements résolus |
| `Phil\CssSanitizer\Syntax\SimpleBlock` | `final readonly class` | Les valeurs entre deux délimiteurs appariés |
| `Phil\CssSanitizer\Syntax\FunctionValue` | `final readonly class` | Une fonction et ses arguments |
| `Phil\CssSanitizer\Syntax\Stylesheet` | `final readonly class` | Les règles d'une feuille de style analysée |
| `Phil\CssSanitizer\Syntax\QualifiedRule` | `final readonly class` | Un prélude et un bloc |
| `Phil\CssSanitizer\Syntax\AtRule` | `final readonly class` | Le nom, le prélude et le bloc facultatif d'une règle @ |
| `Phil\CssSanitizer\Syntax\Declaration` | `final readonly class` | Une propriété, sa valeur et `!important` |
| `Phil\CssSanitizer\Syntax\Malformed` | `final readonly class` | Ce que l'analyseur a sauté comme erreur d'analyse |
| `Phil\CssSanitizer\Check\Sanitization` | `final class`, interne | Une passe sur une source analysée : règles gardées, retraits notés |
| `Phil\CssSanitizer\Check\Selectors` | `final readonly class`, interne | La grammaire des sélecteurs et les noms de la politique |
| `Phil\CssSanitizer\Check\Values` | `final readonly class`, interne | Valeurs vérifiées, URL réécrites |
| `Phil\CssSanitizer\Check\Preludes` | `final readonly class`, interne | Requêtes média, conditions, sélecteurs de page et conditions d'`@import` |
| `Phil\CssSanitizer\Check\Imports` | `final readonly class`, interne | Une règle `@import` vérifiée, son URL réécrite |
| `Phil\CssSanitizer\Check\FontSources` | `final readonly class`, interne | Les sources d'`@font-face` vérifiées une à une |
| `Phil\CssSanitizer\Validator\SafeCss` | `final class` | La contrainte : du CSS gardé en entier |
| `Phil\CssSanitizer\Validator\SafeCssValidator` | `final class` | Chaque retrait devient une violation |
| `Phil\CssSanitizer\Exception\CssSanitizerException` | interface | Toutes les exceptions de la bibliothèque |
| `Phil\CssSanitizer\Exception\InvalidPolicy` | `final class` | Une politique à qui l'on demande ce qui n'est jamais permis, ou une limite impossible |
| `Phil\CssSanitizer\Exception\NestingTooDeep` | `final class` | Des blocs imbriqués plus profondément que la limite de l'analyseur |
