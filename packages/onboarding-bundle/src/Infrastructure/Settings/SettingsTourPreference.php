<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Infrastructure\Settings;

use Override;
use Phil\OnboardingBundle\Application\Contract\TourPreference;
use Phil\SettingsBundle\Application\Settings;

/**
 * The tours as each user chose in their preferences, from the setting `onboarding.tours` of phil/settings-bundle,
 * which the bundle declares on by default.
 */
final readonly class SettingsTourPreference implements TourPreference
{
    public const string KEY = 'onboarding.tours';

    public function __construct(
        private Settings $settings,
    ) {
    }

    #[Override]
    public function wantsTours(string $user): bool
    {
        return $this->settings->bool(self::KEY, $user);
    }
}
