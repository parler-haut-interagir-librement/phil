<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\Role;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroomBundle\Application\WebhookSigner;
use Phil\VirtualClassroomBundle\Domain\Model\Room;
use Phil\VirtualClassroomBundle\Infrastructure\Memory\InMemoryClassroomStore;
use Phil\VirtualClassroomBundle\Infrastructure\Privacy\ClassroomPrivacy;
use Phil\VirtualClassroomBundle\Infrastructure\Routing\RoutedCallbackUrls;
use Phil\VirtualClassroomBundle\Infrastructure\Security\SecurityCurrentParticipant;
use Phil\VirtualClassroomBundle\Tests\Fixtures\RoleChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(SecurityCurrentParticipant::class)]
#[CoversClass(RoutedCallbackUrls::class)]
#[CoversClass(ClassroomPrivacy::class)]
final class AdaptersTest extends TestCase
{
    public function testTheAuthenticatedUserIsTheParticipant(): void
    {
        self::assertNull($this->signedIn(null)->participant(), 'Nobody signed in, nobody joins.');

        $participant = $this->signedIn('user-7')->participant();
        self::assertNotNull($participant);
        self::assertSame('user-7', $participant->id->value);
        self::assertSame('user-7', $participant->displayName);
        self::assertNull($this->signedIn('ada@example.com')->participant(), 'An e-mail address names nobody.');
        self::assertNull($this->signedIn(str_repeat('x', 129))->participant(), 'An identifier too long for a name names nobody.');
    }

    public function testTheRoleComesFromTheConfiguredSymfonyRole(): void
    {
        $room = new Room(RoomId::fromString('room1'), 'main', 'Algebra', false, null, null, new DateTimeImmutable('@0'));
        $checker = new RoleChecker(['ROLE_TEACHER']);

        self::assertSame(Role::Moderator, new SecurityCurrentParticipant(new TokenStorage(), $checker, 'ROLE_TEACHER')->role($room));
        self::assertSame([['ROLE_TEACHER', $room]], $checker->seen);
        self::assertSame(Role::Attendee, new SecurityCurrentParticipant(new TokenStorage(), new RoleChecker(), 'ROLE_TEACHER')->role($room));
    }

    public function testTheCallbacksOfARoomCarryTheirProof(): void
    {
        $signer = new WebhookSigner('the-secret-of-the-webhook');
        $callbacks = new RoutedCallbackUrls($this->generator('app.example.com'), $signer);

        self::assertSame('https://app.example.com/vc/webhook/main/room1/ended/'.$signer->token('main', 'room1', 'ended'), $callbacks->ended('main', RoomId::fromString('room1'))?->toString());
        self::assertSame('https://app.example.com/vc/webhook/main/room1/events/'.$signer->token('main', 'room1', 'events'), $callbacks->events('main', RoomId::fromString('room1'))?->toString());
    }

    public function testNoCallbackOnLocalhostUnlessAllowed(): void
    {
        $signer = new WebhookSigner('the-secret-of-the-webhook');

        self::assertNull(new RoutedCallbackUrls($this->generator('localhost'), $signer)->ended('main', RoomId::fromString('room1')));
        self::assertNull(new RoutedCallbackUrls($this->generator('127.0.0.1'), $signer, true)->events('main', RoomId::fromString('room1')));
        self::assertStringStartsWith('https://localhost/vc/webhook/main/room1/ended/', (string) new RoutedCallbackUrls($this->generator('localhost'), $signer, true)->ended('main', RoomId::fromString('room1'))?->toString());
    }

    public function testTheStaysOfAPersonAreExportedAndErased(): void
    {
        $store = new InMemoryClassroomStore();
        $store->arrive(RoomId::fromString('room1'), ParticipantId::fromString('user-7'), new DateTimeImmutable('@1700000000'));
        $store->leave(RoomId::fromString('room1'), ParticipantId::fromString('user-7'), new DateTimeImmutable('@1700000600'));
        $store->arrive(RoomId::fromString('room2'), ParticipantId::fromString('user-7'), new DateTimeImmutable('@1700001000'));

        $privacy = new ClassroomPrivacy($store, 'lms');

        self::assertSame('virtual_classroom', $privacy->module()->value);
        self::assertSame(['stays' => [
            ['room' => 'room1', 'joined_at' => '2023-11-14T22:13:20+00:00', 'left_at' => '2023-11-14T22:23:20+00:00'],
            ['room' => 'room2', 'joined_at' => '2023-11-14T22:30:00+00:00', 'left_at' => null],
        ]], $privacy->export(DataSubject::fromString('user-7')));
        self::assertSame([], $privacy->export(DataSubject::fromString('user-8')));
        self::assertSame([], $privacy->export(DataSubject::fromString('ada@example.com')));

        $report = $privacy->erase(DataSubject::fromString('user-7'));
        self::assertSame(['lms_stay' => ErasureEffect::Deleted], $report->effects);
        self::assertSame([], $store->staysOf(ParticipantId::fromString('user-7')));
        self::assertSame(['lms_stay' => ErasureEffect::Deleted], $privacy->erase(DataSubject::fromString('user-7'))->effects, 'An erasure runs again.');
        self::assertSame(['phil_virtual_classroom_stay' => ErasureEffect::Deleted], new ClassroomPrivacy($store)->erase(DataSubject::fromString('ada@example.com'))->effects);
    }

    private function signedIn(?string $user): SecurityCurrentParticipant
    {
        $tokens = new TokenStorage();

        if (null !== $user) {
            $tokens->setToken(new UsernamePasswordToken(new InMemoryUser($user, null), 'main'));
        }

        return new SecurityCurrentParticipant($tokens, new RoleChecker(), 'ROLE_TEACHER');
    }

    private function generator(string $host): UrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add(RoutedCallbackUrls::ROUTE, new Route('/vc/webhook/{provider}/{room}/{channel}/{token}'));

        return new UrlGenerator($routes, new RequestContext('', 'GET', $host, 'https'));
    }
}
