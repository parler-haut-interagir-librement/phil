<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\UserSecretsBundle\Application\Audit\AuditTrail;
use Phil\UserSecretsBundle\Application\Audit\SecretEvent;
use Phil\UserSecretsBundle\Application\Audit\SecretEventKind;
use Phil\UserSecretsBundle\Application\KeyRotation;
use Phil\UserSecretsBundle\Application\UserSecrets;
use Phil\UserSecretsBundle\Domain\Credential\ApiKey;
use Phil\UserSecretsBundle\Domain\Credential\CredentialKind;
use Phil\UserSecretsBundle\Domain\Credential\OAuthToken;
use Phil\UserSecretsBundle\Domain\Model\StoredSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SealedSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use Phil\UserSecretsBundle\Infrastructure\Audit\LoggerAuditor;
use Phil\UserSecretsBundle\Infrastructure\Console\GenerateKeyCommand;
use Phil\UserSecretsBundle\Infrastructure\Console\RotateKeysCommand;
use Phil\UserSecretsBundle\Infrastructure\Memory\InMemoryStoredSecretRepository;
use Phil\UserSecretsBundle\Infrastructure\Privacy\UserSecretsPrivacy;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\DetachedRepository;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\Keys;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\MeanwhileRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\Local\SodiumKmsFactory;

#[CoversClass(UserSecretsPrivacy::class)]
#[CoversClass(LoggerAuditor::class)]
#[CoversClass(GenerateKeyCommand::class)]
#[CoversClass(RotateKeysCommand::class)]
final class IntegrationsTest extends TestCase
{
    public function testThePrivacyExportListsSecretsWithoutValues(): void
    {
        $clock = new MockClock('2026-11-06 10:00:00 UTC');
        $secrets = $this->secrets($clock);
        $secrets->store(Owner::fromString('member-1'), SecretName::fromString('openai'), ServiceName::fromString('openai'), ApiKey::of('sk-secret'));
        $secrets->store(Owner::fromString('member-1'), SecretName::fromString('github'), ServiceName::fromString('github'), OAuthToken::of('at-secret', null, new DateTimeImmutable('2026-12-01 00:00:00 UTC')));
        $secrets->revoke(Owner::fromString('member-1'), SecretName::fromString('openai'));

        $privacy = new UserSecretsPrivacy($secrets);

        self::assertSame('user_secrets', $privacy->module()->value);
        self::assertSame(['secrets' => [
            ['name' => 'github', 'service' => 'github', 'kind' => 'oauth_token', 'created_at' => '2026-11-06T10:00:00+00:00', 'updated_at' => '2026-11-06T10:00:00+00:00', 'last_used_at' => null, 'expires_at' => '2026-12-01T00:00:00+00:00', 'revoked_at' => null],
            ['name' => 'openai', 'service' => 'openai', 'kind' => 'api_key', 'created_at' => '2026-11-06T10:00:00+00:00', 'updated_at' => '2026-11-06T10:00:00+00:00', 'last_used_at' => null, 'expires_at' => null, 'revoked_at' => '2026-11-06T10:00:00+00:00'],
        ]], $privacy->export(DataSubject::fromString('member-1')));
        self::assertSame([], $privacy->export(DataSubject::fromString('member-2')));
    }

    public function testThePrivacyErasureRemovesEverySecretOfThePerson(): void
    {
        $secrets = $this->secrets(new MockClock());
        $secrets->store(Owner::fromString('member-1'), SecretName::fromString('openai'), ServiceName::fromString('openai'), ApiKey::of('sk-secret'));

        $privacy = new UserSecretsPrivacy($secrets);

        $report = $privacy->erase(DataSubject::fromString('member-1'));
        self::assertSame('user_secrets', $report->module->value);
        self::assertSame(['phil_user_secret' => ErasureEffect::Deleted], $report->effects);
        self::assertSame([], $secrets->secrets(Owner::fromString('member-1')));
        self::assertSame(['phil_user_secret' => ErasureEffect::Deleted], $privacy->erase(DataSubject::fromString('member-1'))->effects);
    }

    public function testTheLoggerAuditorWritesEventsWithoutValues(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{mixed, string, array<array-key, mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [$level, (string) $message, $context];
            }
        };

        new LoggerAuditor($logger)->record(new SecretEvent(SecretEventKind::Revealed, Owner::fromString('member-1'), SecretName::fromString('openai'), new DateTimeImmutable('2026-11-06 10:00:00 UTC'), 'drafting'));

