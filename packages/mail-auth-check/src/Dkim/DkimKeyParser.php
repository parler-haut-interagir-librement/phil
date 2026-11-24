<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Dkim;

use Phil\MailAuthCheck\Exception\InvalidRecord;
use Phil\MailAuthCheck\Internal\RsaKeySize;
use Phil\MailAuthCheck\Internal\TagList;
use SodiumException;

/**
 * Reads a DKIM key record (RFC 6376, section 3.6.1): `v=DKIM1` first when present, the key type `k` (RSA by default
 * or Ed25519), the testing flag of `t`, and the public key `p`, whose size it measures.
 */
final readonly class DkimKeyParser
{
    /**
     * @throws InvalidRecord
     */
    public function parse(string $text): DkimKey
    {
        $tags = TagList::parse($text, 'DKIM');

        if (isset($tags['v']) && ('DKIM1' !== $tags['v'] || 'v' !== array_key_first($tags))) {
            throw InvalidRecord::because('the record does not start with "v=DKIM1".');
        }

        $type = DkimKeyType::tryFrom(strtolower($tags['k'] ?? 'rsa'))
            ?? throw InvalidRecord::because(\sprintf('the key type "%s" is neither rsa nor ed25519.', substr($tags['k'] ?? '', 0, 20)));

        if (!isset($tags['p'])) {
            throw InvalidRecord::because('the record has no public key ("p=").');
        }

        $testing = \in_array('y', array_map(trim(...), explode(':', strtolower($tags['t'] ?? ''))), true);
        $encoded = (string) preg_replace('/[ \t\r\n]+/', '', $tags['p']);

        if ('' === $encoded) {
            return new DkimKey($type, 0, revoked: true, testing: $testing);
        }

        try {
            $key = sodium_base642bin($encoded, \SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (SodiumException) {
            throw InvalidRecord::because('the public key is not base64.');
        }

        if (DkimKeyType::Ed25519 === $type) {
            if (32 !== \strlen($key)) {
                throw InvalidRecord::because('the Ed25519 public key is not 32 bytes long.');
            }

            return new DkimKey($type, 256, testing: $testing);
        }

        return new DkimKey($type, RsaKeySize::bits($key), testing: $testing);
    }
}
