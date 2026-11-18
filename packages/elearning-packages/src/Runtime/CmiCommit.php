<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Runtime;

/**
 * Applies a commit the browser sends — the elements the content wrote since the last one, in the order it wrote them —
 * to the data of the attempt, each as `SetValue()` would: a value the data model refuses is left out and named with its
 * error code, the others kept. A commit names at most `MAX_VALUES` elements.
 */
final class CmiCommit
{
    public const int MAX_VALUES = 4_000;

    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<string, int> the elements left out, with their error codes; empty when every value is kept
     */
    public static function apply(CmiData $data, array $values): array
    {
        $errors = [];
        $count = 0;

        foreach ($values as $element => $value) {
            $element = (string) $element;

            if (++$count > self::MAX_VALUES) {
                $errors[mb_substr($element, 0, DataModel::MAX_ELEMENT_LENGTH)] = ScormError::for($data->version, Mistake::SetFailed);

                break;
            }

            $result = $data->set($element, $value);

            if ($result->failed()) {
                $errors[mb_substr($element, 0, DataModel::MAX_ELEMENT_LENGTH)] = $result->error;
            }
        }

        return $errors;
    }
}
