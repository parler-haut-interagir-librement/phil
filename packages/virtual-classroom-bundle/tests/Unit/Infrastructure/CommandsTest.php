<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Unit\Infrastructure;

use Phil\VirtualClassroom\Capabilities;
use Phil\VirtualClassroom\Exception\Unsupported;
use Phil\VirtualClassroom\Participant;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\Presences;
use Phil\VirtualClassroom\Role;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroom\RoomState;
use Phil\VirtualClassroomBundle\Application\WebhookSigner;
use Phil\VirtualClassroomBundle\Infrastructure\Console\SyncCommand;
use Phil\VirtualClassroomBundle\Infrastructure\Console\WebhookUrlCommand;
use Phil\VirtualClassroomBundle\Infrastructure\Http\WebhookController;
use Phil\VirtualClassroomBundle\Infrastructure\Messenger\SyncRoom;
use Phil\VirtualClassroomBundle\Infrastructure\Messenger\SyncRoomHandler;
use Phil\VirtualClassroomBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(SyncCommand::class)]
#[CoversClass(WebhookUrlCommand::class)]
#[CoversClass(SyncRoom::class)]
#[CoversClass(SyncRoomHandler::class)]
final class CommandsTest extends TestCase
{
    public function testTheOpenRoomsAreSynced(): void
    {
        $stage = $this->stageWithOpenRooms(3);
        $stage->main->presences = new Presences(RoomState::Running, [ParticipantId::fromString('ada')]);

        $tester = new CommandTester(new SyncCommand($stage->classrooms, $stage->store));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame('3 room(s) synced, 0 failed.', trim($tester->getDisplay()));
        self::assertSame(['presences room0', 'presences room1', 'presences room2'], $this->syncs($stage));

        $stage->main->calls = [];
        self::assertSame(Command::SUCCESS, $tester->execute(['--limit' => '2']));
        self::assertSame(['presences room0', 'presences room1'], $this->syncs($stage));

        $stage->main->calls = [];
        self::assertSame(Command::SUCCESS, $tester->execute(['--limit' => '0']));
        self::assertSame(['presences room0'], $this->syncs($stage), 'At least one room.');

        $stage->main->calls = [];
        self::assertSame(Command::SUCCESS, $tester->execute(['--limit' => 'many']));
        self::assertCount(3, $this->syncs($stage), 'A limit that is not a number is the default.');
    }

    public function testALimitThatIsNoNumberIsAHundred(): void
    {
        $stage = $this->stageWithOpenRooms(101);
        $stage->main->presences = new Presences(RoomState::Running);

        $tester = new CommandTester(new SyncCommand($stage->classrooms, $stage->store));
        $tester->execute(['--limit' => 'many']);

        self::assertSame('100 room(s) synced, 0 failed.', trim($tester->getDisplay()));
    }

    public function testAThousandRoomsAtMost(): void
    {
        $stage = $this->stageWithOpenRooms(1001);
        $stage->main->presences = new Presences(RoomState::Running);

        $tester = new CommandTester(new SyncCommand($stage->classrooms, $stage->store));

        $tester->execute(['--limit' => '5000']);

        self::assertSame('1000 room(s) synced, 0 failed.', trim($tester->getDisplay()));
    }

    public function testOneRoomIsSynced(): void
    {
        $stage = $this->stageWithOpenRooms(2);
        $stage->main->presences = new Presences(RoomState::Running);

        $tester = new CommandTester(new SyncCommand($stage->classrooms, $stage->store));

        self::assertSame(Command::SUCCESS, $tester->execute(['--room' => 'room1']));
        self::assertSame(['presences room1'], $this->syncs($stage));
        self::assertSame('1 room(s) synced, 0 failed.', trim($tester->getDisplay()));
    }

    public function testARoomThatIsNotOneIsInvalid(): void
    {
        $stage = new Stage();
        $tester = new CommandTester(new SyncCommand($stage->classrooms, $stage->store));

        self::assertSame(Command::INVALID, $tester->execute(['--room' => 'Room 1'], ['decorated' => true]));
        self::assertStringContainsString('A room is 2 to 64 lowercase letters', $tester->getDisplay());
        self::assertMatchesRegularExpression('/^\\e\\[37;41mA room is [^\\e]*\\e\\[39;49m$/', trim($tester->getDisplay()));
    }

    public function testAFailingRoomIsReportedAndTheOthersGoOn(): void
    {
        $stage = $this->stageWithOpenRooms(1);
        $tester = new CommandTester(new SyncCommand($stage->classrooms, $stage->store));

        self::assertSame(Command::FAILURE, $tester->execute(['--room' => 'room9']));
        self::assertStringContainsString('room9: No room is named "room9".', $tester->getDisplay());
        self::assertStringContainsString('0 room(s) synced, 1 failed.', $tester->getDisplay());

        self::assertSame(Command::FAILURE, $tester->execute([]), 'A server without presences fails the room.');
        self::assertStringContainsString('room0: No presences.', $tester->getDisplay());
    }

    public function testTheWebhookAddressOfAnAccount(): void
    {
        $stage = new Stage();
        $signer = new WebhookSigner('the-secret-of-the-webhook');
        $routes = new RouteCollection();
        $routes->add(WebhookController::ROUTE, new Route('/vc/webhook/{provider}/{token}'));

        $tester = new CommandTester(new WebhookUrlCommand($stage->providers, $signer, new UrlGenerator($routes, new RequestContext('', 'GET', 'app.example.com', 'https'))));

        self::assertSame(Command::SUCCESS, $tester->execute(['provider' => 'basic']));
        self::assertSame('https://app.example.com/vc/webhook/basic/'.$signer->token('basic', null, 'account'), trim($tester->getDisplay()));
        self::assertSame(Command::INVALID, $tester->execute(['provider' => 'other']));
        self::assertSame('No provider of the configuration has this name.', trim($tester->getDisplay()));
    }

    public function testAMessageSyncsItsRoom(): void
    {
        $stage = $this->stageWithOpenRooms(1);
        $stage->main->presences = new Presences(RoomState::Running, [ParticipantId::fromString('ada')]);

        $handler = new SyncRoomHandler($stage->classrooms);

        $handler(new SyncRoom('room0'));
        $handler(new SyncRoom('room9'));
        $handler(new SyncRoom('Not a room'));

        self::assertSame(['presences room0'], $this->syncs($stage));
        self::assertSame('room0', new SyncRoom('room0')->room);
    }

    public function testAServerOutOfReachFailsTheMessage(): void
    {
        $stage = $this->stageWithOpenRooms(1);
        $stage->main->presences = null;
        $stage->main->capabilities = new Capabilities(true, true, false, true);

        $this->expectException(Unsupported::class);

        new SyncRoomHandler($stage->classrooms)(new SyncRoom('room0'));
    }

    private function stageWithOpenRooms(int $count): Stage
    {
        $stage = new Stage();

        for ($i = 0; $i < $count; ++$i) {
            $stage->classrooms->schedule('Room '.$i, id: RoomId::fromString('room'.$i));
            $stage->clock->sleep(1);
            $stage->classrooms->joinUrl(RoomId::fromString('room'.$i), new Participant(ParticipantId::fromString('host'), 'Host'), Role::Moderator);
        }

        $stage->main->calls = [];

        return $stage;
    }

    /**
     * @return list<string>
     */
    private function syncs(Stage $stage): array
    {
        return array_values(array_filter($stage->main->calls, static fn (string $call): bool => str_starts_with($call, 'presences')));
    }
}
