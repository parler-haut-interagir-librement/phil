<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Infrastructure\Privacy;

use Override;
use Phil\DoctrineBehaviours\Exception\InvalidOwner;
use Phil\DoctrineBehaviours\OwnerEraser;
use Phil\DoctrineBehaviours\OwnerId;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * The owner's part of an access or erasure request, the data subject being the owner identifier: the export counts the
 * rows the person owns, by table; the erasure hands them to the configured pseudonym, or to nobody, and reports every
 * owned table. A subject that cannot be an owner — one with an `@` — owns nothing, and is reported all the same.
 */
final readonly class OwnerPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'behaviours.owner';

    public function __construct(
        private OwnerEraser $eraser,
        private ?OwnerId $replacement = null,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        $owner = $this->owner($subject);
        $found = $owner instanceof OwnerId ? $this->eraser->find($owner) : [];

        return [] === $found ? [] : ['rows_by_table' => $found];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $owner = $this->owner($subject);

        if ($owner instanceof OwnerId) {
            $this->eraser->replace($owner, $this->replacement);
        }

        return ErasureReport::of(self::MODULE, Stores::of($this->eraser->tables(), self::MODULE, $this->replacement instanceof OwnerId ? ErasureEffect::Pseudonymised : ErasureEffect::Redacted));
    }

    private function owner(DataSubject $subject): ?OwnerId
    {
        try {
            return new OwnerId($subject->value);
        } catch (InvalidOwner) {
            return null;
        }
    }
}
