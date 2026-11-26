<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Commerce;

use Phil\CommerceBundle\Domain\Event\OrderPurchased;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CoursesBundle\Application\Catalogue\Catalogues;
use Phil\CoursesBundle\Domain\Exception\CoursesException;
use Phil\CoursesBundle\Domain\Model\CatalogueEntry;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

/**
 * Enrolls the buyer in the courses of an order once it is paid in full: at each `OrderPurchased` of
 * phil/commerce-bundle, every line of type `course` enrolls the order's customer — the learner's identifier — in its
 * course, through `Catalogues::grantPurchase()`. An order without a customer enrolls nobody; a line whose course is no
 * longer sold in its catalogue is left, and paying the same course twice keeps one enrollment.
 */
final readonly class CoursePurchases
{
    public function __construct(
        private OrderRepository $orders,
        private Catalogues $catalogues,
    ) {
    }

    /**
     * @return list<Enrollment> the enrollments of the buyer in the courses of the order
     *
     * @phpstan-impure the answer follows the order and the enrollments, which change between two calls
     */
    public function __invoke(OrderPurchased $event): array
    {
        $order = $this->orders->find($event->orderNumber);
        $customer = $order?->customer();

        if (!$order instanceof Order || null === $customer) {
            return [];
        }

        try {
            $learner = LearnerId::of($customer);
        } catch (CoursesException) {
            return [];
        }

        $enrollments = [];

        foreach ($order->lines() as $line) {
            $named = CatalogueEntry::SELLABLE_TYPE === $line->reference->type ? CourseSellables::named($line->reference->id) : null;

            if (null === $named) {
                continue;
            }

            try {
                $enrollments[] = $this->catalogues->grantPurchase($named[0], $named[1], $learner);
            } catch (CoursesException) {
                continue;
            }
        }

        return $enrollments;
    }
}
