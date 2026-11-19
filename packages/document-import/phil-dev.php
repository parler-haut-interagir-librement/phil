<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'words' => ['CCITT', 'CMYK', 'Flate', 'JBIG', 'Pandoc', 'strikethrough']],
    'fuzz' => [
        // Each reader on files nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'docx' => 'tests/Fuzz/docx.php',
            'odt' => 'tests/Fuzz/odt.php',
            'fodt' => 'tests/Fuzz/fodt.php',
            'rtf' => 'tests/Fuzz/rtf.php',
            'pdf' => 'tests/Fuzz/pdf.php',
        ],
    ],
    'infection' => [
        // Each mutant left alive by name is an equivalent: no input can tell it from the code. The reason is given
        // by file and method.
        'mutators' => [
            'UnwrapFinally' => [
                // PdfFilters::inflate() and partialInflate(): the handler that hides the warnings of zlib, left in
                // place, hides nothing a caller sees.
                'ignoreSourceCodeByRegex' => ['.*\$inflated = zlib_decode\(.*', '.*\$chunks = str_split\(\$bytes, 1024\);.*'],
            ],
            'TrueValue' => [
                // PdfFilters: a warning of zlib let through goes to PHP's own handler, never to the application's.
                'ignoreSourceCodeByRegex' => [
                    '.*set_error_handler\(static fn \(\): bool => true\);.*',
                    // RtfReader::closeNote(): a note is closed after its context was saved, so the stack is never empty
                    // there and the context made instead is never used.
                    '.*array_pop\(\$this->contexts\) \?\? new RtfContext\(true\);.*',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    // PdfParser::maybeReference(): what is left of the reference when only its generation is skipped is
                    // a number and an `R`, which the dictionary reads as a key and its value, the pairs staying apart.
                    '.*\$this->at \+= \\\\strlen\(\$match\[0\]\);.*',
                    // PdfDocument::table(): the subsection headers are found by a pattern; one byte more or less at
                    // either end of the table is "f" of "xref", an end of line or a space before "trailer".
                    '.*substr\(\$this->bytes, \$offset \+ 4, \$end - \$offset - 4\).*',
                    // PdfDocument::stream(): a row without offset field gives offset 0 of a used object, or object
                    // stream 0, neither of which is an object.
                    '.*\$value = \$this->field\(\$data, \$at \+ \$typeWidth, \$valueWidth, 0\);.*',
                    // PdfFont: one width more than the codes of a font can ask for is never read.
                    '.*\\\\array_slice\(\$(widths, 0, 256|next, 0, 65_536|widths, 0, 65_536)\).*',
                    '.*min\(\$last, \$first \+ 65_535\).*',
                    // RtfReader::format(): no code page 1253 is known, and an unknown one is read as 1252, the default.
                    '.*\'ansicpg\' === \$word => \$this->codepage = .*',
                    // PdfFilters::inflate(): a stream zlib_decode() refuses for its length is inflated again by
                    // partialInflate(), which gives the same bytes. partialInflate(): chunks of another size give the
                    // same bytes, and a stream one byte past the limit is refused however much more is kept.
                    '.*zlib_decode\(\$bytes, \$this->entryBytes \+ 1\);.*',
                    '.*str_split\(\$bytes, 1024\);.*',
                    '.*return substr\(\$output, 0, \$limit \+ 1\);.*',
                    // PdfLayout::html(): a marker is only ever at the start, which a second replacement cannot reach;
                    // interrupt(): a list is told apart by its number, whichever step numbers them.
                    '.*preg_replace\(\[self::BULLET, self::NUMBER\], \'\', ltrim\(\$text\), 1\).*',
                    '.*return \$this->list \+ 1;.*',
                    // PdfReader::withoutRunningLines(): every key looked up was counted first.
                    '.*\(\$counts\[\$key\] \?\? 0\) >= \$threshold.*',
                    // PdfFilters::predicted(): the row is bounded by the data before it reaches 2 to the 19th
                    // columns.
                    '.*\$columns = max\(1, min\(1 << 20,.*',
                ],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*substr\(\$this->bytes, \$offset \+ 4, \$end - \$offset - 4\).*',
                    '.*\$value = \$this->field\(\$data, \$at \+ \$typeWidth, \$valueWidth, 0\);.*',
                    '.*zlib_decode\(\$bytes, \$this->entryBytes \+ 1\);.*',
                    '.*str_split\(\$bytes, 1024\);.*',
                    '.*\$columns = max\(1, min\(1 << 20,.*',
                    // PdfFilters::predicted(): a default of no colour is raised to one by max().
                    '.*\$colors = max\(1, min\(32,.*',
                    // PdfFilters::png(): before the first pixel the left byte is 0, and Paeth then picks the byte
                    // above whether the corner is 0 or -1.
                    '.*\$corner = \$index >= \$pixel \? .*',
                    // PdfFilters::ascii85(): the "~" of "<~" left is no digit, and is skipped anyway.
                    '.*str_starts_with\(\$data, \'<~\'\) \? substr\(\$data, 2\).*',
                    '.*\(\$counts\[\$key\] \?\? 0\) >= \$threshold.*',
                    // PdfFilters::lzw(): the bits of a negative first buffer are shifted out before a code reads them.
                    '.*\$buffer = 0;.*',
                    // PdfCMap::__construct(): the whole match of a block holds its keywords besides the block, and the
                    // keywords hold no pair of codes in angle brackets, so each pattern finds the same pairs.
                    '.*foreach \(\$blocks\[1\] as \$block\) \{.*',
                    // PdfParser::maybeReference(): a number with spaces and an `R` after it is read as the number by
                    // the cast, as the generation alone is.
                    '.*new PdfReference\(\$number, \(int\) \$match\[1\]\).*',
                    // RtfReader::read(): a negative number of characters to skip skips none, as 0 does. fallback():
                    // `\uc-1` is read as no fallback, as `\uc0` is.
                    '.*\$this->toSkip = RtfLexer::WORD === \$type && \'u\' === \$value \? .*',
                    '.*\$this->state->fallback = max\(0, \$count\);.*',
                    // RtfLexer::control(): `\bin` with no length has none, whatever negative number stands for it; the
                    // end of a line after a backslash is skipped again by the loop that reads the text, whether the
                    // position stays on it or moves past it.
                    '.*\$length = \$parameter \?\? 0;.*',
                    '.*"\\\\r", "\\\\n" => \[\[self::WORD, \'par\', null\], \$at \+ 1\],.*',
                ],
            ],
            'Minus' => [
                'ignoreSourceCodeByRegex' => ['.*substr\(\$this->bytes, \$offset \+ 4, \$end - \$offset - 4\).*'],
            ],
            'Plus' => [
                // PdfFilters::inflate() and PdfLayout::interrupt(): see IncrementInteger.
                'ignoreSourceCodeByRegex' => ['.*zlib_decode\(\$bytes, \$this->entryBytes \+ 1\);.*', '.*return \$this->list \+ 1;.*'],
            ],
            'PregMatchRemoveFlags' => [
                // PdfLayout::flush(): the punctuation looked for is ASCII, which no byte of another character is.
                'ignoreSourceCodeByRegex' => [
                    '.*1 !== preg_match\(\'/\[\.,;:\]\\\\s\*\$/u\', \$first->text\(\)\).*',
                    // PdfReader::link(): the marks of the text keep only a web, mail or telephone address whatever
                    // the reader lets through; the pattern only spares them the rest.
                    '.*\$web = \\\\is_string\(\$address\) && 1 === preg_match\(.*',
                ],
            ],
            'PregMatchRemoveCaret' => [
                'ignoreSourceCodeByRegex' => ['.*\$web = \\\\is_string\(\$address\) && 1 === preg_match\(.*'],
            ],
            'PregMatchRemoveDollar' => [
                'ignoreSourceCodeByRegex' => ['.*\$web = \\\\is_string\(\$address\) && 1 === preg_match\(.*'],
            ],
            'RoundingFamily' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\$cx = \(int\) round\(.*',
                    // PdfReader::withoutRunningLines(): half a count of pages is whole or a half, which round() and
                    // ceil() both raise.
                    '.*\$threshold = max\(2, \(int\) ceil\(\\\\count\(\$pages\) / 2\)\);.*',
                ],
            ],
            'UnwrapSubstr' => [
                // PdfFilters::partialInflate(): past the limit the stream is refused, however much more is kept.
                'ignoreSourceCodeByRegex' => ['.*return substr\(\$output, 0, \$limit \+ 1\);.*'],
            ],
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => [
                    // PdfDocument::stream(): rows of no byte all say "object 0 at offset 0", which parses as nothing,
                    // and the section's catalog is missing: the file is scanned again from nothing.
                    '.*\$entry < \$count && \$size > 0 &&.*',
                    // RtfReader::bytes(): with no character left to skip, skipping 0 of them changes nothing.
                    '.*if \(\$this->toSkip > 0\) \{.*',
                ],
            ],
            'LessThanOrEqualTo' => [
                // PdfFilters::partialInflate(): stopping at the limit or one byte past it gives the same answer.
                'ignoreSourceCodeByRegex' => ['.*\\\\strlen\(\$output\) <= \$limit; \+\+\$index\) \{.*'],
            ],
            'LessThan' => [
                'ignoreSourceCodeByRegex' => [
                    // PdfFilters::runLength(): 128 ends the data before this test.
                    '.*if \(\$count < 128\) \{.*',
                    // PdfFilters::lzw(): a 4 097th entry has no code of twelve bits to reach it.
                    '.*\\\\count\(\$table\) < 4096\) \{.*',
                    // PdfParser::skipSpace() and literal(): at the end of the bytes nothing is skipped, and a string with no
                    // closing parenthesis ends there with the text it holds.
                    '.*while \(\$this->at < \$this->length\) \{.*',
                ],
            ],
            'CastFloat' => [
                // An integer given where a float is expected — returned by a function of type float, or assigned to
                // a property of that type — is widened to that float.
                'ignoreSourceCodeByRegex' => ['.*\? \(float\) \$(value|size) :.*'],
            ],
            'AssignCoalesce' => [
                // PdfContent::setFont(): the fonts of a document are read once; read again, a font is the same.
                'ignoreSourceCodeByRegex' => ['.*\$this->font = \$this->fonts\[\$key\] \?\?= .*'],
            ],
            'UnwrapArraySlice' => [
                // PdfFont: the slices bound the work a font asks for; a simple font has no code above 255 and a
                // composite font none above 65 535, so a width read past them is never asked for.
                'ignoreSourceCodeByRegex' => [
                    '.*foreach \(\\\\array_slice\(\$(widths, 0, 256|next, 0, 65_536)\).*',
                    '.*\$items = \\\\is_array\(\$widths\) \? \\\\array_slice\(\$widths, 0, 65_536\).*',
                ],
            ],
            // OdtWriter::paragraphStyle(): the key only tells apart the combinations that occur, and a page break is
            // always of the body style without alignment: joined otherwise, or with its parts swapped, the keys stay
            // apart.
            'Concat' => [
                'ignoreSourceCodeByRegex' => ['.*\$key = \$parent\.\'\|\'\.\$align.*'],
            ],
            'ConcatOperandRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*\$key = \$parent\.\'\|\'\.\$align.*'],
            ],
            'Ternary' => [
                'ignoreSourceCodeByRegex' => ['.*\$key = \$parent\.\'\|\'\.\$align.*'],
            ],
            'CastString' => [
                // preg_replace() answers null only on an error of its pattern, and the subjects are valid UTF-8 —
                // text of the DOM, or scrubbed first; Blocks::image(): the media type of bytes fetched is known.
                'ignoreSourceCodeByRegex' => [
                    '.*\(string\) preg_replace.*',
                    '.*\(string\) \$type\).*',
                    // RtfReader::picture(): sodium_hex2bin() answers a string for an even number of hexadecimal digits,
                    // which is what is given to it.
                    '.*\$bytes = \(string\) \(\$capture->properties\[\'binary\'\] \?\? sodium_hex2bin.*',
                ],
            ],
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    // PdfDocument::table(): the first number of a subsection is a string of digits, which PHP adds as
                    // the integer it writes.
                    '.*\$this->register\(\(int\) \$start \+ \$index.*',
                    // DocxWriter::picture(): the width in EMU is whole — 9 525 a pixel, or the six inches of the
                    // widest —, and a rounded float is written as the integer it holds.
                    '.*\$c[xy] = \(int\) round\(.*',
                    // PdfLayout::level(): only a line of a heading size is asked for its level, which array_search()
                    // then finds as an integer.
                    '.*return 1 \+ \(int\) array_search\(.*',
                    // PdfFont::glyphs(): a code of no more than four bytes is an integer hexdec() gives as one.
                    '.*\(int\) hexdec\(sodium_bin2hex.*',
                    // PdfCMap and PdfParser::name(): the codes of a CMap have four bytes at most and the escape of a
                    // name two digits, which hexdec() gives as integers; a float it gives beyond 63 bits compares and
                    // indexes as the integer would.
                    '.*\(int\) hexdec\(\$(low|high|hex|match\[1\])\).*',
                    // RtfStyles::define(): the numbers of a style are integers, as captureWord() reads them.
                    '.*\? \(int\) \$properties\[\'(sbasedon|outlinelevel)\'\] : null,.*',
                    // Codepage::decode(): the code pages are numbers in strings, which PHP reads as integer keys.
                    '.*self::KNOWN\[\(int\) \$codepage\].*',
                ],
            ],
            'MethodCallRemoval' => [
                // DocumentExporter::zip(): libzip stores an entry that deflating does not shrink, as the short
                // mimetype, and deflates the others; the call makes it explicit for every libzip.
                'ignoreSourceCodeByRegex' => ['.*\$zip->setCompressionName\(.*'],
            ],
            'LogicalAndSingleSubExprNegation' => [
                // Zip::read() and write(): where this PHP has no ZipArchive::openString(), the test of the memory
                // is false whether the request for it is negated or not; where it has, the same tests tell the two
                // apart, and infection:next runs them there.
                'ignoreSourceCodeByRegex' => ['.*if \(\$inMemory && self::inMemory\(\)\) \{.*'],
            ],
            'LogicalAnd' => [
                // RtfReader::captureWord(): the properties kept for a word of the list outside a style are never read,
                // so keeping them or not changes nothing.
                'ignoreSourceCodeByRegex' => ['.*\'style\' === \$capture->name && \\\\in_array\(\$word, \[\'s\', \'cs\'.*'],
            ],
            'Throw_' => [
                // Archive::xml(): with the flags given no warning of the parser reaches PHP, so the handler that
                // would turn one into the exception of the library is never called.
                'ignoreSourceCodeByRegex' => ['.*set_error_handler\(static fn \(\): never => throw \$malformed\);.*'],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : variable de boucle jamais lue.
            [
                'identifier' => 'foreach.unusedValue',
                'path' => 'src/Internal/Odt/OdtReader.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'path' => 'src/MediaStore.php',
                'count' => 1,
            ],
        ],
    ],
    'deptrac' => [
        // The importers read documents through the DOM extension of PHP, which is outside the default, write UJO
        // through the readers of phil/markup, and sanitize SVG images with phil/svg-sanitizer.
        'ruleset' => [
            'PhilDocumentImport' => ['DefaultAllowed', 'PhilMarkup', 'PhilMarkupAdapter', 'PhilSvgSanitizer', 'PhilUjo', 'Forbidden'],
        ],
    ],
];
