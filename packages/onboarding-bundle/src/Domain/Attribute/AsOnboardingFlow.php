<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Domain\Attribute;

use Attribute;

/**
 * Declares an onboarding flow on a class of the application, which the container then autoconfigures. Its steps are
 * declared with `AsOnboardingStep`, on the same class or on others. The arguments are those of `FlowDefinition`.
 *
 * ```php
 * #[AsOnboardingFlow('author', 'onboarding.author.title')]
 * #[AsOnboardingStep('author', 'profile', 'onboarding.author.profile', route: 'app_profile', target: '#profile-form')]
 * final class AuthorOnboarding {}
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AsOnboardingFlow
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description = null,
        public string $translationDomain = 'messages',
    ) {
    }
}
