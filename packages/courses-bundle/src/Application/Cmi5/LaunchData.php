<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Cmi5;

use Phil\CoursesBundle\Domain\Cmi5\Cmi5Iri;

/**
 * The `LMS.LaunchData` state document an assignable unit reads first: the context template its statements copy, the
 * launch mode, the `moveOn`, the mastery score, the launch parameters and the entitlement key.
 */
final class LaunchData
{
    private function __construct()
    {
    }

    /**
     * @param ?string $returnUrl where the learner goes back once the unit is done
     *
     * @return array<string, mixed>
     */
    public static function document(Cmi5Context $context, ?string $returnUrl = null): array
    {
        return array_filter([
            'contextTemplate' => [
                'contextActivities' => ['grouping' => [['objectType' => 'Activity', 'id' => $context->publisherId]]],
                'extensions' => [Cmi5Iri::SESSION => $context->session],
            ],
            'launchMode' => 'Normal',
            'moveOn' => $context->moveOn->value,
            'masteryScore' => $context->masteryScore,
            'launchParameters' => $context->launchParameters,
            'entitlementKey' => null === $context->entitlementKey ? null : ['courseStructure' => $context->entitlementKey],
            'returnURL' => $returnUrl,
        ], static fn (mixed $value): bool => null !== $value);
    }
}
