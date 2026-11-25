<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Jitsi;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use OpenSSLAsymmetricKey;
use Override;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Phil\VirtualClassroom\Capabilities;
use Phil\VirtualClassroom\Contract\VirtualClassroom;
use Phil\VirtualClassroom\Event\ParticipantJoined;
use Phil\VirtualClassroom\Event\ParticipantLeft;
use Phil\VirtualClassroom\Event\RecordingReady;
use Phil\VirtualClassroom\Event\RoomEnded;
use Phil\VirtualClassroom\Exception\InvalidValue;
use Phil\VirtualClassroom\Exception\InvalidWebhook;
use Phil\VirtualClassroom\Exception\Unsupported;
use Phil\VirtualClassroom\OpenedRoom;
use Phil\VirtualClassroom\Participant;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\Presences;
use Phil\VirtualClassroom\Recording;
use Phil\VirtualClassroom\Role;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroom\RoomSpec;
use Phil\VirtualClassroom\Timestamps;
use Phil\VirtualClassroom\Token\JsonWebToken;
use Phil\VirtualClassroom\Webhook;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

/**
 * A Jitsi Meet server: a room exists once someone enters it, by a link that carries a JSON Web Token naming the room,
 * the participant, whether they moderate, and when the link expires. A server of the application's own, set up for
 * tokens, reads one signed with HS256 under its application secret; JaaS, 8x8's hosted Jitsi, reads one signed with
 * RS256 under the private key of the application's API key, its identifier in the `kid` header.
 *
 * Jitsi has no API to end a room, to say who is in it or to list its recordings: those calls throw `Unsupported`.
 * The attendance and the recordings come by notifications, when a webhook secret is set: JaaS posts them signed with
 * HMAC-SHA-256 in `X-Jaas-Signature` — participants joining and leaving, the room destroyed, a recording uploaded
 * with a download link that works for a day —; a server of the application's own sends them only through a module
 * that posts the same events signed the same way.
 */
