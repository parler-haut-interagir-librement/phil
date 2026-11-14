<?php

declare(strict_types=1);

namespace Phil\FormLogic\JsonLogic;

use Phil\FormLogic\Exception\InvalidLogic;

/**
 * How large a rule the reader takes: its JSON in bytes, the depth of its operations, its nodes, the items of a list and
 * the characters of a text. A rule within them is read, checked and evaluated in time proportional to its size.
 */
final readonly class Limits
{
    /** @var int<1, max> */
    public int $maxBytes;

    /** @var int<1, max> */
    public int $maxDepth;

    /** @var int<1, max> */
    public int $maxNodes;

    /** @var int<1, max> */
    public int $maxListItems;

    /** @var int<1, max> */
    public int $maxTextLength;

    /**
     * @throws InvalidLogic when a limit is under 1
     */
    public function __construct(int $maxBytes = 65_536, int $maxDepth = 32, int $maxNodes = 1_000, int $maxListItems = 500, int $maxTextLength = 10_000)
    {
        if ($maxBytes < 1 || $maxDepth < 1 || $maxNodes < 1 || $maxListItems < 1 || $maxTextLength < 1) {
            throw InvalidLogic::because('Each limit of the reader is at least 1.');
        }

        $this->maxBytes = $maxBytes;
        $this->maxDepth = $maxDepth;
        $this->maxNodes = $maxNodes;
        $this->maxListItems = $maxListItems;
        $this->maxTextLength = $maxTextLength;
    }
}
