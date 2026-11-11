<?php

declare(strict_types=1);

namespace Phil\BounceParser;

use Phil\BounceParser\Exception\InvalidLimits;

/**
 * The bounds of the work on one message, whatever it holds. A report puts what matters first: what lies past
 * `maxBytes` — most often the end of the original message — is not read.
 */
final readonly class Limits
{
    /**
     * @param int $maxBytes   the octets of the message read
     * @param int $maxDepth   the nesting of multiparts and held messages
     * @param int $maxParts   the MIME parts read in all
     * @param int $maxHeaders the header fields read in each part
     * @param int $maxText    the octets of text a rule or the plain-text reading looks at
     *
     * @throws InvalidLimits when one is below 1
     */
    public function __construct(
        public int $maxBytes = 1_048_576,
        public int $maxDepth = 8,
        public int $maxParts = 64,
        public int $maxHeaders = 500,
        public int $maxText = 65_536,
    ) {
        if (min($maxBytes, $maxDepth, $maxParts, $maxHeaders, $maxText) < 1) {
            throw new InvalidLimits('Every limit is at least 1.');
        }
    }
}
