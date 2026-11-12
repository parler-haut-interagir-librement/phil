<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\DataMapper;

use Override;
use Phil\ContentBundle\Application\Pipeline\DataMapper;
use Phil\ContentBundle\Domain\Capability\ExcerptCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;

/**
 * Writes `ExcerptCapable`: `excerptTitle` and `excerptDescription` on the localized row, `excerptImage` on the
 * unlocalized row, since one image usually serves every language.
 *
 * Each key is optional; an absent key leaves its value alone, `null` clears it.
 */
final readonly class ExcerptDataMapper implements DataMapper
{
    #[Override]
    public function map(DimensionContent $unlocalized, DimensionContent $localized, array $data): void
    {
        if ($localized instanceof ExcerptCapable) {
            if (SubmittedData::has($data, 'excerptTitle')) {
                $localized->setExcerptTitle(SubmittedData::string($data, 'excerptTitle'));
            }

            if (SubmittedData::has($data, 'excerptDescription')) {
                $localized->setExcerptDescription(SubmittedData::string($data, 'excerptDescription'));
            }
        }

        if ($unlocalized instanceof ExcerptCapable && SubmittedData::has($data, 'excerptImage')) {
            $unlocalized->setExcerptImage(SubmittedData::string($data, 'excerptImage'));
        }
    }
}
