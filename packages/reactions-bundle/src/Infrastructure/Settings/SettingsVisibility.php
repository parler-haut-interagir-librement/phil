<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Infrastructure\Settings;

use Override;
use Phil\ReactionsBundle\Application\Contract\VisibilityPolicy;
use Phil\ReactionsBundle\Domain\Status\Audience;
use Phil\ReactionsBundle\Domain\ValueObject\ReactionVisibility;
use Phil\Resource\ResourceReference;
use Phil\SettingsBundle\Application\Settings;

/**
 * The visibility a site's administrators choose among its settings; a setting left at `configured` keeps what the
 * configuration says.
 */
final readonly class SettingsVisibility implements VisibilityPolicy
{
    public const string PANEL = 'reactions.panel';

    public const string COUNTS = 'reactions.counts';

    public const string NAMES = 'reactions.names';

    public const string CONFIGURED = 'configured';

    public function __construct(
        private VisibilityPolicy $configured,
        private Settings $settings,
    ) {
    }

    #[Override]
    public function of(ResourceReference $content): ReactionVisibility
    {
        $configured = $this->configured->of($content);

        return new ReactionVisibility(
            $this->audience(self::PANEL, $configured->panel),
            $this->audience(self::COUNTS, $configured->counts),
            $this->audience(self::NAMES, $configured->names),
        );
    }

    private function audience(string $key, Audience $configured): Audience
    {
        return Audience::tryFrom($this->settings->string($key)) ?? $configured;
    }
}
