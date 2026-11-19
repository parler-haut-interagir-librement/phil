<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Pdf;

use Phil\DocumentImport\Internal\Codepage;

/**
 * The text of the glyph names a font's `Differences` give: `uniXXXX` and `uXXXX[XX]` by their code, single letters
 * as themselves, and the names of the Latin-1, Latin Extended and punctuation glyphs of the standard encodings.
 *
 * @internal
 */
final class GlyphNames
{
    private const array NAMES = [
        'space' => ' ', 'exclam' => '!', 'quotedbl' => '"', 'numbersign' => '#', 'dollar' => '$', 'percent' => '%',
        'ampersand' => '&', 'quotesingle' => "'", 'quoteright' => '’', 'parenleft' => '(', 'parenright' => ')',
        'asterisk' => '*', 'plus' => '+', 'comma' => ',', 'hyphen' => '-', 'period' => '.', 'slash' => '/',
        'zero' => '0', 'one' => '1', 'two' => '2', 'three' => '3', 'four' => '4', 'five' => '5', 'six' => '6',
        'seven' => '7', 'eight' => '8', 'nine' => '9', 'colon' => ':', 'semicolon' => ';', 'less' => '<',
        'equal' => '=', 'greater' => '>', 'question' => '?', 'at' => '@', 'bracketleft' => '[', 'backslash' => '\\',
        'bracketright' => ']', 'asciicircum' => '^', 'underscore' => '_', 'grave' => '`', 'quoteleft' => '‘',
        'braceleft' => '{', 'bar' => '|', 'braceright' => '}', 'asciitilde' => '~', 'exclamdown' => '¡',
        'cent' => '¢', 'sterling' => '£', 'currency' => '¤', 'yen' => '¥', 'brokenbar' => '¦', 'section' => '§',
        'dieresis' => '¨', 'copyright' => '©', 'ordfeminine' => 'ª', 'guillemotleft' => '«', 'logicalnot' => '¬',
        'registered' => '®', 'macron' => '¯', 'degree' => '°', 'plusminus' => '±', 'acute' => '´', 'mu' => 'µ',
        'paragraph' => '¶', 'periodcentered' => '·', 'cedilla' => '¸', 'ordmasculine' => 'º', 'guillemotright' => '»',
        'onequarter' => '¼', 'onehalf' => '½', 'threequarters' => '¾', 'questiondown' => '¿', 'multiply' => '×',
        'divide' => '÷', 'germandbls' => 'ß', 'AE' => 'Æ', 'ae' => 'æ', 'OE' => 'Œ', 'oe' => 'œ', 'Oslash' => 'Ø',
        'oslash' => 'ø', 'Eth' => 'Ð', 'eth' => 'ð', 'Thorn' => 'Þ', 'thorn' => 'þ', 'dotlessi' => 'ı',
        'Lslash' => 'Ł', 'lslash' => 'ł', 'endash' => '–', 'emdash' => '—', 'quotedblleft' => '“',
        'quotedblright' => '”', 'quotesinglbase' => '‚', 'quotedblbase' => '„', 'dagger' => '†', 'daggerdbl' => '‡',
        'bullet' => '•', 'ellipsis' => '…', 'perthousand' => '‰', 'guilsinglleft' => '‹', 'guilsinglright' => '›',
        'trademark' => '™', 'fi' => 'fi', 'fl' => 'fl', 'ff' => 'ff', 'ffi' => 'ffi', 'ffl' => 'ffl', 'florin' => 'ƒ',
        'fraction' => '⁄', 'circumflex' => 'ˆ', 'tilde' => '˜', 'breve' => '˘', 'dotaccent' => '˙', 'ring' => '˚',
        'hungarumlaut' => '˝', 'ogonek' => '˛', 'caron' => 'ˇ', 'Euro' => '€', 'minus' => '−', 'nbspace' => "\u{A0}",
        'sfthyphen' => "\u{AD}", 'Scaron' => 'Š', 'scaron' => 'š', 'Zcaron' => 'Ž', 'zcaron' => 'ž', 'Ydieresis' => 'Ÿ',
        'Agrave' => 'À', 'Aacute' => 'Á', 'Acircumflex' => 'Â', 'Atilde' => 'Ã', 'Adieresis' => 'Ä', 'Aring' => 'Å',
        'Ccedilla' => 'Ç', 'Egrave' => 'È', 'Eacute' => 'É', 'Ecircumflex' => 'Ê', 'Edieresis' => 'Ë',
        'Igrave' => 'Ì', 'Iacute' => 'Í', 'Icircumflex' => 'Î', 'Idieresis' => 'Ï', 'Ntilde' => 'Ñ', 'Ograve' => 'Ò',
        'Oacute' => 'Ó', 'Ocircumflex' => 'Ô', 'Otilde' => 'Õ', 'Odieresis' => 'Ö', 'Ugrave' => 'Ù', 'Uacute' => 'Ú',
        'Ucircumflex' => 'Û', 'Udieresis' => 'Ü', 'Yacute' => 'Ý', 'agrave' => 'à', 'aacute' => 'á',
        'acircumflex' => 'â', 'atilde' => 'ã', 'adieresis' => 'ä', 'aring' => 'å', 'ccedilla' => 'ç', 'egrave' => 'è',
        'eacute' => 'é', 'ecircumflex' => 'ê', 'edieresis' => 'ë', 'igrave' => 'ì', 'iacute' => 'í',
        'icircumflex' => 'î', 'idieresis' => 'ï', 'ntilde' => 'ñ', 'ograve' => 'ò', 'oacute' => 'ó',
        'ocircumflex' => 'ô', 'otilde' => 'õ', 'odieresis' => 'ö', 'ugrave' => 'ù', 'uacute' => 'ú',
        'ucircumflex' => 'û', 'udieresis' => 'ü', 'yacute' => 'ý', 'ydieresis' => 'ÿ', 'Cacute' => 'Ć',
        'cacute' => 'ć', 'Ccaron' => 'Č', 'ccaron' => 'č', 'Dcaron' => 'Ď', 'dcaron' => 'ď', 'Ecaron' => 'Ě',
        'ecaron' => 'ě', 'Nacute' => 'Ń', 'nacute' => 'ń', 'Ncaron' => 'Ň', 'ncaron' => 'ň', 'Rcaron' => 'Ř',
        'rcaron' => 'ř', 'Sacute' => 'Ś', 'sacute' => 'ś', 'Scedilla' => 'Ş', 'scedilla' => 'ş', 'Tcaron' => 'Ť',
        'tcaron' => 'ť', 'Uring' => 'Ů', 'uring' => 'ů', 'Zacute' => 'Ź', 'zacute' => 'ź',
    ];

    public static function text(string $name): ?string
    {
        $name = explode('.', $name)[0];

        if (isset(self::NAMES[$name])) {
            return self::NAMES[$name];
        }

        if (1 === preg_match('/^[A-Za-z]$/D', $name)) {
            return $name;
        }

        if (1 === preg_match('/^uni((?:[0-9A-F]{4})+)$/D', $name, $match)) {
            return self::codes(str_split($match[1], 4));
        }

        if (1 === preg_match('/^u([0-9A-F]{4,6})$/D', $name, $match)) {
            return self::codes([$match[1]]);
        }

        return null;
    }

    /**
     * @param list<string> $codes
     */
    private static function codes(array $codes): string
    {
        $text = '';

        foreach ($codes as $code) {
            $text .= Codepage::character((int) hexdec($code));
        }

        return $text;
    }
}
