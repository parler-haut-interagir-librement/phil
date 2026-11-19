<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Tests\Unit;

use DateTimeImmutable;
use Phil\Url\Url;
use Phil\VirtualClassroom\BigBlueButton\BigBlueButton;
use Phil\VirtualClassroom\BigBlueButton\ChecksumAlgorithm;
use Phil\VirtualClassroom\BigBlueButton\XmlAnswer;
use Phil\VirtualClassroom\Contract\RoomEvent;
use Phil\VirtualClassroom\Event\ParticipantJoined;
use Phil\VirtualClassroom\Event\ParticipantLeft;
use Phil\VirtualClassroom\Event\RecordingReady;
use Phil\VirtualClassroom\Event\RoomEnded;
use Phil\VirtualClassroom\Exception\InvalidResponse;
use Phil\VirtualClassroom\Exception\InvalidValue;
use Phil\VirtualClassroom\Exception\InvalidWebhook;
use Phil\VirtualClassroom\Exception\ProviderRefused;
use Phil\VirtualClassroom\OpenedRoom;
use Phil\VirtualClassroom\Participant;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\Role;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroom\RoomSpec;
use Phil\VirtualClassroom\RoomState;
use Phil\VirtualClassroom\Token\JsonWebToken;
use Phil\VirtualClassroom\Webhook;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(BigBlueButton::class)]
#[CoversClass(XmlAnswer::class)]
#[CoversClass(ChecksumAlgorithm::class)]
final class BigBlueButtonTest extends TestCase
{
    private const string SECRET = 'shared-secret-of-the-server-0123';

    private const string API = 'https://bbb.example.com/bigbluebutton/api/';

    /** @var list<string> */
    private array $calls = [];

    public function testTheChecksumIsTheHashOfTheCallTheQueryAndTheSecret(): void
    {
        $bbb = $this->bbb([]);
        $query = 'meetingID=room1&name=Algebra%20%C3%A9';

        self::assertSame(self::API.'create?'.$query.'&checksum='.hash('sha256', 'create'.$query.self::SECRET), $bbb->signed('create', ['meetingID' => 'room1', 'name' => 'Algebra é']));
        self::assertSame(self::API.'getMeetings?checksum='.hash('sha256', 'getMeetings'.self::SECRET), $bbb->signed('getMeetings', []));

        foreach ([ChecksumAlgorithm::Sha256, ChecksumAlgorithm::Sha384, ChecksumAlgorithm::Sha512] as $algorithm) {
            $signed = new BigBlueButton(new MockHttpClient(), Url::fromString('https://bbb.example.com/bigbluebutton/api'), self::SECRET, $this->clock(), $algorithm)->signed('end', ['meetingID' => 'r1']);

            self::assertSame(self::API.'end?meetingID=r1&checksum='.hash($algorithm->value, 'endmeetingID=r1'.self::SECRET), $signed);
        }
    }

