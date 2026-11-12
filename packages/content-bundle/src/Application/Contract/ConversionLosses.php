<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Contract;

use Phil\ContentBundle\Application\Publication\ConversionLoss;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\Markup\MarkupFormat;

/**
 * What publishing a draft would lose: the parts of its source the conversion into UJO does not keep, so that its
 * author sees them before publishing.
 */
interface ConversionLosses
{
    /**
     * @param MarkupFormat|string $format a format of `phil/markup`, or the name of a format an add-on brings
     *
     * @return list<ConversionLoss> in the order of the source; empty when the conversion keeps everything
     *
     * @throws InvalidContent when the source cannot be read in its format
     */
    public function of(string $source, MarkupFormat|string $format): array;
}
