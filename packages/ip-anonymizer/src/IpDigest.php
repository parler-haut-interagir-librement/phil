<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer;

use Stringable;

/**
 * The keyed digest of an address: the identifier of the salt it was taken with, and its bytes.
 */
final readonly class IpDigest implements Stringable
{
    public function __construct(
        public string $saltId,
        public string $bytes,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->saltId === $other->saltId && hash_equals($this->bytes, $other->bytes);
    }

    /**
     * `<salt id>:<hexadecimal digest>`, the form a counter or a log line keys on.
     */
    public function toString(): string
    {
        return $this->saltId.':'.sodium_bin2hex($this->bytes);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
