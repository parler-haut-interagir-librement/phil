<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application\Audit;

use Phil\UserSecretsBundle\Domain\ValueObject\SecretValue;
use Throwable;

/**
 * What a log may keep of a failure met around a secret: the message of the exception, redacted.
 *
 * A connector or a KMS client writes its diagnostic for a developer, and it may carry what the bundle keeps secret: the
 * token it was sending, a signed URL, a header. The reason keeps the message without the known secret values, without
 * the user information, query and fragment of any URL, without the value of a credential scheme (`Bearer …`) or of a
 * field named like a credential (`access_token=…`, `"client_secret": …`), and without any long opaque string — every
 * run of 20 letters, digits, `-`, `_`, `+` or `=` holding both a letter and a digit. Line breaks and control
 * characters become spaces, so that a reason is one line. The message is read on its first 1,024 bytes and the reason
 * holds at most 240 characters, so a long diagnostic costs bounded work.
 *
 * @internal
 */
final class FailureReason
{
    public const string REDACTED = '[redacted]';

    public const int MAX_INPUT = 1024;

    public const int MAX_LENGTH = 240;

    /**
     * Known values shorter than this are left to the patterns: a two-letter value would cut words apart.
     */
    private const int MIN_KNOWN_LENGTH = 8;

    private function __construct()
    {
    }

    public static function of(Throwable $failure, ?SecretValue ...$known): string
    {
        $message = mb_scrub(substr($failure->getMessage(), 0, self::MAX_INPUT), 'UTF-8');

        foreach ($known as $value) {
            if ($value instanceof SecretValue && \strlen($value->reveal()) >= self::MIN_KNOWN_LENGTH) {
                $message = str_replace($value->reveal(), self::REDACTED, $message);
            }
        }

        $message = (string) preg_replace(
            [
                // Line breaks and control characters.
                '/[\x00-\x1F\x7F]++/u',
                // The user information of a URL.
                '~\b([a-z][a-z0-9+.\-]*+://)[^\s/?#@]*+@~i',
                // The query and the fragment of a URL.
                '~\b([a-z][a-z0-9+.\-]*+://[^\s?#]*+)[?#]\S*+~i',
                // The value of a credential scheme.
                '~\b(Bearer|Basic|Digest|Token|Bot|MAC|HOBA|Negotiate|NTLM)\s++(?!\[redacted\])[^\s,;]++~i',
                // The value of a field named like a credential.
                '~\b([\w\-]*?(?:token|secret|password|passwd|pwd|authorization|api[_\-]?key|apikey|signature|assertion|cookie|session|credential|private[_\-]?key|code[_\-]?verifier|auth[_\-]?code)[\w\-]*+)(["\']?\s*+[:=]\s*+["\']?)(?!\[redacted\])[^\s"\'&,;}\]]++~i',
            ],
            [' ', '$1', '$1?'.self::REDACTED, '$1 '.self::REDACTED, '$1$2'.self::REDACTED],
            $message,
        );

        $message = (string) preg_replace_callback(
            '/[A-Za-z0-9_+=\-]{20,}+/',
            static fn (array $match): string => 1 === preg_match('/\d/', $match[0]) && 1 === preg_match('/[A-Za-z]/', $match[0]) ? self::REDACTED : $match[0],
            $message,
        );

        $message = trim((string) preg_replace('/ {2,}+/', ' ', $message));

        return mb_strlen($message) > self::MAX_LENGTH ? mb_substr($message, 0, self::MAX_LENGTH - 1).'…' : $message;
    }
}
