<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Infrastructure\Privacy;

use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\SettingsBundle\Application\SettingCatalog;
use Phil\SettingsBundle\Application\Settings;
use Phil\SettingsBundle\Domain\Contract\SettingRepository;
use Phil\SettingsBundle\Domain\Exception\InvalidSetting;
use Phil\SettingsBundle\Domain\ValueObject\Subject;

/**
 * The settings' part of an access or erasure request, the data subject being the user: the export lists what the
 * person chose — every value stored, read as its setting reads it, or as stored when its setting is no longer
 * declared —; the erasure removes it all. A person who never chose has the defaults, which say nothing of them.
 */
final readonly class SettingsPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'settings';

    public function __construct(
        private SettingCatalog $catalog,
        private SettingRepository $repository,
        private Settings $settings,
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
        $user = $this->user($subject);
        $values = [];

        foreach ($user instanceof Subject ? $this->repository->values($user) : [] as $key => $stored) {
            $values[$key] = ($this->catalog->has($key) ? $this->catalog->get($key)->decode($stored) : null) ?? $stored;
        }

        ksort($values, \SORT_STRING);

        return [] === $values ? [] : ['settings' => $values];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $user = $this->user($subject);

        if ($user instanceof Subject) {
            $this->settings->forget($user);
        }

        return ErasureReport::of(self::MODULE, ['phil_settings' => ErasureEffect::Deleted]);
    }

    /**
     * The user the data subject names, or null when no user can have that identifier.
     */
    private function user(DataSubject $subject): ?Subject
    {
        try {
            return Subject::user($subject->value);
        } catch (InvalidSetting) {
            return null;
        }
    }
}
