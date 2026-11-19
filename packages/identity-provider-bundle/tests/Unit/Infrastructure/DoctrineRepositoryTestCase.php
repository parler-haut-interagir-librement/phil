<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\IdentityProviderBundle\Application\Contract\Transactions;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Contract\AccountSessionRepository;
use Phil\IdentityProviderBundle\Domain\Contract\ClientRepository;
use Phil\IdentityProviderBundle\Domain\Contract\GrantRepository;
use Phil\IdentityProviderBundle\Domain\Contract\LinkedIdentityRepository;
use Phil\IdentityProviderBundle\Domain\Contract\PasskeyRepository;
use Phil\IdentityProviderBundle\Domain\Contract\SecondFactorRepository;
use Phil\IdentityProviderBundle\Domain\Contract\SigningKeyRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineAccountRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineAccountSessionRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineClientRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineGrantRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineLinkedIdentityRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrinePasskeyRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineSecondFactorRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineSigningKeyRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineTransactions;

/**
 * The Doctrine stores on a connection, their tables dropped and created from the mapping.
 */
abstract class DoctrineRepositoryTestCase extends RepositoryTestCase
{
    protected ?EntityManager $entityManager = null;

    #[Override]
    protected function tearDown(): void
    {
        $this->entityManager?->getConnection()->close();
        $this->entityManager = null;
    }

    abstract protected function connection(): Connection;

    protected function entityManager(): EntityManager
    {
        if (!$this->entityManager instanceof EntityManager) {
            $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
            $configuration->enableNativeLazyObjects(true);
            $this->entityManager = new EntityManager($this->connection(), $configuration);
            $this->entityManager->getEventManager()->addEventListener(Events::loadClassMetadata, new BinaryKeysListener());
            $tool = new SchemaTool($this->entityManager);
            $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
            $tool->dropSchema($metadata);
            $tool->createSchema($metadata);
        }

        return $this->entityManager;
    }

    #[Override]
    protected function transactions(): Transactions
    {
        return new DoctrineTransactions($this->entityManager());
    }

    #[Override]
    protected function accounts(): AccountRepository
    {
        return new DoctrineAccountRepository($this->entityManager());
    }

    #[Override]
    protected function passkeys(): PasskeyRepository
    {
        return new DoctrinePasskeyRepository($this->entityManager());
    }

    #[Override]
    protected function factors(): SecondFactorRepository
    {
        return new DoctrineSecondFactorRepository($this->entityManager());
    }

    #[Override]
    protected function sessions(): AccountSessionRepository
    {
        return new DoctrineAccountSessionRepository($this->entityManager());
    }

    #[Override]
    protected function identities(): LinkedIdentityRepository
    {
        return new DoctrineLinkedIdentityRepository($this->entityManager());
    }

    #[Override]
    protected function clients(): ClientRepository
    {
        return new DoctrineClientRepository($this->entityManager());
    }

    #[Override]
    protected function grants(): GrantRepository
    {
        return new DoctrineGrantRepository($this->entityManager());
    }

    #[Override]
    protected function keys(): SigningKeyRepository
    {
        return new DoctrineSigningKeyRepository($this->entityManager());
    }

    #[Override]
    protected function clear(): void
    {
        $this->entityManager?->clear();
    }
}
