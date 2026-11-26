<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CoursesBundle\Domain\Catalogue\CatalogueAudience;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Identifiers;
use Phil\CoursesBundle\Domain\ValueObject\CatalogueId;

/**
 * A catalogue of courses: its title and description, and who it is shown to — everyone, or the members of one unit of
 * an organization, named by the unit's identifier, an opaque value of the application. Its moment of creation is kept in
 * UTC.
 */
final class Catalogue
{
    public const int DESCRIPTION_LENGTH = 10_000;

    public const int UNIT_LENGTH = 128;

    public readonly DateTimeImmutable $createdAt;

    public private(set) string $title;

    public private(set) string $description;

    public private(set) CatalogueAudience $audience;

    public private(set) ?string $unit = null;

    /**
     * @throws InvalidCourse when the title or the description is not one a catalogue holds, or the unit does not match
     *                       the audience
     */
    public function __construct(
        public readonly CatalogueId $id,
        string $title,
        DateTimeImmutable $createdAt,
        string $description = '',
        CatalogueAudience $audience = CatalogueAudience::Public,
        ?string $unit = null,
    ) {
        $this->title = Identifiers::title($title);
        $this->description = self::description($description);
        $this->createdAt = $createdAt->setTimezone(new DateTimeZone('UTC'));
        [$this->audience, $this->unit] = self::audience($audience, $unit);
    }

    /**
     * @throws InvalidCourse when the title or the description is not one a catalogue holds
     */
    public function rename(string $title, string $description): void
    {
        $this->title = Identifiers::title($title);
        $this->description = self::description($description);
    }

    /**
     * Shows the catalogue to everyone, or to the members of the unit only.
     *
     * @throws InvalidCourse when the unit does not match the audience
     */
    public function showTo(CatalogueAudience $audience, ?string $unit = null): void
    {
        [$this->audience, $this->unit] = self::audience($audience, $unit);
    }

    public function isPublic(): bool
    {
        return CatalogueAudience::Public === $this->audience;
    }

    /**
     * Whether a learner of these units sees the catalogue: always when it is public.
     *
     * @param list<string> $units the units whose catalogues reach the learner
     */
    public function isShownTo(array $units): bool
    {
        return $this->isPublic() || \in_array($this->unit, $units, true);
    }

    private static function description(string $description): string
    {
        $description = trim($description);

        if (mb_strlen($description) > self::DESCRIPTION_LENGTH) {
            throw InvalidCourse::because(\sprintf('A description is %d characters at most.', self::DESCRIPTION_LENGTH));
        }

        return $description;
    }

    /**
     * @return array{CatalogueAudience, ?string}
     */
    private static function audience(CatalogueAudience $audience, ?string $unit): array
    {
        if (CatalogueAudience::Public === $audience) {
            if (null !== $unit) {
                throw InvalidCourse::because('A public catalogue is shown to everyone: it names no unit.');
            }

            return [$audience, null];
        }

        if (null === $unit) {
            throw InvalidCourse::because('The catalogue of a unit names its unit.');
        }

        return [$audience, Identifiers::check('A unit', $unit, self::UNIT_LENGTH)];
    }
}