    public function testTheServerIsReachedOverHttpsWithALongSecret(): void
    {
        try {
            new BigBlueButton(new MockHttpClient(), Url::fromString('http://bbb.example.com/api/'), self::SECRET, $this->clock());
            self::fail('An address without TLS was accepted.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame('The API of a BigBlueButton server is reached over https.', $invalidValue->getMessage());
        }

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The secret shared with a BigBlueButton server is 16 bytes or more.');

        new BigBlueButton(new MockHttpClient(), Url::fromString(self::API), str_repeat('s', 15), $this->clock());
    }

    public function testASixteenByteSecretIsEnough(): void
    {
        self::assertTrue(new BigBlueButton(new MockHttpClient(), Url::fromString(self::API), str_repeat('s', 16), $this->clock())->capabilities()->end);
    }

    public function testItCanDoEverything(): void
    {
        $capabilities = $this->bbb([])->capabilities();

        self::assertTrue($capabilities->end);
        self::assertTrue($capabilities->presences);
        self::assertTrue($capabilities->recordings);
        self::assertTrue($capabilities->webhooks);
    }

    public function testARoomIsOpenedWithItsCallbacks(): void
    {
        $bbb = $this->bbb(['create' => $this->success('<meetingID>room1</meetingID><createTime>1700000000123</createTime>')]);
        $spec = new RoomSpec(RoomId::fromString('room1'), 'Algebra', true, 90, Url::fromString('https://example.com/ended'), Url::fromString('https://example.com/events'));

        $opened = $bbb->open($spec);

        self::assertSame('room1', $opened->id->value);
        self::assertTrue($opened->record);
        self::assertSame('1700000000123', $opened->sessionKey);
        self::assertSame([
            'name' => 'Algebra',
            'meetingID' => 'room1',
            'record' => 'true',
            'duration' => '90',
            'meetingEndedURL' => 'https://example.com/ended',
            'meta_bbb-recording-ready-url' => 'https://example.com/events',
            'meta_analytics-callback-url' => 'https://example.com/events',
        ], $this->query(0, 'create'));
    }

    public function testARoomWithoutRecordingAsksForNoRecordingCallback(): void
    {
        $bbb = $this->bbb(['create' => $this->success('<createTime>1</createTime>')], analytics: false);

        $opened = $bbb->open(new RoomSpec(RoomId::fromString('room1'), 'Algebra', eventsUrl: Url::fromString('https://example.com/events')));

        self::assertFalse($opened->record);
        self::assertSame(['name' => 'Algebra', 'meetingID' => 'room1', 'record' => 'false'], $this->query(0, 'create'));
    }

    public function testARoomWithoutCallbacks(): void
    {
        $bbb = $this->bbb(['create' => $this->success('<createTime>1</createTime>')]);

        $bbb->open(new RoomSpec(RoomId::fromString('room1'), 'Algebra', true));

        self::assertSame(['name' => 'Algebra', 'meetingID' => 'room1', 'record' => 'true'], $this->query(0, 'create'));
    }

    public function testARoomAlreadyRunningWithOtherSettingsIsFoundByItsInformation(): void
    {
        $bbb = $this->bbb([
            'create' => $this->failure('idNotUnique'),
            'getMeetingInfo' => $this->success('<createTime>1700000000999</createTime><running>true</running>'),
        ]);

        self::assertSame('1700000000999', $bbb->open(new RoomSpec(RoomId::fromString('room1'), 'Algebra'))->sessionKey);
        self::assertSame(['meetingID' => 'room1'], $this->query(1, 'getMeetingInfo'));
    }

    public function testARoomThatVanishesWhileOpenedHasNoCreationTime(): void
    {
        $bbb = $this->bbb(['create' => $this->failure('idNotUnique'), 'getMeetingInfo' => $this->failure('notFound')]);

        $this->expectException(InvalidResponse::class);
        $this->expectExceptionMessageIsOrContains('The answer to "create" gives no creation time.');

        $bbb->open(new RoomSpec(RoomId::fromString('room1'), 'Algebra'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function creationTimes(): iterable
    {
        yield 'none' => [''];
        yield 'not digits' => ['<createTime>soon</createTime>'];
        yield 'digits and a line' => ["<createTime>12\n3</createTime>"];
        yield 'twenty-one digits' => ['<createTime>'.str_repeat('1', 21).'</createTime>'];
    }

    #[DataProvider('creationTimes')]
    public function testAnAnswerWithoutACreationTime(string $xml): void
    {
        $bbb = $this->bbb(['create' => $this->success($xml)]);

        $this->expectException(InvalidResponse::class);

        $bbb->open(new RoomSpec(RoomId::fromString('room1'), 'Algebra'));
    }

    public function testTwentyDigitsAreACreationTime(): void
    {
        $bbb = $this->bbb(['create' => $this->success('<createTime>'.str_repeat('1', 20).'</createTime>')]);

        self::assertSame(str_repeat('1', 20), $bbb->open(new RoomSpec(RoomId::fromString('room1'), 'Algebra'))->sessionKey);
    }

    public function testARefusedRoomNamesTheKey(): void
    {
        $bbb = $this->bbb(['create' => $this->failure('checksum<Error>')]);

        $this->expectException(ProviderRefused::class);
        $this->expectExceptionMessageIsOrContains('The call "create" was refused: checksumError.');

        $bbb->open(new RoomSpec(RoomId::fromString('room1'), 'Algebra'));
    }

    public function testAJoinLinkIsSignedForTheRoleAndTheSession(): void
    {
        $bbb = $this->bbb([]);
        $participant = new Participant(ParticipantId::fromString('user-7'), 'Ada Lovelace');

        $moderator = $bbb->joinUrl(new OpenedRoom(RoomId::fromString('room1'), false, '1700000000123'), $participant, Role::Moderator);
        $query = 'fullName=Ada%20Lovelace&meetingID=room1&role=MODERATOR&userID=user-7&createTime=1700000000123';
        self::assertSame(self::API.'join?'.$query.'&checksum='.hash('sha256', 'join'.$query.self::SECRET), $moderator);

        $attendee = $bbb->joinUrl(new OpenedRoom(RoomId::fromString('room1')), $participant, Role::Attendee);
        $query = 'fullName=Ada%20Lovelace&meetingID=room1&role=VIEWER&userID=user-7';
        self::assertSame(self::API.'join?'.$query.'&checksum='.hash('sha256', 'join'.$query.self::SECRET), $attendee);
        self::assertSame([], $this->calls);
    }

    public function testARoomIsEnded(): void
    {
        $bbb = $this->bbb(['end' => $this->success('<messageKey>sentEndMeetingRequest</messageKey>')]);

        $bbb->end(RoomId::fromString('room1'));

        self::assertSame(['meetingID' => 'room1'], $this->query(0, 'end'));
    }

    public function testEndingARoomAlreadyGoneIsNotAnError(): void
    {
        $bbb = $this->bbb(['end' => $this->failure('notFound')]);

        $bbb->end(RoomId::fromString('room1'));

        self::assertCount(1, $this->calls);
    }

    public function testWhoIsInARunningRoom(): void
    {
        $bbb = $this->bbb(['getMeetingInfo' => $this->success('<running>true</running><attendees>
            <attendee><userID>user-7</userID><fullName>Ada</fullName><role>MODERATOR</role></attendee>
            <attendee><userID>user-8</userID></attendee>
            <attendee><userID>user-7</userID></attendee>
            <attendee><userID>w_abc@example.com</userID></attendee>
            <attendee><fullName>A guest</fullName></attendee>
        </attendees>')]);

        $presences = $bbb->presences(RoomId::fromString('room1'));

        self::assertSame(RoomState::Running, $presences->state);
        self::assertSame(['user-7', 'user-8'], array_map(static fn (ParticipantId $id): string => $id->value, $presences->participants));
        self::assertSame(['meetingID' => 'room1'], $this->query(0, 'getMeetingInfo'));
    }

    public function testARoomWaitingForItsFirstParticipant(): void
    {
        $presences = $this->bbb(['getMeetingInfo' => $this->success('<running>false</running><attendees/>')])->presences(RoomId::fromString('room1'));

        self::assertSame(RoomState::Waiting, $presences->state);
        self::assertSame([], $presences->participants);
    }

    public function testARoomTheServerDoesNotKnowIsGone(): void
    {
        $presences = $this->bbb(['getMeetingInfo' => $this->failure('notFound')])->presences(RoomId::fromString('room1'));

        self::assertSame(RoomState::Gone, $presences->state);
    }

    public function testTheRecordingsOfARoom(): void
    {
        $bbb = $this->bbb(['getRecordings' => $this->success('<recordings>
            <recording>
                <recordID>abc-1</recordID><meetingID>room1</meetingID><published>true</published>
                <startTime>1700000000000</startTime><endTime>1700003600000</endTime>
                <playback>
                    <format><type>presentation</type><url>https://bbb.example.com/playback/presentation/2.3/abc-1</url><length>60</length></format>
                    <format><type>video</type><url>https://bbb.example.com/video/abc-1/</url></format>
                    <format><type>Bad Type</type><url>https://bbb.example.com/x</url></format>
                    <format><type>notes</type><url>not an address</url></format>
                    <format><type>podcast</type><url></url></format>
                </playback>
            </recording>
            <recording><recordID>other</recordID><meetingID>room2</meetingID><playback><format><type>video</type></format></playback></recording>
            <recording><recordID>abc-2</recordID><meetingID>room1</meetingID><published>false</published><playback><format><type>video</type></format></playback></recording>
        </recordings>')]);

        $recordings = $bbb->recordings(RoomId::fromString('room1'));

        self::assertSame(['meetingID' => 'room1'], $this->query(0, 'getRecordings'));
        self::assertCount(4, $recordings);
        [$presentation, $video, $podcast, $unpublished] = $recordings;
        self::assertSame('abc-1', $presentation->id);
        self::assertSame('room1', $presentation->room->value);
        self::assertSame('presentation', $presentation->format);
        self::assertSame('https://bbb.example.com/playback/presentation/2.3/abc-1', $presentation->url);
        self::assertTrue($presentation->published);
        self::assertSame('2023-11-14T22:13:20+00:00', $presentation->startedAt?->format(\DATE_ATOM));
        self::assertSame('2023-11-14T23:13:20+00:00', $presentation->endedAt?->format(\DATE_ATOM));
        self::assertNull($presentation->expiresAt);
        self::assertSame('video', $video->format);
        self::assertSame('podcast', $podcast->format);
        self::assertNull($podcast->url);
        self::assertSame('abc-2', $unpublished->id);
        self::assertFalse($unpublished->published);
        self::assertNull($unpublished->url);
        self::assertNull($unpublished->startedAt);
    }

    public function testARoomWithoutRecordings(): void
    {
        self::assertSame([], $this->bbb(['getRecordings' => $this->success('<recordings/><messageKey>noRecordings</messageKey>')])->recordings(RoomId::fromString('room1')));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unreadable(): iterable
    {
        yield 'empty' => ['', 'The answer to "getMeetingInfo" is not the XML of a response.'];
        yield 'blank' => ["  \n", 'The answer to "getMeetingInfo" is not the XML of a response.'];
        yield 'not XML' => ['{"returncode":"SUCCESS"}', 'The answer to "getMeetingInfo" is not the XML of a response.'];
        yield 'another root' => ['<meeting><returncode>SUCCESS</returncode></meeting>', 'The answer to "getMeetingInfo" is not the XML of a response.'];
        yield 'a document type' => ['<!DOCTYPE response [<!ENTITY x "SUCCESS">]><response><returncode>&x;</returncode></response>', 'The answer to "getMeetingInfo" is not the XML of a response.'];
        yield 'no return code' => ['<response><running>true</running></response>', 'The answer to "getMeetingInfo" has no return code.'];
        yield 'another return code' => ['<response><returncode>MAYBE</returncode></response>', 'The answer to "getMeetingInfo" has no return code.'];
    }

    #[DataProvider('unreadable')]
    public function testAnAnswerThatCannotBeRead(string $body, string $message): void
    {
        $bbb = $this->bbb(['getMeetingInfo' => $body]);

        $this->expectException(InvalidResponse::class);
        $this->expectExceptionMessageIsOrContains($message);

        $bbb->presences(RoomId::fromString('room1'));
    }

    public function testReadingAnAnswerLeavesNoTraceInLibxml(): void
    {
        foreach ([true, false] as $previous) {
            $before = libxml_use_internal_errors($previous);

            try {
                XmlAnswer::read('<response><returncode>SUCCESS</returncode><broken></response>', 'a');
                self::fail('The answer is not XML.');
            } catch (InvalidResponse) {
                self::assertSame([], libxml_get_errors());
                self::assertSame($previous, libxml_use_internal_errors($previous));
            } finally {
                libxml_use_internal_errors($before);
            }
        }
    }

    public function testTheTextOfAnElementIsTrimmed(): void
    {
        $answer = XmlAnswer::read("<response><returncode>SUCCESS</returncode><x>\n  hello \n</x></response>", 'a');

        self::assertSame('hello', $answer->text('/response/x'));
        self::assertNull($answer->text('/response/y'));
    }

    public function testAFailureWithoutKey(): void
    {
        $bbb = $this->bbb(['getRecordings' => '<response><returncode>FAILED</returncode></response>']);

        $this->expectException(ProviderRefused::class);
        $this->expectExceptionMessageIsOrContains('The call "getRecordings" was refused: no message key.');

        $bbb->recordings(RoomId::fromString('room1'));
    }

    public function testAKeyIsCutToSixtyFourCharacters(): void
    {
        $bbb = $this->bbb(['getRecordings' => $this->failure(str_repeat('k', 70))]);

        $this->expectException(ProviderRefused::class);
        $this->expectExceptionMessageIsOrContains('The call "getRecordings" was refused: '.str_repeat('k', 64).'.');

        $bbb->recordings(RoomId::fromString('room1'));
    }

    public function testTheEndOfARoom(): void
    {
        $events = $this->bbb([])->receive(new Webhook('GET', [], ['meetingID' => 'room1', 'recordingmarks' => 'false'], '', Webhook::ENDED));

        self::assertCount(1, $events);
        self::assertInstanceOf(RoomEnded::class, $events[0]);
        self::assertSame('room1', $events[0]->room->value);
        self::assertSame('2023-11-14T22:13:20+00:00', $events[0]->at->format(\DATE_ATOM));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function notRooms(): iterable
    {
        yield 'none' => [[]];
        yield 'not an identifier' => [['meetingID' => 'Room 1']];
        yield 'a list' => [['meetingID' => ['room1']]];
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('notRooms')]
    public function testTheEndOfARoomNamesARoom(array $query): void
    {
        $this->expectException(InvalidWebhook::class);
        $this->expectExceptionMessageIsOrContains('The notification names no room of the application.');

        $this->bbb([])->receive(new Webhook('GET', [], $query, '', Webhook::ENDED));
    }

    public function testARecordingReady(): void
    {
        $token = JsonWebToken::sign(['meeting_id' => 'room1', 'record_id' => 'abc-1'], 'HS256', self::SECRET);

        $events = $this->bbb([])->receive(new Webhook('POST', ['Content-Type' => 'application/x-www-form-urlencoded'], [], 'signed_parameters='.$token));

        self::assertCount(1, $events);
        self::assertInstanceOf(RecordingReady::class, $events[0]);
        self::assertSame('room1', $events[0]->room->value);
        self::assertSame('abc-1', $events[0]->recordingId);
        self::assertNull($events[0]->recording);
        self::assertSame('2023-11-14T22:13:20+00:00', $events[0]->at->format(\DATE_ATOM));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedRecordings(): iterable
    {
        yield 'another secret' => ['signed_parameters='.JsonWebToken::sign(['meeting_id' => 'room1', 'record_id' => 'a'], 'HS256', 'another-secret-of-the-server-012'), 'The signature of the token does not match.'];
        yield 'HS512' => ['signed_parameters='.JsonWebToken::sign(['meeting_id' => 'room1', 'record_id' => 'a'], 'HS512', self::SECRET), 'The token is signed with an algorithm that is not accepted.'];
        yield 'no recording' => ['signed_parameters='.JsonWebToken::sign(['meeting_id' => 'room1'], 'HS256', self::SECRET), 'The notification of a recording names no recording.'];
        yield 'a recording that is not an identifier' => ['signed_parameters='.JsonWebToken::sign(['meeting_id' => 'room1', 'record_id' => 'a b'], 'HS256', self::SECRET), 'The notification of a recording names no recording.'];
        yield 'a recording followed by a line' => ['signed_parameters='.JsonWebToken::sign(['meeting_id' => 'room1', 'record_id' => "a\n"], 'HS256', self::SECRET), 'The notification of a recording names no recording.'];
        yield 'a recording as a number' => ['signed_parameters='.JsonWebToken::sign(['meeting_id' => 'room1', 'record_id' => 5], 'HS256', self::SECRET), 'The notification of a recording names no recording.'];
        yield 'no room' => ['signed_parameters='.JsonWebToken::sign(['record_id' => 'a'], 'HS256', self::SECRET), 'The notification names no room of the application.'];
        yield 'parameters as a list' => ['signed_parameters[]=a', 'The signed parameters of a recording are a token.'];
    }

    #[DataProvider('refusedRecordings')]
    public function testARefusedRecording(string $body, string $message): void
    {
        $this->expectException(InvalidWebhook::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->bbb([])->receive(new Webhook('POST', [], [], $body));
    }

    public function testTheAttendanceOfASession(): void
    {
        $body = json_encode(['version' => '1.0', 'meeting_id' => 'room1', 'data' => ['attendees' => [
            ['ext_user_id' => 'user-7', 'name' => 'Ada', 'joins' => [1_700_000_100_000, '2023-11-14T22:50:00Z'], 'leaves' => [['timestamp' => 1_700_001_000]]],
            ['ext_user_id' => 'user-8', 'joins' => [1_700_000_050], 'leaves' => 'soon'],
            ['ext_user_id' => 'ada@example.com', 'joins' => [1_700_000_000]],
            ['name' => 'A guest', 'joins' => [1_700_000_000]],
            'not an attendee',
            ['ext_user_id' => 'user-9', 'joins' => ['never', null]],
            ['ext_user_id' => 'user-10', 'joins' => [1_700_000_300]],
        ]]], \JSON_THROW_ON_ERROR);

        $events = $this->bbb([])->receive(new Webhook('POST', ['Authorization' => 'Bearer '.self::bearer()], [], $body));

        self::assertSame([
            [ParticipantJoined::class, 'user-8', '2023-11-14T22:14:10+00:00'],
            [ParticipantJoined::class, 'user-7', '2023-11-14T22:15:00+00:00'],
            [ParticipantJoined::class, 'user-10', '2023-11-14T22:18:20+00:00'],
            [ParticipantLeft::class, 'user-7', '2023-11-14T22:30:00+00:00'],
            [ParticipantJoined::class, 'user-7', '2023-11-14T22:50:00+00:00'],
        ], array_map(static fn (RoomEvent $event): array => [$event::class, $event instanceof ParticipantJoined || $event instanceof ParticipantLeft ? $event->participant->value : '', $event->at->format(\DATE_ATOM)], $events));
        self::assertSame('room1', $events[0]->room->value);
    }

    public function testAnAttendanceWithoutAttendees(): void
    {
        $bbb = $this->bbb([]);

        self::assertSame([], $bbb->receive(new Webhook('POST', ['Authorization' => 'Bearer '.self::bearer()], [], '{"meeting_id":"room1"}')));
        self::assertSame([], $bbb->receive(new Webhook('POST', ['Authorization' => 'Bearer '.self::bearer()], [], '{"meeting_id":"room1","data":{"attendees":"none"}}')));
        self::assertSame([], $bbb->receive(new Webhook('POST', ['Authorization' => 'Bearer '.self::bearer()], [], '{"meeting_id":"room1","data":"none"}')));
    }

    public function testTheAttendanceIsIgnoredWhenAnalyticsAreOff(): void
    {
        self::assertSame([], $this->bbb([], analytics: false)->receive(new Webhook('POST', [], [], '{"meeting_id":"room1"}')));
    }

    public function testAnAttendanceIsSignedAndAskedForByDefault(): void
    {
        $calls = [];
        $client = new MockHttpClient(function (string $method, string $url) use (&$calls): MockResponse {
            $calls[] = $url;

            return new MockResponse($this->success('<createTime>1</createTime>'));
        });
        $bbb = new BigBlueButton($client, Url::fromString(self::API), self::SECRET, $this->clock());

        $bbb->open(new RoomSpec(RoomId::fromString('room1'), 'Algebra', eventsUrl: Url::fromString('https://example.com/events')));

        self::assertCount(1, $calls);
        self::assertStringContainsString('meta_analytics-callback-url=', $calls[0]);

        $this->expectException(InvalidWebhook::class);
        $this->expectExceptionMessageIsOrContains('The attendance of a session comes with a bearer token.');

        $bbb->receive(new Webhook('POST', [], [], '{"meeting_id":"room1"}'));
    }

    public function testAnAttendanceTokenIsSignedWithAnyHmac(): void
    {
        foreach (['HS256', 'HS384', 'HS512'] as $algorithm) {
            $token = JsonWebToken::sign(['exp' => 1_700_086_400], $algorithm, self::SECRET);

            self::assertSame([], $this->bbb([])->receive(new Webhook('POST', ['Authorization' => 'Bearer '.$token], [], '{"meeting_id":"room1"}')));
        }
    }

    public function testAnUnsignedAttendanceIsReadWhenTheServerSendsNoToken(): void
    {
        $events = $this->bbb([], signedAnalytics: false)->receive(new Webhook('POST', [], [], '{"meeting_id":"room1","data":{"attendees":[{"ext_user_id":"u1","joins":[1700000000]}]}}'));

        self::assertCount(1, $events);
    }

    public function testAThousandAttendeesAndTwoHundredStaysAreRead(): void
    {
        $attendees = array_map(static fn (int $i): array => ['ext_user_id' => 'u'.$i, 'joins' => [], 'leaves' => []], range(1, 999));
        $attendees[] = ['ext_user_id' => 'u0', 'joins' => array_fill(0, 200, 1_700_000_000)];
        $body = json_encode(['meeting_id' => 'room1', 'data' => ['attendees' => $attendees]], \JSON_THROW_ON_ERROR);

        self::assertCount(200, $this->bbb([], signedAnalytics: false)->receive(new Webhook('POST', [], [], $body)));
    }

    /**
     * @return iterable<string, array{array<string, string>, string, string}>
     */
    public static function refusedAttendances(): iterable
    {
        $attendees = array_map(static fn (int $i): array => ['ext_user_id' => 'u'.$i], range(0, 1000));

        yield 'no token' => [[], '{"meeting_id":"room1"}', 'The attendance of a session comes with a bearer token.'];
        yield 'another scheme' => [['Authorization' => 'Basic '.self::bearer()], '{"meeting_id":"room1"}', 'The attendance of a session comes with a bearer token.'];
        yield 'another secret' => [['Authorization' => 'Bearer '.JsonWebToken::sign([], 'HS512', 'another-secret-of-the-server-012')], '{"meeting_id":"room1"}', 'The signature of the token does not match.'];
        yield 'not JSON' => [['Authorization' => 'Bearer '.self::bearer()], '{meeting', 'The attendance of a session is not JSON.'];
        yield 'nested too deep' => [['Authorization' => 'Bearer '.self::bearer()], str_repeat('[', 32).str_repeat(']', 32), 'The attendance of a session is not JSON.'];
        yield 'nested thirty-one deep' => [['Authorization' => 'Bearer '.self::bearer()], str_repeat('[', 31).str_repeat(']', 31), 'The notification names no room of the application.'];
        yield 'a string' => [['Authorization' => 'Bearer '.self::bearer()], '"room1"', 'The attendance of a session is not a JSON object.'];
        yield 'no room' => [['Authorization' => 'Bearer '.self::bearer()], '{"data":{}}', 'The notification names no room of the application.'];
        yield 'too many attendees' => [['Authorization' => 'Bearer '.self::bearer()], json_encode(['meeting_id' => 'room1', 'data' => ['attendees' => $attendees]], \JSON_THROW_ON_ERROR), 'The attendance of a session lists more than 1000 attendees.'];
        yield 'too many stays' => [['Authorization' => 'Bearer '.self::bearer()], json_encode(['meeting_id' => 'room1', 'data' => ['attendees' => [['ext_user_id' => 'u', 'leaves' => range(1, 201)]]]], \JSON_THROW_ON_ERROR), 'An attendee of the session has more than 200 stays.'];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('refusedAttendances')]
    public function testARefusedAttendance(array $headers, string $body, string $message): void
    {
        $this->expectException(InvalidWebhook::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->bbb([])->receive(new Webhook('POST', $headers, [], $body));
    }

    /**
     * @return iterable<string, array{Webhook, string}>
     */
    public static function refusedNotifications(): iterable
    {
        yield 'events by GET' => [new Webhook('GET', [], ['meetingID' => 'room1']), 'BigBlueButton notifies the end of a room by GET and its events by POST.'];
        yield 'the account channel' => [new Webhook('POST', [], [], '{}', Webhook::ACCOUNT), 'BigBlueButton notifies the end of a room by GET and its events by POST.'];
        yield 'over a mebibyte' => [new Webhook('POST', [], [], str_repeat(' ', 1_048_577)), 'The notification is too large.'];
        yield 'an end over a mebibyte' => [new Webhook('GET', [], ['meetingID' => 'room1'], str_repeat(' ', 1_048_577), Webhook::ENDED), 'The notification is too large.'];
    }

    #[DataProvider('refusedNotifications')]
    public function testARefusedNotification(Webhook $webhook, string $message): void
    {
        $this->expectException(InvalidWebhook::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->bbb([])->receive($webhook);
    }

    public function testAMebibyteIsReadWhenTheEndComesByGet(): void
    {
        self::assertCount(1, $this->bbb([])->receive(new Webhook('GET', [], ['meetingID' => 'room1'], str_repeat(' ', 1_048_576), Webhook::ENDED)));
    }

    /**
     * @param array<string, string> $answers the XML answer of each call, by its name
     */
    private function bbb(array $answers, bool $analytics = true, bool $signedAnalytics = true): BigBlueButton
    {
        $client = new MockHttpClient(function (string $method, string $url) use ($answers): MockResponse {
            $this->calls[] = $url;
            $call = (string) preg_replace('#^.*/api/([A-Za-z]+)\?.*$#', '$1', $url);

            return new MockResponse($answers[$call] ?? $this->failure('unexpected'));
        });

        return new BigBlueButton($client, Url::fromString(self::API), self::SECRET, $this->clock(), analytics: $analytics, signedAnalytics: $signedAnalytics);
    }

    /**
     * The query of a call made, its checksum checked and taken out.
     *
     * @return array<string, string>
     */
    private function query(int $index, string $call): array
    {
        self::assertArrayHasKey($index, $this->calls);
        $url = $this->calls[$index];
        self::assertStringStartsWith(self::API.$call.'?', $url);
        $query = substr($url, \strlen(self::API.$call.'?'));
        $position = strrpos($query, 'checksum=');
        self::assertNotFalse($position);
        $signed = rtrim(substr($query, 0, $position), '&');
        self::assertSame(hash('sha256', $call.$signed.self::SECRET), substr($query, $position + 9));
        parse_str($signed, $parameters);

        /** @var array<string, string> $parameters */
        return $parameters;
    }

    private function success(string $inner): string
    {
        return '<?xml version="1.0"?><response><returncode>SUCCESS</returncode>'.$inner.'</response>';
    }

    private function failure(string $key): string
    {
        return '<response><returncode>FAILED</returncode><messageKey>'.htmlspecialchars($key, \ENT_QUOTES).'</messageKey><message>Refused.</message></response>';
    }

    private static function bearer(): string
    {
        return JsonWebToken::sign(['exp' => 1_700_086_400], 'HS512', self::SECRET);
    }

    private function clock(): MockClock
    {
        return new MockClock(new DateTimeImmutable('@1700000000'));
    }
}
