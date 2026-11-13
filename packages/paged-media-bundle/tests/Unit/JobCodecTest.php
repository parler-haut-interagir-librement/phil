<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Tests\Unit;

use Phil\PagedMedia\Gotenberg\Download;
use Phil\PagedMedia\Gotenberg\Exception\InvalidRequest;
use Phil\PagedMedia\Gotenberg\Files;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\LibreOffice\LibreOfficeOptions;
use Phil\PagedMedia\Gotenberg\LibreOffice\PaperSize;
use Phil\PagedMedia\Gotenberg\Pipeline;
use Phil\PagedMedia\Gotenberg\Profile;
use Phil\PagedMedia\Gotenberg\Signing\Certificate;
use Phil\PagedMedia\Gotenberg\Stage\Operation;
use Phil\PagedMedia\Gotenberg\Stage\Optimize;
use Phil\PagedMedia\Gotenberg\Stage\Sign;
use Phil\PagedMedia\Gotenberg\Stage\StampImage;
use Phil\PagedMediaBundle\Application\Exception\JobUnavailable;
use Phil\PagedMediaBundle\Application\Job\ConversionJob;
use Phil\PagedMediaBundle\Application\Job\PipelineJob;
use Phil\PagedMediaBundle\Application\Job\RenderJob;
use Phil\PagedMediaBundle\Application\Job\StoredStage;
use Phil\PagedMediaBundle\Infrastructure\Storage\JobCodec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(JobCodec::class)]
#[CoversClass(StoredStage::class)]
#[CoversClass(RenderJob::class)]
#[CoversClass(PipelineJob::class)]
#[CoversClass(ConversionJob::class)]
#[CoversClass(JobUnavailable::class)]
final class JobCodecTest extends TestCase
{
    public function testARenderJobIsWrittenAsJsonAndReadBack(): void
    {
        $codec = new JobCodec();
        $json = $codec->encode(new RenderJob('ticket', ['seat' => 'A1', 'price' => 12.0, 'tags' => ['vip']], 'tickets/a1.pdf', null, true, false, 'a1.pdf'));

        self::assertSame('{"type":"render","template":"ticket","parameters":{"seat":"A1","price":12.0,"tags":["vip"]},"filename":"a1.pdf","pipeline":null,"path":"tickets/a1.pdf","sign":true,"timestamp":false}', $json);
        self::assertEquals(new RenderJob('ticket', ['seat' => 'A1', 'price' => 12.0, 'tags' => ['vip']], 'tickets/a1.pdf', null, true, false, 'a1.pdf'), $codec->decode($json));
        self::assertEquals(new RenderJob('ticket', [], 'a.pdf'), $codec->decode($codec->encode(new RenderJob('ticket', [], 'a.pdf'))));
    }

