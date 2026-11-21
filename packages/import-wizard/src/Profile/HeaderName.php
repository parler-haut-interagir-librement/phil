<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Profile;

/**
 * Internal: a header folded for comparison — lower case, the accents of Latin letters removed, every character that is
 * not a letter or a digit dropped — so that `Prénom`, `prenom` and `PRE-NOM` read alike.
 */
final class HeaderName
{
    private const array FOLD = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'œ' => 'oe',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'ß' => 'ss',
    ];

    private function __construct()
    {
    }

    public static function fold(string $header): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', strtr(mb_strtolower($header), self::FOLD));
    }
}
