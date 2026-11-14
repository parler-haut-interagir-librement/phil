<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Preferences;

use Phil\NotificationsBundle\Application\Channels;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\NotificationsBundle\Domain\Identifiers;

/**
 * What a recipient chose: their digest, their quiet hours, the channels they turned off — for one type, `type:channel`,
 * or for every type, `*:channel` —, and the locale their notifications are written in.
 */
final readonly class RecipientSettings
{
    /** @var list<string> */
    public array $optOuts;

    /**
     * @param list<string> $optOuts `type:channel` or `*:channel`
     *
     * @throws InvalidNotification when an opt-out or the locale is not well formed
     */
    public function __construct(
        public DigestFrequency $digest = DigestFrequency::Never,
        public ?QuietHours $quietHours = null,
        array $optOuts = [],
        public ?string $locale = null,
    ) {
        foreach ($optOuts as $optOut) {
            [$type, $channel] = explode(':', $optOut, 2) + [1 => ''];

            if ('*' !== $type) {
                Identifiers::key($type, 'opted-out type');
            }

            Channels::check($channel);
        }

        if (null !== $locale && 1 !== preg_match('/^[a-z]{2,3}([_-][A-Za-z0-9]{2,8})*$/D', $locale)) {
            throw InvalidNotification::because(\sprintf('"%s" is not a locale.', $locale));
        }

        $optOuts = array_unique($optOuts);
        sort($optOuts);
        $this->optOuts = $optOuts;
    }

    public function allows(string $type, string $channel): bool
    {
        return !\in_array($type.':'.$channel, $this->optOuts, true) && !\in_array('*:'.$channel, $this->optOuts, true);
    }
}
