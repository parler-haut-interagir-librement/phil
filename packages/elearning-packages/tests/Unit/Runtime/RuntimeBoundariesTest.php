<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit\Runtime;

use Phil\ElearningPackages\Exception\RuntimeRefused;
use Phil\ElearningPackages\Runtime\CmiAttempt;
use Phil\ElearningPackages\Runtime\CmiCommit;
use Phil\ElearningPackages\Runtime\CmiData;
use Phil\ElearningPackages\Runtime\CmiRules;
use Phil\ElearningPackages\Runtime\Duration;
use Phil\ElearningPackages\Runtime\ElementName;
use Phil\ElearningPackages\Runtime\Entry;
use Phil\ElearningPackages\Runtime\LaunchSettings;
use Phil\ElearningPackages\Runtime\Mistake;
use Phil\ElearningPackages\Runtime\Score;
use Phil\ElearningPackages\Runtime\ScormError;
use Phil\ElearningPackages\Runtime\ScormSession;
use Phil\ElearningPackages\Runtime\ScormVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The edges of the run time: the lengths where a value is cut or refused, the checks that run in a given order, and
 * the patterns that must match a whole value and nothing else.
 */
#[CoversClass(CmiAttempt::class)]
#[CoversClass(CmiCommit::class)]
#[CoversClass(CmiData::class)]
#[CoversClass(CmiRules::class)]
#[CoversClass(Duration::class)]
#[CoversClass(ElementName::class)]
#[CoversClass(LaunchSettings::class)]
#[CoversClass(Score::class)]
#[CoversClass(ScormSession::class)]
final class RuntimeBoundariesTest extends TestCase
{
    public function testTheLaunchDataIsCutInCharactersAtTheLengthOfItsVersion(): void
    {
        foreach ([[ScormVersion::Scorm12, 4096], [ScormVersion::Scorm2004, 4000]] as [$version, $length]) {
            $data = new CmiData($version);
            CmiAttempt::begin($data, new LaunchSettings('ada', launchData: str_repeat('é', 5000)), Entry::AbInitio);

            self::assertSame(str_repeat('é', $length), $data->value('cmi.launch_data'));
        }
    }

    public function testTheCommitNamesTheElementsLeftOutByTheirFirstCharacters(): void
    {
        $long = 'a'.str_repeat('é', 300);
        $errors = CmiCommit::apply(new CmiData(ScormVersion::Scorm2004), [$long => 'x']);

        self::assertSame(['a'.str_repeat('é', 254) => 401], $errors);

        $values = [];

        for ($index = 0; $index < CmiCommit::MAX_VALUES; ++$index) {
            $values['x'.$index] = '1';
        }

        $values[$long] = '1';
        $errors = CmiCommit::apply(new CmiData(ScormVersion::Scorm12), $values);

        self::assertCount(CmiCommit::MAX_VALUES + 1, $errors);
        self::assertSame(101, $errors['a'.str_repeat('é', 254)]);
        self::assertSame(401, $errors['x0']);
    }

    public function testAnElementThatIsNotOneIsNamedByItsFirstEightyCharacters(): void
    {
        $name = str_repeat('é', 100);

        foreach ([
            static fn () => new CmiData(ScormVersion::Scorm12, [$name => 'x']),
            static fn () => new CmiData(ScormVersion::Scorm12)->put($name, 'x'),
        ] as $attempt) {
            try {
                $attempt();
                self::fail('An element that is not one is kept.');
            } catch (RuntimeRefused $runtimeRefused) {
                self::assertStringContainsString('"'.str_repeat('é', 80).'"', $runtimeRefused->getMessage());
            }
        }
    }

