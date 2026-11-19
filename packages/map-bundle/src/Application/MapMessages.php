<?php

declare(strict_types=1);

namespace Phil\MapBundle\Application;

use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

/**
 * The words the bundle writes on a map and in its list, from the `phil_map` translation domain, in English when the
 * application has no translator or no message for them.
 */
final readonly class MapMessages
{
    public const string DOMAIN = 'phil_map';

    public const array ENGLISH = [
        'list.places' => 'Places on the map',
        'list.neighbourhood' => 'Areas on the map',
        'list.plan' => 'Booths of the plan',
        'neighbourhood.area' => 'One person in this area|%count% people in this area',
        'neighbourhood.near' => 'Around %coordinates%',
        'booth.free' => 'Free',
        'booth.taken' => 'Taken',
        'booth.closed' => 'Closed',
        'booth.unknown' => 'Availability unknown',
    ];

    private TranslatorInterface $english;

    public function __construct(
        private ?TranslatorInterface $translator = null,
    ) {
        $this->english = new class implements TranslatorInterface {
            use TranslatorTrait;
        };
    }

    /**
     * @param key-of<self::ENGLISH>           $id
     * @param array<string, string|int|float> $parameters
     */
    public function trans(string $id, array $parameters = []): string
    {
        $text = $this->translator?->trans($id, $parameters, self::DOMAIN);

        return null === $text || $id === $text ? $this->english->trans(self::ENGLISH[$id], $parameters) : $text;
    }
}
