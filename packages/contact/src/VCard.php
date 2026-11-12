<?php

declare(strict_types=1);

namespace Phil\Contact;

use Phil\InstantMessenger\Handle;
use Phil\Phone\PhoneFormat;
use Phil\Url\TypedUrl;

/**
 * A card as vCard 4.0 (RFC 6350): what address books import from a `.vcf` file.
 *
 * A structured name is `N`, with the second family name and the generation of RFC 9554 when it has them, its
 * culture as `LANGUAGE` and, when its culture sorts it otherwise than it is written, `SORT-AS`. Phone lines are `tel:`
 * addresses typed by their role; handles with a link are `IMPP`. Lines longer than 75 octets are folded, never inside
 * a character; lines end with CRLF.
 */
final class VCard
{
    public const string MEDIA_TYPE = 'text/vcard';

    public static function of(ContactCard $card): string
    {
        $lines = [
            'BEGIN:VCARD',
            'VERSION:4.0',
            'KIND:'.($card->isPerson() || null === $card->organization ? 'individual' : 'org'),
            'FN:'.self::text($card->displayName() ?? ''),
        ];

        if ($card->structuredName instanceof PersonName) {
            $lines[] = self::name($card->structuredName);
        }

        if (null !== $card->organization) {
            $lines[] = 'ORG:'.self::text($card->organization);
        }

        if (null !== $card->title) {
            $lines[] = 'TITLE:'.self::text($card->title);
        }

        foreach ($card->emails as $email) {
            $lines[] = 'EMAIL:'.self::text($email->value);
        }

        foreach ($card->phones as $line) {
            $lines[] = 'TEL;VALUE=uri;TYPE='.$line->role->vCardType().':'.$line->number->format(PhoneFormat::Rfc3966);
        }

        $lines = [
            ...$lines,
            ...array_map(static fn (TypedUrl $url): string => 'URL:'.$url->url->toString(), $card->urls),
            ...array_map(static fn (string $link): string => 'IMPP:'.$link, array_filter(array_map(static fn (Handle $handle): ?string => $handle->link(), $card->handles), static fn (?string $link): bool => null !== $link)),
            'END:VCARD',
        ];

        return implode('', array_map(self::fold(...), $lines));
    }

    private static function name(PersonName $name): string
    {
        $components = [
            self::values([$name->surname(), $name->secondFamily]),
            self::values([$name->given]),
            self::values($name->additional),
            self::values([$name->honorific]),
            self::values([$name->generation, $name->credential]),
        ];

        if (null !== $name->secondFamily || null !== $name->generation) {
            $components = [...$components, self::values([$name->secondFamily]), self::values([$name->generation])];
        }

        $parameters = null === $name->culture ? '' : ';LANGUAGE='.$name->culture;
        $sorted = $name->sortFamily();

        if ($sorted !== $name->surname()) {
            $parameters .= ';SORT-AS="'.self::parameter((string) $sorted).','.self::parameter((string) $name->given).'"';
        }

        return 'N'.$parameters.':'.implode(';', $components);
    }

    /**
     * The values of a component, separated by commas.
     *
     * @param list<?string> $values
     */
    private static function values(array $values): string
    {
        return implode(',', array_map(self::text(...), array_filter($values, static fn (?string $value): bool => null !== $value)));
    }

    /**
     * A value in a quoted parameter, as RFC 6868 escapes it: a comma, which would separate two values, becomes a space.
     */
    private static function parameter(string $value): string
    {
        return strtr($value, ['^' => '^^', '"' => "^'", ',' => ' ']);
    }

    private static function text(string $value): string
    {
        return str_replace(['\\', ',', ';', "\r\n", "\r", "\n"], ['\\\\', '\,', '\;', '\n', '\n', '\n'], $value);
    }

    private static function fold(string $line): string
    {
        $folded = mb_strcut($line, 0, 75);

        for ($rest = substr($line, \strlen($folded)); '' !== $rest; $rest = substr($rest, \strlen($part))) {
            $part = mb_strcut($rest, 0, 74);
            $folded .= "\r\n ".$part;
        }

        return $folded."\r\n";
    }
}
