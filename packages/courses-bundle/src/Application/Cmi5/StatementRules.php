<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Cmi5;

use Phil\CoursesBundle\Domain\Cmi5\Cmi5Iri;
use Phil\CoursesBundle\Domain\Cmi5\Cmi5Verb;
use Phil\CoursesBundle\Domain\Cmi5\SessionState;
use Phil\CoursesBundle\Domain\Exception\StatementRefused;
use Phil\CoursesBundle\Domain\Model\Progress;

/**
 * The rules of cmi5 an assignable unit's statement must keep: the session's actor, registration and session; for a
 * statement cmi5 defines, the activity's IRI, the `cmi5` category, and its place in the session — `initialized` first
 * and once, nothing after `terminated` —; a `completed` once per registration with its completion, a `passed` once and
 * a `failed` never after it, each with its success, its `moveon` category, its duration, and a scaled score against the
 * mastery score when there is one. The verbs only the platform issues are refused. Any other verb is allowed between
 * `initialized` and `terminated`.
 */
final class StatementRules
{
    private const string UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di';

    private const string DURATION = '/^P(?=\d|T\d)(\d+(\.\d+)?Y)?(\d+(\.\d+)?M)?(\d+(\.\d+)?W)?(\d+(\.\d+)?D)?(T(?=\d)(\d+(\.\d+)?H)?(\d+(\.\d+)?M)?(\d+(\.\d+)?S)?)?$/D';

    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $statement the statement, decoded from its JSON
     *
     * @return ?Cmi5Verb the verb cmi5 defines, or null for an allowed statement of another verb
     *
     * @throws StatementRefused when the statement breaks a rule
     */
    public static function check(array $statement, Cmi5Context $context, SessionState $state, ?Progress $progress): ?Cmi5Verb
    {
        self::identity($statement, $context);
        $verb = Cmi5Verb::tryFrom(self::string(self::map($statement, 'verb'), 'id', 'The verb'));

        if (!$verb instanceof Cmi5Verb) {
            if (SessionState::Initialized !== $state) {
                throw StatementRefused::because('A statement of another verb comes between "initialized" and "terminated".');
            }

            return null;
        }

        if ($verb->isIssuedByThePlatform()) {
            throw StatementRefused::because(\sprintf('Only the platform issues "%s".', $verb->display()));
        }

        self::order($verb, $state);
        self::defined($statement, $context, $verb);
        self::result($statement, $context, $verb, $progress);

        return $verb;
    }

    /**
     * @param array<array-key, mixed> $statement
     */
    private static function identity(array $statement, Cmi5Context $context): void
    {
        $id = $statement['id'] ?? null;

        if (null !== $id && (!\is_string($id) || 1 !== preg_match(self::UUID, $id))) {
            throw StatementRefused::because('The id of a statement is a UUID.');
        }

        $actor = self::map($statement, 'actor');
        $account = self::map($actor, 'account');

        if (($actor['objectType'] ?? 'Agent') !== 'Agent' || \count(array_intersect_key($actor, array_flip(['mbox', 'mbox_sha1sum', 'openid']))) > 0
            || ($account['homePage'] ?? null) !== $context->homePage || ($account['name'] ?? null) !== $context->learner) {
            throw StatementRefused::because('The actor of a statement is the agent the unit was launched with.');
        }

        $statementContext = self::map($statement, 'context');

        if (($statementContext['registration'] ?? null) !== $context->registration) {
            throw StatementRefused::because('The registration of a statement is the one the unit was launched with.');
        }

        if ((self::map($statementContext, 'extensions')[Cmi5Iri::SESSION] ?? null) !== $context->session) {
            throw StatementRefused::because('The session of a statement is the one the unit was launched with.');
        }

        self::string(self::map($statement, 'object'), 'id', 'The object');
    }

    private static function order(Cmi5Verb $verb, SessionState $state): void
    {
        if (!$state->isOpen()) {
            throw StatementRefused::because('The session is ended: no statement follows "terminated".');
        }

        if (Cmi5Verb::Initialized === $verb && SessionState::Launched !== $state) {
            throw StatementRefused::because('A session is initialized once.');
        }

        if (Cmi5Verb::Initialized !== $verb && SessionState::Launched === $state) {
            throw StatementRefused::because('The first statement of a session is "initialized".');
        }
    }

