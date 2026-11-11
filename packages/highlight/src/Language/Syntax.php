<?php

declare(strict_types=1);

namespace Phil\Highlight\Language;

/**
 * What a programming language looks like to its tokenizer, `Code`: its words, comments, quotes and sigils. Most
 * languages are one `Syntax`; `Definitions` has those the library ships, and an application writes its own the same
 * way.
 */
final readonly class Syntax
{
    /**
     * @param list<string>          $aliases           other names, in lower case
     * @param list<string>          $keywords          reserved words
     * @param list<string>          $types             type names
     * @param list<string>          $constants         literal words
     * @param list<string>          $builtins          functions the language provides
     * @param list<string>          $lineComments      the marks that open a comment to the end of the line
     * @param array<string, string> $blockComments     each opening mark, to its closing one
     * @param list<Quote>           $quotes            tried in order: the longer marks first
     * @param string                $stringPrefixes    letters or signs that may stand before a quote, as `r` and `f` in Python
     * @param string                $variablePrefixes  the sigils of variables, as `$`
     * @param string                $specialVariables  the characters that make a variable of one character after a sigil, as `$?` in shell
     * @param string                $annotation        the sign before a decorator or annotation, as `@`
     * @param list<string>          $metaMarks         marks read as meta wherever they stand, before comments: `#[`, `<?php`
     * @param bool                  $preprocessor      whether `#` opening a line opens a preprocessor line
     * @param bool                  $caseInsensitive   whether words are matched in any case
     * @param bool                  $capitalizedTypes  whether a word that starts with a capital is a type
     * @param bool                  $regex             whether `/` opens a regular expression where a value is expected
     * @param bool                  $propertyKeys      whether a quoted text followed by `:` is a key
     * @param bool                  $shebang           whether a first line that opens with `#!` is meta
     * @param bool                  $commentAfterSpace whether a line comment opens only at the start of a word
     * @param bool                  $symbols           whether `:` before a word makes a symbol, as in Ruby
     * @param bool                  $macros            whether a word followed by `!` is a macro call, as in Rust
     * @param string                $callAfter         the signs after which a word is a call, as `|` before a Twig filter
     * @param string                $wordChars         characters a word may hold besides letters, digits and `_`
     * @param string                $wordSuffixes      characters that may end a word, as `?` and `!` in Ruby, unless `=` follows
     * @param string                $operators         the characters of operators
     * @param string                $punctuation       the characters of punctuation
     */
    public function __construct(
        public string $name,
        public array $aliases = [],
        public array $keywords = [],
        public array $types = [],
        public array $constants = [],
        public array $builtins = [],
        public array $lineComments = [],
        public array $blockComments = [],
        public array $quotes = [],
        public string $stringPrefixes = '',
        public string $variablePrefixes = '',
        public string $specialVariables = '',
        public string $annotation = '',
        public array $metaMarks = [],
        public bool $preprocessor = false,
        public bool $caseInsensitive = false,
        public bool $capitalizedTypes = false,
        public bool $regex = false,
        public bool $propertyKeys = false,
        public bool $shebang = false,
        public bool $commentAfterSpace = false,
        public bool $symbols = false,
        public bool $macros = false,
        public string $callAfter = '',
        public string $wordChars = '',
        public string $wordSuffixes = '',
        public string $operators = '+-*/%=<>!&|^~?:',
        public string $punctuation = '()[]{};,.',
    ) {
    }
}
