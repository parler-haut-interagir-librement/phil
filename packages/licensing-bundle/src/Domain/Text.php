<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain;

use Phil\LicensingBundle\Domain\Exception\InvalidLicensing;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * The checks of the texts the licensing keeps: trimmed, bounded, without control characters but line breaks and tabs.
 */
final class Text
{
    private const string CODE = '/^[a-z0-9][a-z0-9.-]{0,63}$/D';

    private const string CONTROL = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/';

    private function __construct()
    {
    }

    /**
     * A text that must be given.
     *
     * @throws InvalidLicensing
     */
    public static function required(string $text, int $max, string $field): string
    {
        return self::optional($text, $max, $field) ?? throw InvalidLicensing::because(\sprintf('The %s is required.', $field));
    }

    /**
     * A text that may be left out: blank, it is `null`.
     *
     * @throws InvalidLicensing
     */
    public static function optional(?string $text, int $max, string $field): ?string
    {
        $text = trim($text ?? '');

        if ('' === $text) {
            return null;
        }

        if (1 !== preg_match('//u', $text) || 1 === preg_match(self::CONTROL, $text)) {
            throw InvalidLicensing::because(\sprintf('The %s is UTF-8 text without control characters.', $field));
        }

        if (mb_strlen($text) > $max) {
            throw InvalidLicensing::because(\sprintf('The %s holds %d characters at most.', $field, $max));
        }

        return $text;
    }

    /**
     * An absolute http or https address, 2 048 characters at most, or `null`, kept as it was written.
     *
     * The address is read by `phil/url`, as a browser reads it: an international host is accepted, and an address with
     * a user name or a password, an IP address, `localhost` or a single name for its host is refused. A license and a
     * credited resource are published on the web, never on the machine that serves the page.
     *
     * @throws InvalidLicensing
     */
    public static function url(?string $url, string $field): ?string
    {
        $url = self::optional($url, 2048, $field);

        if (null === $url) {
            return null;
        }

        try {
            Url::fromString($url);
        } catch (InvalidUrl) {
            throw InvalidLicensing::because(\sprintf('The %s is an absolute http or https address.', $field));
        }

        return $url;
    }

    /**
     * A code of 1 to 64 lowercase letters, digits, dots and hyphens, starting with a letter or a digit.
     *
     * @throws InvalidLicensing
     */
    public static function code(string $code, string $field): string
    {
        if (1 !== preg_match(self::CODE, $code)) {
            throw InvalidLicensing::because(\sprintf('The %s "%s" is 1 to 64 lowercase letters, digits, dots and hyphens.', $field, $code));
        }

        return $code;
    }
}
