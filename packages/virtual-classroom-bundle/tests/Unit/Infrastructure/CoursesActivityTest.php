<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\CoursesBundle\Application\LaunchWindow;
use Phil\CoursesBundle\Domain\Exception\ActivityUnavailable;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\ModuleId;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroomBundle\Domain\Model\Room;
use Phil\VirtualClassroomBundle\Infrastructure\Courses\VirtualClassroomActivity;
use Phil\VirtualClassroomBundle\Infrastructure\Http\JoinController;
use Phil\VirtualClassroomBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(VirtualClassroomActivity::class)]
final class CoursesActivityTest extends TestCase
{
    public function testItIsALiveClassTheAttendanceCompletes(): void
    {
        $type = $this->type(new Stage());

        self::assertSame('virtual_classroom', $type->name());
        self::assertFalse($type->completesManually());
        self::assertSame('phil_courses:a1', VirtualClassroomActivity::owner('a1'));
    }

    public function testTheSettingsAreChecked(): void
    {
        $type = $this->type(new Stage());

        self::assertSame(['record' => false], $type->settings([]));
        self::assertSame(['provider' => 'basic', 'record' => true, 'duration' => 90, 'threshold' => 80], $type->settings(['provider' => 'basic', 'record' => true, 'duration' => 90, 'threshold' => 80, 'other' => 'x']));
        self::assertSame(['record' => false, 'duration' => 1, 'threshold' => 1], $type->settings(['duration' => 1, 'threshold' => 1]));
        self::assertSame(['record' => false, 'duration' => 1440, 'threshold' => 100], $type->settings(['duration' => 1440, 'threshold' => 100]));
    }

    /**
     * @return iterable<string, array{array<string, bool|float|int|string|null>, string}>
     */
    public static function wrongSettings(): iterable
    {
        yield 'an unknown provider' => [['provider' => 'other'], 'The "provider" of a live class is a server of the configuration.'];
        yield 'a provider that is not a name' => [['provider' => 5], 'The "provider" of a live class is a server of the configuration.'];
        yield 'a record that is not a boolean' => [['record' => 'yes'], 'Whether a live class is recorded, its "record", is true or false.'];
        yield 'no duration' => [['duration' => 0], 'The "duration" of a live class is 1 to 1440 minutes.'];
        yield 'a duration over a day' => [['duration' => 1441], 'The "duration" of a live class is 1 to 1440 minutes.'];
        yield 'a duration as text' => [['duration' => '60'], 'The "duration" of a live class is 1 to 1440 minutes.'];
        yield 'no threshold' => [['threshold' => 0], 'The "threshold" of a live class is 1 to 100 percent of the session.'];
        yield 'a threshold over the whole' => [['threshold' => 101], 'The "threshold" of a live class is 1 to 100 percent of the session.'];
        yield 'a threshold as a float' => [['threshold' => 50.5], 'The "threshold" of a live class is 1 to 100 percent of the session.'];
    }