    public function testTheChecksOfAWriteRunInTheirOrder(): void
    {
        // An index past the next free one is refused before the identifier it lacks.
        $data = new CmiData(ScormVersion::Scorm2004);
        self::assertSame(ScormError::for(ScormVersion::Scorm2004, Mistake::OutOfOrder), $data->set('cmi.interactions.5.type', 'choice')->error);

        // The identifier an element lacks is refused before the length of its value.
        self::assertSame(0, $data->set('cmi.interactions.0.id', 'q1')->error);
        self::assertSame(ScormError::for(ScormVersion::Scorm2004, Mistake::Dependency), $data->set('cmi.interactions.0.learner_response', str_repeat('a', 4001))->error);

        // The pattern of a value is checked before the identifier of an objective that never changes.
        self::assertSame(0, $data->set('cmi.objectives.0.id', 'o1')->error);
        self::assertSame(ScormError::for(ScormVersion::Scorm2004, Mistake::TypeMismatch), $data->set('cmi.objectives.0.id', 'o 2')->error);
        self::assertSame(ScormError::for(ScormVersion::Scorm2004, Mistake::SetFailed), $data->set('cmi.objectives.0.id', 'o2')->error);
    }

    public function testAnIdentifierIsGivenOnceToTheObjectivesOfACollection(): void
    {
        $data = new CmiData(ScormVersion::Scorm2004);
        $setFailed = ScormError::for(ScormVersion::Scorm2004, Mistake::SetFailed);

        self::assertSame(0, $data->set('cmi.objectives.0.id', 'o1')->error);
        self::assertSame($setFailed, $data->set('cmi.objectives.1.id', 'o1')->error);
        self::assertSame(0, $data->set('cmi.objectives.1.id', 'o2')->error);
        self::assertSame(0, $data->set('cmi.objectives.1.id', 'o2')->error);
        self::assertSame(0, $data->set('cmi.interactions.0.id', 'i1')->error);
        self::assertSame(0, $data->set('cmi.interactions.0.objectives.0.id', 'o1')->error);
        self::assertSame($setFailed, $data->set('cmi.interactions.0.objectives.1.id', 'o1')->error);
        self::assertSame(0, $data->set('cmi.interactions.0.objectives.1.id', 'o2')->error);
    }

