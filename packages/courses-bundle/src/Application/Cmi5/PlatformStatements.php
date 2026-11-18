<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Cmi5;

use DateTimeImmutable;
use Phil\CoursesBundle\Domain\Cmi5\Cmi5Iri;
use Phil\CoursesBundle\Domain\Cmi5\Cmi5Verb;
use Symfony\Component\Uid\Uuid;

/**
 * The statements the platform issues, as cmi5 defines them: `launched` with the launch mode, URL, `moveOn` and
 * parameters; `abandoned` with the time the session lasted; `waived` with its reason; `satisfied` for a block, and for
 * the course, once every required unit it holds is, the course named as their grouping.
 */
final class PlatformStatements
{
    private function __construct()
    {
    }

    /**
     * @return array<string, mixed>
     */
    public static function launched(Cmi5Context $context, string $launchUrl, DateTimeImmutable $at): array
    {
        $statement = self::statement($context, Cmi5Verb::Launched, $context->activityIri, $at);
        $statement['context']['extensions'] += array_filter([
            Cmi5Iri::LAUNCH_MODE => 'Normal',
            Cmi5Iri::LAUNCH_URL => $launchUrl,
            Cmi5Iri::MOVE_ON => $context->moveOn->value,
            Cmi5Iri::LAUNCH_PARAMETERS => $context->launchParameters,
        ], static fn (?string $value): bool => null !== $value);

        return $statement;
    }

    /**
     * @return array<string, mixed>
     */
    public static function abandoned(Cmi5Context $context, DateTimeImmutable $launchedAt, DateTimeImmutable $at): array
    {
        $statement = self::statement($context, Cmi5Verb::Abandoned, $context->activityIri, $at);
        $statement['result'] = ['duration' => \sprintf('PT%dS', max(0, $at->getTimestamp() - $launchedAt->getTimestamp()))];

        return $statement;
    }

    /**
     * @return array<string, mixed>
     */
    public static function waived(Cmi5Context $context, DateTimeImmutable $at): array
    {
        $statement = self::statement($context, Cmi5Verb::Waived, $context->activityIri, $at);
        $statement['result'] = ['completion' => true, 'success' => true];
        $statement['context']['extensions'][Cmi5Iri::REASON] = 'Administrative';
        $statement['context']['contextActivities']['category'][] = ['id' => Cmi5Iri::MOVE_ON_CATEGORY];

        return $statement;
    }

    /**
     * @param ?string $course the IRI of the course: the identifier its course structure gives it, or the platform's
     *
     * @return array<string, mixed>
     */
    public static function courseSatisfied(Cmi5Context $context, DateTimeImmutable $at, ?string $course = null): array
    {
        return self::satisfied($context, $course ?? $context->courseIri, $course ?? $context->courseIri, $at);
    }

    /**
     * @param string $block  the identifier the course structure gives the block
     * @param string $course the IRI of the course that holds it
     *
     * @return array<string, mixed>
     */
    public static function blockSatisfied(Cmi5Context $context, string $block, string $course, DateTimeImmutable $at): array
    {
        return self::satisfied($context, $block, $course, $at);
    }

    /**
     * @return array<string, mixed>
     */
    private static function satisfied(Cmi5Context $context, string $object, string $course, DateTimeImmutable $at): array
    {
        $statement = self::statement($context, Cmi5Verb::Satisfied, $object, $at);
        $statement['context']['contextActivities']['grouping'] = [['objectType' => 'Activity', 'id' => $course]];
        $statement['context']['contextActivities']['category'][] = ['id' => Cmi5Iri::MOVE_ON_CATEGORY];

        return $statement;
    }

    /**
     * @return array{id: string, actor: array<string, mixed>, verb: array<string, mixed>, object: array<string, mixed>, context: array{registration: string, contextActivities: array<string, list<array<string, string>>>, extensions: array<string, string>}, timestamp: string}
     */
    private static function statement(Cmi5Context $context, Cmi5Verb $verb, string $object, DateTimeImmutable $at): array
    {
        return [
            'id' => Uuid::v4()->toRfc4122(),
            'actor' => $context->actor(),
            'verb' => ['id' => $verb->value, 'display' => ['en-US' => $verb->display()]],
            'object' => ['objectType' => 'Activity', 'id' => $object],
            'context' => [
                'registration' => $context->registration,
                'contextActivities' => [
                    'category' => [['id' => Cmi5Iri::CATEGORY]],
                    'grouping' => [['objectType' => 'Activity', 'id' => $context->publisherId]],
                ],
                'extensions' => [Cmi5Iri::SESSION => $context->session],
            ],
            'timestamp' => $at->format('Y-m-d\TH:i:s.vP'),
        ];
    }
}