        self::assertSame([['info', 'User secret {secret} of {owner}: {event}.', ['event' => 'revealed', 'owner' => 'member-1', 'secret' => 'openai', 'at' => '2026-11-06T10:00:00+00:00', 'purpose' => 'drafting']]], $logger->records);
    }

    public function testTheGeneratedKeyIsAMasterKeyOfASodiumDsn(): void
    {
        $tester = new CommandTester(new GenerateKeyCommand());

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $key = trim($tester->getDisplay());
        self::assertSame(32, \strlen(sodium_base642bin($key, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING)));
        self::assertSame(43, \strlen($key));
        $kms = new SodiumKmsFactory()->create(Dsn::fromString('sodium://?keys[k1]='.$key));
        self::assertSame('hello', $kms->decrypt($kms->encrypt('k1', 'hello')));
        $tester->execute([]);
        self::assertNotSame($key, trim($tester->getDisplay()));
    }

    public function testTheRotationCommandSaysHowManySecretsWereSealedAgain(): void
    {
        $clock = new MockClock();
        $repository = new InMemoryStoredSecretRepository();
        $old = new UserSecrets($repository, Keys::envelope('k1'), new AuditTrail($clock), $clock);
        $old->store(Owner::fromString('member-1'), SecretName::fromString('a'), ServiceName::fromString('openai'), ApiKey::of('sk-a'));
        $old->store(Owner::fromString('member-1'), SecretName::fromString('b'), ServiceName::fromString('openai'), ApiKey::of('sk-b'));

        $tester = new CommandTester(new RotateKeysCommand(new KeyRotation($repository, Keys::envelope('k2'), new AuditTrail($clock))));

        self::assertSame(Command::SUCCESS, $tester->execute(['--batch' => '1']));
        self::assertSame("2 secrets re-sealed under the current key.\n", $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame("0 secrets re-sealed under the current key.\n", $tester->getDisplay());

        foreach (['0', 'many', '1.5'] as $batch) {
            self::assertSame(Command::INVALID, $tester->execute(['--batch' => $batch]));
            self::assertSame("The batch is a whole number of at least 1.\n", $tester->getDisplay());
        }
    }

    public function testTheRotationCommandNamesTheSecretsThatDidNotOpenAndFails(): void
    {
        $clock = new MockClock();
        $repository = new InMemoryStoredSecretRepository();
        $repository->save(
            StoredSecret::seal(Owner::fromString('member-1'), SecretName::fromString('a'), ServiceName::fromString('openai'), CredentialKind::ApiKey, new SealedSecret('k1', base64_encode("\1old")), null, new DateTimeImmutable('@1000')),
            StoredSecret::seal(Owner::fromString('member-2'), SecretName::fromString('b'), ServiceName::fromString('openai'), CredentialKind::ApiKey, new SealedSecret('k1', base64_encode("\1old")), null, new DateTimeImmutable('@1000')),
        );
        new UserSecrets($repository, Keys::envelope('k1'), new AuditTrail($clock), $clock)
            ->store(Owner::fromString('member-1'), SecretName::fromString('c'), ServiceName::fromString('openai'), ApiKey::of('sk-c'));

        $tester = new CommandTester(new RotateKeysCommand(new KeyRotation($repository, Keys::envelope('k2'), new AuditTrail($clock))));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertSame("1 secrets re-sealed under the current key.\n2 secrets did not open and stay under their key:\n  member-1 / a\n  member-2 / b\n", $tester->getDisplay());
    }

    public function testTheRotationCommandSaysHowManySecretsAnotherProcessChangedMeanwhile(): void
    {
        $clock = new MockClock();
        $rows = new DetachedRepository();
        new UserSecrets($rows, Keys::envelope('k1'), new AuditTrail($clock), $clock)
            ->store(Owner::fromString('member-1'), SecretName::fromString('a'), ServiceName::fromString('openai'), ApiKey::of('sk-a'));
        $other = new UserSecrets($rows, Keys::envelope('k2'), new AuditTrail($clock), $clock);
        $repository = new MeanwhileRepository($rows, static function () use ($other): void {
            $other->revoke(Owner::fromString('member-1'), SecretName::fromString('a'));
        });

        $tester = new CommandTester(new RotateKeysCommand(new KeyRotation($repository, Keys::envelope('k2'), new AuditTrail($clock))));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame("0 secrets re-sealed under the current key.\n1 secrets changed by another process meanwhile, left as it wrote them.\n", $tester->getDisplay());
    }

    public function testTheDryRunCountsTheSecretsLeftByKeyWithoutWriting(): void
    {
        $clock = new MockClock();
        $kms = Keys::kms(['k0' => 'c', 'k1' => 'a', 'k2' => 'b']);
        $repository = new InMemoryStoredSecretRepository();
        new UserSecrets($repository, Keys::envelope('k1'), new AuditTrail($clock), $clock)
            ->store(Owner::fromString('member-1'), SecretName::fromString('a'), ServiceName::fromString('openai'), ApiKey::of('sk-a'));
        $old = new UserSecrets($repository, Keys::envelope('k0', $kms), new AuditTrail($clock), $clock);
        $old->store(Owner::fromString('member-1'), SecretName::fromString('b'), ServiceName::fromString('openai'), ApiKey::of('sk-b'));
        $old->store(Owner::fromString('member-2'), SecretName::fromString('c'), ServiceName::fromString('openai'), ApiKey::of('sk-c'));
        new UserSecrets($repository, Keys::envelope('k2'), new AuditTrail($clock), $clock)
            ->store(Owner::fromString('member-2'), SecretName::fromString('d'), ServiceName::fromString('openai'), ApiKey::of('sk-d'));

        $tester = new CommandTester(new RotateKeysCommand(new KeyRotation($repository, Keys::envelope('k2', $kms), new AuditTrail($clock))));

        self::assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true, '--batch' => '0']));
        self::assertSame("k0: 2 secrets to re-seal\nk1: 1 secrets to re-seal\n3 secrets left to re-seal under the current key.\n", $tester->getDisplay());
        self::assertSame('k0', $repository->get(Owner::fromString('member-1'), SecretName::fromString('b'))?->sealed()->keyId, 'nothing was written');

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));
        self::assertSame("0 secrets left to re-seal under the current key.\n", $tester->getDisplay());
    }

    private function secrets(MockClock $clock): UserSecrets
    {
        return new UserSecrets(new InMemoryStoredSecretRepository(), Keys::envelope(), new AuditTrail($clock), $clock);
    }
}
