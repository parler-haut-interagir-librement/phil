<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Application\Event;

/**
 * Some settings of a subject changed, or were erased: the scope, `user` or `site`, the user's identifier or `site`,
 * and the keys. Identifiers only — a listener reads the new values through `Settings` —, so the event can travel on
 * a message bus and in a log without carrying what a person chose.
 */
final readonly class SettingsChanged
{
    /**
     * @param list<string> $keys
     */
    public function __construct(
        public string $scope,
        public string $subject,
        public array $keys,
    ) {
    }
}
