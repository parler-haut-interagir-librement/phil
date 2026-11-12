<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Domain\Syntax;

use Phil\BrowserSecurityBundle\Domain\Enum\Directive;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;

/**
 * The grammar of Content Security Policy Level 3 for what a directive takes: a source expression, a sandbox flag, a
 * Trusted Types policy name. Every value is checked before it reaches a header, so that no space, `;` nor `,` of a
 * configuration or an attribute can add a directive or a policy of its own.
 */
final class SourceSyntax
{
    public const int MAX_LENGTH = 1024;

    public const string NONE = "'none'";

    /**
     * The quoted keywords of a source list.
     */
    public const array KEYWORDS = [
        "'self'", "'none'", "'unsafe-inline'", "'unsafe-eval'", "'strict-dynamic'", "'unsafe-hashes'",
        "'report-sample'", "'unsafe-allow-redirects'", "'wasm-unsafe-eval'", "'inline-speculation-rules'",
    ];

    /**
     * The keywords that loosen what scripts and styles may run: never in a navigation or document directive.
     */
    private const array SCRIPT_KEYWORDS = [
        "'unsafe-inline'", "'unsafe-eval'", "'strict-dynamic'", "'unsafe-hashes'", "'report-sample'",
        "'wasm-unsafe-eval'", "'inline-speculation-rules'",
    ];

    /**
     * The flags of the `sandbox` directive.
     */
    public const array SANDBOX_FLAGS = [
        'allow-downloads', 'allow-forms', 'allow-modals', 'allow-orientation-lock', 'allow-pointer-lock',
        'allow-popups', 'allow-popups-to-escape-sandbox', 'allow-presentation', 'allow-same-origin', 'allow-scripts',
        'allow-storage-access-by-user-activation', 'allow-top-navigation', 'allow-top-navigation-by-user-activation',
        'allow-top-navigation-to-custom-protocols',
    ];

    private const string NONCE_OR_HASH = "/^'(?:nonce|sha256|sha384|sha512)-[A-Za-z0-9+\\/_-]+={0,2}'$/D";

    private const string SCHEME = '/^[A-Za-z][A-Za-z0-9+.-]*:$/D';

    private const string HOST = '#^(?:[A-Za-z][A-Za-z0-9+.-]*://)?(?:\*|(?:\*\.)?[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.?)(?::(?:[0-9]{1,5}|\*))?(?:/[A-Za-z0-9._~!$&()*+=:@%/-]*)?$#D';

    private const string POLICY_NAME = '~^[A-Za-z0-9#=_/@.%-]+$~D';

    private function __construct()
    {
    }

    /**
     * @throws InvalidPolicy when the value is not one the directive takes
     */
    public static function assertValid(Directive $directive, string $value): void
    {
        if ('' === $value || \strlen($value) > self::MAX_LENGTH) {
            throw new InvalidPolicy(\sprintf('A value of "%s" is empty or longer than %d bytes.', $directive->value, self::MAX_LENGTH));
        }

        $valid = match ($directive) {
            Directive::Sandbox => \in_array($value, self::SANDBOX_FLAGS, true),
            Directive::RequireTrustedTypesFor => "'script'" === $value,
            Directive::TrustedTypes => \in_array($value, ["'none'", "'allow-duplicates'", '*'], true) || 1 === preg_match(self::POLICY_NAME, $value),
            Directive::UpgradeInsecureRequests => false,
            default => self::isSource($directive, $value),
        };

        if (!$valid) {
            throw new InvalidPolicy(\sprintf('"%s" is not a value of "%s"%s.', $value, $directive->value, self::hint($value)));
        }
    }

    /**
     * Whether a source is a nonce or a hash, which the bundle writes itself for each request.
     */
    public static function isNonceOrHash(string $value): bool
    {
        return 1 === preg_match(self::NONCE_OR_HASH, $value);
    }

    /**
     * The source that allows an inline element by the SHA-256 of its exact content: `'sha256-…'`.
     */
    public static function hashOf(string $content): string
    {
        return "'sha256-".sodium_bin2base64(hash('sha256', $content, true), \SODIUM_BASE64_VARIANT_ORIGINAL)."'";
    }

    private static function isSource(Directive $directive, string $value): bool
    {
        if (\in_array($value, self::KEYWORDS, true) || self::isNonceOrHash($value)) {
            return $directive->takesScriptKeywords() || (!\in_array($value, self::SCRIPT_KEYWORDS, true) && !self::isNonceOrHash($value));
        }

        return !self::looksLikeKeyword($value) && (1 === preg_match(self::SCHEME, $value) || 1 === preg_match(self::HOST, $value));
    }

    private static function looksLikeKeyword(string $value): bool
    {
        return \in_array("'".strtolower($value)."'", self::KEYWORDS, true) || 1 === preg_match('/^(?:nonce|sha256|sha384|sha512)-/i', $value);
    }

    private static function hint(string $value): string
    {
        return self::looksLikeKeyword($value) ? ', a keyword is written between single quotes' : '';
    }
}
