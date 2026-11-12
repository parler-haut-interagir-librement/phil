<!-- phil-dev source-sha256=41b7dbf021deab123aee4366f12254c8c00d1b21c3738a74f8fc3e89c4091a37 -->
# Politique

`Policy` est le point unique de configuration d'une vérification. Ses réglages scalaires sont des arguments de son
constructeur. Ses collections s'ajoutent avec `with…()`, qui retourne chaque fois une nouvelle politique.

```php
use Phil\AccessibilityCheck\ContrastLevel;
use Phil\AccessibilityCheck\HeadingPolicy;
use Phil\AccessibilityCheck\Policy;
use Phil\AccessibilityCheck\Severity;

$policy = new Policy(
    headings: HeadingPolicy::None,
    contrast: ContrastLevel::AAA,
    foreground: '#222',
    background: '#fdfaf3',
    language: 'fr',
    locale: 'fr',
)
    ->withColourTokens(['accent' => '#8a1c1c', 'muted' => '#6b6b6b'])
    ->withGenericLinkTexts('fr', 'cliquez ici', 'ici', 'lire la suite', 'découvrir')
    ->withoutRules('svg-name')
    ->withSeverity('link-text', Severity::Error);
```

## Limites

| Argument | Valeur par défaut | Refuse |
|---|---|---|
| `maxBytes` | 5 242 880 (5 Mio) | un contenu HTML, ou un champ HTML d'UJO, plus grand que cette valeur, avant l'analyse |
| `maxDepth` | 256 | des éléments imbriqués plus profondément ; en UJO, des blocs et leurs données imbriqués plus profondément |
| `maxElements` | 100 000 | un contenu HTML comptant plus d'éléments ; un document UJO comptant plus de blocs |

Une limite inférieure à 1 lève `InvalidPolicy`. Un contenu qui dépasse une limite lève `LimitExceeded`, que la
contrainte, la garde et la commande transforment en refus.

## Titres

`headings` est une `HeadingPolicy` : `Single`, la valeur par défaut — au plus un `h1`, le contenu est la page entière ou
contient son titre — ; `None` — la page affiche son titre au-dessus du contenu, dont les titres commencent à `h2`, et
`heading-order` compte un `h1` avant le premier titre — ; `Any` — `heading-h1` ne dit rien.

## Contraste et couleurs

| Argument | Valeur par défaut | Signification |
|---|---|---|
| `contrast` | `ContrastLevel::AA` | 4,5:1 et 3:1 pour le grand texte ; `AAA`, 7:1 et 4,5:1 |
| `foreground` | `#000000` | la couleur d'un texte auquel le contenu donne un fond, mais pas de couleur |
| `background` | `#ffffff` | la couleur derrière un texte auquel le contenu donne une couleur, mais pas de fond, et derrière les fonds translucides |

`foreground` et `background` sont des couleurs CSS opaques, sinon `InvalidPolicy` : celles de l'apparence dans laquelle
le contenu s'affiche. `withColourTokens(array<string, string>)` donne les couleurs des jetons de l'apparence, qu'UJO
écrit `<span class="text-color" data-color="accent">`. Un jeton que la politique ne connaît pas relève de l'apparence,
et le texte n'est pas vérifié. La couleur d'un jeton peut être translucide ; une couleur illisible lève `InvalidPolicy`.

## Langues

`language` est la langue d'un contenu qui n'indique pas la sienne — aucun `lang` autour d'un lien, aucune *tune*
`language` — pour `link-text`. L'argument `$language` de `checkHtml()` et de `checkUjo()` la remplace pour une
vérification.

## Textes de lien génériques

`genericLinkTexts` contient, par langue principale, les textes qui ne disent rien de la destination d'un lien. Les
valeurs par défaut (`GenericLinkText::DEFAULTS`) couvrent l'anglais, le français, l'espagnol, l'allemand, l'italien et
le portugais. `withGenericLinkTexts(string $language, string ...$texts)` remplace la liste d'une langue — `fr`, `nl` —
par des textes normalisés comme les noms de liens : en minuscules, espaces réduites, sans la ponctuation ni les flèches
qui les entourent. Sans texte, elle désactive la langue.

## Règles

| Membre | Effet |
|---|---|
| `disabledRules`, `withoutRules(string ...)` | les règles qui ne s'exécutent pas ; elles n'ont aucun résultat dans le rapport |
| `severities`, `withSeverity(string, Severity)` | une sévérité pour chaque constat d'une règle, ses résultats compris |
| `isEnabled(string)` | si une règle s'exécute |

## Messages

`locale` est la locale dans laquelle le rapport rédige ses messages, `en` par défaut : voir [Messages](messages.md).
