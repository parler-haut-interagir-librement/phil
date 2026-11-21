<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Override;
use Phil\IdentityProviderBundle\Domain\Model\Link\LinkToken;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\AuthorizationCode;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\IssuedToken;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\UsedAssertion;
use Phil\IdentityProviderBundle\Domain\Model\Totp\TotpCredential;
use Phil\IdentityProviderBundle\Domain\Oidc\TokenKind;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineGrantRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineLinkTokenRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineSecondFactorRepository;
use Phil\IdentityProviderBundle\Tests\Unit\Infrastructure\DoctrineRepositoryTestCase;

/**
 * The Doctrine stores on a real server read from the environment, their tables dropped and created from the mapping;
 * and two connections that read the same row before either writes, as two workers would.
 */
abstract class ServerRepositoryTestCase extends DoctrineRepositoryTestCase
{
    private ?EntityManager $second = null;

    protected function tearDown(): void
    {
        $this->second?->getConnection()->close();
        $this->second = null;
        parent::tearDown();
    }

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    public function testTwoConnectionsUseACodeAndRotateATokenOnce(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $this->grants()->saveCode(AuthorizationCode::issue('code', 'app', self::ADA, 'https://a/cb', ['openid'], null, null, $now, [], null, 'FAMILY0000000000000000000A', $now->modify('+60 seconds')));
        $this->grants()->saveToken(IssuedToken::issue(IssuedToken::idOfRefresh('r'), TokenKind::Refresh, 'FAMILY0000000000000000000A', 'app', self::ADA, ['offline_access'], null, $now, [], $now, $now->modify('+30 days')));
        $other = new DoctrineGrantRepository($this->second());

        $mine = $this->grants()->code(AuthorizationCode::idOf('code'));
        $theirs = $other->code(AuthorizationCode::idOf('code'));
        self::assertNotNull($mine);
        self::assertNotNull($theirs);
        self::assertTrue($this->grants()->consumeCode($mine, $now));
        self::assertFalse($other->consumeCode($theirs, $now));

        $mine = $this->grants()->token(IssuedToken::idOfRefresh('r'));
        $theirs = $other->token(IssuedToken::idOfRefresh('r'));
        self::assertNotNull($mine);
        self::assertNotNull($theirs);
        self::assertTrue($other->rotateToken($theirs, $now));
        self::assertFalse($this->grants()->rotateToken($mine, $now));
    }

    public function testTheMarkOfARevokedFamilyReachesTheTokensTheOtherConnectionSaves(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $family = 'FAMILY0000000000000000000A';
        $other = new DoctrineGrantRepository($this->second());

        // Saved before the revocation: the other connection revokes it.
        $this->grants()->saveToken(IssuedToken::issue('jti-1', TokenKind::Access, $family, 'app', self::ADA, [], null, $now, [], $now, $now->modify('+10 minutes')));
        $other->revokeFamily($family, $now, $now->modify('+30 days'));
        $this->clear();
        self::assertTrue($this->grants()->token('jti-1')?->isRevoked());

        // Saved after it: the mark is there to read, and revoking again reaches it.
        $this->grants()->saveToken(IssuedToken::issue('jti-2', TokenKind::Access, $family, 'app', self::ADA, [], null, $now, [], $now, $now->modify('+10 minutes')));
        self::assertTrue($this->grants()->isFamilyRevoked($family));
        $this->grants()->revokeFamily($family, $now, $now->modify('+30 days'));
        self::assertTrue($other->token('jti-2')?->isRevoked());
    }

    public function testTwoConnectionsRecordAClientAssertionOnce(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $other = new DoctrineGrantRepository($this->second());

        self::assertTrue($other->useAssertion(UsedAssertion::record('machine', 'jti', $now)));
        self::assertFalse($this->grants()->useAssertion(UsedAssertion::record('machine', 'jti', $now)));
        self::assertTrue($this->grants()->useAssertion(UsedAssertion::record('machine', 'JTI', $now)), 'The identifier compares byte by byte.');
    }

    public function testTwoConnectionsConsumeALinkTokenOnce(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $id = str_repeat('a', 64);
        $this->linkTokens()->save(LinkToken::issue($id, self::ADA, 'reset', 'fingerprint', $now->modify('+1 hour'), $now));
        $other = new DoctrineLinkTokenRepository($this->second());

        self::assertNotNull($this->linkTokens()->get($id));
        self::assertNotNull($other->get($id));
        self::assertTrue($other->consume($id));
        self::assertFalse($this->linkTokens()->consume($id));
        self::assertNull($this->linkTokens()->get($id), 'A token removed by the other connection is gone for this one too.');
    }

    public function testTwoConnectionsAcceptATotpCounterOnce(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $this->factors()->save(TotpCredential::enroll(self::ADA, 'plain.SECRET', $now));
        $other = new DoctrineSecondFactorRepository($this->second());
        $mine = $this->factors()->totp(self::ADA);
        $theirs = $other->totp(self::ADA);
        self::assertNotNull($mine);
        self::assertNotNull($theirs);
        $accept = static function (TotpCredential $current) use ($now): bool {
            if (57_000_000_000 <= ($current->lastCounter() ?? -1)) {
                return false;
            }

            $current->accept(57_000_000_000, $now);

            return true;
        };

        self::assertTrue($this->factors()->apply($mine, $accept));
        self::assertFalse($other->apply($theirs, $accept));
        self::assertSame(57_000_000_000, $theirs->lastCounter());
    }

    #[Override]
    protected function connection(): Connection
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        return DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
    }

    /**
     * A second entity manager on its own connection to the same server, over the tables of the first.
     */
    private function second(): EntityManager
    {
        $this->entityManager();

        if (!$this->second instanceof EntityManager) {
            $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2).'/src/Domain/Model'], true);
            $configuration->enableNativeLazyObjects(true);
            $this->second = new EntityManager($this->connection(), $configuration);
            $this->second->getEventManager()->addEventListener(Events::loadClassMetadata, new BinaryKeysListener());
        }

        return $this->second;
    }
}
