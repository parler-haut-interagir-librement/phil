<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Unit\Infrastructure;

use Phil\VirtualClassroom\Event\RoomEnded;
use Phil\VirtualClassroom\Exception\InvalidResponse;
use Phil\VirtualClassroom\Exception\ProviderRefused;
use Phil\VirtualClassroom\Exception\ProviderUnavailable;
use Phil\VirtualClassroom\Participant;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\Role;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroomBundle\Application\WebhookSigner;
use Phil\VirtualClassroomBundle\Domain\Exception\UnknownProvider;
use Phil\VirtualClassroomBundle\Infrastructure\Http\JoinController;
use Phil\VirtualClassroomBundle\Infrastructure\Http\WebhookController;
use Phil\VirtualClassroomBundle\Tests\Fixtures\FixedParticipant;
use Phil\VirtualClassroomBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

#[CoversClass(JoinController::class)]
#[CoversClass(WebhookController::class)]
final class HttpTest extends TestCase
{
    private const string SECRET = 'the-secret-of-the-webhook';

    public function testAParticipantIsSentToTheRoomForTheirRole(): void
    {
        $stage = new Stage();
        $stage->classrooms->schedule('Algebra', id: RoomId::fromString('room1'));

        $controller = new JoinController($stage->classrooms, new FixedParticipant(new Participant(ParticipantId::fromString('ada'), 'Ada'), Role::Moderator));

        $response = $controller('room1');

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('https://meet.example.com/room1?as=ada&role=moderator&session=session-1', $response->headers->get('Location'));
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
    }

    /**
     * @return iterable<string, array{string, ?Throwable, bool, class-string<Throwable>, string}>
     */
    public static function refusedJoins(): iterable
    {
        yield 'not a room' => ['Room 1', null, true, NotFoundHttpException::class, 'No room is named so.'];
        yield 'nobody' => ['room1', null, false, AccessDeniedHttpException::class, 'Nobody of the request may join a room.'];
        yield 'an unknown room' => ['room9', null, true, NotFoundHttpException::class, 'No room is named "room9".'];
        yield 'a server no longer configured' => ['room1', UnknownProvider::of('main'), true, ServiceUnavailableHttpException::class, 'The conferencing server cannot open the room now.'];
        yield 'a server out of reach' => ['room1', ProviderUnavailable::because('Down.'), true, ServiceUnavailableHttpException::class, 'The conferencing server cannot open the room now.'];
        yield 'a server that refuses' => ['room1', ProviderRefused::because('No.'), true, ServiceUnavailableHttpException::class, 'The conferencing server cannot open the room now.'];
        yield 'a server answering nonsense' => ['room1', InvalidResponse::because('?'), true, ServiceUnavailableHttpException::class, 'The conferencing server cannot open the room now.'];
    }

    /**
     * @param class-string<Throwable> $expected
     */
    #[DataProvider('refusedJoins')]
    public function testARefusedJoin(string $room, ?Throwable $failure, bool $someone, string $expected, string $message): void
    {
        $stage = new Stage();
        $stage->classrooms->schedule('Algebra', id: RoomId::fromString('room1'));
        $stage->main->failure = $failure;

        $controller = new JoinController($stage->classrooms, new FixedParticipant($someone ? new Participant(ParticipantId::fromString('ada'), 'Ada') : null));

        try {
            $controller($room);
            self::fail('The join succeeded.');
        } catch (Throwable $throwable) {
            self::assertInstanceOf($expected, $throwable);
            self::assertSame($message, $throwable->getMessage());
        }
    }

    public function testAServerOutOfReachIsRetriedInThirtySeconds(): void
    {
        $stage = new Stage();
        $stage->classrooms->schedule('Algebra', id: RoomId::fromString('room1'));
        $stage->main->failure = ProviderUnavailable::because('Down.');

        try {
            new JoinController($stage->classrooms, new FixedParticipant(new Participant(ParticipantId::fromString('ada'), 'Ada')))('room1');
            self::fail('The join succeeded.');
        } catch (ServiceUnavailableHttpException $serviceUnavailableHttpException) {
            self::assertSame(30, $serviceUnavailableHttpException->getHeaders()['Retry-After'] ?? null);
            self::assertInstanceOf(ProviderUnavailable::class, $serviceUnavailableHttpException->getPrevious());
        }
    }

    public function testAnEndedRoomIsGone(): void
    {
        $stage = new Stage();
        $stage->classrooms->schedule('Algebra', id: RoomId::fromString('room1'));
        $stage->classrooms->end(RoomId::fromString('room1'));

        $this->expectException(GoneHttpException::class);
        $this->expectExceptionMessageIsOrContains('The room "room1" has ended.');

        new JoinController($stage->classrooms, new FixedParticipant(new Participant(ParticipantId::fromString('ada'), 'Ada')))('room1');
    }

    public function testANotificationOfARoomIsApplied(): void
    {
        $stage = new Stage();
        $stage->classrooms->schedule('Algebra', id: RoomId::fromString('room1'));
        $stage->main->events = [new RoomEnded(RoomId::fromString('room1'), $stage->at(0))];

        $signer = new WebhookSigner(self::SECRET);
        $request = Request::create('/webhook?meetingID=room1', 'GET', server: ['HTTP_X_TEST' => 'yes']);

        $response = new WebhookController($stage->classrooms, $signer)($request, 'main', $signer->token('main', 'room1', 'ended'), 'room1', 'ended');

        self::assertSame(204, $response->getStatusCode());
        self::assertTrue($stage->classrooms->room(RoomId::fromString('room1'))->hasEnded());
    }

