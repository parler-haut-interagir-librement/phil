<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Fixture;

use Override;
use Phil\FeatureFlagsBundle\Application\Flag\ExposureRecorder;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\VariantName;

final class RecordingExposures implements ExposureRecorder
{
    /** @var list<string> "flag:variant" */
    public array $exposures = [];

    #[Override]
    public function record(FlagName $flag, VariantName $variant): void
    {
        $this->exposures[] = $flag->value.':'.$variant->value;
    }
}
