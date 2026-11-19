<?php

declare(strict_types=1);

namespace Phil\MimeReader;

use Phil\MimeReader\Exception\InvalidLimits;

/**
 * The bounds of the work on one message, whatever it holds. What lies past them is not read, and the part they cut is
 * marked incomplete.
 */
final readonly class Limits
{
    /**
     * @param int $maxBytes      the octets of the message read
     * @param int $maxDepth      the nesting of multiparts and held messages, the message itself counted
     * @param int $maxParts      the MIME parts read in all, the message itself counted
     * @param int $maxHeaders    the header fields read in each part
     * @param int $maxParameters the parameters read in one header field
     *
     * @throws InvalidLimits when one is below 1
     */
    public function __construct(
        public int $maxBytes = 26_214_400,
        public int $maxDepth = 12,
        public int $maxParts = 256,
        public int $maxHeaders = 500,
        public int $maxParameters = 32,
    ) {
        if (min($maxBytes, $maxDepth, $maxParts, $maxHeaders, $maxParameters) < 1) {
            throw new InvalidLimits('Every limit is at least 1.');
        }
    }
}
