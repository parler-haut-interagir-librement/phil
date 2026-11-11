<!-- phil-dev source-sha256=63c850bd4aeff35e97d8f9c540dae51241161e555b32500798af2ecd7b3a5653 -->
# Points d'extension

La bibliothèque est du PHP simple et ne déclare aucun service. `Markup` se compose de deux ports, un compilateur et un
assainisseur, et chaque format est lu par un lecteur qui lui est propre.

## `MarkupCompiler`

`Phil\Markup\MarkupCompiler` : une source dans un format, changée en HTML.

| Méthode | Contrat |
|---|---|
| `compile(string $source, MarkupFormat\|string $format): string` | Du HTML **qu'on ne peut pas afficher sans risque** : `Markup` l'assainit ensuite |

### `LevelAwareCompiler`

`Phil\Markup\LevelAwareCompiler` : un compilateur à qui l'on indique le niveau auquel le HTML sera assaini, pour que le
HTML brut soit gardé à `all` et échappé en dessous.

| Méthode | Contrat |
|---|---|
| `compileFor(string $source, MarkupFormat\|string $format, Level $level): string` | Comme `compile()`, pour ce niveau |

`Adapter\DefaultCompiler` compile chaque format par UJO ; il prend le `HtmlRenderer` de `phil/ujo`, pour que les
moteurs de rendu de blocs de l'application produisent le rendu de ses propres blocs. Un compilateur propre à
l'application le décore (pour ajouter une ponctuation typographique, par exemple) et reste attentif au niveau.

## `HtmlPurifier`

`Phil\Markup\HtmlPurifier` : ce qui reste du HTML à un niveau donné.

| Méthode | Contrat |
|---|---|
| `purify(string $html, Level $level): string` | Du HTML qu'on peut afficher sans risque à ce niveau, quoi qu'on lui ait donné |

`Adapter\SanitizerPurifier` contient un `symfony/html-sanitizer` par niveau : `defaultPolicies($mediaHosts)` construit
ceux qui sont fournis, avec les hôtes d'où peuvent venir les images ; `new SanitizerPurifier($sanitizers)` prend ceux de
l'application.

## Lecteurs

`Phil\Markup\Reader\MarkupReader` : un format lu et changé en document UJO.

| Méthode | Contrat |
|---|---|
| `read(string $source): Reading` | Le `Document` et les métadonnées que déclare la source ; `InvalidSource` seulement pour un format dont une source peut enfreindre les règles, comme le JSON UJO ; le lecteur d'un format écrit ne lance jamais d'exception, et lit ce qu'il peut en temps linéaire |

`Reading` liste aussi ce que le lecteur n'a pas gardé, `$losses`, chacun un `Loss` avec son élément, son chemin et sa
`LossReason`.

## `ExtractedElementStore`

`Phil\Markup\Contract\ExtractedElementStore` : l'endroit où le lecteur HTML stocke les éléments qu'il extrait sous forme
de fichiers, comme un dessin SVG en ligne, assaini par `phil/svg-sanitizer` avant d'être donné.

| Méthode | Contrat |
|---|---|
| `store(string $content, string $mediaType, string $name): ?string` | L'adresse du fichier stocké (`http`, `https` ou relative), `null` s'il est refusé ; un dessin dont l'adresse est refusée est laissé de côté, et la perte est enregistrée |

## Autres points

| Point | Comment |
|---|---|
| Les services qu'un cadre peut être | `EmbedServices` de `phil/ujo`, donné à `HtmlReader` |
| Où mènent les liens de wiki | `Reader\Wiki\WikiLinks`, donné aux lecteurs de wikis : l'adresse d'une page et d'un média que nomme le texte |
| Le HTML brut dans le Markdown | L'indicateur `$rawHtml` de `MarkdownReader` |
| Un dialecte de Markdown | Un `MarkdownDialect` donné à `MarkdownReader` : voir plus bas |
| Un autre format | Un `ReaderProvider` donné à `DefaultCompiler` sous le nom du format : voir plus bas |

Les règles des syntaxes de wikis sont internes.

## Formats des modules complémentaires

`Phil\Markup\Reader\Contract\ReaderProvider` : le lecteur d'un format que la bibliothèque ne fournit pas.