    /**
     * @param array<string, bool|float|int|string|null> $settings
     */
    #[DataProvider('wrongSettings')]
    public function testAWrongSetting(array $settings, string $message): void
    {
        $this->expectException(InvalidCourse::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->type(new Stage())->settings($settings);
    }

    public function testTheFirstLaunchSchedulesTheRoomOfTheActivity(): void
    {
        $stage = new Stage();
        $type = $this->type($stage);
        $activity = $this->activity('Live session on algebra', ['provider' => 'basic', 'record' => true, 'duration' => 45]);

        $launch = $type->launch($activity, $this->enrollment());

        $room = $stage->classrooms->ofOwner('phil_courses:'.$activity->id->value);
        self::assertNotNull($room);
        self::assertSame('basic', $room->provider);
        self::assertTrue($room->record);
        self::assertSame(45, $room->maxMinutes);
        self::assertSame('Live session on algebra', $room->title);
        self::assertSame(LaunchWindow::NewWindow, $launch->window);
        self::assertSame('https://app.example.com/vc/'.$room->id->value.'/join', $launch->url);
        self::assertSame($launch->url, $type->launch($activity, $this->enrollment())->url);
        self::assertTrue(VirtualClassroomActivity::owns($room));
    }

    public function testTheTitleOfTheRoomFitsARoom(): void
    {
        $stage = new Stage();
        $type = $this->type($stage);
        $long = $this->activity(str_repeat('é', 70));
        $short = $this->activity('A');

        $type->launch($long, $this->enrollment());
        $type->launch($short, $this->enrollment());

        self::assertSame(str_repeat('é', 64), $stage->classrooms->ofOwner('phil_courses:'.$long->id->value)?->title);
        self::assertSame('Live class', $stage->classrooms->ofOwner('phil_courses:'.$short->id->value)?->title);
        $room = $stage->classrooms->ofOwner('phil_courses:'.$short->id->value);
        self::assertNotNull($room);
        self::assertSame('main', $room->provider);
        self::assertFalse($room->record);
        self::assertNull($room->maxMinutes);
    }

    public function testTheTitleIsTrimmedBeforeItIsCut(): void
    {
        $stage = new Stage();
        $type = $this->type($stage);
        $padded = $this->activity('   '.str_repeat('é', 70));
        $two = $this->activity('ab');
        $one = $this->activity('é');
        $blank = $this->activity('  A  ');

        foreach ([$padded, $two, $one, $blank] as $activity) {
            $type->launch($activity, $this->enrollment());
        }

        $titles = array_map(static fn (Activity $activity): ?string => $stage->classrooms->ofOwner('phil_courses:'.$activity->id->value)?->title, [$padded, $two, $one, $blank]);
        self::assertSame([str_repeat('é', 64), 'ab', 'Live class', 'Live class'], $titles);
    }

    public function testAnEndedLiveClassCannotBeLaunched(): void
    {
        $stage = new Stage();
        $activity = $this->activity('Live');
        $this->type($stage)->launch($activity, $this->enrollment());
        $room = $stage->classrooms->ofOwner('phil_courses:'.$activity->id->value);
        self::assertNotNull($room);
        $stage->classrooms->end($room->id);

        $this->expectException(ActivityUnavailable::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The live class "%s" has ended.', $activity->id->value));

        $this->type($stage)->launch($activity, $this->enrollment());
    }

    public function testALiveClassOnAServerNoLongerConfigured(): void
    {
        $activity = $this->activity('Live', ['provider' => 'gone']);

        $this->expectException(ActivityUnavailable::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The live class "%s" cannot be scheduled: No provider is named "gone".', $activity->id->value));

        $this->type(new Stage())->launch($activity, $this->enrollment());
    }

    public function testOnlyTheRoomsOfActivitiesBelongToTheCourses(): void
    {
        $at = new DateTimeImmutable('@0');

        self::assertFalse(VirtualClassroomActivity::owns(new Room(RoomId::fromString('room1'), 'main', 'Algebra', false, null, null, $at)));
        self::assertFalse(VirtualClassroomActivity::owns(new Room(RoomId::fromString('room1'), 'main', 'Algebra', false, null, 'meeting:1', $at)));
        self::assertFalse(VirtualClassroomActivity::owns(new Room(RoomId::fromString('room1'), 'main', 'Algebra', false, null, 'x_phil_courses:1', $at)));
    }

    private function type(Stage $stage): VirtualClassroomActivity
    {
        $routes = new RouteCollection();
        $routes->add(JoinController::ROUTE, new Route('/vc/{room}/join'));

        return new VirtualClassroomActivity($stage->classrooms, $stage->providers, new UrlGenerator($routes, new RequestContext('', 'GET', 'app.example.com', 'https')));
    }

    /**
     * @param array<string, bool|float|int|string|null> $settings
     */
    private function activity(string $title, array $settings = []): Activity
    {
        return new Activity(ActivityId::generate(), CourseId::generate(), ModuleId::generate(), $title, VirtualClassroomActivity::NAME, 0, $settings);
    }

    private function enrollment(): Enrollment
    {
        return new Enrollment(EnrollmentId::generate(), CourseId::generate(), LearnerId::of('user-7'), new DateTimeImmutable('@0'));
    }
}
