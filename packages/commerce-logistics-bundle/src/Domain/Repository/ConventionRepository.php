<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Repository;

use DateTimeImmutable;
use Phil\CommerceLogisticsBundle\Domain\Model\Convention;
use Phil\CommerceLogisticsBundle\Domain\Model\ConventionQuantity;
use Phil\CommerceLogisticsBundle\Domain\Model\PrintJob;

/**
 * Finds and stores conventions, the quantities prepared for them, and the units sent to print on demand.
 * `Infrastructure\Doctrine\DoctrineLogisticsRepository` is the Doctrine adapter.
 */
interface ConventionRepository
{
    public function findConvention(string $code): ?Convention;

    public function addConvention(Convention $convention): void;

    /**
     * @return list<Convention> the conventions with a day between the two days, both included, the earliest first
     */
    public function conventionsMeeting(DateTimeImmutable $from, DateTimeImmutable $until): array;

    public function quantity(string $convention, string $sku): ?ConventionQuantity;

    /**
     * @return list<ConventionQuantity> what is prepared for the convention, by SKU
     */
    public function quantitiesOf(string $convention): array;

    public function addQuantity(ConventionQuantity $quantity): void;

    /**
     * @param list<string> $skus
     *
     * @return list<string> those of the SKUs prepared for any convention: the convention items among them
     */
    public function conventionSkus(array $skus): array;

    /**
     * @return list<PrintJob> the order's units sent to print, oldest first
     */
    public function printJobsOf(string $orderNumber): array;

    public function addPrintJob(PrintJob $job): void;
}
