<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Application\Refunds;
use Phil\CommerceBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrinePaymentLock;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrinePaymentRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineRefundRepository;

/**
 * An entity manager of the bundle's entities on a server, and the refunds as the bundle wires them on it: shared by
 * `ServerRefundLockTestCase` and the second clerk it starts in a process of its own, `grant-refund.php`.
 */
final class RefundLockStage
{
    private function __construct()
    {
    }

    public static function entityManager(string $url): EntityManager
    {
        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager($connection, $config);
        $entityManager->getEventManager()->addEventListener(Events::loadClassMetadata, new BinaryKeysListener());

        return $entityManager;
    }

    public static function refunds(EntityManager $entityManager): Refunds
    {
        $payments = new DoctrinePaymentRepository($entityManager);

        return new Refunds(new DoctrineRefundRepository($entityManager), $payments, new Payments([], $payments), lock: new DoctrinePaymentLock($entityManager));
    }
}
