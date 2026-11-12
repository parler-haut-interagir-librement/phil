<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Exception;

use Phil\ContentBundle\Application\Publication\ConversionLoss;
use Phil\DddBundle\Domain\Exception\DomainError;
use RuntimeException;

/**
 * Publishing would lose part of the draft's body: its conversion into UJO does not keep everything the author wrote.
 * The publication is refused, from the console, from PHP or from a schedule alike, until the editor accepts the losses
 * it lists — the `--accept-losses` option, `ContentManager::publish(..., acceptLosses: true)`, the workflow context
 * `accept_losses` — or changes the draft.
 */
final class PublicationLosesContent extends RuntimeException implements DomainError
{
    /**
     * @param list<ConversionLoss> $losses
     */
    public function __construct(string $message, public readonly array $losses)
    {
        parent::__construct($message);
    }

    /**
     * @param non-empty-list<ConversionLoss> $losses
     */
    public static function of(string $dimension, array $losses): self
    {
        $elements = array_unique(array_map(static fn (ConversionLoss $loss): string => $loss->element, $losses));

        return new self(\sprintf('Publishing "%s" would lose %d element(s) of its body in UJO (%s); accept the losses to publish anyway.', $dimension, \count($losses), implode(', ', $elements)), $losses);
    }
}
