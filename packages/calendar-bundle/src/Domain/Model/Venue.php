<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\CalendarBundle\Domain\Exception\InvalidEvent;
use Phil\DoctrineBehaviours\Contract\Translatable;
use Phil\LocaleDimensions\Locale;
use Symfony\Component\Uid\Uuid;

/**
 * Where events take place: a place of the calendar's own — a bar, a room that has no page of its own on the site — or
 * one the application keeps elsewhere, a listing of its directory, named by a kind and a reference as hosts are. A
 * room narrows a venue down; coordinates place it on a map.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_calendar_venue')]
#[ORM\Index(columns: ['listing'])]
final class Venue implements Translatable
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
    public readonly string $id;

    #[ORM\Column(name: 'name', length: 200)]
    private string $name;

    #[ORM\Column(name: 'room', length: 100, nullable: true)]
    private ?string $room = null;

    #[ORM\Column(name: 'address', length: 500, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(name: 'latitude', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(name: 'longitude', nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(name: 'listing', length: 129, nullable: true)]
    private ?string $listing = null;

    public function __construct(string $name)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->rename($name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $name = trim($name);

        if ('' === $name || mb_strlen($name) > 200) {
            throw InvalidEvent::because('The name of a venue is 1 to 200 characters.');
        }

        $this->name = $name;
    }

    public function room(): ?string
    {
        return $this->room;
    }

    public function address(): ?string
    {
        return $this->address;
    }

    /**
     * Its room, address and coordinates, latitude −90 to 90 and longitude −180 to 180, both or neither.
     */
    public function locate(?string $room, ?string $address, ?float $latitude = null, ?float $longitude = null): void
    {
        $room = $this->text($room, 100, 'room');
        $address = $this->text($address, 500, 'address');

        if ((null === $latitude) !== (null === $longitude) || (null !== $latitude && (abs($latitude) > 90 || abs((float) $longitude) > 180))) {
            throw InvalidEvent::because('A venue has a latitude, −90 to 90, and a longitude, −180 to 180, or neither.');
        }

        $this->room = $room;
        $this->address = $address;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    /**
     * @return array{float, float}|null its latitude and longitude
     */
    public function coordinates(): ?array
    {
        // Both or neither, as locate() keeps them.
        return null === $this->longitude ? null : [$this->latitude ?? 0.0, $this->longitude];
    }

    /**
     * The listing of the application's directory it is, as `kind:reference`, or `null` for a place of its own.
     */
    public function listing(): ?string
    {
        return $this->listing;
    }

    public function listAs(?string $kind, ?string $reference): void
    {
        if (null === $kind && null === $reference) {
            $this->listing = null;

            return;
        }

        foreach (['kind' => $kind, 'reference' => $reference] as $name => $value) {
            if (null === $value || 1 !== preg_match('/^[\w.-]{1,64}$/', $value)) {
                throw InvalidEvent::because(\sprintf('The %s of a listing is 1 to 64 letters, digits, "_", "." or "-".', $name));
            }
        }

        $this->listing = $kind.':'.$reference;
    }

    /**
     * Its name, and its room after a dash: *Salle Le Royal — salle B*.
     */
    public function label(): string
    {
        return null === $this->room ? $this->name : $this->name.' — '.$this->room;
    }

    private function text(?string $value, int $length, string $what): ?string
    {
        $value = null === $value ? null : trim($value);

        if ('' === $value) {
            return null;
        }

        if (null !== $value && mb_strlen($value) > $length) {
            throw InvalidEvent::because(\sprintf('The %s of a venue is %d characters at most.', $what, $length));
        }

        return $value;
    }

    /**
     * @return class-string<VenueTranslation>
     */
    #[Override]
    public static function translationClass(): string
    {
        return VenueTranslation::class;
    }

    /**
     * @return non-empty-list<string>
     */
    #[Override]
    public static function translatedFields(): array
    {
        return ['name'];
    }

    #[Override]
    public function newTranslation(Locale $locale): VenueTranslation
    {
        return new VenueTranslation($this, $locale);
    }
}