| Méthode | Contrat |
|---|---|
| `reader(Level $level, Normalizer $normalizer): MarkupReader` | Un lecteur pour une source lue à ce niveau, le HTML brut gardé à `all` seulement, son document normalisé par `$normalizer` |

`new DefaultCompiler($renderer, ['name' => $provider])` prend les fournisseurs par nom. Un nom compte de 1 à 16 lettres
minuscules, chiffres et traits d'union, commence par une lettre, et n'est pas un cas de `MarkupFormat` ; tout autre nom
lance `UnknownFormat`. Le compilateur compile alors ce format, et `reader()` le lit, comme un format intégré, par son
nom : `compile($source, 'name')` ; `formats()` le liste après les formats intégrés. Un `MarkedUpText` stocke le nom, qui
tient dans ses 16 caractères.

```php
final readonly class ShoutReaders implements ReaderProvider
{
    public function reader(Level $level, Normalizer $normalizer): MarkupReader
    {
        return new MarkdownReader(Level::All === $level, $normalizer, new ShoutDialect());
    }
}

$compiler = new DefaultCompiler(null, ['shout' => new ShoutReaders()]);
$compiler->compile('Hello!', 'shout');
```

## Dialectes de Markdown

`Phil\Markup\Reader\Markdown\Contract\MarkdownDialect` : ce qu'un dialecte ajoute au Markdown, ou lit autrement.
`Reader\Markdown\BaseDialect` lit chaque point comme le fait le Markdown simple ; un dialecte l'étend et redéfinit ce
qu'il change.

| Méthode | Contrat |
|---|---|
| `inlineRules(): list<InlineRule>` | Essayées après les règles intégrées des mêmes caractères, dans cet ordre |
| `blockRules(): list<BlockRule>` | Essayées sur une ligne après les règles intégrées, dans cet ordre ; une ligne qui en commence une termine le paragraphe au-dessus |
| `disables(): list<MarkdownSyntax>` | La syntaxe intégrée désactivée : `ColonBlocks`, les blocs `:::` |
| `frontMatter(list<string> $lines): ?array` | Les métadonnées avant le texte et les lignes après ; `null` pour le préambule YAML |
| `define(string $label, string $value, Context $context): bool` | `true` quand le dialecte lit lui-même une ligne `[label]: value`, qui ne produit alors aucune référence de lien |
| `definition(string $line): ?string` | Le texte d'une définition qu'une ligne commence sous un terme, sans sa marque ; `: ` est essayé en premier |
| `paragraph(string $text): array{string, array}` | Le texte d'un paragraphe sans sa marque, et les ajustements (*tunes*) que lui donne la marque |

`Reader\Markdown\Rule\InlineRule` : un type de jeton en ligne.

| Méthode | Contrat |
|---|---|
| `triggers(): string` | Les caractères par lesquels commence un jeton |
| `lex(Cursor $cursor, InlineParser $parser): Delimiter\|string\|null` | Le HTML du jeton, échappé par la règle (`InlineParser::escape()`, `$parser->parse()` pour du Markdown imbriqué), ou un `Delimiter` à apparier en emphase, consommé ; `null`, avec le curseur là où il était, quand aucun ne commence là |

`new Rule\DelimiterRule('^')` lit `^sup^` et `^^sub^^`, les exposants que la règle intégrée laisse de côté.

`Reader\Markdown\Contract\BlockRule` : un type de bloc.

| Méthode | Contrat |
|---|---|
| `read(list<string> $lines, int $at, Context $context): ?array` | Les blocs que commence la ligne à `$at`, produits par `Context::block()`, leur contenu en ligne sous forme d'`Inline`, et l'indice de la ligne qui les suit ; `null` quand la ligne n'en commence aucun |

Un dialecte est partagé par toutes les lectures et ne garde rien : ce qu'il apprend d'une source (des variables, par
exemple) va dans `Context::$extra`, qui vit aussi longtemps que la lecture. Ses règles lisent avec les outils du
lecteur : `Lexer\Cursor`, `Attributes` pour les accolades, `Curly` pour les blocs et segments `{{ }}` et
`Curly::scan()` pour un segment sur sa ligne, `FrontMatter::yaml()` pour le YAML. Une règle lit en un temps linéaire
dans ce qu'elle consomme, et s'imbrique jusqu'à une borne qui lui est propre.