    public function testAPipelineJobKeepsItsStagesAndItsFiles(): void
    {
        $directory = sys_get_temp_dir().'/phil-paged-media-codec-'.bin2hex(random_bytes(6));
        mkdir($directory);
        file_put_contents($directory.'/report.pdf', '%PDF-r');

        try {
            $logo = InputFile::fromContents('logo.png', "\x89PNG\0");
            $pipeline = new Pipeline(new StampImage($logo, 10.0, 20.0), new Optimize(), new Sign(Certificate::pkcs12(InputFile::fromContents('signer.p12', 'P12'), 'secret'), reason: 'Approval'));
            $files = Files::of(InputFile::fromPath($directory.'/report.pdf', 'a-report.pdf'), InputFile::fromContents('annex.pdf', '%PDF-a'))
                ->withArchive(InputFile::fromContents('scans.zip', 'PK'))
                ->withDownload(new Download('https://files.example.com/b.pdf', ['Authorization' => 'Bearer t']))
                ->withDownload(new Download('https://files.example.com/c.pdf'));
            $codec = new JobCodec();

            $json = $codec->encode(new PipelineJob($files, $pipeline, 'out/report.pdf', false, true));
            $job = $codec->decode($json);

            self::assertStringContainsString('{"name":"a-report.pdf","path":"'.$directory.'/report.pdf"}', $json);
            self::assertStringContainsString('{"name":"logo.png","contents":"'.base64_encode("\x89PNG\0").'"}', $json);
            self::assertStringContainsString('"downloads":[{"url":"https://files.example.com/b.pdf","extraHttpHeaders":{"Authorization":"Bearer t"}},{"url":"https://files.example.com/c.pdf"}]', $json);
            self::assertInstanceOf(PipelineJob::class, $job);
            self::assertSame('out/report.pdf', $job->path);
            self::assertFalse($job->sign);
            self::assertTrue($job->timestamp);
            self::assertSame($pipeline->toJson(), $job->pipeline->toJson());
            self::assertContainsOnlyInstancesOf(StoredStage::class, $job->pipeline->stages);
            self::assertSame(Operation::StampImage, $job->pipeline->stages[0]->operation());
            self::assertCount(2, $job->pipeline->files());
            self::assertTrue($job->pipeline->files()[0]->sameAs($logo));
            self::assertTrue($job->pipeline->files()[1]->sameAs(InputFile::fromContents('signer.p12', 'P12')));
            self::assertCount(2, $job->files->files);
            self::assertTrue($job->files->files[0]->sameAs(InputFile::fromPath($directory.'/report.pdf', 'a-report.pdf')));
            self::assertTrue($job->files->files[1]->sameAs(InputFile::fromContents('annex.pdf', '%PDF-a')));
            self::assertCount(1, $job->files->archives);
            self::assertTrue($job->files->archives[0]->sameAs(InputFile::fromContents('scans.zip', 'PK')));
            self::assertEquals($files->downloads, $job->files->downloads);
            self::assertSame($json, $codec->encode($job));
        } finally {
            unlink($directory.'/report.pdf');
            rmdir($directory);
        }
    }

    public function testAConversionJobKeepsTheOptionsSet(): void
    {
        $options = new LibreOfficeOptions(password: 'open', paperSize: PaperSize::A4, paperWidth: 8.5, paperHeight: '11in', landscape: false, nativeQuality: 80, nativePdfa: Profile::PdfA2b);
        $codec = new JobCodec();

        $json = $codec->encode(new ConversionJob(Files::of(InputFile::fromContents('a.docx', 'DOCX')), 'a.pdf', $options, new Pipeline(new Optimize()), true));
        $job = $codec->decode($json);

        self::assertStringContainsString('"options":{"password":"open","paperSize":"A4","paperWidth":8.5,"paperHeight":"11in","landscape":false,"nativeQuality":80,"nativePdfa":"PDF/A-2b"}', $json);
        self::assertInstanceOf(ConversionJob::class, $job);
        self::assertEquals($options, $job->options);
        self::assertSame('[{"op":"optimize"}]', $job->pipeline?->toJson());
        self::assertTrue($job->sign);
        self::assertFalse($job->timestamp);
        self::assertTrue($job->documents->files[0]->sameAs(InputFile::fromContents('a.docx', 'DOCX')));

        $plain = $codec->decode($codec->encode(new ConversionJob(Files::of(InputFile::fromContents('a.docx', 'D')), 'a.pdf')));
        self::assertInstanceOf(ConversionJob::class, $plain);
        self::assertEquals(new LibreOfficeOptions(), $plain->options);
    }

    public function testAStoredStageGivesWhatItWasGiven(): void
    {
        $file = InputFile::fromContents('logo.png', 'PNG');
        $stage = new StoredStage(Operation::Rotate, ['angle' => 90], [$file]);

        self::assertSame(Operation::Rotate, $stage->operation());
        self::assertSame(['angle' => 90], $stage->options());
        self::assertSame([$file], $stage->files());
        self::assertSame([], new StoredStage(Operation::Optimize)->options());
        self::assertSame([], new StoredStage(Operation::Optimize)->files());
    }

