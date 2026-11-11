<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`, and the worker-mode page inside
        // FrankenPHP, which gives it `frankenphp_handle_request()`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*', 'tests/Worker/*'],
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : type d’argument trop large relevé par bleedingEdge.
            [
                'identifier' => 'argument.type',
                'paths' => [
                    'src/Reader/BBCode/Builder.php',
                    'src/Reader/Html/Walker.php',
                    'src/Reader/Wiki/Spans.php',
                ],
            ],
            // à corriger : complexité cognitive de classe au-dessus de 100, à découper.
            [
                'identifier' => 'complexity.classLike',
                'paths' => [
                    'src/Reader/Html/Walker.php',
                    'src/Reader/Markdown/BlockParser.php',
                    'src/Reader/MediaWiki/MediaWikiSyntax.php',
                    'src/Reader/Wiki/Containers.php',
                ],
            ],
            // à corriger : complexité cognitive au-dessus de 25, à découper : BlockParser::listing() (31),
            // Containers::split() (44).
            [
                'identifier' => 'complexity.functionLike',
                'paths' => [
                    'src/Reader/Markdown/BlockParser.php',
                    'src/Reader/Wiki/Containers.php',
                ],
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/HtmlPurifier.php',
                    'src/LevelAwareCompiler.php',
                    'src/MarkupCompiler.php',
                    'src/Reader/Markdown/Rule/InlineRule.php',
                    'src/Reader/MarkupReader.php',
                    'src/Reader/Wiki/Syntax.php',
                ],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The HTML reader reads no Markdown: whether its context keeps raw HTML changes nothing, the HTML it gives
            // the inline parser being written already.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$context = new Context\\(false\\);',
                ],
            ],
            // Limits declared as constants: mutating their declaration produces a mutant no reasonable test kills. The
            // character after a check box, in `Structures::listing()`, is a space the content of the element loses
            // anyway; a UTF-8 character fits in four bytes at most, in `CreoleSyntax::escape()`, and a fifth is never
            // read.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'private const int MAX_.*',
                    '.*substr\\(ltrim\\(\\$text\\), 3\\).*',
                    '\\$next = mb_substr\\(substr\\(\\$cursor\\->text, \\$cursor\\->position\\(\\) \\+ 1, 4\\), 0, 1\\);',
                ],
            ],
            // See `IncrementInteger`. The `|+` of a MediaWiki caption, in `MediaWikiSyntax::table()`: the attributes
            // read before its bar are the same with or without the `+`. The space after the mark of a DokuWiki item or
            // definition, which the content loses anyway: keeping it or not gives the same text. The same for the space
            // after `:` that starts a definition of Markdown, which `ltrim()` removes from the text.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'private const int MAX_.*',
                    '.*str_starts_with\\(\\$text, \': \'\\) \\? ltrim\\(substr\\(\\$text, 2\\)\\).*',
                    '\\$entries\\[\\] = \\[\\$level, substr\\(ltrim\\(\\$line\\), 2\\)\\];',
                    '\\$text = substr\\(ltrim\\(\\$line\\), 2\\);',
                    '\\$parts = explode\\(\'\\|\', substr\\(\\$trimmed, 2\\), 2\\);',
                ],
            ],
            // The memo of absent delimiters of `Spans::through()`: searching again for a delimiter known to be absent
            // returns the same `null`; only the time changes, which the linearity tests check.
            'LessThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$at = \\(\\$this\\->missing\\[\\$close\\] \\?\\? \\\\PHP_INT_MAX\\) <= \\$from \\? false : stripos\\(\\$cursor\\->text, \\$close, \\$from\\);',
                ],
            ],
            // The nested blocks of a UJO block, in `Resolver::value()`: a list whose first element is a block only
            // holds blocks, always as a list; the filter and `array_values()` only tell PHPStan, which reads the data
            // of a block as `mixed`.
            'UnwrapArrayFilter' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$this->blocks\\(array_values\\(array_filter\\(\\$value, .*',
                ],
            ],
            // The same `Resolver::value()`, and the items of a list read by `BlockParser::items()`, always written as a
            // list by the reader itself, also when `Walker::lines()` reads the nested ones.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$this->blocks\\(array_values\\(array_filter\\(\\$value, .*',
                    'return \\\\is_array\\(\\$items\\) \\? array_values\\(\\$items\\) : \\[\\];',
                    '\\$nested = \\\\is_array\\(\\$item\\[\'items\'\\] \\?\\? null\\) \\? array_values\\(\\$item\\[\'items\'\\]\\) : \\[\\];',
                ],
            ],
            // The mark of an image in the text `InlineParser` reads only serves to find the image in the registry of
            // the reading, and each is unique: the form of the key shows nowhere. The characters that stop a run of
            // text in `Spans` form a set: their order in the string changes nothing.
            'Concat' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$key = \'i\'\\.\\\\count\\(\\$this->context->images\\);',
                    '\\$this->stops = \'hHwW\'\\.\\$this->dialect->opener\\(\\)\\[0\\]\\.\\$syntax->triggers\\(\\);',
                ],
            ],
            // See `Concat`. The link an unknown frame leaves, in `Walker::frame()`: the normalizer closes an element
            // left open at the end of an inline content, so leaving out `</a>` gives the same text.
            'ConcatOperandRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$key = \'i\'\\.\\\\count\\(\\$this->context->images\\);',
                    '.*Autolink::escape\\(\'\' === \\$title \\? \\$source : \\$title\\)\\.\'</a>\'.*',
                ],
            ],
            // The floor of the search for an opener, in the matching of emphases of `InlineParser`: starting from 0
            // rather than from the floor kept repeats a search already vain, and only changes the time. The same memo
            // of `Spans::through()`, and the order in which `Spans` tries a tag or an address, then the syntax of the
            // wiki: what the syntax reads at `<` (an email address, a Creole placeholder) never starts like a tag, and
            // no syntax reads `h` or `w`, where an address starts.
            'Coalesce' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$found = \\$this->opener\\(\\$nodes, \\$previous, \\$close, \\$closer, \\$bottoms\\[\\$close->character\\]\\[\\$close->length\\] \\?\\? 0\\);',
                    '\\$at = \\(\\$this\\->missing\\[\\$close\\] \\?\\? \\\\PHP_INT_MAX\\) <= \\$from \\? false : stripos\\(\\$cursor\\->text, \\$close, \\$from\\);',
                    '\\? \\$this->tag\\(\\$cursor\\) \\?\\? \\$this->syntax->inline\\(\\$cursor, \\$this\\)',
                    ': \\$this->address\\(\\$cursor\\) \\?\\? \\$this->syntax->inline\\(\\$cursor, \\$this\\);',
                ],
            ],
            // The values of the properties of a stat block, in `Statblock::properties()`: the definitions of a
            // definition list are always inline contents; `Inline::of()` only tells PHPStan.
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\'value\' => Inline::join\\(array_map\\(Inline::of\\(\\.\\.\\.\\), self::list\\(.*',
                ],
            ],
            // The memo of unclosed links and images of a row, in `Structures::cells()`: a wrong memo searches again for
            // an absent delimiter, or cuts a cell that would have been kept whole; searching again gives the same text,
            // only the time changes, which the linearity tests check.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$missing = null === \\$inner \\&\\& null !== \\$close \\? \\[\\.\\.\\.\\$missing, \\$close\\] : \\$missing;',
                ],
            ],
            // The tag of a container put on its own line by `Containers::split()`: keeping the whole line changes
            // nothing, the container ignoring what follows its tag; only the memory sees it, which the linearity tests
            // check.
            'Minus' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$split\\[\\] = substr\\(\\$line, 0, \\\\strlen\\(\\$line\\) \\- \\\\strlen\\(\\$found\\[1\\]\\)\\);',
                ],
            ],
            // The memo of `Structures::cells()`: see `ArrayItemRemoval`.
            'LogicalAndAllSubExprNegation' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$missing = null === \\$inner \\&\\& null !== \\$close \\? \\[\\.\\.\\.\\$missing, \\$close\\] : \\$missing;',
                ],
            ],
            // The same tag of `Containers::split()`: see `Minus`.
            'UnwrapSubstr' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$split\\[\\] = substr\\(\\$line, 0, \\\\strlen\\(\\$line\\) \\- \\\\strlen\\(\\$found\\[1\\]\\)\\);',
                ],
            ],
            // The memo of `Structures::cells()`: see `ArrayItemRemoval`.
            'NotIdentical' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$missing = null === \\$inner \\&\\& null !== \\$close \\? \\[\\.\\.\\.\\$missing, \\$close\\] : \\$missing;',
                ],
            ],
            // The memo of `Structures::cells()`: see `ArrayItemRemoval`.
            'SpreadRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$missing = null === \\$inner \\&\\& null !== \\$close \\? \\[\\.\\.\\.\\$missing, \\$close\\] : \\$missing;',
                ],
            ],
            // The BBCode reader closes the element or the cell left open above their parent: two open tags never hold
            // the same position of the stack, so `>=` only differs from `>` at an equality that does not happen.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(null !== \\$at && \\$at > \\$within\\) \\{',
                ],
            ],
            // An HTML document parsed by PHP always has its root element, and every element the walker tunes its
            // parent: `HtmlReader::metadata()` and `Walker::language()` only tell PHPStan.
            'NullSafeMethodCall' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$language = \\$document->documentElement\\?->getAttribute\\(\'lang\'\\) \\?\\? \'\';',
                    '\\$around = \\$element->parentElement\\?->closest\\(\'\\[lang\\]\'\\)\\?->getAttribute\\(\'lang\'\\);',
                ],
            ],
            // The caption of a wiki table, in `Structures::table()`: the inline reader trims the text it reads.
            'UnwrapTrim' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\'\' === trim\\(\\$caption\\) \\? null : \\$engine->inline\\(trim\\(\\$caption\\)\\).*',
                ],
            ],
            // The note a comment adds to a block, in `Walker::noted()`: only the walker writes the `note` tune, always
            // as an array holding a text, so either check alone decides.
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$earlier = \\\\is_array\\(\\$note\\) && \\\\is_string\\(\\$note\\[\'text\'\\] \\?\\? null\\) .*',
                ],
            ],
            // The name of a `data-*` attribute, in `Walker::tuned()`: an attribute name never ends with a new line, so
            // `D` has nothing to refuse.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => [
                    '.*preg_match\\(\'/\\^\\[a-z\\]\\[a-z0-9-\\]\\{0,63\\}\\$/D\', substr\\(\\$name, 5\\)\\).*',
                ],
            ],
        ],
    ],
    'deptrac' => [
        // The formats, levels, ports and exceptions are the core, which a domain may use; the adapters, the lexer, the
        // readers, the facade and the embeddable see phil/ujo, phil/svg-sanitizer — which purifies the drawings the
        // HTML reader extracts — and the rest of the world: the DOM extension, symfony/html-sanitizer, symfony/yaml
        // and Doctrine DBAL.
        'layers' => [
            'PhilMarkup' => '^Phil\\\\Markup\\\\(?!Adapter\\\\|Lexer\\\\|Reader\\\\|MarkedUpText$|Markup$).*',
            'PhilMarkupAdapter' => '^Phil\\\\Markup\\\\(Adapter\\\\.*|Lexer\\\\.*|Reader\\\\.*|MarkedUpText|Markup)$',
        ],
        'ruleset' => [
            'PhilMarkup' => ['DefaultAllowed'],
            'PhilMarkupAdapter' => ['PhilMarkup', 'DefaultAllowed', 'PhilUjo', 'PhilSvgSanitizer', 'Forbidden'],
        ],
    ],
    'fuzz' => [
        // Each parser on inputs nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'markup-bbcode' => 'tests/Fuzz/markup-bbcode.php',
            'markup-creole' => 'tests/Fuzz/markup-creole.php',
            'markup-dokuwiki' => 'tests/Fuzz/markup-dokuwiki.php',
            'markup-html' => 'tests/Fuzz/markup-html.php',
            'markup-markdown' => 'tests/Fuzz/markup-markdown.php',
            'markup-mediawiki' => 'tests/Fuzz/markup-mediawiki.php',
        ],
    ],
    // The page `composer bench:worker phil/markup` measures, served by FrankenPHP in worker mode.
    'bench' => [
        'worker' => ['script' => 'tests/Worker/worker.php', 'requests' => 1000, 'warmup' => 50],
    ],
];
