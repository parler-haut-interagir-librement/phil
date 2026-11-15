<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\CertificatesBundle\Application\Courses;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Infrastructure\Console\CourseCommand;
use Phil\CertificatesBundle\Tests\Unit\Support\FixedUrls;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryCourses;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(CourseCommand::class)]
final class CourseCommandTest extends TestCase
{
    private InMemoryCourses $courses;

    private CommandTester $tester;

    #[Override]
    protected function setUp(): void
    {
        $this->courses = new InMemoryCourses();
        $this->tester = new CommandTester(new CourseCommand(new Courses($this->courses), new FixedUrls()));
    }

    public function testACourseIsDefinedWithItsCriteria(): void
    {
        $this->tester->execute([
            'slug' => 'first-aid',
            'name' => 'First aid',
            'narrative' => 'Score 70 % or more and practise CPR.',
            '--description' => 'The gestures that save lives.',
            '--min-score' => '70',
            '--min-minutes' => '240',
            '--module' => ['cpr', 'aed'],
            '--validity' => '24',
            '--image' => 'https://example.com/first-aid.png',
        ]);

        self::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        self::assertSame("Defined the course \"first-aid\", First aid.\nhttps://example.com/certificates/courses/first-aid\n", $this->tester->getDisplay());
        $course = $this->course();
        self::assertSame(['First aid', 'The gestures that save lives.', 'Score 70 % or more and practise CPR.', 24, 'https://example.com/first-aid.png'], [$course->name, $course->description, $course->narrative, $course->validityMonths, $course->image]);
        self::assertSame([70, 240, ['cpr', 'aed']], [$course->criteria()->minimumScore, $course->criteria()->minimumMinutes, $course->criteria()->requiredModules]);
    }

    public function testACourseNamesTheCreditsOfItsRecertificationAndItsEnd(): void
    {
        $this->tester->execute(['slug' => 'cpr', 'name' => 'CPR', 'narrative' => 'Attend.', '--credits' => '7,5', '--ends' => '2030-01-01']);

        self::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        self::assertSame(['7.5', '2030-01-01 00:00:00 +00:00'], [$this->course('cpr')->recertificationCredits?->formatted(), $this->course('cpr')->endsAt?->format('Y-m-d H:i:s P')]);

        $this->tester->execute(['slug' => 'cpr', 'name' => 'CPR', 'narrative' => 'Attend.', '--revise' => true]);
        self::assertNull($this->course('cpr')->recertificationCredits);
        self::assertNull($this->course('cpr')->endsAt);
    }

    public function testACourseNamesItsCompletionSourceWhichARevisionChangesOnlyWhenGiven(): void
    {
        $this->tester->execute(['slug' => 'cpr', 'name' => 'CPR', 'narrative' => 'Attend.', '--completion-source' => 'cpr-2027']);
        self::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        self::assertSame('cpr-2027', $this->course('cpr')->completionSource);

        $this->tester->execute(['slug' => 'cpr', 'name' => 'CPR II', 'narrative' => 'Attend.', '--revise' => true]);
        self::assertSame(['CPR II', 'cpr-2027'], [$this->course('cpr')->name, $this->course('cpr')->completionSource]);

        $this->tester->execute(['slug' => 'cpr', 'name' => 'CPR II', 'narrative' => 'Attend.', '--revise' => true, '--completion-source' => 'cpr-2028']);
        self::assertSame("Revised the course \"cpr\", CPR II.\nhttps://example.com/certificates/courses/cpr\n", $this->tester->getDisplay());
        self::assertSame('cpr-2028', $this->course('cpr')->completionSource);

        $this->tester->execute(['slug' => 'cpr', 'name' => 'CPR II', 'narrative' => 'Attend.', '--revise' => true, '--completion-source' => '']);
        self::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        self::assertNull($this->course('cpr')->completionSource);

        $this->tester->execute(['slug' => 'botany', 'name' => 'Botany', 'narrative' => 'Attend.', '--completion-source' => '']);
        self::assertNull($this->course('botany')->completionSource);

        $this->tester->execute(['slug' => 'zoology', 'name' => 'Zoology', 'narrative' => 'Attend.', '--completion-source' => 'a b']);
        self::assertSame(Command::INVALID, $this->tester->getStatusCode());
        self::assertSame("The completion source of a course has from 1 to 255 characters, without spaces nor control characters.\n", $this->tester->getDisplay());
    }

