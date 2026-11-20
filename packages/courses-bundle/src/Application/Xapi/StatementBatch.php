<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Xapi;

use JsonException;
use Phil\CoursesBundle\Domain\Exception\StatementRefused;

/**
 * The body of a request to the statements resource, checked whole before any statement is: its size, its JSON of a
 * bounded depth, its number of statements, each an object, and the identifier a PUT request names.
 */
final class StatementBatch
{
    public const int MAX_BYTES = 1_048_576;

    public const int MAX_STATEMENTS = 50;

    private const int DEPTH = 32;

    private function __construct()
    {
    }

    /**
     * @param ?string $statementId the identifier a PUT request names, which the statement must carry or takes
     *
     * @return list<array<array-key, mixed>>
     *
     * @throws StatementRefused when the body is not a statement or a list of them, within the bounds
     */
    public static function decode(string $body, ?string $statementId = null): array
    {
        if (\strlen($body) > self::MAX_BYTES) {
            throw StatementRefused::because(\sprintf('A request holds %d bytes of statements at most.', self::MAX_BYTES));
        }

        try {
            $decoded = json_decode($body, true, self::DEPTH, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            throw StatementRefused::because('The body is not JSON of a bounded depth.');
        }

        if (!\is_array($decoded) || [] === $decoded) {
            throw StatementRefused::because('The body is a statement, or a list of them.');
        }

        $statements = array_is_list($decoded) ? $decoded : [$decoded];

        if (\count($statements) > self::MAX_STATEMENTS) {
            throw StatementRefused::because(\sprintf('A request holds %d statements at most.', self::MAX_STATEMENTS));
        }

        if (null !== $statementId) {
            if (\count($statements) > 1 || !\is_array($statements[0]) || ($statements[0]['id'] ?? $statementId) !== $statementId) {
                throw StatementRefused::because('A statement put at an identifier is one statement of that identifier.');
            }

            $statements[0]['id'] = $statementId;
        }

        return array_map(static fn (mixed $statement): array => \is_array($statement) ? $statement : throw StatementRefused::because('Each statement is an object.'), $statements);
    }
}
