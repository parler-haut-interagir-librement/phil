<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Infrastructure\Privacy;

use DateTimeInterface;
use Override;
use Phil\FormsBundle\Application\SealedValues;
use Phil\FormsBundle\Domain\Exception\UnreadableValue;
use Phil\FormsBundle\Domain\Model\Entry;
use Phil\FormsBundle\Domain\Repository\FormRepository;
use Phil\FormsBundle\Domain\Subject\EmailDigester;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * The forms' part of an access or erasure request: the entries about the person, found by the identifier they
 * were known by, or by the digest of the address in a form's designated email field — the request's subject
 * being then that address. The values kept sealed are exported opened; one that no longer opens is left out, its
 * field named under `unreadable`. Erasing deletes the entries.
 */
final readonly class FormEntriesPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'forms';

    public const string STORE = 'phil_form_entry';

    private SealedValues $values;

    public function __construct(
        private FormRepository $forms,
        private EmailDigester $digester,
        ?SealedValues $values = null,
    ) {
        $this->values = $values ?? new SealedValues();
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        $entries = $this->entries($subject);

        return [] === $entries ? [] : ['entries' => array_map($this->exported(...), $entries)];
    }

    /**
     * @return array<string, mixed>
     */
    private function exported(Entry $entry): array
    {
        $exported = [
            'id' => $entry->id,
            'form' => $entry->form->key,
            'title' => $entry->form->title(),
            'submitted_at' => $entry->submittedAt()->format(DateTimeInterface::ATOM),
            'delete_after' => $entry->purgeAfter()->format(DateTimeInterface::ATOM),
            'locale' => $entry->locale,
        ];

        try {
            $exported['values'] = $this->values->reveal($entry);
        } catch (UnreadableValue) {
            $exported['values'] = array_diff_key($entry->values, array_flip($entry->sealed));
            $exported['unreadable'] = $entry->sealed;
        }

        return $exported;
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $entries = $this->entries($subject);

        if ([] !== $entries) {
            $this->forms->remove(...$entries);
        }

        return ErasureReport::of(self::MODULE, [self::STORE => ErasureEffect::Deleted]);
    }

    /**
     * @return list<Entry>
     */
    private function entries(DataSubject $subject): array
    {
        return $this->forms->about(Entry::subjectKey($subject->value), $this->digester->digest($subject->value));
    }
}
