<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit\Runtime;

use Phil\ElearningPackages\Runtime\CmiData;
use Phil\ElearningPackages\Runtime\RuntimeResult;
use Phil\ElearningPackages\Runtime\ScormError;
use Phil\ElearningPackages\Runtime\ScormSession;
use Phil\ElearningPackages\Runtime\ScormVersion;
use Phil\ElearningPackages\Runtime\SessionState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScormSession::class)]
#[CoversClass(SessionState::class)]
#[CoversClass(RuntimeResult::class)]
#[CoversClass(ScormError::class)]
final class ScormSessionTest extends TestCase
{
    public function testASessionOfScorm2004AnswersEachCallByItsState(): void
    {
        $session = new ScormSession(new CmiData(ScormVersion::Scorm2004));

        self::assertSame(SessionState::NotInitialized, $session->state());
        self::assertSame('', $session->getValue('cmi.location'));
        self::assertSame('122', $session->getLastError());
        self::assertSame('Retrieve Data Before Initialization', $session->getErrorString('122'));
        self::assertSame('The session is not initialized.', $session->getDiagnostic());
        self::assertSame('false', $session->setValue('cmi.location', 'p1'));
        self::assertSame('132', $session->getLastError());
        self::assertSame('false', $session->commit());
        self::assertSame('142', $session->getLastError());
        self::assertSame('false', $session->terminate());
        self::assertSame('112', $session->getLastError());
        self::assertSame('false', $session->initialize('x'));
        self::assertSame('201', $session->getLastError());

        self::assertSame('true', $session->initialize());
        self::assertSame('0', $session->getLastError());
        self::assertSame(SessionState::Running, $session->state());
        self::assertSame('false', $session->initialize());
        self::assertSame('103', $session->getLastError());
        self::assertSame('true', $session->setValue('cmi.location', 'p1'));
        self::assertSame('p1', $session->getValue('cmi.location'));
        self::assertSame('false', $session->setValue('cmi.location', str_repeat('x', 1001)));
        self::assertSame('406', $session->getLastError());
        self::assertSame('The value holds 1000 characters at most.', $session->getDiagnostic('406'));
        self::assertSame('Data Model Element Value Out Of Range', $session->getDiagnostic('407'));
        self::assertSame('false', $session->commit('x'));
        self::assertSame('201', $session->getLastError());
        self::assertSame('true', $session->commit());
        self::assertSame('false', $session->terminate('x'));
        self::assertSame('201', $session->getLastError());
        self::assertSame('true', $session->terminate());
        self::assertSame(SessionState::Terminated, $session->state());

        self::assertSame('', $session->getValue('cmi.location'));
        self::assertSame('123', $session->getLastError());
        self::assertSame('The session is terminated.', $session->getDiagnostic());
        self::assertSame('false', $session->setValue('cmi.location', 'p2'));
        self::assertSame('133', $session->getLastError());
        self::assertSame('false', $session->commit());
        self::assertSame('143', $session->getLastError());
        self::assertSame('false', $session->terminate());
        self::assertSame('113', $session->getLastError());
        self::assertSame('false', $session->initialize());
        self::assertSame('104', $session->getLastError());
        self::assertSame('', $session->getErrorString('999'));
        self::assertSame('', $session->getErrorString('abc'));
        self::assertSame('', $session->getErrorString('1000'));
    }

    public function testASessionOfScorm12AnswersEachCallByItsState(): void
    {
        $session = new ScormSession(new CmiData(ScormVersion::Scorm12));

        self::assertSame('', $session->getValue('cmi.core.lesson_location'));
        self::assertSame('301', $session->getLastError());
        self::assertSame('false', $session->setValue('cmi.core.lesson_location', 'p1'));
        self::assertSame('301', $session->getLastError());
        self::assertSame('false', $session->commit());
        self::assertSame('301', $session->getLastError());
        self::assertSame('false', $session->terminate());
        self::assertSame('301', $session->getLastError());
        self::assertSame('false', $session->initialize('x'));
        self::assertSame('201', $session->getLastError());
        self::assertSame('Invalid argument error', $session->getErrorString('201'));

        self::assertSame('true', $session->initialize());
        self::assertSame('false', $session->initialize());
        self::assertSame('101', $session->getLastError());
        self::assertSame('false', $session->commit('x'));
        self::assertSame('201', $session->getLastError());
        self::assertSame('false', $session->terminate('x'));
        self::assertSame('201', $session->getLastError());
        self::assertSame('true', $session->terminate());

        self::assertSame('false', $session->setValue('cmi.core.lesson_location', 'p2'));
        self::assertSame('301', $session->getLastError());
        self::assertSame('false', $session->terminate());
        self::assertSame('301', $session->getLastError());
        self::assertSame('false', $session->initialize());
        self::assertSame('101', $session->getLastError());
    }

    public function testTheHooksKeepTheDataAndMayFail(): void
    {
        $commits = [];
        $answers = [true, false];
        $data = new CmiData(ScormVersion::Scorm2004);
        $session = new ScormSession($data, static function (CmiData $kept) use (&$commits, &$answers): bool {
            $commits[] = $kept->values();

            return array_shift($answers) ?? false;
        }, static fn (): bool => false);

        $session->initialize();
        $session->setValue('cmi.location', 'p1');

        self::assertSame('true', $session->commit());
        self::assertSame('false', $session->commit());
        self::assertSame('391', $session->getLastError());
        self::assertSame([['cmi.location' => 'p1'], ['cmi.location' => 'p1']], $commits);
        self::assertSame('false', $session->terminate());
        self::assertSame('111', $session->getLastError());
        self::assertSame(SessionState::Running, $session->state());

        $twelve = new ScormSession(new CmiData(ScormVersion::Scorm12), static fn (): bool => false, static fn (): bool => true);
        $twelve->initialize();

        self::assertSame('false', $twelve->commit());
        self::assertSame('101', $twelve->getLastError());
        self::assertSame('true', $twelve->terminate());

        $failing = new ScormSession(new CmiData(ScormVersion::Scorm12), null, static fn (): bool => false);
        $failing->initialize();

        self::assertSame('false', $failing->terminate());
        self::assertSame('101', $failing->getLastError());
    }

    public function testAWriteAnswersTrueOrTheErrorOfTheDataModel(): void
    {
        $session = new ScormSession(new CmiData(ScormVersion::Scorm2004));
        $session->initialize();

        self::assertSame('false', $session->setValue('cmi.learner_id', 'x'));
        self::assertSame('404', $session->getLastError());
        self::assertSame('The element is read only.', $session->getDiagnostic());
        self::assertSame('true', $session->setValue('cmi.score.raw', 7));
        self::assertSame('0', $session->getLastError());
        self::assertSame('', $session->getDiagnostic());
        self::assertSame('7', $session->getValue('cmi.score.raw'));
    }
}
