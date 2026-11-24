<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Unit\Application;

use Phil\SchedulingPollBundle\Application\AnswerForm;
use Phil\SchedulingPollBundle\Domain\Availability;
use Phil\SchedulingPollBundle\Domain\Exception\InvalidSchedulingPoll;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AnswerForm::class)]
final class AnswerFormTest extends TestCase
{
    public function testTheAnswersAndTheZonePostedAreRead(): void
    {
        $fields = ['answers' => ['a' => 'yes', 'b' => 'if_need_be', 'c' => 'no'], 'timezone' => 'Europe/Paris', 'token' => 'x'];

        self::assertSame(['a' => Availability::Yes, 'b' => Availability::IfNeedBe, 'c' => Availability::No], AnswerForm::answers($fields));
        self::assertSame('Europe/Paris', AnswerForm::timezone($fields));
        self::assertSame([], AnswerForm::answers([]));
        self::assertNull(AnswerForm::timezone([]));
        self::assertNull(AnswerForm::timezone(['timezone' => '']));
        self::assertNull(AnswerForm::timezone(['timezone' => ['UTC']]));
        self::assertCount(100, AnswerForm::answers(['answers' => array_fill_keys(array_map(static fn (int $i): string => 's'.$i, range(1, 100)), 'no')]));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function refused(): iterable
    {
        yield 'a text' => [['answers' => 'yes']];
        yield 'an unknown answer' => [['answers' => ['a' => 'maybe']]];
        yield 'a list' => [['answers' => ['yes']]];
        yield 'a nested list' => [['answers' => ['a' => ['yes']]]];
        yield 'too many dates' => [['answers' => array_fill_keys(array_map(static fn (int $i): string => 's'.$i, range(1, 101)), 'no')]];
    }

    /**
     * @param array<array-key, mixed> $fields
     */
    #[DataProvider('refused')]
    public function testAnythingElseIsRefused(array $fields): void
    {
        $this->expectExceptionObject(InvalidSchedulingPoll::because('The answers are a map of dates to "yes", "if_need_be" or "no".'));

        AnswerForm::answers($fields);
    }
}
