<?php

declare(strict_types=1);

/*
 * The second clerk of ServerRefundLockTestCase: opens a transaction, reads the payment — the snapshot of a repeatable
 * read starts there —, says "ready", grants a refund of the amount given, and prints "granted" or "refused".
 */

use Phil\CommerceBundle\Domain\Exception\PaymentRefused;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Tests\Integration\RefundLockStage;
use Phil\Money\Currency;
use Phil\Money\Money;

require dirname(__DIR__, 4).'/vendor/autoload.php';

[, $url, $paymentId, $minorUnits] = $argv;
$entityManager = RefundLockStage::entityManager($url);
$entityManager->beginTransaction();
$payment = $entityManager->find(Payment::class, $paymentId) ?? throw new RuntimeException('No such payment.');
echo "ready\n";

try {
    RefundLockStage::refunds($entityManager)->grant($payment, Money::of((int) $minorUnits, Currency::of('CAD')), 'Second clerk', 'clerk-2', new DateTimeImmutable('2026-11-20 10:00:01'));
    $entityManager->flush();
    $entityManager->commit();
    echo "granted\n";
} catch (PaymentRefused) {
    $entityManager->rollback();
    echo "refused\n";
}
