<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use JsonException;
use Phil\UserSecretsBundle\Application\Encryption\Envelope;
use Phil\UserSecretsBundle\Application\Exception\SealingFailed;
use Phil\UserSecretsBundle\Application\Exception\UnknownKey;
use Phil\UserSecretsBundle\Application\Exception\UnreadableSecret;
use Phil\UserSecretsBundle\Domain\Credential\ApiKey;
use Phil\UserSecretsBundle\Domain\Credential\CredentialKind;
use Phil\UserSecretsBundle\Domain\Credential\OAuthToken;
use Phil\UserSecretsBundle\Domain\Encryption\SealedAlgorithm;
use Phil\UserSecretsBundle\Domain\Encryption\SealedFormat;
use Phil\UserSecretsBundle\Domain\Exception\InvalidSecret;
use Phil\UserSecretsBundle\Domain\Model\StoredSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SealedSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use Phil\UserSecretsBundle\Infrastructure\KeyManagement\KeyManagementCipher;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\FailingKms;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\Keys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\KeyManagement\Envelope as KmsEnvelope;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\Exception\RuntimeException as KmsRuntimeException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\SodiumKms;
use Symfony\Component\KeyManagement\StoredFormat;
use Throwable;

#[CoversClass(KeyManagementCipher::class)]
#[CoversClass(Envelope::class)]
#[CoversClass(UnknownKey::class)]
#[CoversClass(UnreadableSecret::class)]
#[CoversClass(SealingFailed::class)]
final class EncryptionTest extends TestCase
{
    private const string ROW = '["member-1","openai","openai","api_key"]';

    private const string HEADED_ROW = '[3,1,"member-1","openai","openai","api_key"]';

    public function testTheKeyManagementCipherSealsAnEnvelopeUnderTheMasterKeyItIsGiven(): void
    {
        $cipher = new KeyManagementCipher(Keys::kms());
        $first = $cipher->seal('k2', 'hello', 'row');
        $second = $cipher->seal('k2', 'hello', 'row');

        self::assertNotSame($first, $second);
        self::assertSame('k2', KmsEnvelope::fromBytes($first)->keyId);
        self::assertSame('hello', $cipher->open('k2', $first, 'row'));
        self::assertSame('hello', $cipher->open('k2', $second, 'row'));
        self::assertSame('', $cipher->open('k1', $cipher->seal('k1', '', ''), ''));
    }

    public function testTheKeyManagementCipherOpensUnderTheMasterKeyTheEnvelopeNames(): void
    {
        $cipher = new KeyManagementCipher(Keys::kms());

        self::assertSame('hello', $cipher->open('k2', $cipher->seal('k1', 'hello', 'row'), 'row'));
    }

    public function testAKeyManagementValueOpensOnlyWithItsAdditionalData(): void
    {
        $cipher = new KeyManagementCipher(Keys::kms());

        try {
            $cipher->open('k1', $cipher->seal('k1', 'hello', 'row'), 'another row');
            self::fail('A value opened with other additional data.');
        } catch (UnreadableSecret $unreadableSecret) {
            self::assertSame('A sealed secret does not open: it was altered, moved to another row, or sealed under a master key the KMS does not hold.', $unreadableSecret->getMessage());
            self::assertInstanceOf(DecryptionFailedException::class, $unreadableSecret->getPrevious());
        }
    }

    public function testAnAlteredKeyManagementValueDoesNotOpen(): void
    {
        $cipher = new KeyManagementCipher(Keys::kms());
        $raw = $cipher->seal('k1', 'hello', 'row');
        $raw[\strlen($raw) - 1] = \chr(\ord($raw[\strlen($raw) - 1]) ^ 1);

        $this->expectException(UnreadableSecret::class);
        $this->expectExceptionMessageIsOrContains('A sealed secret does not open');

        $cipher->open('k1', $raw, 'row');
    }

