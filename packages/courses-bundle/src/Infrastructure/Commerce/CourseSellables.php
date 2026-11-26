<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Commerce;

use Override;
use Phil\CommerceBundle\Application\Port\SellableResolver;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CoursesBundle\Application\Catalogue\Catalogues;
use Phil\CoursesBundle\Domain\Contract\CourseRepository;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Model\CatalogueEntry;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\ValueObject\CatalogueId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\CoursePrice;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * Tells phil/commerce-bundle what a course sold from a catalogue is: the references of type `course`, whose identifier
 * is `<catalogue>/<course>`, sell the course at its price in that catalogue, described by its title, under the tax
 * category of the configuration. A course free in that catalogue, or not in it, is nothing sold.
 */
final readonly class CourseSellables implements SellableResolver
{
    public function __construct(
        private Catalogues $catalogues,
        private CourseRepository $courses,
        private string $taxCategory,
    ) {
    }

    #[Override]
    public function supports(string $type): bool
    {
        return CatalogueEntry::SELLABLE_TYPE === $type;
    }

    #[Override]
    public function resolve(SellableReference $reference): ?Sellable
    {
        $named = self::named($reference->id);

        if (null === $named) {
            return null;
        }

        $price = $this->catalogues->entry($named[0], $named[1])?->price;
        $course = $this->courses->find($named[1]);

        if (!$price instanceof CoursePrice || !$course instanceof Course) {
            return null;
        }

        return Sellable::of($reference, $course->title, Money::of($price->minorUnits, Currency::of($price->currency)), $this->taxCategory);
    }

    /**
     * The catalogue and the course an identifier `<catalogue>/<course>` names, or null when it names none.
     *
     * @return ?array{CatalogueId, CourseId}
     */
    public static function named(string $id): ?array
    {
        $parts = explode('/', $id);

        if (2 !== \count($parts)) {
            return null;
        }

        try {
            return [CatalogueId::of($parts[0]), CourseId::of($parts[1])];
        } catch (InvalidCourse) {
            return null;
        }
    }
}
