<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Database;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Override;
use Phil\PrivacyBundle\Application\Contract\GuestRequestStore;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequest;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequestKind;
use SensitiveParameter;
use SodiumException;

/**
 * The requests made without an account in `phil_privacy_guest_request`, through DBAL, every date in UTC.
 *
 * The address is sealed with libsodium under a key derived from the application secret, so that a copy of the table
 * gives no one's address; the secret of the link is only a hash. A row that cannot be opened reads as absent.
 */
final readonly class DbalGuestRequests implements GuestRequestStore
{
    private string $key;

    public function __construct(
        private Connection $connection,
        #[SensitiveParameter]
        string $secret,
    ) {
        $this->key = hash_hmac('sha256', 'phil_privacy.guest_request_store', $secret, true);
    }

    #[Override]
    public function save(GuestRequest $request): void
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $this->connection->insert(PrivacyTables::GUEST_REQUESTS, [
            'id' => $request->id,
            'kind' => $request->kind->value,
            'email' => sodium_bin2base64($nonce.sodium_crypto_secretbox($request->email, $nonce, $this->key), \SODIUM_BASE64_VARIANT_ORIGINAL),
            'token_hash' => $request->tokenHash,
            'requested_at' => $this->text($request->requestedAt),
            'expires_at' => $this->text($request->expiresAt),
        ]);
    }

    #[Override]
    public function find(string $id): ?GuestRequest
    {
        $row = $this->connection->fetchAssociative('SELECT id, kind, email, token_hash, requested_at, expires_at FROM '.PrivacyTables::GUEST_REQUESTS.' WHERE id = ?', [$id]);

        if (false === $row) {
            return null;
        }

        $email = $this->open(\is_string($row['email']) ? $row['email'] : '');
        $kind = GuestRequestKind::tryFrom(\is_string($row['kind']) ? $row['kind'] : '');

        if (null === $email || !$kind instanceof GuestRequestKind || !\is_string($row['token_hash'])) {
            return null;
        }

        try {
            $requestedAt = UtcMoments::read($this->connection, $row['requested_at'], 'requested_at');
            $expiresAt = UtcMoments::read($this->connection, $row['expires_at'], 'expires_at');
        } catch (InvalidPrivacyValue) {
            // A link whose dates cannot be read is a link that no longer works, never one that works for ever.
            return null;
        }

        return new GuestRequest($id, $kind, $email, $row['token_hash'], $requestedAt, $expiresAt);
    }

    #[Override]
    public function delete(string $id): void
    {
        $this->connection->delete(PrivacyTables::GUEST_REQUESTS, ['id' => $id]);
    }

    #[Override]
    public function retain(string $id, DateTimeImmutable $until): void
    {
        // A hash of a secret nobody holds: the link that opened the request opens it no more.
        $this->connection->update(PrivacyTables::GUEST_REQUESTS, ['token_hash' => hash('sha256', random_bytes(32)), 'expires_at' => $this->text($until)], ['id' => $id]);
    }

    #[Override]
    public function purgeExpired(DateTimeImmutable $now): int
    {
        return (int) $this->connection->executeStatement('DELETE FROM '.PrivacyTables::GUEST_REQUESTS.' WHERE expires_at <= ?', [$this->text($now)]);
    }

    private function open(string $sealed): ?string
    {
        try {
            $bytes = sodium_base642bin($sealed, \SODIUM_BASE64_VARIANT_ORIGINAL);
            // A payload too short for a nonce throws, and one too short for the MAC does not open.
            $email = sodium_crypto_secretbox_open(substr($bytes, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($bytes, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key);
        } catch (SodiumException) {
            return null;
        }

        return false === $email ? null : $email;
    }

    private function text(DateTimeImmutable $moment): string
    {
        return UtcMoments::text($this->connection, $moment);
    }
}
