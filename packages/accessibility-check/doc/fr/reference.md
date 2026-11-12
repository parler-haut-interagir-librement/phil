<!-- phil-dev source-sha256=6b00b9fc7880649e326c7021fcb533a9d62859c4d58b22f02e4a9be56b0780dd -->
# Référence

## Vérification

| Type | Rôle |
|---|---|
| `AccessibilityChecker` | Exécute les règles retenues par la politique sur du HTML (`checkHtml()`) ou un document UJO (`checkUjo()`), et retourne un `Report` |
| `Policy` | Limites, titres, contraste et couleurs, jetons de couleur, langue, textes de lien génériques, règles désactivées ou dotées d'une autre sévérité, locale |
| `HeadingPolicy` | `Single`, `None` ou `Any` : les titres de premier niveau qu'un contenu peut avoir |
| `ContrastLevel` | `AA` ou `AAA` : les rapports de contraste exigés et leur critère de succès |
| `Severity` | `Error`, `Warning` ou `Notice`, avec `isAtLeast()` |
| `SuccessCriterion` | Les 86 critères de succès de WCAG 2.2 par numéro, avec `title()`, `level()` et `understandingUrl()` |
| `ConformanceLevel` | `A`, `AA` ou `AAA` |

## Le rapport

| Type | Rôle |
|---|---|
| `Report` | Le résultat de chaque règle et les constats, filtrés, traduits et sérialisés en JSON |
| `RuleResult` | Le résultat d'une règle : identifiant, critère, sévérité, nombre de constats, `passed()` |
| `Finding` | Un problème : règle, critère, sévérité, message, emplacement |
| `Location` | L'emplacement d'un constat : ligne, chemin de l'élément et extrait en HTML ; bloc, type et pointeur en UJO |
| `Message` | Une clé du domaine `phil_accessibility_check` et les valeurs de ses paramètres substituables |
| `Violation` | Ce que produit une règle : un emplacement, un message, et sa propre sévérité ou son propre critère s'il y a lieu |

## Règles

| Type | Rôle |
|---|---|
| `Contract\Rule` | Identifiant, critère de succès et sévérité d'une règle |
| `Contract\HtmlRule` | Une règle qui lit du HTML |
| `Contract\UjoRule` | Une règle qui lit des documents UJO |
| `Rule\DefaultRules` | Les quinze règles de la bibliothèque, dans leur ordre d'exécution |
| `Rule\FragmentRule` | La base d'une règle qui lit les données de chaque bloc, puis chacun de ses champs HTML |
| `Rule\ImageAlt`, `HeadingOrder`, `HeadingH1`, `EmptyHeading`, `LinkName`, `ButtonName`, `LinkText`, `ColorContrast`, `TableHeaders`, `HtmlLang`, `LangChange`, `FrameTitle`, `VideoCaptions`, `DuplicateId`, `SvgName` | Les règles : voir [Règles](rules.md) |
| `Rule\Heading` | Un titre d'un contenu, en HTML ou en UJO |
| `Rule\FileName` | Si un texte de remplacement est un nom de fichier |
| `Rule\GenericLinkText` | Les textes de lien génériques de six langues, et la façon d'y comparer un nom de lien |

## Lecture

| Type | Rôle |
|---|---|
| `Html\HtmlTarget` | Le HTML analysé et indexé, avec ce que les règles demandent d'un élément |
| `Ujo\UjoTarget` | Un document UJO tel que le lisent les règles : ses blocs et ses champs HTML |
| `Ujo\UjoReader` | Parcourt les blocs et leurs données pour `UjoTarget` |
| `Ujo\BlockVisit` | Un bloc tel que le rencontre la vérification : son pointeur et sa langue |
| `Ujo\Fragment` | Un champ HTML d'un bloc, analysé |
| `Ujo\PlainFields` | Les champs de données de bloc qui ne contiennent jamais de HTML |
| `Colour\Colour` | Une couleur sRGB, avec la luminance relative et le rapport de contraste de WCAG |
| `Colour\ColourParser` | Lit les couleurs CSS |
| `Language\LanguageTag` | Si une étiquette de langue BCP 47 est bien formée, et sa langue principale |

## Messages

| Type | Rôle |
|---|---|
| `Translation\Messages` | Les messages pour `symfony/translation` : `resources()`, `translator()`, `translate()`, `locale()` |
| `Translation\English`, `French`, `Spanish`, `German`, `Italian`, `Portuguese` | Les messages de chaque langue |

## Symfony

| Type | Rôle |
|---|---|
| `Validator\AccessibleContent`, `AccessibleContentValidator` | La contrainte et son validateur |
| `Workflow\PublicationGuard` | La garde qui retient une publication |
| `Contract\SubjectContent` | Le contenu du sujet d'un workflow, pour la garde |
| `Console\CheckCommand` | La commande `phil:accessibility:check` |

## Exceptions

| Type | Rôle |
|---|---|
| `Exception\AccessibilityCheckException` | Implémentée par chaque exception de la bibliothèque |
| `Exception\InvalidPolicy` | Une politique ou un ensemble de règles inutilisable |
| `Exception\LimitExceeded` | Un contenu qui dépasse une limite de la politique |
| `Exception\InvalidContent` | Du HTML qui n'est pas en UTF-8 |