    /**
     * @param array<array-key, mixed> $statement
     */
    private static function defined(array $statement, Cmi5Context $context, Cmi5Verb $verb): void
    {
        if (self::map($statement, 'object')['id'] !== $context->activityIri) {
            throw StatementRefused::because('The object of a statement cmi5 defines is the activity the unit was launched as.');
        }

        $categories = self::categories(self::map($statement, 'context'));

        if (!\in_array(Cmi5Iri::CATEGORY, $categories, true)) {
            throw StatementRefused::because('A statement cmi5 defines carries the "cmi5" category.');
        }

        if ($verb->movesOn() && !\in_array(Cmi5Iri::MOVE_ON_CATEGORY, $categories, true)) {
            throw StatementRefused::because(\sprintf('A "%s" statement carries the "moveon" category.', $verb->display()));
        }
    }

    /**
     * @param array<array-key, mixed> $statement
     */
    private static function result(array $statement, Cmi5Context $context, Cmi5Verb $verb, ?Progress $progress): void
    {
        $result = \is_array($statement['result'] ?? null) ? $statement['result'] : [];
        $scaled = self::scaled($result);

        if ($verb->movesOn() || Cmi5Verb::Terminated === $verb) {
            $duration = $result['duration'] ?? null;

            if (!\is_string($duration) || 1 !== preg_match(self::DURATION, $duration)) {
                throw StatementRefused::because(\sprintf('A "%s" statement gives its duration in ISO 8601.', $verb->display()));
            }
        }

        match ($verb) {
            Cmi5Verb::Completed => self::completed($result, $progress),
            Cmi5Verb::Passed => self::judged($result, $context, $progress, $scaled, true),
            Cmi5Verb::Failed => self::judged($result, $context, $progress, $scaled, false),
            default => null,
        };
    }

    /**
     * @param array<array-key, mixed> $result
     */
    private static function completed(array $result, ?Progress $progress): void
    {
        if (true !== ($result['completion'] ?? null)) {
            throw StatementRefused::because('A "completed" statement sets its completion to true.');
        }

        if (true === $progress?->completed) {
            throw StatementRefused::because('The unit is completed already in this registration.');
        }
    }

    /**
     * @param array<array-key, mixed> $result
     */
    private static function judged(array $result, Cmi5Context $context, ?Progress $progress, ?float $scaled, bool $passed): void
    {
        $name = $passed ? 'passed' : 'failed';

        if (($result['success'] ?? null) !== $passed) {
            throw StatementRefused::because(\sprintf('A "%s" statement sets its success to %s.', $name, $passed ? 'true' : 'false'));
        }

        if (true === $progress?->success) {
            throw StatementRefused::because(\sprintf('The unit is passed already in this registration: no "%s" follows.', $name));
        }

        if (null === $context->masteryScore) {
            return;
        }

        if (null === $scaled) {
            throw StatementRefused::because(\sprintf('A "%s" statement of a unit with a mastery score gives its scaled score.', $name));
        }

        if (($scaled >= $context->masteryScore) !== $passed) {
            throw StatementRefused::because(\sprintf('The scaled score of a "%s" statement is %s the mastery score.', $name, $passed ? 'at least' : 'below'));
        }
    }

    /**
     * @param array<array-key, mixed> $result
     */
    private static function scaled(array $result): ?float
    {
        $score = $result['score'] ?? null;

        if (!\is_array($score) || !\array_key_exists('scaled', $score)) {
            return null;
        }

        $scaled = $score['scaled'];

        if (!\is_int($scaled) && !\is_float($scaled) || $scaled < -1 || $scaled > 1) {
            throw StatementRefused::because('A scaled score is a number from -1 to 1.');
        }

        return $scaled;
    }

    /**
     * @param array<array-key, mixed> $context
     *
     * @return array<array-key, mixed> the identifiers of the categories
     */
    private static function categories(array $context): array
    {
        $activities = \is_array($context['contextActivities'] ?? null) ? $context['contextActivities'] : [];
        $categories = \is_array($activities['category'] ?? null) ? $activities['category'] : [];

        return array_map(static fn (mixed $category): mixed => \is_array($category) ? ($category['id'] ?? null) : null, $categories);
    }

    /**
     * @param array<array-key, mixed> $parent
     *
     * @return array<array-key, mixed>
     */
    private static function map(array $parent, string $key): array
    {
        $value = $parent[$key] ?? null;

        if (!\is_array($value)) {
            throw StatementRefused::because(\sprintf('A statement has its "%s" as an object.', $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $parent
     */
    private static function string(array $parent, string $key, string $what): string
    {
        $value = $parent[$key] ?? null;

        if (!\is_string($value) || '' === $value) {
            throw StatementRefused::because(\sprintf('%s of a statement has its "%s".', $what, $key));
        }

        return $value;
    }
}