    public function testAnElementMayHoldItsLargestIndexAndNoMore(): void
    {
        $data = new CmiData(ScormVersion::Scorm12);

        for ($index = 0; $index < 100; ++$index) {
            self::assertSame(0, $data->set('cmi.objectives.'.$index.'.id', 'o'.$index)->error);
        }

        self::assertSame(ScormError::for(ScormVersion::Scorm12, Mistake::SetFailed), $data->set('cmi.objectives.100.id', 'o100')->error);
        self::assertSame('100', $data->get('cmi.objectives._count')->value);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function names(): iterable
    {
        yield 'the longest' => [str_repeat('a.', 127).'a', true];
        yield 'one past the longest' => [str_repeat('a.', 127).'ab', false];
        yield 'an empty name' => ['', false];
        yield 'a capital' => ['Cmi.location', false];
        yield 'a line break after it' => ["cmi.location\n", false];
        yield 'a segment that starts with a digit' => ['cmi.1abc', false];
        yield 'a segment that starts with two digits' => ['cmi.12ab', false];
        yield 'a navigation request with a line break after it' => ["adl.nav.request_valid.choice.{target=intro}\n", false];
        yield 'a navigation request with more after it' => ['adl.nav.request_valid.choice.{target=intro}x', false];
        yield 'a navigation request' => ['adl.nav.request_valid.choice.{target=intro}', true];
    }

    #[DataProvider('names')]
    public function testANameIsReadWholeOrNotAtAll(string $name, bool $valid): void
    {
        self::assertSame($valid, ElementName::parse($name) instanceof ElementName);
    }

    public function testAnIndexedElementFillsItsCollectionsIndexes(): void
    {
        $name = ElementName::parse('cmi.interactions.12.objectives.3.id');

        self::assertInstanceOf(ElementName::class, $name);
        self::assertSame('cmi.interactions.12.objectives', $name->collection(1));
        self::assertSame('cmi.interactions', $name->collection(0));
        self::assertSame('cmi.interactions.n.objectives', $name->collectionPattern(1));
    }

    public function testDurationsAreReadWholeAndWithinTheirLimits(): void
    {
        self::assertNotInstanceOf(Duration::class, Duration::parseTimespan("0001:00:00\n"));
        self::assertSame(360_000, Duration::parseTimespan('0001:00:00')?->centiseconds);
        self::assertNotInstanceOf(Duration::class, Duration::parseInterval("P1D\n"));
        self::assertNotInstanceOf(Duration::class, Duration::parseInterval('xP1D'));
        self::assertNotInstanceOf(Duration::class, Duration::parseInterval('P1Dx'));

        // Sixty-four characters are read; sixty-five are not.
        self::assertSame(0, Duration::parseInterval('P'.str_repeat('0', 62).'D')?->centiseconds);
        self::assertNotInstanceOf(Duration::class, Duration::parseInterval('P'.str_repeat('0', 63).'D'));

        // The most a value may hold is kept as it is, and what is past it is brought back to it.
        self::assertSame(3_599_999_950, Duration::parseInterval('PT9999H59M59.50S')?->centiseconds);
        self::assertSame(Duration::MAX_CENTISECONDS, Duration::parseInterval('PT9999H59M59.99S')?->centiseconds);
        self::assertSame(Duration::MAX_CENTISECONDS, Duration::parseInterval('PT10000H')?->centiseconds);
        self::assertSame(3_580_000_000, Duration::parseInterval('PT9944H26M40S')?->centiseconds);
        self::assertSame((365 + 30 + 1) * 86_400 * 100, Duration::parseInterval('P1Y1M1D')?->centiseconds);
    }

    /**
     * @return iterable<string, array{int, string, string}>
     */
    public static function spans(): iterable
    {
        yield 'a minute short of an hour' => [59 * 60 * 100, '0000:59:00.00', 'PT0H59M0S'];
        yield 'an hour and a half' => [(3600 + 1800 + 5) * 100 + 25, '0001:30:05.25', 'PT1H30M5.25S'];
        yield 'a hundred hours' => [360_000 * 100, '0100:00:00.00', 'PT100H0M0S'];
        yield 'a hundred hours and a minute short' => [(360_000 + 3590) * 100, '0100:59:50.00', 'PT100H59M50S'];
        yield 'the most' => [Duration::MAX_CENTISECONDS, '9999:59:59.99', 'PT9999H59M59.99S'];
    }

    #[DataProvider('spans')]
    public function testASpanIsWrittenInTheFormOfItsVersion(int $centiseconds, string $timespan, string $interval): void
    {
        $span = Duration::ofCentiseconds($centiseconds);

        self::assertSame($timespan, $span->timespan());
        self::assertSame($interval, $span->interval());
        self::assertSame($timespan, $span->format(ScormVersion::Scorm12));
        self::assertSame($interval, $span->format(ScormVersion::Scorm2004));
        self::assertSame($centiseconds, Duration::parse(ScormVersion::Scorm12, $timespan)?->centiseconds);
        self::assertSame($centiseconds, Duration::parse(ScormVersion::Scorm2004, $interval)?->centiseconds);
    }

    public function testAScoreIsOutOfOneHundredOnlyWhenTheCallerSaysSo(): void
    {
        self::assertNull(Score::scaled(null, '50', null, null));
        self::assertSame(0.5, Score::scaled(null, '50', null, null, true));
        self::assertNull(Score::scaled(null, '5', null, '10'));
        self::assertNull(Score::scaled(null, '5', '1', null));
        self::assertSame(0.5, Score::scaled(null, '5', '0', '10'));
    }

    public function testTheLearnerOfASessionIsNamedByPrintableCharactersOnly(): void
    {
        $this->expectException(RuntimeRefused::class);

        new LaunchSettings("ada\n");
    }

    public function testTheTextOfAnErrorCodeIsOnlyGivenForAWholeCode(): void
    {
        foreach ([ScormVersion::Scorm12, ScormVersion::Scorm2004] as $version) {
            $session = new ScormSession(new CmiData($version));

            self::assertNotSame('', $session->getErrorString('0'));

            foreach (["0\n", 'x0', '0x', '1234', ''] as $code) {
                self::assertSame('', $session->getErrorString($code), $code);
            }
        }
    }
}
