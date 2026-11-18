<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Scorm;

use Phil\ElearningPackages\Runtime\CmiData;
use Phil\ElearningPackages\Runtime\DataModel;
use Phil\ElearningPackages\Runtime\ScormSession;
use Phil\ElearningPackages\Runtime\ScormVersion;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The fixtures the tests of the scripts read, `tests/js/fixture/`, held to the data model of phil/elearning-packages:
 * the descriptions of the data model are those the page hands the browser, and every call of the vectors answers on
 * the server what the vectors say, which `cmi.test.mjs` asks of the browser. Both run the same calls; they agree.
 */
#[CoversNothing]
final class BrowserParityTest extends TestCase
{
    private const string FIXTURE = __DIR__.'/../../js/fixture/';

    public function testTheBrowserIsHandedTheDataModelOfTheServer(): void
    {
        foreach (ScormVersion::cases() as $version) {
            self::assertSame(
                json_decode((string) json_encode(DataModel::describe($version)), true),
                json_decode((string) file_get_contents(self::FIXTURE.'model-'.$version->value.'.json'), true),
                $version->value,
            );
        }
    }

    public function testTheServerAnswersTheVectorsTheBrowserAnswers(): void
    {
        /** @var list<array{version: string, values: array<string, string>, calls: list<array{0: string, 1?: string, 2?: string|int|array{repeat: array{string, int}}}>, expect: list<array{string, string}>}> $vectors */
        $vectors = json_decode((string) file_get_contents(self::FIXTURE.'vectors.json'), true, 16, \JSON_THROW_ON_ERROR);
        self::assertCount(2, $vectors);

        foreach ($vectors as $vector) {
            $session = new ScormSession(new CmiData(ScormVersion::from($vector['version']), $vector['values']));

            foreach ($vector['calls'] as $index => $call) {
                $argument = $call[1] ?? '';
                $written = $call[2] ?? null;
                $written = \is_array($written) ? str_repeat($written['repeat'][0], $written['repeat'][1]) : $written;
                $answer = match ($call[0]) {
                    'setValue' => $session->setValue($argument, $written),
                    'getValue' => $session->getValue($argument),
                    'initialize' => $session->initialize($argument),
                    'terminate' => $session->terminate($argument),
                    'commit' => $session->commit($argument),
                    'getErrorString' => $session->getErrorString($argument),
                    default => $session->getDiagnostic($argument),
                };

                self::assertSame($vector['expect'][$index], [$answer, $session->getLastError()], $vector['version'].' '.$call[0].'('.$argument.')');
            }
        }
    }
}