    public function testANotificationOfTheAccount(): void
    {
        $stage = new Stage();
        $stage->classrooms->schedule('Algebra', id: RoomId::fromString('room1'));
        $stage->main->events = [new RoomEnded(RoomId::fromString('room1'), $stage->at(0))];

        $signer = new WebhookSigner(self::SECRET);

        $response = new WebhookController($stage->classrooms, $signer)(Request::create('/webhook', 'POST', content: '{}'), 'main', $signer->token('main', null, 'account'));

        self::assertSame(204, $response->getStatusCode());
        self::assertTrue($stage->classrooms->room(RoomId::fromString('room1'))->hasEnded());
    }

    /**
     * @return iterable<string, array{string, ?string, string, string}>
     */
    public static function wrongAddresses(): iterable
    {
        yield 'another provider' => ['basic', 'room1', 'ended', 'main'];
        yield 'another room' => ['main', 'room2', 'ended', 'main'];
        yield 'another channel' => ['main', 'room1', 'events', 'main'];
        yield 'the account' => ['main', null, 'account', 'main'];
    }

    #[DataProvider('wrongAddresses')]
    public function testAWrongProofIsAnAddressThatDoesNotExist(string $provider, ?string $room, string $channel, string $signedProvider): void
    {
        $stage = new Stage();
        $signer = new WebhookSigner(self::SECRET);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('No webhook has this address.');

        new WebhookController($stage->classrooms, $signer)(Request::create('/webhook', 'POST'), $provider, $signer->token($signedProvider, 'room1', 'ended'), $room, $channel);
    }

    public function testAnUnknownProviderOrOneWithoutNotificationsIsAnAddressThatDoesNotExist(): void
    {
        $stage = new Stage();
        $signer = new WebhookSigner(self::SECRET);
        $controller = new WebhookController($stage->classrooms, $signer);

        foreach ([['other', ''], ['main', 'unsupported']] as [$provider, $body]) {
            try {
                $controller(Request::create('/webhook', 'POST', content: $body), $provider, $signer->token($provider, null, 'account'));
                self::fail('The notification was accepted.');
            } catch (NotFoundHttpException $notFound) {
                self::assertSame('No webhook has this address.', $notFound->getMessage());
            }
        }
    }

    public function testARefusedNotificationIsUnauthorized(): void
    {
        $stage = new Stage();
        $signer = new WebhookSigner(self::SECRET);

        $response = new WebhookController($stage->classrooms, $signer)(Request::create('/webhook', 'POST', content: 'refuse'), 'main', $signer->token('main', null, 'account'));

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }

    public function testABodyOverAMebibyteIsTooLarge(): void
    {
        $stage = new Stage();
        $signer = new WebhookSigner(self::SECRET);
        $controller = new WebhookController($stage->classrooms, $signer);
        $token = $signer->token('main', null, 'account');

        self::assertSame(413, $controller(Request::create('/webhook', 'POST', content: str_repeat(' ', 1_048_577)), 'main', $token)->getStatusCode());
        self::assertSame(413, $controller(Request::create('/webhook', 'POST', server: ['CONTENT_LENGTH' => '1048577'], content: '{}'), 'main', $token)->getStatusCode());
        self::assertSame(204, $controller(Request::create('/webhook', 'POST', server: ['CONTENT_LENGTH' => '1048576'], content: str_repeat(' ', 1_048_576)), 'main', $token)->getStatusCode());
    }

    public function testAContentLengthThatIsNoNumberIsNotTooLarge(): void
    {
        $stage = new Stage();
        $signer = new WebhookSigner(self::SECRET);

        $response = new WebhookController($stage->classrooms, $signer)(Request::create('/webhook', 'POST', server: ['CONTENT_LENGTH' => 'abc'], content: '{}'), 'main', $signer->token('main', null, 'account'));

        self::assertSame(204, $response->getStatusCode());
    }

    public function testARoomThatIsNoRoomIsAnAddressThatDoesNotExist(): void
    {
        $stage = new Stage();
        $signer = new WebhookSigner(self::SECRET);

        $this->expectException(NotFoundHttpException::class);

        new WebhookController($stage->classrooms, $signer)(Request::create('/webhook', 'POST'), 'main', $signer->token('main', 'Room 1', 'ended'), 'Room 1', 'ended');
    }

    public function testTheHeadersAndTheQueryReachTheProvider(): void
    {
        $stage = new Stage();
        $signer = new WebhookSigner(self::SECRET);
        $request = Request::create('/webhook?meetingID=room1&recordingmarks=false', 'GET', server: ['HTTP_X_JAAS_SIGNATURE' => 't=1,v1=a', 'HTTP_AUTHORIZATION' => 'Bearer x']);

        new WebhookController($stage->classrooms, $signer)($request, 'main', $signer->token('main', 'room1', 'ended'), 'room1', 'ended');

        $webhook = $stage->main->received;
        self::assertNotNull($webhook);
        self::assertSame('GET', $webhook->method);
        self::assertSame(['meetingID' => 'room1', 'recordingmarks' => 'false'], $webhook->query);
        self::assertSame('t=1,v1=a', $webhook->header('x-jaas-signature'));
        self::assertSame('Bearer x', $webhook->header('authorization'));
        self::assertSame('', $webhook->body);
        self::assertSame('ended', $webhook->channel);
    }
}
