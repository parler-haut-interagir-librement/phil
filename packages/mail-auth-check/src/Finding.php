<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck;

/**
 * One thing a check found: the check that found it, its status, a stable code an application may translate or link to
 * help, and a message in English a domain owner can act on.
 */
final readonly class Finding
{
    /**
     * @param string $check the name of the check, such as `spf`
     * @param string $code  a stable code, such as `spf.too-many-lookups`
     */
    public function __construct(
        public string $check,
        public Status $status,
        public string $code,
        public string $message,
    ) {
    }

    public static function pass(string $check, string $code, string $message): self
    {
        return new self($check, Status::Pass, $code, $message);
    }

    public static function warning(string $check, string $code, string $message): self
    {
        return new self($check, Status::Warning, $code, $message);
    }

    public static function fail(string $check, string $code, string $message): self
    {
        return new self($check, Status::Fail, $code, $message);
    }
}