    public function testAKeyManagementValueUnderAMasterKeyTheKmsDoesNotHoldDoesNotOpen(): void
    {
        $sealed = new KeyManagementCipher(Keys::kms())->seal('k2', 'hello', 'row');

        $this->expectException(UnreadableSecret::class);
        $this->expectExceptionMessageIsOrContains('sealed under a master key the KMS does not hold');

        new KeyManagementCipher(Keys::kms(['k1' => 'a']))->open('k2', $sealed, 'row');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedKeyManagementValues(): iterable
    {
        yield 'nothing' => [''];
        yield 'not an envelope' => ["\x09junk"];
    }

    #[DataProvider('malformedKeyManagementValues')]
    public function testAMalformedKeyManagementValueIsUnreadable(string $envelope): void
    {
        try {
            new KeyManagementCipher(Keys::kms())->open('k1', $envelope, 'row');
            self::fail('A malformed value opened.');
        } catch (UnreadableSecret $unreadableSecret) {
            self::assertSame('A sealed secret is malformed.', $unreadableSecret->getMessage());
            self::assertInstanceOf(InvalidArgumentException::class, $unreadableSecret->getPrevious());
        }
    }

    public function testAnEnvelopeReferringToAStoredDataKeyDoesNotOpen(): void
    {
        $format = new StoredFormat();
        $envelope = KmsEnvelope::referencing('reference', str_repeat("\0", $format->ivBytes()), str_repeat("\0", $format->tagBytes()), 'ciphertext', str_repeat("\0", $format->saltBytes()));

        try {
            new KeyManagementCipher(Keys::kms())->open('k1', (string) $envelope, 'row');
            self::fail('An envelope of a stored data key opened.');
        } catch (UnreadableSecret $unreadableSecret) {
            self::assertStringStartsWith('A sealed secret does not open', $unreadableSecret->getMessage());
            self::assertInstanceOf(LogicException::class, $unreadableSecret->getPrevious());
        }
    }

    public function testSealingUnderAMasterKeyTheKmsDoesNotHoldNamesTheCurrentKey(): void
    {
        try {
            new KeyManagementCipher(Keys::kms())->seal('k9', 'hello', 'row');
            self::fail('A value sealed under an unknown master key.');
        } catch (UnknownKey $unknownKey) {
            self::assertSame('The KMS has no master key "k9" to seal with: check phil_user_secrets.current_key.', $unknownKey->getMessage());
            self::assertInstanceOf(KeyNotFoundException::class, $unknownKey->getPrevious());
        }
    }

    public function testTheKeyManagementCipherSealsThroughAnyKms(): void
    {
        $cipher = new KeyManagementCipher(new SodiumKms(new InMemoryKeyLoader(['master' => str_repeat('m', 32)])));

        self::assertSame('hello', $cipher->open('master', $cipher->seal('master', 'hello', 'row'), 'row'));
    }

    /**
     * @return iterable<string, array{Throwable}>
     */
    public static function kmsFailures(): iterable
    {
        yield 'a failure of the component' => [new KmsRuntimeException('The KMS is unreachable.')];
        yield 'a failure of its client' => [new RuntimeException('The KMS is unreachable.')];
        yield 'a refusal of the component' => [new InvalidArgumentException('The KMS is unreachable.')];
    }

    #[DataProvider('kmsFailures')]
    public function testAFailureOfTheKmsWhileOpeningIsAnUnreadableSecretThatKeepsIt(Throwable $failure): void
    {
        $sealed = new KeyManagementCipher(Keys::kms())->seal('k1', 'hello', 'row');

        try {
            new KeyManagementCipher(new FailingKms($failure))->open('k1', $sealed, 'row');
            self::fail('A value opened without its KMS.');
        } catch (UnreadableSecret $unreadableSecret) {
            self::assertSame('The KMS did not open a sealed secret of the master key "k1": The KMS is unreachable.', $unreadableSecret->getMessage());
            self::assertSame($failure, $unreadableSecret->getPrevious());
        }
    }

    #[DataProvider('kmsFailures')]
    public function testAFailureOfTheKmsWhileSealingIsASealingFailureThatKeepsIt(Throwable $failure): void
    {
        try {
            new KeyManagementCipher(new FailingKms($failure))->seal('k1', 'hello', 'row');
            self::fail('A value sealed without its KMS.');
        } catch (SealingFailed $sealingFailed) {
            self::assertSame('The KMS did not seal a value under the master key "k1": The KMS is unreachable.', $sealingFailed->getMessage());
            self::assertSame($failure, $sealingFailed->getPrevious());
        }
    }

    public function testTheEnvelopeSealsUnderTheCurrentKeyAndOpensTheCredentialOfItsRow(): void
    {
        $envelope = Keys::envelope('k2');
        $token = OAuthToken::of('at', 'rt', new DateTimeImmutable('@2000'), ['repo']);
        $sealed = $envelope->seal(Owner::fromString('member-1'), SecretName::fromString('github'), ServiceName::fromString('github'), $token);

        self::assertSame('k2', $sealed->keyId);
        self::assertSame('k2', $envelope->currentKey());
        self::assertSame(SealedFormat::Algorithm, $sealed->format());
        self::assertSame("\3\1", substr((string) $sealed->bytes(), 0, 2));
        self::assertSame('k2', KmsEnvelope::fromBytes($this->envelopeOf($sealed))->keyId);
        self::assertSame($token->payload(), $envelope->open($this->row($sealed, CredentialKind::OAuthToken))->payload());
    }

    public function testTheSealedValueNamesItsRowInClearAndAsAdditionalData(): void
    {
        $sealed = Keys::envelope()->seal(Owner::fromString('member-1'), SecretName::fromString('openai'), ServiceName::fromString('openai'), ApiKey::of('sk-1'));

        self::assertSame('{"owner":"member-1","name":"openai","service":"openai","kind":"api_key","credential":{"key":"sk-1"}}', new KeyManagementCipher(Keys::kms())->open('k1', $this->envelopeOf($sealed), self::HEADED_ROW));
    }

    public function testTheHeaderIsAuthenticatedWithTheValue(): void
    {
        $sealed = Keys::envelope()->seal(Owner::fromString('member-1'), SecretName::fromString('openai'), ServiceName::fromString('openai'), ApiKey::of('sk-1'));
        // The same envelope behind the header of the earlier format: the additional data no longer matches.
        $downgraded = new SealedSecret('k1', base64_encode("\2".$this->envelopeOf($sealed)));

        $this->expectException(UnreadableSecret::class);
        $this->expectExceptionMessageIsOrContains('A sealed secret does not open');

        Keys::envelope()->open($this->row($downgraded, CredentialKind::ApiKey, 'openai'));
    }

    public function testAValueOfTheEarlierFormatStillOpens(): void
    {
        $plaintext = '{"owner":"member-1","name":"openai","service":"openai","kind":"api_key","credential":{"key":"sk-1"}}';
        $legacy = Keys::sealed($plaintext, self::ROW);

        self::assertSame(SealedFormat::KeyManagement, $legacy->format());
        self::assertSame(['key' => 'sk-1'], Keys::envelope()->open($this->row($legacy, CredentialKind::ApiKey, 'openai'))->payload());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownAlgorithms(): iterable
    {
        yield 'no algorithm' => [base64_encode("\3")];
        yield 'the byte before it' => [base64_encode("\3\0later")];
        yield 'the byte after it' => [base64_encode("\3\2later")];
        yield 'a known byte after the unknown one' => [base64_encode("\3\x09\1later")];
    }

    #[DataProvider('unknownAlgorithms')]
    public function testAValueOfNoKnownAlgorithmIsUnreadable(string $ciphertext): void
    {
        $this->expectException(UnreadableSecret::class);
        $this->expectExceptionMessageIsOrContains('The sealed value of secret "openai" names no known algorithm.');

        Keys::envelope()->open($this->row(new SealedSecret('k1', $ciphertext), CredentialKind::ApiKey, 'openai'));
    }

    public function testTheKeyManagementCipherNamesItsAlgorithm(): void
    {
        self::assertSame(SealedAlgorithm::KeyManagementAes256Gcm, new KeyManagementCipher(Keys::kms())->algorithm());
    }

    public function testAKeyManagementValueCopiedToAnotherRowDoesNotOpen(): void
    {
        $sealed = Keys::envelope()->seal(Owner::fromString('member-2'), SecretName::fromString('openai'), ServiceName::fromString('openai'), ApiKey::of('sk-1'));

        $this->expectException(UnreadableSecret::class);
        $this->expectExceptionMessageIsOrContains('moved to another row');

        Keys::envelope()->open($this->row($sealed, CredentialKind::ApiKey, 'openai'));
    }

    public function testTheEnvelopeResealsACredentialUnderTheCurrentKey(): void
    {
        $plaintext = '{"owner":"member-1","name":"openai","service":"openai","kind":"api_key","credential":{"key":"sk-1"}}';
        $envelope = Keys::envelope('k2');
        $resealed = $envelope->reseal($this->row(Keys::sealed($plaintext, self::ROW), CredentialKind::ApiKey, 'openai'));

        self::assertSame('k2', $resealed->keyId);
        self::assertSame(SealedFormat::Algorithm, $resealed->format());
        self::assertSame('k2', KmsEnvelope::fromBytes($this->envelopeOf($resealed))->keyId);
        self::assertSame($plaintext, new KeyManagementCipher(Keys::kms())->open('k2', $this->envelopeOf($resealed), self::HEADED_ROW));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownFormats(): iterable
    {
        yield 'unknown version' => [base64_encode("\x7Flater")];
        yield 'the version before it' => [base64_encode("\1later")];
        yield 'the version after it' => [base64_encode("\4later")];
        yield 'not base64' => ['%%%'];
    }

    #[DataProvider('unknownFormats')]
    public function testAValueOfNoKnownFormatIsUnreadable(string $ciphertext): void
    {
        $this->expectException(UnreadableSecret::class);
        $this->expectExceptionMessageIsOrContains('The sealed value of secret "openai" names no known format.');

        Keys::envelope()->open($this->row(new SealedSecret('k1', $ciphertext), CredentialKind::ApiKey, 'openai'));
    }

    /**
     * @return iterable<string, array{string, CredentialKind}>
     */
    public static function foreignValues(): iterable
    {
        yield 'scalar' => ['"text"', CredentialKind::ApiKey];
        yield 'another owner' => ['{"owner":"member-2","name":"openai","service":"openai","kind":"api_key","credential":{"key":"sk"}}', CredentialKind::ApiKey];
        yield 'another name' => ['{"owner":"member-1","name":"github","service":"openai","kind":"api_key","credential":{"key":"sk"}}', CredentialKind::ApiKey];
        yield 'another service' => ['{"owner":"member-1","name":"openai","service":"github","kind":"api_key","credential":{"key":"sk"}}', CredentialKind::ApiKey];
        yield 'another kind' => ['{"owner":"member-1","name":"openai","service":"openai","kind":"api_key","credential":{"key":"sk"}}', CredentialKind::OAuthToken];
        yield 'no credential' => ['{"owner":"member-1","name":"openai","service":"openai","kind":"api_key","credential":"sk"}', CredentialKind::ApiKey];
        yield 'nothing' => ['{}', CredentialKind::ApiKey];
    }

    #[DataProvider('foreignValues')]
    public function testAValueOfAnotherRowDoesNotOpen(string $plaintext, CredentialKind $kind): void
    {
        $this->expectException(UnreadableSecret::class);
        $this->expectExceptionMessageIsOrContains('The sealed value of secret "openai" belongs to another row.');

        Keys::envelope()->open($this->row(Keys::sealed($plaintext, $this->aad('openai', $kind)), $kind, 'openai'));
    }

    public function testAValueThatIsNotJsonIsUnreadable(): void
    {
        try {
            Keys::envelope()->open($this->row(Keys::sealed('{', $this->aad('github', CredentialKind::ApiKey)), CredentialKind::ApiKey));
            self::fail('A value that is not JSON opened.');
        } catch (UnreadableSecret $unreadableSecret) {
            self::assertSame('A sealed secret holds no credential.', $unreadableSecret->getMessage());
            self::assertInstanceOf(JsonException::class, $unreadableSecret->getPrevious());
        }
    }

    public function testACredentialOfTheWrongShapeIsUnreadable(): void
    {
        $plaintext = '{"owner":"member-1","name":"openai","service":"openai","kind":"api_key","credential":{"token":"sk"}}';

        try {
            Keys::envelope()->open($this->row(Keys::sealed($plaintext, self::ROW), CredentialKind::ApiKey, 'openai'));
            self::fail('A credential of the wrong shape opened.');
        } catch (UnreadableSecret $unreadableSecret) {
            self::assertSame('The sealed value of secret "openai" is not a credential of its kind.', $unreadableSecret->getMessage());
            self::assertInstanceOf(InvalidSecret::class, $unreadableSecret->getPrevious());
        }
    }

    /**
     * The bytes after the header of the current format.
     */
    private function envelopeOf(SealedSecret $sealed): string
    {
        return substr(sodium_base642bin($sealed->ciphertext, \SODIUM_BASE64_VARIANT_ORIGINAL), 2);
    }

    private function aad(string $name, CredentialKind $kind): string
    {
        return json_encode(['member-1', $name, $name, $kind->value], \JSON_THROW_ON_ERROR);
    }

    private function row(SealedSecret $sealed, CredentialKind $kind, string $name = 'github'): StoredSecret
    {
        return StoredSecret::seal(Owner::fromString('member-1'), SecretName::fromString($name), ServiceName::fromString($name), $kind, $sealed, null, new DateTimeImmutable('@1000'));
    }
}
