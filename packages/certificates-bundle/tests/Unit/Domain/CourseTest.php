<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Domain;

use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\ValueObject\PassCriteria;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(Course::class)]
final class CourseTest extends TestCase
{
    public function testACourseIsDefinedWithWhatItSaysAndAsks(): void
    {
        $course = Course::define('first-aid', 'First aid', 'The gestures.', 'Score 70 %.', new PassCriteria(70, 60, ['cpr']), 24, 'https://example.com/a.png');

        self::assertTrue(Uuid::isValid($course->id));
        self::assertSame(['first-aid', 'First aid', 'The gestures.', 'Score 70 %.', 24, 'https://example.com/a.png', false], [$course->slug, $course->name, $course->description, $course->narrative, $course->validityMonths, $course->image, $course->retired]);
        self::assertEquals(new PassCriteria(70, 60, ['cpr']), $course->criteria());
        self::assertNotSame($course->id, Course::define('first-aid', 'First aid', '', 'Pass.')->id);
    }

    public function testACourseIsRevisedRetiredAndReopened(): void
    {
        $course = Course::define('first-aid', 'First aid', 'The gestures.', 'Score 70 %.', new PassCriteria(70), 24, 'https://example.com/a.png');
        $course->revise('First aid, revised', 'New.', 'Score 80 %.', new PassCriteria(80));

        self::assertSame(['First aid, revised', 'New.', 'Score 80 %.', null, null], [$course->name, $course->description, $course->narrative, $course->validityMonths, $course->image]);
        self::assertEquals(new PassCriteria(80), $course->criteria());

        $course->retire();
        self::assertTrue($course->retired);
        $course->reopen();
        self::assertFalse($course->retired);
    }

    public function testACourseNamesItsCompletionSourceWhichARevisionKeeps(): void
    {
        self::assertNull(Course::define('first-aid', 'First aid', '', 'Pass.')->completionSource);

        $course = Course::define('first-aid', 'First aid', '', 'Pass.', completionSource: 'first-aid-2027');
        self::assertSame('first-aid-2027', $course->completionSource);

        $course->revise('First aid, revised', '', 'Pass.');
        self::assertSame('first-aid-2027', $course->completionSource);

        $course->changeCompletionSource(str_repeat('k', 255));
        self::assertSame(str_repeat('k', 255), $course->completionSource);

        $course->changeCompletionSource(null);
        self::assertNull($course->completionSource);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCompletionSources(): iterable
    {
        yield 'an empty key' => [''];
        yield 'a key too long' => [str_repeat('k', 256)];
        yield 'a key with a space' => ['first aid'];
        yield 'a key with a line break' => ["first-aid\n"];
        yield 'a key with a control character' => ["first\x7Faid"];
    }

    #[DataProvider('invalidCompletionSources')]
    public function testACompletionSourceThatCannotBeKeptIsRefused(string $source): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The completion source of a course has from 1 to 255 characters, without spaces nor control characters.');

        Course::define('first-aid', 'First aid', '', 'Pass.', completionSource: $source);
    }

    public function testValuesAtTheirBoundsAreAccepted(): void
    {
        $slug = str_repeat('a', 64);
        $name = str_repeat('é', 255);
        $image = 'https://example.com/'.str_repeat('a', 2048 - 20);
        $course = Course::define($slug, $name, '', 'Pass.', validityMonths: 1, image: $image);

        self::assertSame([$slug, $name, 1, $image], [$course->slug, $course->name, $course->validityMonths, $course->image]);
        self::assertSame(1200, Course::define('a1-b2', 'A', '', 'Pass.', validityMonths: 1200)->validityMonths);
        self::assertSame('http://example.com', Course::define('a', 'A', '', 'Pass.', image: 'http://example.com')->image);
    }

    /**
     * @return iterable<string, array{string, string, string, ?int, ?string}>
     */
    public static function invalidCourses(): iterable
    {
        yield 'a slug too long' => [str_repeat('a', 65), 'A', 'Pass.', null, null];
        yield 'a slug in capitals' => ['First', 'A', 'Pass.', null, null];
        yield 'a slug with two dashes' => ['a--b', 'A', 'Pass.', null, null];
        yield 'a slug starting with a dash' => ['-a', 'A', 'Pass.', null, null];
        yield 'a slug ending with a dash' => ['a-', 'A', 'Pass.', null, null];
        yield 'a slug with a line break' => ["a\n", 'A', 'Pass.', null, null];
        yield 'a slug longer in front' => ['A-a', 'A', 'Pass.', null, null];
        yield 'an empty slug' => ['', 'A', 'Pass.', null, null];
        yield 'a blank name' => ['a', '  ', 'Pass.', null, null];
        yield 'a name too long' => ['a', str_repeat('é', 256), 'Pass.', null, null];
        yield 'a blank narrative' => ['a', 'A', ' ', null, null];
        yield 'no month' => ['a', 'A', 'Pass.', 0, null];
        yield 'too many months' => ['a', 'A', 'Pass.', 1201, null];
        yield 'an image too long' => ['a', 'A', 'Pass.', null, 'https://example.com/'.str_repeat('a', 2049 - 20)];
        yield 'an image not on the web' => ['a', 'A', 'Pass.', null, 'ftp://example.com/a.png'];
        yield 'an image without a host' => ['a', 'A', 'Pass.', null, 'https:///a.png'];
        yield 'an image with a space' => ['a', 'A', 'Pass.', null, 'https://example.com/a b.png'];
        yield 'an image with a line break' => ['a', 'A', 'Pass.', null, "https://example.com/a.png\n"];
        yield 'an image longer in front' => ['a', 'A', 'Pass.', null, 'see https://example.com/a.png'];
    }

    #[DataProvider('invalidCourses')]
    public function testACourseThatDoesNotHoldTogetherIsRefused(string $slug, string $name, string $narrative, ?int $months, ?string $image): void
    {
        $this->expectException(InvalidValue::class);

        Course::define($slug, $name, '', $narrative, validityMonths: $months, image: $image);
    }
}
