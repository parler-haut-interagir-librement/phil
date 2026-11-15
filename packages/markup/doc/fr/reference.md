<!-- phil-dev source-sha256=e51a0a86757b07ca933701fb38b96bac4067218551fee80c3e9eef1e8bfc2d2d -->
# Référence

## Types

| Type | Nature | Rôle |
|---|---|---|
| `MarkupFormat` | enum | `Markdown`, `Html`, `BBCode`, `MediaWiki`, `DokuWiki`, `Creole`, `Text`, `Ujo` ; `values()` ; `nameOf()`, le nom d'un cas ou d'un format qu'apporte un module complémentaire |
| `Level` | enum | `Basic`, `Forum`, `All` |
| `MarkupCompiler` | interface | `compile(string $source, MarkupFormat\|string $format): string`, du HTML **qu'on ne peut pas afficher sans risque** ; une chaîne nomme un format d'un module complémentaire |
| `LevelAwareCompiler` | interface | Un `MarkupCompiler` avec `compileFor($source, $format, $level)` : on lui indique le niveau auquel le HTML sera assaini |
| `HtmlPurifier` | interface | `purify(string $html, Level $level): string` |
| `Exception\MarkupException`, `Exception\InvalidSource`, `Exception\UnknownFormat` | exceptions | Voir [Exceptions](exceptions.md) |
| `Markup` | `final readonly class` | `toHtml()` et `text()` : compiler (au niveau voulu, pour un `LevelAwareCompiler`), puis assainir ; `Level::All` sauf indication contraire |
| `MarkedUpText` | objet intégrable | `source` (texte), `format` (le nom du format, 16 caractères), `html` (texte) |
| `Lexer\Cursor` | `final class` | Le curseur de l'analyseur : voir [L'analyseur](analyser.md) |
| `Lexer\Slugger` | `final class` | Les ancres d'un document, uniques |
| `Reader\MarkupReader` | interface | `read(string $source): Reading` |
| `Reader\Reading` | `final readonly class` | `document`, un `Document` UJO, `metadata` et `losses` |
| `Reader\Loss`, `Reader\LossReason` | `final readonly class`, enum | Ce qu'un lecteur n'a pas gardé : élément, chemin, raison |
| `Contract\ExtractedElementStore` | interface | `store(string $content, string $mediaType, string $name): ?string` : où les éléments extraits sont stockés sous forme de fichiers |
| `Reader\Markdown\MarkdownReader` | `MarkupReader` | Le Markdown, et la syntaxe d'un dialecte : voir [Markdown](markdown.md) |
| `Reader\Markdown\Contract\MarkdownDialect`, `Reader\Markdown\BaseDialect` | interface, `abstract class` | Un dialecte de Markdown, et un dialecte qui se lit comme du Markdown simple, à étendre : voir [Points d'extension](extension-points.md#dialectes-de-markdown) |
| `Reader\Markdown\Contract\BlockRule`, `Reader\Markdown\Rule\InlineRule` | interfaces | Une règle de bloc et une règle en ligne d'un dialecte |
| `Reader\Markdown\MarkdownSyntax` | enum | La syntaxe intégrée qu'un dialecte peut désactiver : `ColonBlocks` |
| `Reader\Markdown\Context`, `InlineParser`, `Inline`, `Delimiter`, `Attributes`, `Curly`, `FrontMatter`, `Rule\DelimiterRule` | classes | Ce avec quoi les règles d'un dialecte lisent, et ce qu'elles produisent : voir [Points d'extension](extension-points.md#dialectes-de-markdown) |
| `Reader\Contract\ReaderProvider` | interface | `reader(Level $level, Normalizer $normalizer): MarkupReader`, le lecteur d'un format qu'apporte un module complémentaire |
| `Reader\BBCode\BBCodeReader` | `MarkupReader` | Le BBCode et ses extensions : voir [BBCode](bbcode.md) |
| `Reader\MediaWiki\MediaWikiReader`, `Reader\DokuWiki\DokuWikiReader`, `Reader\Creole\CreoleReader` | `MarkupReader` | Les wikis et leurs extensions : voir [Les wikis](wikis.md) |
| `Reader\Wiki\WikiLinks` | `final readonly class` | Où se trouvent les pages et les médias que nomme un texte de wiki : `pages`, `media` |
| `Reader\Html\HtmlReader` | `MarkupReader` | HTML vers UJO : voir [HTML](html.md) |
| `Reader\UjoReader` | `MarkupReader` | Le JSON UJO, par `phil/ujo` |
| `Reader\Text\TextReader` | `MarkupReader` | Le texte brut : un `paragraph` pour chaque suite de lignes, sauts de ligne gardés, adresses changées en liens |
| `Adapter\DefaultCompiler` | `LevelAwareCompiler` | Chaque format, ceux des modules complémentaires donnés par nom ; `reader($format, $level)` donne le lecteur d'un format vers UJO, y compris ceux du HTML et du texte brut, même si les deux se compilent sans lui ; `formats()`, le nom de chacun |
| `Adapter\SanitizerPurifier` | `HtmlPurifier` | `defaultPolicies()`, un assainisseur par niveau ; `configurations()`, la configuration de chaque niveau |
| `Adapter\AmmoniaPurifier` | `HtmlPurifier` | `defaultPolicies()` sur l'extension `ammonia`, les mêmes politiques ; `isAvailable()` : voir [Accélérateurs](accelerators.md) |
| `Adapter\Purifiers` | `final readonly class` | `defaultPolicies($mediaHosts, $extension)` : `AmmoniaPurifier` là où l'extension est chargée, `SanitizerPurifier` sinon |
| `Adapter\CodeClasses` | `final readonly class` | Les classes gardées sur `code` (`language-…`) et `span` (`hl-…`), un assainisseur d'attributs de `symfony/html-sanitizer` ; `keep()`, les classes qu'il garde d'une valeur |
| `Adapter\PlainText`, `Adapter\Autolink` | compilateurs | Le texte brut, les liens dans le texte |

L'espace de noms racine (formats, niveaux, ports et exceptions) est ce qu'un modèle de domaine peut nommer ;
l'analyseur, les lecteurs, les adaptateurs, `Markup` et `MarkedUpText` relèvent de l'infrastructure. Les classes de
`Reader\Markdown` et les règles sous `Reader\Markdown\Rule` que le tableau ne nomme pas, les classes de `Reader\BBCode`
autres que `BBCodeReader`, celles de `Reader\Wiki` autres que `WikiLinks`, les syntaxes des wikis et le parcoureur de
`Reader\Html` sont internes.

## Formats

| Format | Compilé |
|---|---|
| `markdown` | lu en UJO par `MarkdownReader`, puis rendu par `phil/ujo` ; HTML brut gardé à `all`, échappé en dessous |
| `ujo` | lu et vérifié par `phil/ujo`, puis rendu ; une source invalide lance `InvalidSource` |
| `html` | tel qu'écrit ; `HtmlReader` le convertit en UJO sur demande : voir [HTML](html.md) |
| `bbcode` | lu en UJO par `BBCodeReader`, puis rendu ; HTML brut comme pour le Markdown : voir [BBCode](bbcode.md) |
| `mediawiki`, `dokuwiki`, `creole` | lus en UJO par `MediaWikiReader`, `DokuWikiReader`, `CreoleReader`, puis rendus ; HTML brut comme pour le Markdown : voir [Les wikis](wikis.md) |
| `text` | échappé ; un paragraphe par bloc séparé par une ligne vide, un saut de ligne pour chaque autre nouvelle ligne, adresses changées en liens ; `TextReader` le convertit en paragraphes UJO sur demande |
| un format d'un module complémentaire | lu en UJO par le lecteur que donne son `ReaderProvider`, puis rendu |

Le HTML que produit `phil/ujo` porte les classes de ses blocs (`ujo-paragraph`, `ujo-header`) et les identifiants des
titres. L'assainisseur de chaque niveau retire les classes, sauf celles du code (`Adapter\CodeClasses`) : `language-…`
sur `code`, qui nomme le langage d'un bloc pour un surligneur dans le navigateur, et `hl-…` sur `span`, qu'écrit un
surligneur côté serveur, là où les `span` sont gardés ; `all` garde les identifiants, vers lesquels pointe une table
des matières. [Formats](formats.md) liste, fonctionnalité par fonctionnalité, comment chaque format l'écrit.

## Niveaux

| Niveau | Garde | Pour |
|---|---|---|
| `basic` | `strong`, `b`, `em`, `i`, `u`, `s`, `del`, `sub`, `sup`, `code`, `br` | un nom, une légende, un court commentaire |
| `forum` | basic, avec `p`, `a`, `img`, `ul`, `ol`, `li`, `blockquote`, `cite`, `pre`, `hr` | un message de forum |
| `all` | chaque élément que `symfony/html-sanitizer` considère comme sûr, titres et tableaux compris, et la case à cocher désactivée d'un élément de liste de contrôle | un article qu'écrit un rédacteur |

Un conteneur qu'un niveau ne permet pas (un paragraphe, une liste, un titre, un tableau) est retiré et son texte
gardé ; tout autre élément (un script, un cadre, un formulaire, un objet) est retiré avec son contenu. Chaque niveau
retire les attributs qu'il ne connaît pas, dont les styles et les gestionnaires d'événements. Les liens peuvent
utiliser `http`, `https` et `mailto`, ou être relatifs ; les images `http` et `https`, ou être relatives. **La longueur
de l'entrée n'est pas plafonnée.**

**Une image est une requête à son hôte.** À `forum` et `all`, une image d'un autre site (`[img]https://…[/img]`,
`![](https://…)`, `<img src="https://…">`) fait appeler ce site par le navigateur de chaque lecteur ; le site apprend
leur adresse et le moment où ils lisent. C'est un pistage sans leur consentement quand un membre a écrit l'image, ce
que la Loi 25 et le RGPD interdisent sans consentement. `SanitizerPurifier::defaultPolicies()` prend les hôtes d'où
peuvent venir les images :

```php
SanitizerPurifier::defaultPolicies(['cdn.example.com']);   // that host and its subdomains, and the site's own images
SanitizerPurifier::defaultPolicies([]);                    // the site's own images only
SanitizerPurifier::defaultPolicies();                      // any host
```

Une image d'un autre hôte perd son `src` ; une image relative, servie par le site lui-même, est toujours gardée. Un site
dont les membres écrivent des textes à `forum` donne les hôtes depuis lesquels il sert des images, ou `[]`.

Assainissez de nouveau ce qui a été stocké au moment de l'afficher : une politique resserrée s'applique alors aux textes
écrits avant. `Purifiers::defaultPolicies()` prend les mêmes hôtes, et assainit avec l'extension `ammonia` là où elle est
chargée : voir [Accélérateurs](accelerators.md).

## Listes de contrôle

`phil/ujo` produit le rendu de chaque élément d'une `checklist` avec une case à cocher désactivée, `checked` quand
l'élément l'est, dans un `<label>` qui contient le texte de l'élément : un lecteur d'écran annonce une case à cocher,
son état et son nom, et personne ne peut la cocher. Le niveau décide de ce qui en reste :

| Niveau | Un élément coché, `- [x] done` | Pourquoi |
|---|---|---|
| `all` | `<li><label><input type="checkbox" disabled checked />done</label></li>` | La case à cocher est la forme accessible de l'état |
| `forum` | `<li>☑ done</li>` | Aucun `input` n'est gardé sous `all` : l'état est écrit en texte, `☑` coché et `☐` non coché, que les lecteurs d'écran nomment |
| `basic` | `☑ done` | Les listes sont retirées et leur texte gardé ; l'état reste dans le texte |

`DefaultCompiler` écrit l'état en texte sous `all` avec `CheckboxText`, un filtre sur le HTML dont le rendu est produit ;
le HTML compilé tel qu'il est écrit perd l'état de ses cases à cocher sous `all`. À `all`, `SanitizerPurifier` réécrit
chaque `input` comme `<input type="checkbox" disabled>`, avec `checked` quand il l'avait, et retire un champ de tout
autre type, un champ sans type compris : aucun texte assaini ne contient un champ qu'un lecteur pourrait remplir ou
soumettre.
