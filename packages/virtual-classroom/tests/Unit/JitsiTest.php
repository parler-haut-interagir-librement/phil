<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Tests\Unit;

use DateTimeImmutable;
use Phil\Url\Url;
use Phil\VirtualClassroom\Event\ParticipantJoined;
use Phil\VirtualClassroom\Event\ParticipantLeft;
use Phil\VirtualClassroom\Event\RecordingReady;
use Phil\VirtualClassroom\Event\RoomEnded;
use Phil\VirtualClassroom\Exception\InvalidValue;
use Phil\VirtualClassroom\Exception\InvalidWebhook;
use Phil\VirtualClassroom\Exception\Unsupported;
use Phil\VirtualClassroom\Jitsi\Jitsi;
use Phil\VirtualClassroom\OpenedRoom;
use Phil\VirtualClassroom\Participant;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\Role;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroom\RoomSpec;
use Phil\VirtualClassroom\Token\JsonWebToken;
use Phil\VirtualClassroom\Webhook;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(Jitsi::class)]
final class JitsiTest extends TestCase
{
    private const string SECRET = 'application-secret-of-the-server';

    private const string HOOKS = 'webhook-secret-of-the-account-01';

    private const string APP = 'vpaas-magic-cookie-0123456789abcdef';

    private const int NOW = 1_700_000_000;

    private static ?string $privateKey = null;

