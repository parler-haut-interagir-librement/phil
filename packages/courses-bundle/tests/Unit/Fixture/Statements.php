<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Fixture;

use Phil\CoursesBundle\Application\Cmi5\Cmi5Context;
use Phil\CoursesBundle\Domain\Cmi5\Cmi5Iri;
use Phil\CoursesBundle\Domain\Cmi5\Cmi5Verb;

/**
 * The statements an assignable unit sends, as cmi5 defines them, written for the context of a launch.
 */
final class Statements
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    public static function of(Cmi5Context $context, Cmi5Verb|string $verb, array $result = [], bool $moveOn = false): array
    {
        $categories = [['id' => Cmi5Iri::CATEGORY]];

        if ($moveOn || ($verb instanceof Cmi5Verb && $verb->movesOn())) {
            $categories[] = ['id' => Cmi5Iri::MOVE_ON_CATEGORY];
        }

        return array_filter([
            'actor' => ['objectType' => 'Agent', 'account' => ['homePage' => $context->homePage, 'name' => $context->learner]],
            'verb' => ['id' => $verb instanceof Cmi5Verb ? $verb->value : $verb],
            'object' => ['objectType' => 'Activity', 'id' => $context->activityIri],
            'result' => $result,
            'context' => [
                'registration' => $context->registration,
                'contextActivities' => ['category' => $categories, 'grouping' => [['id' => $context->publisherId]]],
                'extensions' => [Cmi5Iri::SESSION => $context->session],
            ],
        ], static fn (array $part): bool => [] !== $part);
    }

    /**
     * @return array<string, mixed>
     */
    public static function completed(Cmi5Context $context): array
    {
        return self::of($context, Cmi5Verb::Completed, ['completion' => true, 'duration' => 'PT5M']);
    }

    /**
     * @return array<string, mixed>
     */
    public static function passed(Cmi5Context $context, float $scaled = 0.9): array
    {
        return self::of($context, Cmi5Verb::Passed, ['success' => true, 'duration' => 'PT5M', 'score' => ['scaled' => $scaled]]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function failed(Cmi5Context $context, float $scaled = 0.2): array
    {
        return self::of($context, Cmi5Verb::Failed, ['success' => false, 'duration' => 'PT5M', 'score' => ['scaled' => $scaled]]);
    }
}
