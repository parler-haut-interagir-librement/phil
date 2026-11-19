<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application;

use Phil\CalendarBundle\Domain\Model\Venue;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A venue as a form fills it: its name, room and address, its coordinates, and the listing of the directory it is.
 */
final class VenueDraft
{
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 200)]
    public ?string $name = null;

    #[Assert\Length(max: 100)]
    public ?string $room = null;

    #[Assert\Length(max: 500)]
    public ?string $address = null;

    #[Assert\Range(min: -90, max: 90)]
    public ?float $latitude = null;

    #[Assert\Range(min: -180, max: 180)]
    public ?float $longitude = null;

    #[Assert\Regex('/^[\w.-]{1,64}:[\w.-]{1,64}$/D', message: 'calendar.listing')]
    public ?string $listing = null;

    public static function of(Venue $venue): self
    {
        $draft = new self();
        $draft->name = $venue->name();
        $draft->room = $venue->room();
        $draft->address = $venue->address();
        [$draft->latitude, $draft->longitude] = $venue->coordinates() ?? [null, null];
        $draft->listing = $venue->listing();

        return $draft;
    }
}