    public function testACourseWithoutOptionsIsPassedByCompletingItAndHoldsForEver(): void
    {
        $this->tester->execute(['slug' => 'botany', 'name' => 'Botany', 'narrative' => 'Attend.']);

        self::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        $course = $this->course('botany');
        self::assertSame(['', null, null, null, null, []], [$course->description, $course->validityMonths, $course->image, $course->criteria()->minimumScore, $course->criteria()->minimumMinutes, $course->criteria()->requiredModules]);
    }

    public function testACourseIsRevisedWhenAsked(): void
    {
        $this->tester->execute(['slug' => 'botany', 'name' => 'Botany', 'narrative' => 'Attend.']);
        $this->tester->execute(['slug' => 'botany', 'name' => 'Botany II', 'narrative' => 'Score 80.', '--min-score' => '80', '--revise' => true]);

        self::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        self::assertSame("Revised the course \"botany\", Botany II.\nhttps://example.com/certificates/courses/botany\n", $this->tester->getDisplay());
        self::assertSame(['Botany II', 80], [$this->course('botany')->name, $this->course('botany')->criteria()->minimumScore]);
    }

    public function testACourseDefinedTwiceOrRevisedUnknownIsRefused(): void
    {
        $this->tester->execute(['slug' => 'botany', 'name' => 'Botany', 'narrative' => 'Attend.']);
        $this->tester->execute(['slug' => 'botany', 'name' => 'Botany', 'narrative' => 'Attend.']);

        self::assertSame(Command::FAILURE, $this->tester->getStatusCode());
        self::assertSame("A course \"botany\" already exists.\n", $this->tester->getDisplay());

        $this->tester->execute(['slug' => 'zoology', 'name' => 'Zoology', 'narrative' => 'Attend.', '--revise' => true]);

        self::assertSame(Command::FAILURE, $this->tester->getStatusCode());
        self::assertSame("There is no course \"zoology\".\n", $this->tester->getDisplay());
        self::assertSame(1, $this->courses->saved);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidCourses(): iterable
    {
        yield 'a score in words' => [['--min-score' => 'high'], 'The minimum score is a whole number; "high" is not.'];
        yield 'a score above 100' => [['--min-score' => '101'], 'A minimum score is from 1 to 100; 101 is not.'];
        yield 'minutes with a decimal' => [['--min-minutes' => '1.5'], 'The minimum of minutes is a whole number; "1.5" is not.'];
        yield 'no minute' => [['--min-minutes' => '0'], 'A minimum of minutes of training is at least 1.'];
        yield 'a validity in words' => [['--validity' => 'a year'], 'The validity in months is a whole number; "a year" is not.'];
        yield 'no month of validity' => [['--validity' => '0'], 'A certificate is valid from 1 to 1200 months; 0 is not.'];
        yield 'credits in words' => [['--credits' => 'twelve'], 'Credits are a number with up to two decimals, such as 1.5; "twelve" is not.'];
        yield 'no credits' => [['--credits' => '0'], 'A recertification asks for more than 0 credits, or for none at all.'];
        yield 'an end that is no day' => [['--ends' => '2030-13-01'], 'The end is a day written YYYY-MM-DD; "2030-13-01" is not.'];
        yield 'an end with a time' => [['--ends' => '2030-01-01 10:00'], 'The end is a day written YYYY-MM-DD; "2030-01-01 10:00" is not.'];
        yield 'a module twice' => [['--module' => ['cpr', 'cpr']], 'A module is required once.'];
        yield 'a relative image' => [['--image' => '/first-aid.png'], 'The image of a course is an absolute HTTP URL; "/first-aid.png" is not.'];
        yield 'a slug in capitals' => [['slug' => 'First-Aid'], 'The slug of a course is up to 64 lower-case letters, digits and single dashes; "First-Aid" is not.'];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('invalidCourses')]
    public function testACourseThatCannotBeReadIsInvalid(array $options, string $message): void
    {
        $this->tester->execute([...['slug' => 'first-aid', 'name' => 'First aid', 'narrative' => 'Attend.'], ...$options]);

        self::assertSame(Command::INVALID, $this->tester->getStatusCode());
        self::assertSame($message."\n", $this->tester->getDisplay());
        self::assertSame([], $this->courses->courses);
    }

    public function testTheCommandIsNamedAndDescribed(): void
    {
        $command = new CourseCommand(new Courses($this->courses), new FixedUrls());

        self::assertSame('phil:certificates:course', $command->getName());
        self::assertSame('Defines a course and its pass criteria, or revises it', $command->getDescription());
        self::assertSame('m', $command->getDefinition()->getOption('module')->getShortcut());
    }

    private function course(string $slug = 'first-aid'): Course
    {
        $course = $this->courses->findBySlug($slug);
        self::assertInstanceOf(Course::class, $course);

        return $course;
    }
}