final readonly class Jitsi implements VirtualClassroom
{
    public const string JAAS_SERVER = 'https://8x8.vc';

    public const int MAX_WEBHOOK_BYTES = 262_144;

    public const int SIGNATURE_TOLERANCE = 300;

    private function __construct(
        private Url $server,
        private string $appId,
        #[SensitiveParameter]
        private string|OpenSSLAsymmetricKey $key,
        private ?string $keyId,
        private ClockInterface $clock,
        private int $tokenTtl,
        #[SensitiveParameter]
        private ?string $webhookSecret,
        private string $audience,
        private ?string $subject,
    ) {
    }

    /**
     * A server of the application's own, its prosody set up for tokens with this application identifier and secret.
     *
     * @param int     $tokenTtl how long a join link lets its participant in, in seconds
     * @param ?string $subject  the `sub` claim, the host of the server by default
     *
     * @throws InvalidValue when the address is not https, the identifier is empty or a secret is shorter than 16 bytes
     */
    public static function selfHosted(
        Url $server,
        string $appId,
        #[SensitiveParameter]
        string $appSecret,
        ClockInterface $clock,
        int $tokenTtl = 10_800,
        #[SensitiveParameter]
        ?string $webhookSecret = null,
        string $audience = 'jitsi',
        ?string $subject = null,
    ): self {
        if (\strlen($appSecret) < 16) {
            throw InvalidValue::because('The application secret of a Jitsi server is 16 bytes or more.');
        }

        return self::checked(new self($server, $appId, $appSecret, null, $clock, $tokenTtl, $webhookSecret, $audience, $subject ?? $server->host));
    }

    /**
     * JaaS: the application identifier `vpaas-magic-cookie-…`, the identifier of the API key, and its private key.
     *
     * @throws InvalidValue when the key is not an RSA private key, or another value is out of its bounds
     */
    public static function jaas(
        string $appId,
        string $keyId,
        #[SensitiveParameter]
        string $privateKey,
        ClockInterface $clock,
        int $tokenTtl = 10_800,
        #[SensitiveParameter]
        ?string $webhookSecret = null,
        ?Url $server = null,
    ): self {
        $key = openssl_pkey_get_private($privateKey);
        $details = false === $key ? false : openssl_pkey_get_details($key);

        if (false === $key || !\is_array($details) || \OPENSSL_KEYTYPE_RSA !== ($details['type'] ?? null)) {
            throw InvalidValue::because('The private key of a JaaS API key is an RSA key in PEM.');
        }

        if (1 !== preg_match('#^[A-Za-z0-9._:/-]{1,256}$#D', $keyId)) {
            throw InvalidValue::because('The identifier of a JaaS API key is 1 to 256 letters, digits, ".", "_", ":", "/" or "-".');
        }

        return self::checked(new self($server ?? Url::fromString(self::JAAS_SERVER), $appId, $key, $keyId, $clock, $tokenTtl, $webhookSecret, 'jitsi', $appId));
    }

    #[Override]
    public function capabilities(): Capabilities
    {
        return new Capabilities(end: false, presences: false, recordings: false, webhooks: null !== $this->webhookSecret);
    }

    #[Override]
    public function open(RoomSpec $room): OpenedRoom
    {
        return new OpenedRoom($room->id, $room->record);
    }

    #[Override]
    public function joinUrl(OpenedRoom $room, Participant $participant, Role $role): string
    {
        $now = $this->clock->now()->getTimestamp();
        $moderator = Role::Moderator === $role;
        $jaas = null !== $this->keyId;
        $user = ['id' => $participant->id->value, 'name' => $participant->displayName];
        $claims = [
            'aud' => $this->audience,
            'iss' => $jaas ? 'chat' : $this->appId,
            'sub' => $this->subject,
            'room' => $room->id->value,
            'iat' => $now,
            'nbf' => $now - 10,
            'exp' => $now + $this->tokenTtl,
        ];

        if ($jaas) {
            $claims['context'] = [
                'user' => [...$user, 'moderator' => $moderator ? 'true' : 'false'],
                'features' => ['recording' => $moderator && $room->record ? 'true' : 'false', 'livestreaming' => 'false', 'transcription' => 'false', 'outbound-call' => 'false'],
            ];
            $token = JsonWebToken::sign($claims, 'RS256', $this->key, ['kid' => $this->keyId]);
        } else {
            $claims['context'] = ['user' => [...$user, 'moderator' => $moderator, 'affiliation' => $moderator ? 'owner' : 'member']];
            $token = JsonWebToken::sign($claims, 'HS256', $this->key);
        }

        $path = $jaas ? rawurlencode($this->appId).'/'.$room->id->value : $room->id->value;

        return rtrim($this->server->toString(), '/').'/'.$path.'?jwt='.$token;
    }

    #[Override]
    public function end(RoomId $room): void
    {
        throw Unsupported::because('Jitsi has no call to end a room: it ends when its last participant leaves, and its join links expire.');
    }

    #[Override]
    public function presences(RoomId $room): Presences
    {
        throw Unsupported::because('Jitsi has no call to say who is in a room: its webhooks do.');
    }

    #[Override]
    public function recordings(RoomId $room): array
    {
        throw Unsupported::because('Jitsi has no call to list the recordings of a room: its webhooks announce each one.');
    }

    #[Override]
    public function receive(Webhook $webhook): array
    {
        if (null === $this->webhookSecret) {
            throw Unsupported::because('No webhook secret is set for this Jitsi server.');
        }

        if ('POST' !== $webhook->method || \strlen($webhook->body) > self::MAX_WEBHOOK_BYTES) {
            throw InvalidWebhook::because('A Jitsi notification is a POST of at most 256 KiB.');
        }

        $this->checkSignature($webhook->header('x-jaas-signature') ?? '', $webhook->body, $this->webhookSecret);

        try {
            $payload = json_decode($webhook->body, true, 16, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidWebhook::because('A Jitsi notification is JSON.');
        }

        if (!\is_array($payload)) {
            throw InvalidWebhook::because('A Jitsi notification is a JSON object.');
        }

        $type = $payload['eventType'] ?? null;
        $data = \is_array($payload['data'] ?? null) ? $payload['data'] : [];

        if (!\in_array($type, ['PARTICIPANT_JOINED', 'PARTICIPANT_LEFT', 'ROOM_DESTROYED', 'RECORDING_UPLOADED'], true)) {
            return [];
        }

        $room = $this->room($payload['fqn'] ?? null);

        if (!$room instanceof RoomId) {
            return [];
        }

        $at = Timestamps::read($payload['timestamp'] ?? null) ?? $this->clock->now()->setTimezone(new DateTimeZone('UTC'));

        if ('ROOM_DESTROYED' === $type) {
            return [new RoomEnded($room, $at)];
        }

        if ('RECORDING_UPLOADED' === $type) {
            return $this->recordingUploaded($room, $payload, $data, $at);
        }

        $participant = ParticipantId::tryFrom($data['id'] ?? null);

        if (!$participant instanceof ParticipantId || true === ($data['isBreakout'] ?? false)) {
            return [];
        }

        return ['PARTICIPANT_JOINED' === $type ? new ParticipantJoined($room, $participant, $at) : new ParticipantLeft($room, $participant, $at)];
    }

    /**
     * @throws InvalidValue when a value is out of its bounds
     */
    private static function checked(self $jitsi): self
    {
        if ('https' !== $jitsi->server->scheme) {
            throw InvalidValue::because('A Jitsi server is reached over https.');
        }

        if (1 !== preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', $jitsi->appId)) {
            throw InvalidValue::because('The application identifier of a Jitsi server is 1 to 128 letters, digits, ".", "_", ":" or "-".');
        }

        if ($jitsi->tokenTtl < 60 || $jitsi->tokenTtl > 86_400) {
            throw InvalidValue::because('A join link of Jitsi lets its participant in for 60 seconds to a day.');
        }

        if (null !== $jitsi->webhookSecret && \strlen($jitsi->webhookSecret) < 16) {
            throw InvalidValue::because('The webhook secret of a Jitsi server is 16 bytes or more.');
        }

        return $jitsi;
    }

    /**
     * `t=<seconds>,v1=<base64 HMAC-SHA-256 of "<t>.<body>">`, one `v1` or more, the moment within five minutes.
     */
    private function checkSignature(string $header, string $body, string $webhookSecret): void
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $element) {
            [$name, $value] = array_pad(explode('=', trim($element), 2), 2, '');

            if ('t' === $name && 1 === preg_match('/^\d{1,13}$/D', $value)) {
                $timestamp = $value;
            } elseif ('v1' === $name) {
                $signatures[] = $value;
            }
        }

        if (null === $timestamp || [] === $signatures) {
            throw InvalidWebhook::because('The notification carries no signature.');
        }

        $seconds = (int) $timestamp;
        $seconds = $seconds >= 100_000_000_000 ? intdiv($seconds, 1000) : $seconds;

        if (abs($this->clock->now()->getTimestamp() - $seconds) > self::SIGNATURE_TOLERANCE) {
            throw InvalidWebhook::because('The signature of the notification is too old, or from the future.');
        }

        $expected = sodium_bin2base64(hash_hmac('sha256', $timestamp.'.'.$body, $webhookSecret, true), \SODIUM_BASE64_VARIANT_ORIGINAL);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return;
            }
        }

        throw InvalidWebhook::because('The signature of the notification does not match.');
    }

    /**
     * The room of `<application>/<room>`, when it is of this application and an identifier of a room.
     */
    private function room(mixed $fqn): ?RoomId
    {
        if (!\is_string($fqn)) {
            return null;
        }

        $parts = explode('/', $fqn);
        $name = strtolower(array_pop($parts));

        if (null !== $this->keyId && [$this->appId] !== $parts) {
            return null;
        }

        try {
            return RoomId::fromString($name);
        } catch (InvalidValue) {
            return null;
        }
    }

    /**
     * @param array<array-key, mixed> $payload
     * @param array<array-key, mixed> $data
     *
     * @return list<RecordingReady>
     */
    private function recordingUploaded(RoomId $room, array $payload, array $data, DateTimeImmutable $at): array
    {
        $id = $data['recordingSessionId'] ?? $payload['sessionId'] ?? null;
        $link = $data['preAuthenticatedLink'] ?? null;

        try {
            $recording = new Recording(
                \is_string($id) ? $id : '',
                $room,
                'download',
                \is_string($link) ? Url::fromString($link)->toString() : null,
                true === ($data['share'] ?? true),
                Timestamps::read($data['startTimestamp'] ?? null),
                Timestamps::read($data['endTimestamp'] ?? null),
                $at->modify('+1 day'),
            );
        } catch (InvalidValue|InvalidUrl) {
            return [];
        }

        return [new RecordingReady($room, $recording->id, $at, $recording)];
    }
}
