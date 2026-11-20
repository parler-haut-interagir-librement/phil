<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Xapi;

use Phil\CoursesBundle\Domain\Exception\StatementRefused;
use Phil\CoursesBundle\Domain\Xapi\XapiVerb;
use Symfony\Component\Uid\Uuid;

/**
 * The checks an xAPI activity's statement passes before the store keeps it: the actor is the learner of the session,
 * named by the same account; the verb is an IRI, and not `voided`; the object is an activity or a statement reference
 * with an identifier; the registration, when the statement names one, is the session's; the identifier, when given,
 * is a UUID; a scaled score is from -1 to 1. A statement about another activity than the one launched — a page or a
 * question of the package — is kept and moves nothing.
 */
final class XapiRules
{
    public const int MAX_IRI = 2048;

    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $statement
     *
     * @return array<array-key, mixed> the statement with its registration set
     *
     * @throws StatementRefused when a check fails
     */
    public static function check(array $statement, XapiContext $context): array
    {
        $id = $statement['id'] ?? null;

        if (null !== $id && (!\is_string($id) || !Uuid::isValid($id))) {
            throw StatementRefused::because('The "id" of a statement is a UUID.');
        }

        self::actor($statement['actor'] ?? null, $context);
        $verb = \is_array($statement['verb'] ?? null) ? $statement['verb']['id'] ?? null : null;

        if (!self::isIri($verb)) {
            throw StatementRefused::because('The verb of a statement is named by an IRI.');
        }

        if (XapiVerb::Voided->value === $verb) {
            throw StatementRefused::because('This store does not void statements: the outcomes they recorded stand.');
        }

        self::object($statement['object'] ?? null);
        self::score($statement['result'] ?? null);
        $statement['context'] = self::context($statement['context'] ?? null, $context);

        return $statement;
    }

    /**
     * Whether the value is an IRI as a statement names a verb or an activity: a scheme, a colon and no space, of
     * `MAX_IRI` bytes at most.
     */
    public static function isIri(mixed $value): bool
    {
        return \is_string($value) && '' !== $value && \strlen($value) <= self::MAX_IRI && 1 === preg_match('/^[A-Za-z][A-Za-z0-9+.\-]*:\S+$/D', $value);
    }

    private static function actor(mixed $actor, XapiContext $context): void
    {
        $account = \is_array($actor) && \is_array($actor['account'] ?? null) ? $actor['account'] : [];

        if (!\is_array($actor) || 'Agent' !== ($actor['objectType'] ?? 'Agent') || ($account['homePage'] ?? null) !== $context->homePage || ($account['name'] ?? null) !== $context->learner) {
            throw StatementRefused::because('The actor of a statement is the learner of the session, named by the account of the launch.');
        }
    }

    private static function object(mixed $object): void
    {
        if (!\is_array($object) || !\in_array($object['objectType'] ?? 'Activity', ['Activity', 'StatementRef'], true)) {
            throw StatementRefused::because('The object of a statement is an activity, or a reference to a statement.');
        }

        $id = $object['id'] ?? null;

        if ('StatementRef' === ($object['objectType'] ?? 'Activity') ? !\is_string($id) || !Uuid::isValid($id) : !self::isIri($id)) {
            throw StatementRefused::because('The object of a statement is named by its identifier.');
        }
    }

    private static function score(mixed $result): void
    {
        $score = \is_array($result) && \is_array($result['score'] ?? null) ? $result['score'] : [];
        $scaled = $score['scaled'] ?? null;

        if (null !== $scaled && (!\is_int($scaled) && !\is_float($scaled) || $scaled < -1 || $scaled > 1)) {
            throw StatementRefused::because('A scaled score is a number from -1 to 1.');
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function context(mixed $given, XapiContext $context): array
    {
        if (null !== $given && !\is_array($given)) {
            throw StatementRefused::because('The context of a statement is an object.');
        }

        $given ??= [];

        if (($given['registration'] ?? $context->registration) !== $context->registration) {
            throw StatementRefused::because('The registration of a statement is the one of the launch.');
        }

        $given['registration'] = $context->registration;

        return $given;
    }
}
