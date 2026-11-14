<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Application;

use Phil\FormsBundle\Domain\Contract\ChoicePlaces;
use Phil\FormsBundle\Domain\Contract\ConditionEvaluator;
use Phil\FormsBundle\Domain\Contract\FieldSealer;
use Phil\FormsBundle\Domain\Exception\InvalidEntry;
use Phil\FormsBundle\Domain\Exception\UnsealedSensitiveField;
use Phil\FormsBundle\Domain\Model\Entry;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Domain\Repository\FormRepository;
use Phil\FormsBundle\Domain\Subject\EmailDigester;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Takes an entry: the values checked against the form — its conditions read by the `ConditionEvaluator`, its
 * sensitive values sealed by the `FieldSealer`; a form with a sensitive field is refused without one, unless
 * `$allowUnsealed` keeps those values as sent —, a place taken in each choice that has a capacity,
 * the entry kept, then everyone who should hear of it told. A choice whose places are all taken refuses the entry,
 * `full`, and the places it took are given back; so are they when the entry cannot be kept. A
 * notification that fails — for whatever reason: a transport, a template, an application's notifier — is logged with
 * the error's class and message, never the values, and keeps neither the entry nor the others from going through:
 * the visitor's entry is taken, and they are not led to send it again.
 */
final readonly class Submissions
{
    /**
     * @param iterable<EntryNotifier> $notifiers
     */
    public function __construct(
        private FormRepository $forms,
        private ClockInterface $clock,
        private EmailDigester $digester,
        private iterable $notifiers = [],
        private ?LoggerInterface $logger = null,
        private ?ConditionEvaluator $logic = null,
        private ?FieldSealer $sealer = null,
        private ?ChoicePlaces $places = null,
        private bool $allowUnsealed = false,
    ) {
    }

    /**
     * @param array<array-key, mixed> $values
     * @param string|null             $subject the identifier of the person sending it, when they are known
     * @param list<string>            $tiers   the tiers the entry is for, which choose the fields it asks
     *
     * @throws InvalidEntry
     * @throws UnsealedSensitiveField when the form has a sensitive field and no sealer is configured
     */
    public function submit(Form $form, array $values, ?string $locale = null, ?string $subject = null, array $tiers = []): Entry
    {
        $entry = $form->submit($values, $this->clock->now(), $locale, $subject, $this->digester, $tiers, $this->logic, $this->sealer, $this->allowUnsealed);
        $taken = $this->take($entry);

        try {
            $this->forms->save($entry);
        } catch (Throwable $throwable) {
            $this->giveBack($form, $taken);

            throw $throwable;
        }

        foreach ($this->notifiers as $notifier) {
            try {
                $notifier->notify($entry);
            } catch (Throwable $throwable) {
                $this->logger?->error('Entry {entry} of form "{form}": {reason}', ['entry' => $entry->id, 'form' => $form->key, 'reason' => $throwable->getMessage(), 'error' => $throwable::class]);
            }
        }

        return $entry;
    }

    /**
     * The choices of the form whose places are all taken, by field: what its page offers no more.
     *
     * @return array<string, list<string>>
     */
    public function fullChoices(Form $form): array
    {
        if (!$this->places instanceof ChoicePlaces) {
            return [];
        }

        $full = [];

        foreach ($form->fields() as $field) {
            foreach ($field->capacities as $choice => $capacity) {
                if ($this->places->taken($form, $field->name, (string) $choice) >= $capacity) {
                    $full[$field->name][] = (string) $choice;
                }
            }
        }

        return $full;
    }

    /**
     * A place in each choice of the entry that has a capacity.
     *
     * @return array<string, string> the choices taken, by field
     *
     * @throws InvalidEntry when a choice is full, the places already taken given back
     */
    private function take(Entry $entry): array
    {
        if (!$this->places instanceof ChoicePlaces) {
            return [];
        }

        $taken = [];

        foreach ($entry->form->fields() as $field) {
            $value = $entry->values[$field->name] ?? null;
            $capacity = $field->capacity($value);

            if (null === $capacity || !\is_string($value)) {
                continue;
            }

            if (!$this->places->take($entry->form, $field->name, $value, $capacity)) {
                $this->giveBack($entry->form, $taken);

                throw InvalidEntry::with([$field->name => 'full']);
            }

            $taken[$field->name] = $value;
        }

        return $taken;
    }

    /**
     * @param array<string, string> $taken
     */
    private function giveBack(Form $form, array $taken): void
    {
        foreach ($taken as $field => $choice) {
            $this->places?->release($form, $field, $choice);
        }
    }
}
