<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Security;

use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use SensitiveParameter;

/**
 * Recovery codes: ten codes of ten characters from an alphabet without look-alikes, shown once as `XXXXX-XXXXX`, kept
 * as HMAC-SHA-256 under the application's key. A code is read without case, spaces or dashes, compared in constant
 * time against every kept hash, and used once.
 */
final readonly class RecoveryCodes
{
    public const int COUNT = 10;

    public const int LENGTH = 10;

    /** 31 characters, without 0, O, 1, I and L: a code of ten carries more than 49 bits. */
    public const string ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function __construct(
        #[SensitiveParameter]
        private string $key,
    ) {
        if (\strlen($key) < 32) {
            throw InvalidValue::because('The key of the recovery codes holds at least 32 bytes.');
        }
    }

    /**
     * @return list<string> the codes to show once, `XXXXX-XXXXX`
     */
    public function generate(int $count = self::COUNT): array
    {
        $codes = [];
        $last = \strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < $count; ++$i) {
            $code = '';

            for ($j = 0; $j < self::LENGTH; ++$j) {
                $code .= self::ALPHABET[random_int(0, $last)];
            }

            $codes[] = substr($code, 0, 5).'-'.substr($code, 5);
        }

        return $codes;
    }

    public function hash(#[SensitiveParameter] string $code): string
    {
        return hash_hmac('sha256', self::normalize($code), $this->key);
    }

    /**
     * The position of the hash the code matches among those kept, null when none does; a used code is null in the
     * list.
     *
     * @param list<string|null> $hashes
     */
    public function match(#[SensitiveParameter] string $code, array $hashes): ?int
    {
        $hash = $this->hash($code);
        $found = null;

        foreach ($hashes as $position => $kept) {
            if (null !== $kept && hash_equals($kept, $hash)) {
                $found ??= $position;
            }
        }

        return $found;
    }

    public static function normalize(#[SensitiveParameter] string $code): string
    {
        return strtoupper(str_replace([' ', '-'], '', $code));
    }
}
