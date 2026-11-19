<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Override;
use Phil\CoursesBundle\Application\Courses;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\CoursesBundle\Application\LaunchWindow;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Infrastructure\Doctrine\CourseTables;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\VirtualClassroom\BigBlueButton\BigBlueButton;
use Phil\VirtualClassroom\Jitsi\Jitsi;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroomBundle\Application\Classrooms;
use Phil\VirtualClassroomBundle\Application\Contract\ClassroomStore;
use Phil\VirtualClassroomBundle\Application\Providers;
use Phil\VirtualClassroomBundle\Application\WebhookSigner;
use Phil\VirtualClassroomBundle\Infrastructure\Courses\CoursesCompletion;
use Phil\VirtualClassroomBundle\Infrastructure\Courses\VirtualClassroomActivity;
use Phil\VirtualClassroomBundle\Infrastructure\Dbal\DbalClassroomStore;
use Phil\VirtualClassroomBundle\Infrastructure\DependencyInjection\VirtualClassroomServices;
use Phil\VirtualClassroomBundle\Infrastructure\Privacy\ClassroomPrivacy;
use Phil\VirtualClassroomBundle\PhilVirtualClassroomBundle;
use Phil\VirtualClassroomBundle\Tests\Functional\App\ScriptedServer;
use Phil\VirtualClassroomBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(PhilVirtualClassroomBundle::class)]
#[CoversClass(VirtualClassroomServices::class)]
#[CoversClass(CoursesCompletion::class)]
final class BundleTest extends WebTestCase
{
    private KernelBrowser $client;

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $store = self::getContainer()->get(ClassroomStore::class);
        self::assertInstanceOf(DbalClassroomStore::class, $store);
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);
        $manager = $connection->createSchemaManager();
        $manager->migrateSchema(CourseTables::configure($store->configureSchema($manager->introspectSchema()), $connection->getDatabasePlatform()));
    }

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testTheServersAreBuiltFromTheConfiguration(): void
    {
        $providers = self::getContainer()->get(Providers::class);
        self::assertInstanceOf(Providers::class, $providers);

        self::assertSame(['bbb', 'jitsi'], $providers->names());
        self::assertSame('bbb', $providers->defaultName());
        self::assertInstanceOf(BigBlueButton::class, $providers->get('bbb'));
        self::assertInstanceOf(Jitsi::class, $providers->get('jitsi'));
        self::assertTrue($providers->get('jitsi')->capabilities()->webhooks);
    }

    public function testAMemberJoinsARoomOnBigBlueButton(): void
    {
        $room = $this->classrooms()->schedule('Algebra', record: true, id: RoomId::fromString('room1'));
        $this->client->request('GET', '/vc/room1/join', server: ['HTTP_X_USER' => 'user-7', 'HTTP_HOST' => 'app.example.com', 'HTTPS' => 'on']);

        $response = $this->client->getResponse();
        self::assertSame(303, $response->getStatusCode(), (string) $response->getContent());
        $location = (string) $response->headers->get('Location');
        self::assertStringStartsWith('https://bbb.example.com/bigbluebutton/api/join?fullName=user-7&meetingID=room1&role=VIEWER&userID=user-7&createTime=1700000000123&checksum=', $location);
        $create = $this->server()->calls[0];
        $signer = self::getContainer()->get(WebhookSigner::class);
        self::assertInstanceOf(WebhookSigner::class, $signer);
        self::assertStringContainsString('meetingEndedURL='.rawurlencode('https://app.example.com/vc/webhook/bbb/room1/ended/'.$signer->token('bbb', 'room1', 'ended')), $create);
        self::assertStringContainsString('meta_bbb-recording-ready-url='.rawurlencode('https://app.example.com/vc/webhook/bbb/room1/events/'.$signer->token('bbb', 'room1', 'events')), $create);
        self::assertSame('1700000000123', $this->classrooms()->room($room->id)->sessionKey);
    }

    public function testATeacherJoinsAsModerator(): void
    {
        $this->classrooms()->schedule('Algebra', id: RoomId::fromString('room1'));
        $this->client->request('GET', '/vc/room1/join', server: ['HTTP_X_USER' => 'teacher']);

        self::assertStringContainsString('&role=MODERATOR&', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testNobodySignedInJoinsNothing(): void
    {
        $this->classrooms()->schedule('Algebra', id: RoomId::fromString('room1'));

        $this->client->request('GET', '/vc/room1/join');

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertSame([], $this->server()->calls);
    }

    public function testTheEndOfARoomComesByItsWebhook(): void
    {
        $this->classrooms()->schedule('Algebra', id: RoomId::fromString('room1'));
        $signer = self::getContainer()->get(WebhookSigner::class);
        self::assertInstanceOf(WebhookSigner::class, $signer);

        $this->client->request('GET', '/vc/webhook/bbb/room1/ended/'.$signer->token('bbb', 'room1', 'ended').'?meetingID=room1&recordingmarks=false');
        self::assertSame(204, $this->client->getResponse()->getStatusCode());
        self::assertTrue($this->classrooms()->room(RoomId::fromString('room1'))->hasEnded());

        $this->client->request('GET', '/vc/webhook/bbb/room1/ended/'.$signer->token('bbb', 'room1', 'events').'?meetingID=room1');
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testJitsiNotificationsComeToTheAccountAddress(): void
    {
        $this->classrooms()->schedule('Algebra', 'jitsi', id: RoomId::fromString('room1'));
        $signer = self::getContainer()->get(WebhookSigner::class);
        self::assertInstanceOf(WebhookSigner::class, $signer);
        $body = '{"eventType":"PARTICIPANT_JOINED","fqn":"app1/room1","timestamp":1700000000000,"data":{"id":"user-7"}}';
        $signature = 't=1700000000,v1='.sodium_bin2base64(hash_hmac('sha256', '1700000000.'.$body, TestKernel::JITSI_HOOKS, true), \SODIUM_BASE64_VARIANT_ORIGINAL);

        $this->client->request('POST', '/vc/webhook/jitsi/'.$signer->token('jitsi', null, 'account'), server: ['HTTP_X_JAAS_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], content: $body);
        self::assertSame(204, $this->client->getResponse()->getStatusCode());
        self::assertCount(1, $this->classrooms()->attendance(RoomId::fromString('room1')));

        $this->client->request('POST', '/vc/webhook/jitsi/'.$signer->token('jitsi', null, 'account'), server: ['HTTP_X_JAAS_SIGNATURE' => 't=1700000000,v1=bm9wZQ==', 'CONTENT_TYPE' => 'application/json'], content: $body);
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testALiveClassIsCompletedByItsAttendance(): void
    {
        $courses = self::getContainer()->get(Courses::class);
        $enrollments = self::getContainer()->get(Enrollments::class);
        self::assertInstanceOf(Courses::class, $courses);
        self::assertInstanceOf(Enrollments::class, $enrollments);
        $course = $courses->create('Algebra 101');
        $module = $courses->addModule($course->id, 'Week 1');
        $activity = $courses->addActivity($module->id, 'Live session', VirtualClassroomActivity::NAME, ['record' => true, 'threshold' => 60]);
        $ada = $enrollments->enroll($course->id, LearnerId::of('user-7'));
        $bob = $enrollments->enroll($course->id, LearnerId::of('bob'));

        $type = self::getContainer()->get(VirtualClassroomActivity::class);
        self::assertInstanceOf(VirtualClassroomActivity::class, $type);
        $first = $type->launch($courses->activity($activity->id), $ada);
        self::assertSame(LaunchWindow::NewWindow, $first->window);
        $room = $this->classrooms()->ofOwner(VirtualClassroomActivity::owner($activity->id->value));
        self::assertNotNull($room);
        self::assertSame('https://app.example.com/vc/'.$room->id->value.'/join', $first->url);
        self::assertSame($first->url, $type->launch($courses->activity($activity->id), $bob)->url, 'One room for the activity.');

        $this->client->request('GET', '/vc/'.$room->id->value.'/join', server: ['HTTP_X_USER' => 'teacher']);
        self::assertSame(303, $this->client->getResponse()->getStatusCode());
        $clock = self::getContainer()->get('clock');
        self::assertInstanceOf(MockClock::class, $clock);
        $store = self::getContainer()->get(ClassroomStore::class);
        self::assertInstanceOf(ClassroomStore::class, $store);
        $store->arrive($room->id, ParticipantId::fromString('user-7'), $clock->now());
        $store->arrive($room->id, ParticipantId::fromString('bob'), $clock->now()->modify('+50 minutes'));

        $clock->sleep(3600);

        $this->server()->answers['end'] = '<response><returncode>SUCCESS</returncode></response>';
        $this->classrooms()->end($room->id);

        self::assertSame(100, $enrollments->percent($enrollments->get($ada->id)));
        self::assertSame(0, $enrollments->percent($enrollments->get($bob->id)));
    }

    public function testTheStaysAreInTheAccessRequests(): void
    {
        $store = self::getContainer()->get(ClassroomStore::class);
        self::assertInstanceOf(ClassroomStore::class, $store);
        $store->arrive(RoomId::fromString('room1'), ParticipantId::fromString('user-7'), new DateTimeImmutable('@1700000000'));
        $export = self::getContainer()->get(SubjectExport::class);
        self::assertInstanceOf(SubjectExport::class, $export);
        $privacy = self::getContainer()->get(ClassroomPrivacy::class);
        self::assertInstanceOf(ClassroomPrivacy::class, $privacy);

        self::assertSame(['stays' => [['room' => 'room1', 'joined_at' => '2023-11-14T22:13:20+00:00', 'left_at' => null]]], $privacy->export(DataSubject::fromString('user-7')));
    }

    public function testTheCompletionIgnoresWhatIsNotALearnerOfTheActivity(): void
    {
        $courses = self::getContainer()->get(Courses::class);
        $enrollments = self::getContainer()->get(Enrollments::class);
        self::assertInstanceOf(Courses::class, $courses);
        self::assertInstanceOf(Enrollments::class, $enrollments);
        $course = $courses->create('Algebra 101');
        $module = $courses->addModule($course->id, 'Week 1');
        $activity = $courses->addActivity($module->id, 'Live session', VirtualClassroomActivity::NAME);
        $ada = $enrollments->enroll($course->id, LearnerId::of('user-7'));
        $store = self::getContainer()->get(ClassroomStore::class);
        self::assertInstanceOf(ClassroomStore::class, $store);
        $clock = self::getContainer()->get('clock');
        self::assertInstanceOf(MockClock::class, $clock);

        $plain = $this->classrooms()->schedule('Plain', id: RoomId::fromString('plain'));
        $orphan = $this->classrooms()->schedule('Orphan', owner: VirtualClassroomActivity::owner('no-such-activity'), id: RoomId::fromString('orphan'));
        $live = $this->classrooms()->schedule('Live', owner: VirtualClassroomActivity::owner($activity->id->value), id: RoomId::fromString('live'));

        foreach ([$plain, $orphan, $live] as $room) {
            $store->arrive($room->id, ParticipantId::fromString('user-7'), $clock->now());
            $store->arrive($room->id, ParticipantId::fromString('not-enrolled'), $clock->now());
            $store->arrive($room->id, ParticipantId::fromString('a:b'), $clock->now());
        }

        $store->arrive($live->id, ParticipantId::fromString('half'), $clock->now()->modify('+1 minute'));
        $enrollments->enroll($course->id, LearnerId::of('half'));
        $clock->sleep(120);
        $this->classrooms()->end($plain->id);
        $this->classrooms()->end($orphan->id);
        self::assertSame(0, $enrollments->percent($enrollments->get($ada->id)), 'A room of no activity completes nothing.');

        $this->classrooms()->end($live->id);

        self::assertSame(100, $enrollments->percent($enrollments->get($ada->id)), 'The threshold of the configuration, 50 %, is reached.');
        $half = $enrollments->of($course->id, LearnerId::of('half'));
        self::assertNotNull($half);
        self::assertSame(100, $enrollments->percent($half), 'Half the session reaches 50 %.');
    }

    private function classrooms(): Classrooms
    {
        $classrooms = self::getContainer()->get(Classrooms::class);
        self::assertInstanceOf(Classrooms::class, $classrooms);

        return $classrooms;
    }

    private function server(): ScriptedServer
    {
        $server = self::getContainer()->get(ScriptedServer::class);
        self::assertInstanceOf(ScriptedServer::class, $server);

        return $server;
    }
}
