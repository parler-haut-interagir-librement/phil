<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Assert;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The tables of the contents, created afresh in the database of the test application before each test.
 */
final readonly class ContentSchema
{
    public static function recreate(ContainerInterface $container): void
    {
        $doctrine = $container->get('doctrine');
        Assert::assertInstanceOf(ManagerRegistry::class, $doctrine);
        $entities = $doctrine->getManager();
        Assert::assertInstanceOf(EntityManagerInterface::class, $entities);
        $tool = new SchemaTool($entities);
        $metadata = $entities->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }
}
