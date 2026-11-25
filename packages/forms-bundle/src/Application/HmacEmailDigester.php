<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Application;

use InvalidArgumentException;
use Override;
use Phil\FormsBundle\Domain\Subject\EmailDigester;
use SensitiveParameter;

/**
 * HMAC-SHA256 of the address trimmed and lower-cased, in hexadecimal: the same address however written gives the
 * same digest, and without the key no address can be tried against the digests kept. The key is derived from the
 * secret given by HKDF-SHA256 under `USAGE`, so that the digests of the forms never match the pseudonyms another
 * usage of the same secret keeps for the same address.
 */
final readonly class HmacEmailDigester implements EmailDigester
{
    public const string USAGE = 'phil_forms.email_digest';

    private string $key;

    public function __construct(
        #[SensitiveParameter]
        string $secret,
    ) {
        if ('' === $secret) {
            throw new InvalidArgumentException('The key of the email digests cannot be empty.');
        }

        $this->key = hash_hkdf('sha256', $secret, 32, self::USAGE);
    }

    #[Override]
    public function digest(string $email): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($email)), $this->key);
    }
}
