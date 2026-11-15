<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Sending;

use Phil\NewsletterBundle\Application\Exception\NotFound;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;

/**
 * The sender profiles of the configuration, by name, and the one a list or a campaign without its own uses.
 */
final readonly class SenderProfiles
{
    /** @var array<string, SenderProfile> */
    private array $profiles;

    /**
     * @param iterable<SenderProfile> $profiles
     */
    public function __construct(
        iterable $profiles,
        private string $default,
    ) {
        $byName = [];

        foreach ($profiles as $profile) {
            $byName[$profile->name] = $profile;
        }

        if (!isset($byName[$default])) {
            throw InvalidNewsletterValue::because(\sprintf('The default sender profile "%s" is not configured.', $default));
        }

        $this->profiles = $byName;
    }

    /**
     * The profile of a name; the default one for `null`.
     *
     * @throws NotFound
     */
    public function get(?string $name): SenderProfile
    {
        return $this->profiles[$name ?? $this->default] ?? throw NotFound::of('sender profile', (string) $name);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->profiles);
    }
}
