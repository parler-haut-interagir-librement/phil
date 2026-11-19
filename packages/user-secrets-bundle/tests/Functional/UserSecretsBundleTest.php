<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\UserSecretsBundle\Application\KeyRotation;
use Phil\UserSecretsBundle\Application\SecretAccess;
use Phil\UserSecretsBundle\Application\UserSecrets;
use Phil\UserSecretsBundle\Domain\Credential\ApiKey;
use Phil\UserSecretsBundle\Domain\Credential\OAuthToken;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use Phil\UserSecretsBundle\Infrastructure\Lock\SymfonyRefreshLock;
use Phil\UserSecretsBundle\PhilUserSecretsBundle;
use Phil\UserSecretsBundle\Tests\Functional\App\TestKernel;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\RecordingAuditor;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\RecordingRevoker;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * A person's secrets through the container, sealed by the local `sodium://` KMS of Symfony's KeyManagement component:
 * stored in Doctrine on SQLite, read by their task, a token refreshed by an autoconfigured refresher under
 * FrameworkBundle's lock, the events heard by an autoconfigured auditor, and everything gone with the person's erasure
 * by the privacy bundle, the token revoked at its service by an autoconfigured revoker.
 */
#[CoversClass(PhilUserSecretsBundle::class)]
final class UserSecretsBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    public function testASecretLivesAndLeavesWithItsOwner(): void
    {
        $secrets = $this->service(UserSecrets::class, UserSecrets::class);
        $owner = Owner::fromString('member-1');
        $secrets->store($owner, SecretName::fromString('openai'), ServiceName::fromString('openai'), ApiKey::of('sk-1'));
        $secrets->store($owner, SecretName::fromString('github'), ServiceName::fromString('github'), OAuthToken::of('old', 'rt', new DateTimeImmutable('2026-11-06 09:00:00 UTC')));
        $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class)->clear();

        $access = SecretAccess::forTaskOf($owner, 'nightly sync');
        self::assertSame('sk-1', $secrets->apiKey($access, SecretName::fromString('openai'))->key->reveal());
        self::assertSame('fresh-token', $secrets->accessToken($access, SecretName::fromString('github'))->accessToken->reveal());
        self::assertSame(0, $this->service(KeyRotation::class, KeyRotation::class)->rotate(10)->resealed);

        $export = $this->service(SubjectExport::class, SubjectExport::class)->export(DataSubject::fromString('member-1'));
        self::assertStringNotContainsString('sk-1', json_encode($export, \JSON_THROW_ON_ERROR));
        self::assertStringContainsString('openai', json_encode($export, \JSON_THROW_ON_ERROR));

        $this->service(SubjectErasure::class, SubjectErasure::class)->erase(DataSubject::fromString('member-1'));
        self::assertSame([], $secrets->secrets($owner));
        self::assertSame(['fresh-token'], $this->service(RecordingRevoker::class, RecordingRevoker::class)->revoked);
        self::assertSame([
            'stored member-1/openai', 'stored member-1/github', 'revealed member-1/openai nightly sync', 'refreshed member-1/github',
            'revealed member-1/github nightly sync', 'erased member-1/github', 'erased member-1/openai', 'revoked_at_service member-1/github',
        ], $this->service(RecordingAuditor::class, RecordingAuditor::class)->lines());
    }

    public function testTheRefreshRunsUnderFrameworkBundlesLockAndSavesByCompareAndSwap(): void
    {
        $secrets = $this->service(UserSecrets::class, UserSecrets::class);
        self::assertInstanceOf(SymfonyRefreshLock::class, new ReflectionProperty(UserSecrets::class, 'lock')->getValue($secrets));

        $owner = Owner::fromString('member-2');
        $secrets->store($owner, SecretName::fromString('github'), ServiceName::fromString('github'), OAuthToken::of('old', 'rt', new DateTimeImmutable('2026-11-06 09:00:00 UTC')));
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $entityManager->clear();

        $token = $secrets->accessToken(SecretAccess::forTaskOf($owner, 'nightly sync'), SecretName::fromString('github'));
        $entityManager->clear();

        self::assertSame(['fresh-token', 'rt'], [$token->accessToken->reveal(), $token->refreshToken?->reveal()]);
        self::assertSame('fresh-token', $secrets->accessToken(SecretAccess::forTaskOf($owner, 'again'), SecretName::fromString('github'))->accessToken->reveal());
        self::assertSame('2026-11-06T10:00:00+00:00', $secrets->secrets($owner)[0]->lastUsedAt?->format(\DATE_ATOM));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
