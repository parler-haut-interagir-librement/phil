<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Application;

use InvalidArgumentException;
use Override;
use Phil\FormsBundle\Domain\Subject\EmailDigester;
use SensitiveParameter;

/**
 * HMAC-SHA256 of the address trimmed and lower-cased, in hexadecimal: the same address however written gives the
 * same digest, and without the key no address can be tried against the digests kept.
 */
final readonly class HmacEmailDigester implements EmailDigester
{
    public function __construct(
        #[SensitiveParameter]
        private string $key,
    ) {
        if ('' === $key) {
            throw new InvalidArgumentException('The key of the email digests cannot be empty.');
        }
    }

    #[Override]
    public function digest(string $email): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($email)), $this->key);
    }
}