    private static ?string $publicKey = null;

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $pem);
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        self::assertIsString($pem);
        self::assertIsString($details['key']);
        self::$privateKey = $pem;
        self::$publicKey = $details['key'];
    }

    public function testASelfHostedServerCanOnlyOpenAndJoinWithoutAWebhookSecret(): void
    {
        $jitsi = $this->selfHosted(webhookSecret: null);
        $capabilities = $jitsi->capabilities();

        self::assertFalse($capabilities->end);
        self::assertFalse($capabilities->presences);
        self::assertFalse($capabilities->recordings);
        self::assertFalse($capabilities->webhooks);
        self::assertTrue($this->selfHosted()->capabilities()->webhooks);
    }

    public function testOpeningARoomCallsNoServer(): void
    {
        $opened = $this->selfHosted()->open(new RoomSpec(RoomId::fromString('room1'), 'Algebra', true));

        self::assertSame('room1', $opened->id->value);
        self::assertTrue($opened->record);
        self::assertNull($opened->sessionKey);
    }

    public function testASelfHostedJoinLinkCarriesAnHmacToken(): void
    {
        $url = $this->selfHosted()->joinUrl(new OpenedRoom(RoomId::fromString('room1')), new Participant(ParticipantId::fromString('user-7'), 'Ada'), Role::Moderator);

        self::assertStringStartsWith('https://meet.example.com/room1?jwt=', $url);
        $token = substr($url, \strlen('https://meet.example.com/room1?jwt='));
        $claims = JsonWebToken::verify($token, self::SECRET, ['HS256'], new DateTimeImmutable('@'.self::NOW));

        self::assertSame([
            'aud' => 'jitsi',
            'iss' => 'app1',
            'sub' => 'meet.example.com',
            'room' => 'room1',
            'iat' => self::NOW,
            'nbf' => self::NOW - 10,
            'exp' => self::NOW + 3600,
            'context' => ['user' => ['id' => 'user-7', 'name' => 'Ada', 'moderator' => true, 'affiliation' => 'owner']],
        ], $claims);
    }

    public function testAnAttendeeOfASelfHostedServerIsAMember(): void
    {
        $jitsi = Jitsi::selfHosted(Url::fromString('https://meet.example.com/base/'), 'app1', self::SECRET, self::clock(), audience: 'meet', subject: '*');
        $url = $jitsi->joinUrl(new OpenedRoom(RoomId::fromString('room1')), new Participant(ParticipantId::fromString('user-7'), 'Ada'), Role::Attendee);

        self::assertStringStartsWith('https://meet.example.com/base/room1?jwt=', $url);
        $claims = JsonWebToken::verify(substr($url, \strlen('https://meet.example.com/base/room1?jwt=')), self::SECRET, ['HS256'], new DateTimeImmutable('@'.self::NOW));
        self::assertSame(['id' => 'user-7', 'name' => 'Ada', 'moderator' => false, 'affiliation' => 'member'], $this->user($claims));
        self::assertSame('meet', $claims['aud']);
        self::assertSame('*', $claims['sub']);
        self::assertSame(self::NOW + 10_800, $claims['exp']);
    }

    public function testAJaasJoinLinkCarriesAnRsaToken(): void
    {
        $url = $this->jaas()->joinUrl(new OpenedRoom(RoomId::fromString('room1'), true), new Participant(ParticipantId::fromString('user-7'), 'Ada'), Role::Moderator);

        $prefix = 'https://8x8.vc/'.self::APP.'/room1?jwt=';
        self::assertStringStartsWith($prefix, $url);
        [$header, $claims] = $this->verifiedRsa(substr($url, \strlen($prefix)));
        self::assertSame(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => self::APP.'/key1'], $header);
        self::assertSame([
            'aud' => 'jitsi',
            'iss' => 'chat',
            'sub' => self::APP,
            'room' => 'room1',
            'iat' => self::NOW,
            'nbf' => self::NOW - 10,
            'exp' => self::NOW + 600,
            'context' => [
                'user' => ['id' => 'user-7', 'name' => 'Ada', 'moderator' => 'true'],
                'features' => ['recording' => 'true', 'livestreaming' => 'false', 'transcription' => 'false', 'outbound-call' => 'false'],
            ],
        ], $claims);
    }

    public function testAJaasAttendeeMayNotRecord(): void
    {
        $url = $this->jaas()->joinUrl(new OpenedRoom(RoomId::fromString('room1'), true), new Participant(ParticipantId::fromString('user-7'), 'Ada'), Role::Attendee);
        [, $claims] = $this->verifiedRsa(substr($url, (int) strpos($url, '?jwt=') + 5));

        self::assertSame(['id' => 'user-7', 'name' => 'Ada', 'moderator' => 'false'], $this->user($claims));
        self::assertSame('false', $this->feature($claims, 'recording'));

        $moderator = $this->jaas()->joinUrl(new OpenedRoom(RoomId::fromString('room1')), new Participant(ParticipantId::fromString('user-7'), 'Ada'), Role::Moderator);
        [, $unrecorded] = $this->verifiedRsa(substr($moderator, (int) strpos($moderator, '?jwt=') + 5));
        self::assertSame('false', $this->feature($unrecorded, 'recording'));
    }

    public function testJaasOnAnotherServer(): void
    {
        $jitsi = Jitsi::jaas(self::APP, 'key1', (string) self::$privateKey, self::clock(), server: Url::fromString('https://jaas.example.com'));

        self::assertStringStartsWith('https://jaas.example.com/'.self::APP.'/room1?jwt=', $jitsi->joinUrl(new OpenedRoom(RoomId::fromString('room1')), new Participant(ParticipantId::fromString('u'), 'Ada'), Role::Attendee));
    }

    /**
     * @return iterable<string, array{callable(): Jitsi, string}>
     */
    public static function badServers(): iterable
    {
        yield 'a short secret' => [static fn (): Jitsi => Jitsi::selfHosted(Url::fromString('https://meet.example.com'), 'app1', str_repeat('s', 15), self::clock()), 'The application secret of a Jitsi server is 16 bytes or more.'];
        yield 'no TLS' => [static fn (): Jitsi => Jitsi::selfHosted(Url::fromString('http://meet.example.com'), 'app1', self::SECRET, self::clock()), 'A Jitsi server is reached over https.'];
        yield 'an empty application' => [static fn (): Jitsi => Jitsi::selfHosted(Url::fromString('https://meet.example.com'), '', self::SECRET, self::clock()), 'The application identifier of a Jitsi server is 1 to 128 letters, digits, ".", "_", ":" or "-".'];
        yield 'a long application' => [static fn (): Jitsi => Jitsi::selfHosted(Url::fromString('https://meet.example.com'), str_repeat('a', 129), self::SECRET, self::clock()), 'The application identifier of a Jitsi server is 1 to 128 letters, digits, ".", "_", ":" or "-".'];
        yield 'an application with a line' => [static fn (): Jitsi => Jitsi::selfHosted(Url::fromString('https://meet.example.com'), "app\n", self::SECRET, self::clock()), 'The application identifier of a Jitsi server is 1 to 128 letters, digits, ".", "_", ":" or "-".'];
        yield 'a link of 59 seconds' => [static fn (): Jitsi => Jitsi::selfHosted(Url::fromString('https://meet.example.com'), 'app1', self::SECRET, self::clock(), 59), 'A join link of Jitsi lets its participant in for 60 seconds to a day.'];
        yield 'a link of over a day' => [static fn (): Jitsi => Jitsi::selfHosted(Url::fromString('https://meet.example.com'), 'app1', self::SECRET, self::clock(), 86_401), 'A join link of Jitsi lets its participant in for 60 seconds to a day.'];
        yield 'a short webhook secret' => [static fn (): Jitsi => Jitsi::selfHosted(Url::fromString('https://meet.example.com'), 'app1', self::SECRET, self::clock(), webhookSecret: str_repeat('w', 15)), 'The webhook secret of a Jitsi server is 16 bytes or more.'];
        yield 'not a key' => [static fn (): Jitsi => Jitsi::jaas(self::APP, 'key1', 'not a key', self::clock()), 'The private key of a JaaS API key is an RSA key in PEM.'];
        yield 'an elliptic key' => [static fn (): Jitsi => Jitsi::jaas(self::APP, 'key1', self::ellipticKey(), self::clock()), 'The private key of a JaaS API key is an RSA key in PEM.'];
        yield 'an empty key identifier' => [static fn (): Jitsi => Jitsi::jaas(self::APP, '', (string) self::$privateKey, self::clock()), 'The identifier of a JaaS API key is 1 to 256 letters, digits, ".", "_", ":", "/" or "-".'];
        yield 'a key identifier with a line' => [static fn (): Jitsi => Jitsi::jaas(self::APP, "k\n", (string) self::$privateKey, self::clock()), 'The identifier of a JaaS API key is 1 to 256 letters, digits, ".", "_", ":", "/" or "-".'];
        yield 'a long key identifier' => [static fn (): Jitsi => Jitsi::jaas(self::APP, str_repeat('k', 257), (string) self::$privateKey, self::clock()), 'The identifier of a JaaS API key is 1 to 256 letters, digits, ".", "_", ":", "/" or "-".'];
    }

    /**
     * @param callable(): Jitsi $build
     */
    #[DataProvider('badServers')]
    public function testABadServer(callable $build, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testTheBoundsOfAServerAreAccepted(): void
    {
        $edge = Jitsi::selfHosted(Url::fromString('https://meet.example.com'), str_repeat('a', 128), str_repeat('s', 16), self::clock(), 60, str_repeat('w', 16));
        self::assertTrue($edge->capabilities()->webhooks);
        self::assertFalse(Jitsi::selfHosted(Url::fromString('https://meet.example.com'), 'a', self::SECRET, self::clock(), 86_400)->capabilities()->webhooks);
        self::assertFalse(Jitsi::jaas(self::APP, str_repeat('k', 256), (string) self::$privateKey, self::clock())->capabilities()->end);
    }

    /**
     * @return iterable<string, array{callable(Jitsi): mixed, string}>
     */
    public static function unsupported(): iterable
    {
        yield 'end' => [static function (Jitsi $jitsi): void {
            $jitsi->end(RoomId::fromString('room1'));
        }, 'Jitsi has no call to end a room: it ends when its last participant leaves, and its join links expire.'];
        yield 'presences' => [static fn (Jitsi $jitsi): mixed => $jitsi->presences(RoomId::fromString('room1')), 'Jitsi has no call to say who is in a room: its webhooks do.'];
        yield 'recordings' => [static fn (Jitsi $jitsi): mixed => $jitsi->recordings(RoomId::fromString('room1')), 'Jitsi has no call to list the recordings of a room: its webhooks announce each one.'];
    }

    /**
     * @param callable(Jitsi): mixed $call
     */
    #[DataProvider('unsupported')]
    public function testWhatJitsiCannotDo(callable $call, string $message): void
    {
        $this->expectException(Unsupported::class);
        $this->expectExceptionMessageIsOrContains($message);

        $call($this->selfHosted());
    }

    public function testNoNotificationIsReadWithoutASecret(): void
    {
        $this->expectException(Unsupported::class);
        $this->expectExceptionMessageIsOrContains('No webhook secret is set for this Jitsi server.');

        $this->selfHosted(webhookSecret: null)->receive(new Webhook('POST', [], [], '{}'));
    }

    public function testParticipantsJoiningAndLeaving(): void
    {
        $jaas = $this->jaas();
        $joined = $jaas->receive($this->webhook(['eventType' => 'PARTICIPANT_JOINED', 'fqn' => self::APP.'/Room1', 'timestamp' => 1_700_000_100_000, 'data' => ['id' => 'user-7', 'name' => 'Ada', 'moderator' => 'true']]));
        $left = $jaas->receive($this->webhook(['eventType' => 'PARTICIPANT_LEFT', 'fqn' => self::APP.'/room1', 'timestamp' => 1_700_000_200, 'data' => ['id' => 'user-7', 'disconnectReason' => 'left', 'isBreakout' => false]]));

        self::assertCount(1, $joined);
        self::assertInstanceOf(ParticipantJoined::class, $joined[0]);
        self::assertSame('room1', $joined[0]->room->value);
        self::assertSame('user-7', $joined[0]->participant->value);
        self::assertSame('2023-11-14T22:15:00+00:00', $joined[0]->at->format(\DATE_ATOM));
        self::assertCount(1, $left);
        self::assertInstanceOf(ParticipantLeft::class, $left[0]);
        self::assertSame('2023-11-14T22:16:40+00:00', $left[0]->at->format(\DATE_ATOM));
    }

    public function testAnEventWithoutAMomentHappensNow(): void
    {
        $events = $this->jaas()->receive($this->webhook(['eventType' => 'ROOM_DESTROYED', 'fqn' => self::APP.'/room1']));

        self::assertCount(1, $events);
        self::assertInstanceOf(RoomEnded::class, $events[0]);
        self::assertSame('2023-11-14T22:13:20+00:00', $events[0]->at->format(\DATE_ATOM));
        self::assertSame('UTC', $events[0]->at->getTimezone()->getName());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function ignored(): iterable
    {
        yield 'another event' => [['eventType' => 'ROOM_CREATED', 'fqn' => self::APP.'/room1']];
        yield 'no event' => [['fqn' => self::APP.'/room1']];
        yield 'another application' => [['eventType' => 'ROOM_DESTROYED', 'fqn' => 'vpaas-magic-cookie-other/room1']];
        yield 'no application' => [['eventType' => 'ROOM_DESTROYED', 'fqn' => 'room1']];
        yield 'a nested room' => [['eventType' => 'ROOM_DESTROYED', 'fqn' => self::APP.'/a/room1']];
        yield 'no room' => [['eventType' => 'ROOM_DESTROYED']];
        yield 'a room of another name' => [['eventType' => 'ROOM_DESTROYED', 'fqn' => self::APP.'/my room']];
        yield 'a participant without identifier' => [['eventType' => 'PARTICIPANT_JOINED', 'fqn' => self::APP.'/room1', 'data' => ['name' => 'A guest']]];
        yield 'a participant by e-mail' => [['eventType' => 'PARTICIPANT_JOINED', 'fqn' => self::APP.'/room1', 'data' => ['id' => 'ada@example.com']]];
        yield 'a participant in a breakout room' => [['eventType' => 'PARTICIPANT_JOINED', 'fqn' => self::APP.'/room1', 'data' => ['id' => 'user-7', 'isBreakout' => true]]];
        yield 'no data' => [['eventType' => 'PARTICIPANT_LEFT', 'fqn' => self::APP.'/room1', 'data' => 'user-7']];
        yield 'another event of a participant' => [['eventType' => 'PARTICIPANT_UPDATED', 'fqn' => self::APP.'/room1', 'data' => ['id' => 'user-7']]];
        yield 'a recording without identifier' => [['eventType' => 'RECORDING_UPLOADED', 'fqn' => self::APP.'/room1', 'data' => ['preAuthenticatedLink' => 'https://example.com/r']]];
        yield 'a recording with a bad link' => [['eventType' => 'RECORDING_UPLOADED', 'fqn' => self::APP.'/room1', 'sessionId' => 's1', 'data' => ['preAuthenticatedLink' => 'not a link']]];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('ignored')]
    public function testANotificationOfSomethingElse(array $payload): void
    {
        self::assertSame([], $this->jaas()->receive($this->webhook($payload)));
    }

    public function testASelfHostedServerReadsItsRoomWithoutApplication(): void
    {
        $events = $this->selfHosted()->receive($this->webhook(['eventType' => 'ROOM_DESTROYED', 'fqn' => 'whatever/room1'], self::HOOKS));

        self::assertCount(1, $events);
        self::assertSame('room1', $events[0]->room->value);
        self::assertCount(1, $this->selfHosted()->receive($this->webhook(['eventType' => 'ROOM_DESTROYED', 'fqn' => 'room1'], self::HOOKS)));
    }

    public function testARecordingUploaded(): void
    {
        $events = $this->jaas()->receive($this->webhook(['eventType' => 'RECORDING_UPLOADED', 'fqn' => self::APP.'/room1', 'timestamp' => self::NOW, 'sessionId' => 'session-1', 'data' => [
            'preAuthenticatedLink' => 'https://download.example.com/rec.mp4?sig=abc',
            'share' => false,
            'recordingSessionId' => 'rec-9',
            'startTimestamp' => 1_699_996_400_000,
            'endTimestamp' => 1_699_999_000_000,
        ]]));

        self::assertCount(1, $events);
        $ready = $events[0];
        self::assertInstanceOf(RecordingReady::class, $ready);
        self::assertSame('rec-9', $ready->recordingId);
        $recording = $ready->recording;
        self::assertNotNull($recording);
        self::assertSame('rec-9', $recording->id);
        self::assertSame('room1', $recording->room->value);
        self::assertSame('download', $recording->format);
        self::assertSame('https://download.example.com/rec.mp4?sig=abc', $recording->url);
        self::assertFalse($recording->published);
        self::assertSame('2023-11-14T21:13:20+00:00', $recording->startedAt?->format(\DATE_ATOM));
        self::assertSame('2023-11-14T21:56:40+00:00', $recording->endedAt?->format(\DATE_ATOM));
        self::assertSame('2023-11-15T22:13:20+00:00', $recording->expiresAt?->format(\DATE_ATOM));
    }

    public function testARecordingNamedByItsSession(): void
    {
        $events = $this->jaas()->receive($this->webhook(['eventType' => 'RECORDING_UPLOADED', 'fqn' => self::APP.'/room1', 'sessionId' => 'session-1', 'data' => []]));

        self::assertCount(1, $events);
        self::assertInstanceOf(RecordingReady::class, $events[0]);
        self::assertSame('session-1', $events[0]->recordingId);
        self::assertNull($events[0]->recording?->url);
        self::assertTrue($events[0]->recording?->published);
    }

    /**
     * @return iterable<string, array{Webhook, string}>
     */
    public static function refused(): iterable
    {
        $body = '{"eventType":"ROOM_DESTROYED","fqn":"'.self::APP.'/room1"}';
        $sign = static fn (string $t, string $content, string $secret = self::HOOKS): string => sodium_bin2base64(hash_hmac('sha256', $t.'.'.$content, $secret, true), \SODIUM_BASE64_VARIANT_ORIGINAL);

        yield 'a GET' => [new Webhook('GET', [], [], $body), 'A Jitsi notification is a POST of at most 256 KiB.'];
        yield 'too large' => [new Webhook('POST', [], [], str_repeat(' ', 262_145)), 'A Jitsi notification is a POST of at most 256 KiB.'];
        yield 'no signature' => [new Webhook('POST', [], [], $body), 'The notification carries no signature.'];
        yield 'no moment' => [new Webhook('POST', ['X-Jaas-Signature' => 'v1='.$sign('1700000000', $body)], [], $body), 'The notification carries no signature.'];
        yield 'a moment that is not a number' => [new Webhook('POST', ['X-Jaas-Signature' => 't=soon,v1='.$sign('soon', $body)], [], $body), 'The notification carries no signature.'];
        yield 'no v1' => [new Webhook('POST', ['X-Jaas-Signature' => 't=1700000000,v0='.$sign('1700000000', $body)], [], $body), 'The notification carries no signature.'];
        yield 'too old' => [new Webhook('POST', ['X-Jaas-Signature' => 't=1699999699,v1='.$sign('1699999699', $body)], [], $body), 'The signature of the notification is too old, or from the future.'];
        yield 'from the future' => [new Webhook('POST', ['X-Jaas-Signature' => 't=1700000301,v1='.$sign('1700000301', $body)], [], $body), 'The signature of the notification is too old, or from the future.'];
        yield 'another secret' => [new Webhook('POST', ['X-Jaas-Signature' => 't=1700000000,v1='.$sign('1700000000', $body, 'another-secret-of-the-account-01')], [], $body), 'The signature of the notification does not match.'];
        yield 'another body' => [new Webhook('POST', ['X-Jaas-Signature' => 't=1700000000,v1='.$sign('1700000000', '{}')], [], $body), 'The signature of the notification does not match.'];
        yield 'not JSON' => [new Webhook('POST', ['X-Jaas-Signature' => 't=1700000000,v1='.$sign('1700000000', '{x')], [], '{x'), 'A Jitsi notification is JSON.'];
        yield 'nested too deep' => [new Webhook('POST', ['X-Jaas-Signature' => 't=1700000000,v1='.$sign('1700000000', str_repeat('[', 16).str_repeat(']', 16))], [], str_repeat('[', 16).str_repeat(']', 16)), 'A Jitsi notification is JSON.'];
        yield 'a moment with a letter before' => [new Webhook('POST', ['X-Jaas-Signature' => 't=x1700000000,v1='.$sign('x1700000000', $body)], [], $body), 'The notification carries no signature.'];
        yield 'a moment with a letter after' => [new Webhook('POST', ['X-Jaas-Signature' => 't=1700000000x,v1='.$sign('1700000000x', $body)], [], $body), 'The notification carries no signature.'];
        yield 'a string' => [new Webhook('POST', ['X-Jaas-Signature' => 't=1700000000,v1='.$sign('1700000000', '"x"')], [], '"x"'), 'A Jitsi notification is a JSON object.'];
    }

    #[DataProvider('refused')]
    public function testARefusedNotification(Webhook $webhook, string $message): void
    {
        $this->expectException(InvalidWebhook::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->jaas()->receive($webhook);
    }

    public function testASignatureAmongSeveralWithinFiveMinutes(): void
    {
        $body = '{"eventType":"ROOM_DESTROYED","fqn":"'.self::APP.'/room1"}';
        $good = sodium_bin2base64(hash_hmac('sha256', '1699999700.'.$body, self::HOOKS, true), \SODIUM_BASE64_VARIANT_ORIGINAL);
        $header = 't=1699999700, v1=bm90IGl0, v0=x, v1='.$good;

        self::assertCount(1, $this->jaas()->receive(new Webhook('POST', ['X-Jaas-Signature' => $header], [], $body)));

        $future = sodium_bin2base64(hash_hmac('sha256', '1700000300000.'.$body, self::HOOKS, true), \SODIUM_BASE64_VARIANT_ORIGINAL);
        self::assertCount(1, $this->jaas()->receive(new Webhook('POST', ['X-Jaas-Signature' => 't=1700000300000,v1='.$future], [], $body)));
        self::assertCount(0, $this->jaas()->receive($this->webhook(['eventType' => 'NONE'], self::HOOKS, str_repeat(' ', 262_000))));
    }

    public function testANotificationOfExactlyTheCeilingIsRead(): void
    {
        $head = json_encode(['eventType' => 'NONE'], \JSON_THROW_ON_ERROR);
        $webhook = $this->webhook(['eventType' => 'NONE'], self::HOOKS, str_repeat(' ', 262_144 - \strlen($head)));

        self::assertSame(262_144, \strlen($webhook->body));
        self::assertSame([], $this->jaas()->receive($webhook));
    }

    public function testANotificationNestedFifteenDeepIsRead(): void
    {
        $body = str_repeat('[', 15).str_repeat(']', 15);
        $signature = sodium_bin2base64(hash_hmac('sha256', self::NOW.'.'.$body, self::HOOKS, true), \SODIUM_BASE64_VARIANT_ORIGINAL);

        self::assertSame([], $this->jaas()->receive(new Webhook('POST', ['X-Jaas-Signature' => 't='.self::NOW.',v1='.$signature], [], $body)));
    }

    public function testAMomentInMillisecondsFromTheFirstOneIsReadAsSuch(): void
    {
        $clock = new MockClock(new DateTimeImmutable('@100000000'));
        $jitsi = Jitsi::jaas(self::APP, self::APP.'/key1', (string) self::$privateKey, $clock, 600, self::HOOKS);
        $body = '{"eventType":"ROOM_DESTROYED","fqn":"'.self::APP.'/room1"}';
        $signature = sodium_bin2base64(hash_hmac('sha256', '100000000000.'.$body, self::HOOKS, true), \SODIUM_BASE64_VARIANT_ORIGINAL);

        self::assertCount(1, $jitsi->receive(new Webhook('POST', ['X-Jaas-Signature' => 't=100000000000,v1='.$signature], [], $body)));
    }

    public function testAJaasLinkLastsThreeHoursByDefault(): void
    {
        $jaas = Jitsi::jaas(self::APP, self::APP.'/key1', (string) self::$privateKey, self::clock());
        $url = $jaas->joinUrl(new OpenedRoom(RoomId::fromString('room1')), new Participant(ParticipantId::fromString('user-7'), 'Ada'), Role::Attendee);
        [, $claims] = $this->verifiedRsa(substr($url, \strlen('https://8x8.vc/'.self::APP.'/room1?jwt=')));

        self::assertSame(self::NOW + 10_800, $claims['exp']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function webhook(array $payload, string $secret = self::HOOKS, string $padding = ''): Webhook
    {
        $body = json_encode($payload, \JSON_THROW_ON_ERROR).$padding;
        $signature = sodium_bin2base64(hash_hmac('sha256', self::NOW.'.'.$body, $secret, true), \SODIUM_BASE64_VARIANT_ORIGINAL);

        return new Webhook('POST', ['X-Jaas-Signature' => 't='.self::NOW.',v1='.$signature], [], $body, Webhook::ACCOUNT);
    }

    private function selfHosted(?string $webhookSecret = self::HOOKS): Jitsi
    {
        return Jitsi::selfHosted(Url::fromString('https://meet.example.com'), 'app1', self::SECRET, self::clock(), 3600, $webhookSecret);
    }

    private function jaas(): Jitsi
    {
        return Jitsi::jaas(self::APP, self::APP.'/key1', (string) self::$privateKey, self::clock(), 600, self::HOOKS);
    }

    /**
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private function verifiedRsa(string $token): array
    {
        [$header, $claims, $signature] = explode('.', $token);
        self::assertSame(1, openssl_verify($header.'.'.$claims, sodium_base642bin($signature, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), (string) self::$publicKey, \OPENSSL_ALGO_SHA256));
        $decode = static function (string $part): array {
            $value = json_decode(sodium_base642bin($part, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), true, 16, \JSON_THROW_ON_ERROR);
            self::assertIsArray($value);

            /** @var array<string, mixed> $value */
            return $value;
        };

        return [$decode($header), $decode($claims)];
    }

    /**
     * @param array<string, mixed> $claims
     *
     * @return array<array-key, mixed>
     */
    private function user(array $claims): array
    {
        $context = $claims['context'] ?? null;
        self::assertIsArray($context);
        self::assertIsArray($context['user'] ?? null);

        return $context['user'];
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function feature(array $claims, string $name): mixed
    {
        $context = $claims['context'] ?? null;
        self::assertIsArray($context);
        $features = $context['features'] ?? null;
        self::assertIsArray($features);

        return $features[$name] ?? null;
    }

    private static function ellipticKey(): string
    {
        $key = openssl_pkey_new(['private_key_type' => \OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $pem);
        self::assertIsString($pem);

        return $pem;
    }

    private static function clock(): MockClock
    {
        return new MockClock(new DateTimeImmutable('@'.self::NOW));
    }
}
