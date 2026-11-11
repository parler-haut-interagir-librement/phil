<!-- phil-dev source-sha256=e42a2a9fa91bacaece95f64bc5685516fa2ec62c1cf22b7567fa3ac0f9038c11 -->
# Ajouter un langage

`Highlighter::with()` ajoute un langage sous son nom et ses alias, ou remplace le langage qui les avait. Les noms sont
faits de lettres minuscules, de chiffres et de `_ + # . -`, 32 au plus ; toute autre chose lève `InvalidLanguage`.

## Un langage de programmation : une `Syntax`

La plupart des langages de programmation sont une `Syntax` lue par `Code` :

```php
use Phil\Highlight\Highlighter;
use Phil\Highlight\Language\Code;
use Phil\Highlight\Language\Quote;
use Phil\Highlight\Language\Syntax;

$lua = new Code(new Syntax(
    'lua',
    aliases: ['luau'],
    keywords: ['and', 'break', 'do', 'else', 'elseif', 'end', 'for', 'function', 'if', 'in', 'local', 'not', 'or', 'repeat', 'return', 'then', 'until', 'while'],
    constants: ['true', 'false', 'nil'],
    builtins: ['print', 'pairs', 'ipairs', 'require'],
    lineComments: ['--'],
    blockComments: ['--[[' => ']]'],
    quotes: [new Quote('[[', ']]', escape: '', multiline: true), new Quote('"'), new Quote("'")],
));

$highlighter = Highlighter::default()->with($lua);
```

| Champ | Ce qu'il dit |
|---|---|
| `name`, `aliases` | Les noms par lesquels on demande le langage, en minuscules |
| `keywords`, `types`, `constants`, `builtins` | Listes de mots ; un mot présent dans deux listes prend la première de mot-clé, constante, type, fonction intégrée |
| `lineComments` | Les marques qui ouvrent un commentaire jusqu'à la fin de la ligne |
| `blockComments` | Chaque marque d'ouverture, jusqu'à sa marque de fermeture |
| `quotes` | Des `Quote`, essayés dans l'ordre, les marques les plus longues d'abord : marques d'ouverture et de fermeture, caractère d'échappement (vide s'il n'y en a pas), s'il s'étend sur plusieurs lignes, s'il contient un seul caractère |
| `stringPrefixes` | Les lettres ou les signes qui peuvent précéder un guillemet : `rbfu` en Python |
| `variablePrefixes`, `specialVariables` | Les sigils (`$`), et les caractères qui forment une variable d'un seul caractère après l'un d'eux (`?` dans `$?`) |
| `annotation` | Le signe des décorateurs et des annotations : `@` |
| `metaMarks` | Les marques lues comme méta où qu'elles se trouvent, avant les commentaires : `#[`, `<?php` |
| `preprocessor` | Si un `#` qui ouvre une ligne ouvre une ligne du préprocesseur, prolongée par une barre oblique inverse |
| `caseInsensitive` | Si les mots sont reconnus dans n'importe quelle casse |
| `capitalizedTypes` | Si un nom qui commence par une majuscule est un type |
| `regex` | Si `/` ouvre une expression régulière là où une valeur est attendue |
| `propertyKeys` | Si un texte entre guillemets suivi de `:` est une clé |
| `shebang` | Si une première ligne qui commence par `#!` est méta |
| `commentAfterSpace` | Si un commentaire de ligne ne s'ouvre qu'au début d'un mot, comme `#` en shell |
| `symbols` | Si `:` devant un mot forme un symbole |
| `macros` | Si un mot suivi de `!` est un appel de macro |
| `callAfter` | Les signes après lesquels un mot est un appel : `|` devant un filtre Twig |
| `wordChars`, `wordSuffixes` | Les caractères qu'un mot peut contenir (`$` en JavaScript), et ceux qui peuvent le terminer (`?` et `!` en Ruby) |
| `operators`, `punctuation` | Les caractères de chacun |

## Tout autre langage : un `Language`

Un langage qui n'est pas un langage de programmation implémente `Language` : `name()`, `aliases()` et `tokenize()`,
qui retourne des `Token` dont les textes, joints, forment la source, sans jamais lever d'exception, quelle que soit la
source. Les `Ini` et `Diff` fournis sont de courts modèles. Un analyseur lexical qui mélange des langages prend les
autres dans son constructeur, comme `Dockerfile` prend le shell et `Twig` les langages de balisage et d'expression.

```php
use Phil\Highlight\Language;
use Phil\Highlight\Token;
use Phil\Highlight\TokenType;

final readonly class Csv implements Language
{
    public function name(): string { return 'csv'; }

    public function aliases(): array { return ['tsv']; }

    public function tokenize(string $code): array
    {
        $tokens = [];

        foreach (preg_split('/([,\t\n])/', $code, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $part) {
            if ('' !== $part) {
                $tokens[] = new Token($part, str_contains(",\t\n", $part) ? TokenType::Punctuation : TokenType::String);
            }
        }

        return $tokens;
    }
}
```

## Remplacer un langage fourni

Un langage ajouté sous un nom fourni le remplace, alias compris quand il les revendique : `with(new Code(new
Syntax('sql', aliases: ['postgresql'], …)))` lit le SQL et le PostgreSQL à sa façon, tandis que les autres alias de
SQL gardent l'analyseur lexical fourni.
