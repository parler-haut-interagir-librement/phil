<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Application;

use Phil\FormsBundle\Domain\Contract\FieldSealer;
use Phil\FormsBundle\Domain\Exception\UnreadableValue;
use Phil\FormsBundle\Domain\Model\Entry;

/**
 * The values of an entry as they were sent: those kept sealed opened by the `FieldSealer`, for whoever reads the entry
 * — an answer to an access request, a screen of the application.
 */
final readonly class SealedValues
{
    public function __construct(
        private ?FieldSealer $sealer = null,
    ) {
    }

    /**
     * @return array<string, string|bool|null>
     *
     * @throws UnreadableValue when a value does not open, or no sealer is installed to open it
     */
    public function reveal(Entry $entry): array
    {
        $values = $entry->values;

        foreach ($entry->sealed as $field) {
            $context = Entry::context($entry->id, $field);
            $sealed = $values[$field] ?? null;

            if (!\is_string($sealed) || !$this->sealer instanceof FieldSealer) {
                throw UnreadableValue::of($context);
            }

            $values[$field] = $this->sealer->open($sealed, $context);
        }

        return $values;
    }
}
