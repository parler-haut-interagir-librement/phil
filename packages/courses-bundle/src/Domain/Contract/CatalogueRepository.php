<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Contract;

use Phil\CoursesBundle\Domain\Catalogue\OfferedCourse;
use Phil\CoursesBundle\Domain\Model\Catalogue;
use Phil\CoursesBundle\Domain\Model\CatalogueEntry;
use Phil\CoursesBundle\Domain\ValueObject\CatalogueId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;

/**
 * The catalogues and the courses each offers, in their order.
 */
interface CatalogueRepository
{
    public function save(Catalogue $catalogue): void;

    public function find(CatalogueId $id): ?Catalogue;

    /**
     * @return list<Catalogue> a page of the catalogues, by title
     */
    public function all(int $limit = 50, int $offset = 0): array;

    /**
     * @param list<string> $units
     *
     * @return list<Catalogue> the public catalogues and the catalogues of these units, by title, 200 at most
     */
    public function shownTo(array $units): array;

    /**
     * Removes the catalogue with its entries; the learners stay enrolled in its courses.
     */
    public function remove(CatalogueId $id): void;

    /**
     * Replaces the entries of the catalogue with these.
     *
     * @param list<CatalogueEntry> $entries
     */
    public function saveEntries(CatalogueId $catalogue, array $entries): void;

    /**
     * @return list<CatalogueEntry> the entries of the catalogue, in their order
     */
    public function entries(CatalogueId $catalogue): array;

    public function entry(CatalogueId $catalogue, CourseId $course): ?CatalogueEntry;

    /**
     * @return list<OfferedCourse> the courses of the catalogue with their entries and their groups of equivalent
     *                             courses, in their order
     */
    public function offered(CatalogueId $catalogue): array;
}
