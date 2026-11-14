<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Infrastructure;

use Phil\UploadScan\FakeScanner;
use Phil\UploadScan\OnScanError;
use Phil\UploadScan\ScanPolicy;
use Phil\UploadScanBundle\Application\Event\FileInfected;
use Phil\UploadScanBundle\Application\UploadScanner;
use Phil\UploadScanBundle\Infrastructure\Validator\CleanFile;
use Phil\UploadScanBundle\Infrastructure\Validator\CleanFileValidator;
use Phil\UploadScanBundle\Tests\Unit\Fixture\MisdirectedConstraint;
use Phil\UploadScanBundle\Tests\Unit\Fixture\RecordingDispatcher;
use Phil\UploadScanBundle\Tests\Unit\Fixture\Temporary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Validation;

#[CoversClass(CleanFile::class)]
#[CoversClass(CleanFileValidator::class)]
final class CleanFileValidatorTest extends TestCase
{
    private string $directory;

    private RecordingDispatcher $events;

    protected function setUp(): void
    {
        $this->directory = Temporary::directory();
        $this->events = new RecordingDispatcher();
    }

    protected function tearDown(): void
    {
        Temporary::remove($this->directory);
    }

    public function testACleanFilePasses(): void
    {
        self::assertCount(0, $this->validate(new File($this->file('a manuscript')), new CleanFile()));
        self::assertCount(0, $this->validate($this->file('a manuscript'), new CleanFile()));
    }

    public function testAnInfectedUploadIsRefusedUnderItsOwnName(): void
    {
        $upload = new UploadedFile($this->file(Temporary::eicar()), 'Chapter 1.docx', null, \UPLOAD_ERR_OK, true);
        $violations = $this->validate($upload, new CleanFile(context: 'manuscript'));

        self::assertCount(1, $violations);
        self::assertSame('This file contains a threat and was refused.', $violations->get(0)->getMessage());
        self::assertSame(CleanFile::INFECTED_ERROR, $violations->get(0)->getCode());
        self::assertEquals([new FileInfected('Chapter 1.docx', 'Eicar-Test-Signature', 'manuscript')], $this->events->events);
        self::assertSame('INFECTED_ERROR', CleanFile::getErrorName(CleanFile::INFECTED_ERROR));
    }

    public function testAFileAndAPathAreNamedAfterTheirFile(): void
    {
        $this->validate(new File($this->file(Temporary::eicar(), 'a.png')), new CleanFile());
        $this->validate($this->file(Temporary::eicar(), 'b.png'), new CleanFile());

        self::assertEquals([new FileInfected('a.png', 'Eicar-Test-Signature', 'upload'), new FileInfected('b.png', 'Eicar-Test-Signature', 'upload')], $this->events->events);
    }

    public function testAFileThatCouldNotBeScannedIsRefusedOrAcceptedAsThePolicySays(): void
    {
        $refused = $this->validate($this->file('x'), new CleanFile(), new FakeScanner(error: 'down'));

        self::assertCount(1, $refused);
        self::assertSame('This file could not be checked for threats. Please try again later.', $refused->get(0)->getMessage());
        self::assertSame(CleanFile::NOT_SCANNED_ERROR, $refused->get(0)->getCode());
        self::assertSame('NOT_SCANNED_ERROR', CleanFile::getErrorName(CleanFile::NOT_SCANNED_ERROR));
        self::assertCount(0, $this->validate($this->file('x'), new CleanFile(), new FakeScanner(error: 'down'), OnScanError::AcceptAndFlag));
    }

    public function testTheMessagesCanBeChanged(): void
    {
        $constraint = new CleanFile(infectedMessage: 'Refused.', notScannedMessage: 'Later.', groups: ['upload']);

        self::assertSame(['Refused.', 'Later.', ['upload']], [$constraint->infectedMessage, $constraint->notScannedMessage, $constraint->groups]);
        self::assertSame('Refused.', $this->validate($this->file(Temporary::eicar()), new CleanFile(infectedMessage: 'Refused.'))->get(0)->getMessage());
        self::assertSame('Later.', $this->validate($this->file('x'), new CleanFile(notScannedMessage: 'Later.'), new FakeScanner(error: 'down'))->get(0)->getMessage());
        self::assertSame('upload', new CleanFile()->context);
    }

    public function testNothingAndAFailedUploadAreLeftToOtherConstraints(): void
    {
        $failed = new UploadedFile($this->file(Temporary::eicar()), 'x.png', null, \UPLOAD_ERR_PARTIAL, true);

        self::assertCount(0, $this->validate(null, new CleanFile()));
        self::assertCount(0, $this->validate('', new CleanFile()));
        self::assertCount(0, $this->validate($failed, new CleanFile()));
        self::assertSame([], $this->events->events);
    }

    public function testAnotherValueIsAViolationOfItsType(): void
    {
        $violations = $this->validate(new stdClass(), new CleanFile());

        self::assertCount(1, $violations);
        self::assertSame('This value should be of type '.File::class.'|string.', $violations->get(0)->getMessage());
    }

    public function testAnotherConstraintIsRefused(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validate('x', new MisdirectedConstraint());
    }

    private function file(string $contents, string $name = 'upload.bin'): string
    {
        file_put_contents($this->directory.'/'.$name, $contents);

        return $this->directory.'/'.$name;
    }

    private function validate(mixed $value, Constraint $constraint, FakeScanner $scanner = new FakeScanner(), OnScanError $onError = OnScanError::Reject): ConstraintViolationListInterface
    {
        $validator = new CleanFileValidator(new UploadScanner($scanner, new ScanPolicy($onError), events: $this->events));

        return Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([CleanFileValidator::class => $validator]))
            ->getValidator()
            ->validate($value, $constraint);
    }
}
