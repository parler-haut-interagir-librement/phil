<?php

declare(strict_types=1);

namespace Phil\SvgBlocksBundle\Application\Message;

use InvalidArgumentException;

/**
 * Asks the background pass to draw the pending blocks of a document — its diagrams —, named by its subject alone:
 * the document itself never travels in the message.
 */
final readonly class RenderDeferredMarkup
{
    /**
     * @throws InvalidArgumentException when the subject is empty or longer than 255 bytes
     */
    public function __construct(
        public string $subject,
    ) {
        if ('' === $subject || \strlen($subject) > 255) {
            throw new InvalidArgumentException('The subject of a document is 1 to 255 bytes long.');
        }
    }
}
