<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application\Encryption;

use JsonException;
use Phil\UserSecretsBundle\Application\Contract\EnvelopeCipher;
use Phil\UserSecretsBundle\Application\Exception\SealingFailed;
use Phil\UserSecretsBundle\Application\Exception\UnknownKey;
use Phil\UserSecretsBundle\Application\Exception\UnreadableSecret;
use Phil\UserSecretsBundle\Domain\Contract\Credential;
use Phil\UserSecretsBundle\Domain\Credential\CredentialKind;
use Phil\UserSecretsBundle\Domain\Encryption\SealedAlgorithm;
use Phil\UserSecretsBundle\Domain\Encryption\SealedFormat;
use Phil\UserSecretsBundle\Domain\Exception\InvalidSecret;
use Phil\UserSecretsBundle\Domain\Exception\RevokedSecret;
use Phil\UserSecretsBundle\Domain\Model\StoredSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SealedSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;

/**
 * A credential sealed with the row it belongs to: the owner, the name, the service and the kind are sealed beside the
 * credential, and checked when it opens.
 *
 * Encryption authenticates the ciphertext but not where it lies. Without the binding, whoever can write to the table
 * could copy one person's ciphertext into another person's row, and the second person's tasks would use the first
 * person's key; with it, the copy does not open. The row is also the additional authenticated data of the envelope,
 * with the header in the current format.
 *
 * Values are sealed through the cipher under the current master key as the configuration names it, behind a header:
 * the format's byte, then the byte of the cipher's algorithm. A value opens through the same cipher, under the master
 * key its envelope names; a value of the earlier format, without the algorithm's byte, still opens.
 */
final readonly class Envelope
{
    public function __construct(
        private EnvelopeCipher $cipher,
        private string $currentKey,
    ) {
    }

    /**
     * @throws UnknownKey    when there is no master key of the current identifier
     * @throws SealingFailed when the KMS is unreachable or refuses
     */
    public function seal(Owner $owner, SecretName $name, ServiceName $service, Credential $credential): SealedSecret
    {
        $plaintext = json_encode([
            'owner' => $owner->value,
            'name' => $name->value,
            'service' => $service->value,
            'kind' => $credential->kind()->value,
            'credential' => $credential->payload(),
        ], \JSON_THROW_ON_ERROR);
        $format = SealedFormat::current();
        $algorithm = $this->cipher->algorithm();
        $envelope = $this->cipher->seal($this->currentKey, $plaintext, $this->aad($format, $algorithm, $owner, $name, $service, $credential->kind()));
        sodium_memzero($plaintext);

        return new SealedSecret($this->currentKey, sodium_bin2base64(\chr($format->value).\chr($algorithm->value).$envelope, \SODIUM_BASE64_VARIANT_ORIGINAL));
    }

    /**
     * @throws RevokedSecret
     * @throws UnreadableSecret
     */
    public function open(StoredSecret $secret): Credential
    {
        $sealed = $secret->sealed();
        $bytes = $sealed->bytes() ?? '';
        $format = $sealed->format();
        $name = $secret->name()->value;

        [$algorithm, $envelope] = match ($format) {
            SealedFormat::KeyManagement => [SealedAlgorithm::KeyManagementAes256Gcm, substr($bytes, 1)],
            SealedFormat::Algorithm => [SealedAlgorithm::tryFrom(\ord(($bytes."\0\0")[1])), substr($bytes, 2)],
            null => throw UnreadableSecret::because(\sprintf('The sealed value of secret "%s" names no known format.', $name)),
        };

        if (!$algorithm instanceof SealedAlgorithm) {
            throw UnreadableSecret::because(\sprintf('The sealed value of secret "%s" names no known algorithm.', $name));
        }

        $plaintext = $this->cipher->open($sealed->keyId, $envelope, $this->aad($format, $algorithm, $secret->owner(), $secret->name(), $secret->service(), $secret->kind()));

        try {
            $data = json_decode($plaintext, true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw UnreadableSecret::because('A sealed secret holds no credential.', $jsonException);
        }

        if (!\is_array($data)
            || ($data['owner'] ?? null) !== $secret->owner()->value
            || ($data['name'] ?? null) !== $secret->name()->value
            || ($data['service'] ?? null) !== $secret->service()->value
            || ($data['kind'] ?? null) !== $secret->kind()->value
            || !\is_array($data['credential'] ?? null)) {
            throw UnreadableSecret::because(\sprintf('The sealed value of secret "%s" belongs to another row.', $secret->name()->value));
        }

        try {
            return $secret->kind()->credential($data['credential']);
        } catch (InvalidSecret $invalidSecret) {
            throw UnreadableSecret::because(\sprintf('The sealed value of secret "%s" is not a credential of its kind.', $secret->name()->value), $invalidSecret);
        }
    }

    /**
     * The same credential sealed again under the current key.
     *
     * @throws RevokedSecret
     * @throws UnknownKey
     * @throws UnreadableSecret
     */
    public function reseal(StoredSecret $secret): SealedSecret
    {
        return $this->seal($secret->owner(), $secret->name(), $secret->service(), $this->open($secret));
    }

    /**
     * The identifier of the master key every new value is sealed under, as the configuration names it.
     */
    public function currentKey(): string
    {
        return $this->currentKey;
    }

    /**
     * The row the value belongs to, with the header in the current format; the row alone in the earlier one.
     */
    private function aad(SealedFormat $format, SealedAlgorithm $algorithm, Owner $owner, SecretName $name, ServiceName $service, CredentialKind $kind): string
    {
        $row = [$owner->value, $name->value, $service->value, $kind->value];

        return json_encode(SealedFormat::KeyManagement === $format ? $row : [$format->value, $algorithm->value, ...$row], \JSON_THROW_ON_ERROR);
    }
}