    public function testAFileWhosePathIsGoneIsRefusedByTheLibrary(): void
    {
        $this->expectException(InvalidRequest::class);

        new JobCodec()->decode('{"type":"pipeline","files":{"files":[{"name":"a.pdf","path":"/nowhere/a.pdf"}],"archives":[],"downloads":[]},"pipeline":[{"op":"optimize","options":{},"files":[]}],"path":"a.pdf","sign":false,"timestamp":false}');
    }

    #[DataProvider('malformedJobs')]
    public function testAJobTheCodecDidNotWriteIsRefused(string $json, string $key): void
    {
        $this->expectException(JobUnavailable::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A kept job holds no valid "%s": the job store did not write it.', $key));

        new JobCodec()->decode($json);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformedJobs(): iterable
    {
        $tail = '"path":"a.pdf","sign":false,"timestamp":false';
        $files = '"files":{"files":[],"archives":[],"downloads":[]}';
        $stage = static fn (string $stage): string => '{"type":"pipeline",'.$files.',"pipeline":['.$stage.'],'.$tail.'}';
        $fileList = static fn (string $list): string => '{"type":"pipeline","files":'.$list.',"pipeline":[{"op":"optimize","options":{},"files":[]}],'.$tail.'}';
        $conversion = static fn (string $options): string => '{"type":"conversion",'.$files.',"options":'.$options.',"pipeline":null,'.$tail.'}';

        yield 'not an object' => ['"job"', 'job'];
        yield 'no path' => ['{"type":"render","sign":false,"timestamp":false}', 'path'];
        yield 'a sign that is not a boolean' => ['{"type":"render","path":"a.pdf","sign":1,"timestamp":false}', 'sign'];
        yield 'no timestamp' => ['{"type":"render","path":"a.pdf","sign":false}', 'timestamp'];
        yield 'an unknown type' => ['{"type":"print",'.$tail.'}', 'type'];
        yield 'no type' => ['{'.$tail.'}', 'type'];
        yield 'no template' => ['{"type":"render","parameters":{},"filename":"a.pdf",'.$tail.'}', 'template'];
        yield 'no parameters' => ['{"type":"render","template":"t","filename":"a.pdf",'.$tail.'}', 'parameters'];
        yield 'a parameter without a name' => ['{"type":"render","template":"t","parameters":["a"],"filename":"a.pdf",'.$tail.'}', 'parameters'];
        yield 'no filename' => ['{"type":"render","template":"t","parameters":{},'.$tail.'}', 'filename'];
        yield 'a pipeline that is not a list' => ['{"type":"render","template":"t","parameters":{},"filename":"a.pdf","pipeline":{"op":"optimize"},'.$tail.'}', 'pipeline'];
        yield 'a pipeline job without a pipeline' => ['{"type":"pipeline",'.$files.',"pipeline":null,'.$tail.'}', 'pipeline'];
        yield 'a stage that is not an object' => [$stage('"optimize"'), 'pipeline'];
        yield 'an unknown operation' => [$stage('{"op":"print","options":{},"files":[]}'), 'op'];
        yield 'no operation' => [$stage('{"options":{},"files":[]}'), 'op'];
        yield 'no options' => [$stage('{"op":"optimize","files":[]}'), 'options'];
        yield 'an option without a name' => [$stage('{"op":"optimize","options":[true],"files":[]}'), 'options'];
        yield 'an option that is null' => [$stage('{"op":"optimize","options":{"level":null},"files":[]}'), 'options'];
        yield 'no files of a stage' => [$stage('{"op":"optimize","options":{}}'), 'files'];
        yield 'no files' => ['{"type":"pipeline","pipeline":[{"op":"optimize","options":{},"files":[]}],'.$tail.'}', 'files'];
        yield 'no list of files' => [$fileList('{"archives":[],"downloads":[]}'), 'files'];
        yield 'files as an object' => [$fileList('{"files":{"a":{"name":"a.pdf","contents":""}},"archives":[],"downloads":[]}'), 'files'];
        yield 'no archives' => [$fileList('{"files":[],"downloads":[]}'), 'archives'];
        yield 'no downloads' => [$fileList('{"files":[],"archives":[]}'), 'downloads'];
        yield 'a download that is not an object' => [$fileList('{"files":[],"archives":[],"downloads":["https://files.example.com/a.pdf"]}'), 'downloads'];
        yield 'a download without a URL' => [$fileList('{"files":[],"archives":[],"downloads":[{}]}'), 'url'];
        yield 'headers that are not an object' => [$fileList('{"files":[],"archives":[],"downloads":[{"url":"https://files.example.com/a.pdf","extraHttpHeaders":"x"}]}'), 'extraHttpHeaders'];
        yield 'a header that is not a string' => [$fileList('{"files":[],"archives":[],"downloads":[{"url":"https://files.example.com/a.pdf","extraHttpHeaders":{"X-Count":1}}]}'), 'extraHttpHeaders'];
        yield 'a header without a name' => [$fileList('{"files":[],"archives":[],"downloads":[{"url":"https://files.example.com/a.pdf","extraHttpHeaders":["x"]}]}'), 'extraHttpHeaders'];
        yield 'a file that is not an object' => [$fileList('{"files":["a.pdf"],"archives":[],"downloads":[]}'), 'file'];
        yield 'a file without a name' => [$fileList('{"files":[{"contents":""}],"archives":[],"downloads":[]}'), 'name'];
        yield 'a path that is not a string' => [$fileList('{"files":[{"name":"a.pdf","path":null}],"archives":[],"downloads":[]}'), 'path'];
        yield 'a file without contents' => [$fileList('{"files":[{"name":"a.pdf"}],"archives":[],"downloads":[]}'), 'contents'];
        yield 'contents that are not base64' => [$fileList('{"files":[{"name":"a.pdf","contents":"%PDF"}],"archives":[],"downloads":[]}'), 'contents'];
        yield 'contents that are not a string' => [$fileList('{"files":[{"name":"a.pdf","contents":7}],"archives":[],"downloads":[]}'), 'contents'];
        yield 'no options of a conversion' => ['{"type":"conversion",'.$files.',"pipeline":null,'.$tail.'}', 'options'];
        yield 'an unknown option' => [$conversion('{"colour":true}'), 'options'];
        yield 'an option of the wrong type' => [$conversion('{"landscape":[true]}'), 'options'];
        yield 'an option that reflection would convert' => [$conversion('{"landscape":"yes"}'), 'options'];
        yield 'options out of order' => [$conversion('{"landscape":true,"paperSize":"A4"}'), 'options'];
        yield 'an unknown paper size' => [$conversion('{"paperSize":"Folio"}'), 'options'];
        yield 'a paper size that is not a value' => [$conversion('{"paperSize":true}'), 'options'];
    }

    public function testAJobThatIsNotJsonIsRefused(): void
    {
        $this->expectException(JobUnavailable::class);
        $this->expectExceptionMessageIsOrContains('A kept job is not JSON: Syntax error');

        new JobCodec()->decode('{"type":');
    }

    public function testAnArchiveOrAPaperSizeOfTheRightShapeIsRead(): void
    {
        $job = new JobCodec()->decode('{"type":"conversion","files":{"files":[],"archives":[{"name":"a.zip","contents":"UEs="}],"downloads":[]},"options":{"paperSize":"Letter"},"pipeline":null,"path":"a.pdf","sign":false,"timestamp":false}');

        self::assertInstanceOf(ConversionJob::class, $job);
        self::assertTrue($job->documents->archives[0]->sameAs(InputFile::fromContents('a.zip', 'PK')));
        self::assertSame(PaperSize::Letter, $job->options->paperSize);
        self::assertNull($job->pipeline);
    }
}
