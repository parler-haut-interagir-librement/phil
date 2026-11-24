<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Unit\Infrastructure\Twig;

use DateTimeZone;
use Phil\SchedulingPollBundle\Application\SchedulingPollViews;
use Phil\SchedulingPollBundle\Infrastructure\Twig\SchedulingPollTwig;
use Phil\SchedulingPollBundle\Tests\Unit\Fixture\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchedulingPollTwig::class)]
final class SchedulingPollTwigTest extends TestCase
{
    public function testTheFunctionsGiveTheResultsTheZonesAndTheNamesOfTheRecipes(): void
    {
        $test = Fixtures::memory();
        $poll = $test->poll();
        $views = new SchedulingPollViews($test->polls);

        self::assertSame('Table:Row', new SchedulingPollTwig($views)->kit('Table:Row'));
        self::assertSame('ui:Table:Row', new SchedulingPollTwig($views, 'ui')->kit('Table:Row'));
        self::assertSame('ui:kit:Button', new SchedulingPollTwig($views, '/ui/kit/')->kit('Button'));

        $twig = new SchedulingPollTwig($views);
        self::assertSame(DateTimeZone::listIdentifiers(), $twig->timezones());
        self::assertSame($poll->id, $twig->results($poll->id, 'user:ada')->poll);
        self::assertSame('Asia/Tokyo', $twig->results($poll->id, Fixtures::organizer(), 'Asia/Tokyo')->timezone->getName());
    }
}
