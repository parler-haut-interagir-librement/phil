<?php

declare(strict_types=1);

namespace Phil\Checkin;

use Phil\Checkin\Exception\InvalidCheckin;
use SensitiveParameter;

/**
 * What a device read: the payload of a code scanned, or a number typed at the desk. Anyone who holds it gets in, so
 * it is a secret of its holder: the library never records it, never puts it in a message, and only hands a keyed
 * fingerprint of it to an offline device.
 *
 * Valid UTF-8 without control characters, from 1 to {@see self::MAX_LENGTH} bytes, taken as it is: a scanner's line
 * feed is the device's to remove.
 */
final readonly class CredentialCode
{
    public const int MAX_LENGTH = 512;

    private const string PATTERN = '/^\P{Cc}+$/Du';

    /**
     * The bits a character carries in each alphabet, from the narrowest: digits, hexadecimal digits of one case,
     * digits and letters of one case, URL-safe base64, any printable character.
     */
    private const array ALPHABETS = [
        '/^[0-9]+$/D' => 3.321928094887362,
        '/^(?:[0-9a-f]+|[0-9A-F]+)$/D' => 4.0,
        '/^(?:[0-9a-z]+|[0-9A-Z]+)$/D' => 5.169925001442312,
        '/^[0-9A-Za-z_-]+$/D' => 6.0,
    ];

    private const float PRINTABLE_BITS = 6.569855608330948;

    private function __construct(
        #[SensitiveParameter]
        public string $value,
    ) {
    }

    /**
     * @throws InvalidCheckin
     */
    public static function of(#[SensitiveParameter] string $value): self
    {
        if (\strlen($value) > self::MAX_LENGTH || 1 !== preg_match(self::PATTERN, $value)) {
            throw InvalidCheckin::because(\sprintf('A credential code is valid UTF-8 without control characters, 1 to %d bytes.', self::MAX_LENGTH));
        }

        return new self($value);
    }

    /**
     * How many bits the code carries at most: its length in bytes times the bits of a character of the narrowest
     * alphabet that holds it. An upper bound, true of a code drawn at random from that alphabet: a code that follows a
     * pattern — a date, a sequence, a word — carries fewer.
     */
    public function estimatedBits(): int
    {
        $bits = self::PRINTABLE_BITS;

        foreach (self::ALPHABETS as $pattern => $alphabet) {
            if (1 === preg_match($pattern, $this->value)) {
                $bits = $alphabet;

                break;
            }
        }

        return (int) floor(\strlen($this->value) * $bits);
    }

    /**
     * The HMAC-SHA256 of the code under a key, as 64 hexadecimal digits: what an offline manifest carries in place of
     * the code, and what the device computes from what it reads to find it.
     */
    public function fingerprint(#[SensitiveParameter] string $key): string
    {
        return hash_hmac('sha256', $this->value, $key);
    }
}
