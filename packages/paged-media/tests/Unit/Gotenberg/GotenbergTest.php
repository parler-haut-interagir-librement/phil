<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit\Gotenberg;

use Override;
use Phil\PagedMedia\Gotenberg\BasicAuth;
use Phil\PagedMedia\Gotenberg\Download;
use Phil\PagedMedia\Gotenberg\Exception\AccessDenied;
use Phil\PagedMedia\Gotenberg\Exception\ConversionFailed;
use Phil\PagedMedia\Gotenberg\Exception\GotenbergFailure;
use Phil\PagedMedia\Gotenberg\Exception\InvalidRequest;
use Phil\PagedMedia\Gotenberg\Exception\RequestRefused;
use Phil\PagedMedia\Gotenberg\Exception\RouteUnavailable;
use Phil\PagedMedia\Gotenberg\Exception\ServiceUnavailable;
use Phil\PagedMedia\Gotenberg\Exception\UnexpectedResponse;
use Phil\PagedMedia\Gotenberg\Files;
use Phil\PagedMedia\Gotenberg\Gotenberg;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\Internal\Multipart;
use Phil\PagedMedia\Gotenberg\LibreOffice\LibreOfficeOptions;
use Phil\PagedMedia\Gotenberg\LibreOffice\PaperSize;
use Phil\PagedMedia\Gotenberg\Pages;
use Phil\PagedMedia\Gotenberg\Pipeline;
use Phil\PagedMedia\Gotenberg\Profile;
use Phil\PagedMedia\Gotenberg\Read\Attachment;
use Phil\PagedMedia\Gotenberg\Read\DocumentInformation;
use Phil\PagedMedia\Gotenberg\Read\FormField;
use Phil\PagedMedia\Gotenberg\Read\FormFieldType;
use Phil\PagedMedia\Gotenberg\Read\JsonValue;
use Phil\PagedMedia\Gotenberg\Read\PdfInfo;
use Phil\PagedMedia\Gotenberg\Read\Severity;
use Phil\PagedMedia\Gotenberg\Read\ValidationReport;
use Phil\PagedMedia\Gotenberg\Read\Violation;
use Phil\PagedMedia\Gotenberg\RequestOptions;
use Phil\PagedMedia\Gotenberg\Result;
use Phil\PagedMedia\Gotenberg\Stage\Attach;
use Phil\PagedMedia\Gotenberg\Stage\Bookmark;
use Phil\PagedMedia\Gotenberg\Stage\Merge;
use Phil\PagedMedia\Gotenberg\Stage\Optimize;
use Phil\PagedMedia\Gotenberg\Stage\Remediate;
use Phil\PagedMedia\Gotenberg\Stage\SetForm;
use Phil\PagedMedia\Gotenberg\Stage\Split;
use Phil\PagedMedia\Gotenberg\Stage\StampImage;
use Phil\PagedMedia\Gotenberg\Stage\StampText;
use Phil\PagedMedia\Tests\Unit\Fixture\GotenbergServer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(Gotenberg::class)]
#[CoversClass(Multipart::class)]
#[CoversClass(Result::class)]
#[CoversClass(JsonValue::class)]
#[CoversClass(PdfInfo::class)]
#[CoversClass(DocumentInformation::class)]
#[CoversClass(Attachment::class)]
#[CoversClass(FormField::class)]
#[CoversClass(ValidationReport::class)]
#[CoversClass(Violation::class)]
#[CoversClass(GotenbergFailure::class)]
#[CoversClass(RequestRefused::class)]
#[CoversClass(AccessDenied::class)]
#[CoversClass(RouteUnavailable::class)]
#[CoversClass(ServiceUnavailable::class)]
#[CoversClass(ConversionFailed::class)]
#[CoversClass(UnexpectedResponse::class)]
final class GotenbergTest extends TestCase
{
    private GotenbergServer $server;

    private Gotenberg $gotenberg;

    #[Override]
    protected function setUp(): void
    {
        $this->server = new GotenbergServer();
        $this->gotenberg = new Gotenberg($this->server->client, 'http://gotenberg:3000/');
    }

    public function testAListOfStagesIsPostedWithItsPdfsAndTheFilesItNames(): void
    {
        $this->server->answer('%PDF-merged', 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="annex.pdf"',
            'Gotenberg-Trace' => 'abc',
            'Gotenberg-Version' => '9.1.0',
        ]);
        $logo = InputFile::fromContents('logo.png', 'PNG');

        $result = $this->gotenberg->pipe(
            Files::of(InputFile::fromContents('report.pdf', '%PDF-r'), InputFile::fromContents('annex.pdf', '%PDF-a')),
            new Pipeline(new Merge(), new StampImage($logo), new StampImage($logo, 10), new Optimize()),
            new RequestOptions('merged'),
        );

        $request = $this->server->last();
        self::assertSame('POST', $request['method']);
        self::assertSame('http://gotenberg:3000/forms/pdf/pipe', $request['url']);
        self::assertSame(['[{"op":"merge"},{"op":"stamp-image","image":"logo.png","x":0.0,"y":0.0},{"op":"stamp-image","image":"logo.png","x":10.0,"y":0.0},{"op":"optimize"}]'], $request['fields']['stages']);
        self::assertSame([['report.pdf', '%PDF-r'], ['annex.pdf', '%PDF-a'], ['logo.png', 'PNG']], $request['files']['files']);
        self::assertSame(['merged'], $request['headers']['gotenberg-output-filename']);
        self::assertArrayNotHasKey('authorization', $request['headers']);
        self::assertSame('%PDF-merged', $result->contents());
        self::assertSame('annex.pdf', $result->filename);
        self::assertSame('application/pdf', $result->contentType);
        self::assertSame('abc', $result->trace);
        self::assertSame('9.1.0', $result->version);
        self::assertFalse($result->deferred);
        self::assertNull($result->residual);
    }

    public function testTheBodyIsWrittenAsMultipartFormData(): void
    {
        $this->server->answer();

        $this->gotenberg->run(Files::of(InputFile::fromContents('a.pdf', 'A')), Split::every(2));

        $request = $this->server->last();
        self::assertMatchesRegularExpression('/^multipart\/form-data; boundary=phil-[0-9a-f]{32}$/', $request['headers']['content-type'][0]);
        $boundary = substr($request['headers']['content-type'][0], 30);
        $multipart = new Multipart($boundary);
        $multipart->field('every', '2');
        $multipart->file('files', InputFile::fromContents('a.pdf', 'A'));
        self::assertSame(
            "--{$boundary}\r\nContent-Disposition: form-data; name=\"every\"\r\n\r\n2\r\n--{$boundary}\r\nContent-Disposition: form-data; name=\"files\"; filename=\"a.pdf\"\r\nContent-Type: application/octet-stream\r\n\r\nA\r\n--{$boundary}--\r\n",
            implode('', iterator_to_array($multipart->body(), false)),
        );
        self::assertSame('multipart/form-data; boundary=b', new Multipart('b')->contentType());
        self::assertNotSame(new Multipart()->boundary, new Multipart()->boundary);
    }

    public function testOneStageIsPostedToItsRouteWithItsOptionsAsFormFields(): void
    {
        $this->server->answer();

        $this->gotenberg->run(
            Files::of(InputFile::fromContents('form.pdf', '%PDF')),
            new SetForm(['name' => 'Zoé', 'path' => 'a/b', 'colors' => ['Red', 'Green']]),
        );
        $this->server->answer();
        $this->gotenberg->run(Files::of(InputFile::fromContents('form.pdf', '%PDF')), new StampText('A/B', 72, 0.5, rotation: 0));
        $this->server->answer();
        $this->gotenberg->run(Files::of(InputFile::fromContents('form.pdf', '%PDF')), new Remediate(Profile::PdfUa1, aggressive: true));

        self::assertSame('http://gotenberg:3000/forms/pdf/set-form', $this->server->requests[0]['url']);
        self::assertSame(['fields' => ['{"name":"Zoé","path":"a/b","colors":["Red","Green"]}']], $this->server->requests[0]['fields']);
        self::assertSame(['text' => ['A/B'], 'x' => ['72'], 'y' => ['0.5'], 'rotation' => ['0']], $this->server->requests[1]['fields']);
        self::assertSame(['profile' => ['PDF/UA-1'], 'aggressive' => ['true']], $this->server->requests[2]['fields']);
    }

    public function testDownloadsAndArchivesAreSentBesideTheFiles(): void
    {
        $this->server->answer();

        $this->gotenberg->run(
            Files::of()->withDownload(new Download('https://files.example.com/a.pdf', ['Authorization' => 'Bearer t']))->withDownload(new Download('https://files.example.com/b.pdf'))->withArchive(InputFile::fromContents('pdfs.zip', 'PK')),
            new Merge(),
        );

        $request = $this->server->last();
        self::assertSame(['[{"url":"https://files.example.com/a.pdf","extraHttpHeaders":{"Authorization":"Bearer t"}},{"url":"https://files.example.com/b.pdf"}]'], $request['fields']['downloadFrom']);
        self::assertSame([['pdfs.zip', 'PK']], $request['files']['archives']);
        self::assertArrayNotHasKey('files', $request['files']);
    }

    public function testTheSameFileNamedTwiceIsSentOnce(): void
    {
        $this->server->answer();
        $data = InputFile::fromContents('data.csv', 'a,b');

        $this->gotenberg->pipe(Files::of(InputFile::fromContents('a.pdf', 'A'), InputFile::fromContents('data.csv', 'a,b')), new Pipeline(new Attach($data), new StampImage(InputFile::fromContents('logo.png', 'PNG'))));

        self::assertSame([['a.pdf', 'A'], ['data.csv', 'a,b'], ['logo.png', 'PNG']], $this->server->last()['files']['files']);
    }

    public function testTwoDifferentFilesOfTheSameNameAreRefused(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessageIsOrContains('Two different files are named "data.csv"; Gotenberg tells files apart by their names.');

        $this->gotenberg->pipe(Files::of(InputFile::fromContents('data.csv', 'one')), new Pipeline(new Attach(InputFile::fromContents('data.csv', 'two'))));
    }

    public function testARequestWithoutFilesIsRefused(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessageIsOrContains('A request works on one file at least: uploaded, in an archive, or downloaded.');

        $this->gotenberg->pipe(Files::of(), new Pipeline(new Merge()));
    }

    public function testCredentialsAndTheTimeoutGoWithEveryRequest(): void
    {
        $gotenberg = new Gotenberg($this->server->client, 'https://pdf.example.com/gotenberg', new BasicAuth('app', 'secret'), 90.5);
        $this->server->answer('9.1.0', 200, ['Content-Type' => 'text/plain']);

        self::assertSame('9.1.0', $gotenberg->version());

        $request = $this->server->last();
        self::assertSame('GET', $request['method']);
        self::assertSame('https://pdf.example.com/gotenberg/version', $request['url']);
        self::assertSame(['Basic '.base64_encode('app:secret')], $request['headers']['authorization']);
        self::assertSame(90.5, $request['options']['timeout']);
        self::assertSame(90.5, $request['options']['max_duration']);
        self::assertArrayNotHasKey('content-type', $request['headers']);
    }

    public function testWithoutATimeoutTheClientKeepsItsOwn(): void
    {
        $this->server->answer("9.1.0\n");

        self::assertSame('9.1.0', $this->gotenberg->version());
        self::assertNotSame(90.5, $this->server->last()['options']['timeout'] ?? null);
        self::assertNotSame(90.5, $this->server->last()['options']['max_duration'] ?? null);
    }

    public function testGotenbergsUrlIsAnAbsoluteHttpUrl(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessageIsOrContains('Gotenberg\'s URL is an absolute URL with an http or https scheme, a host and no credentials; "gotenberg:3000" is not.');

        new Gotenberg($this->server->client, 'gotenberg:3000');
    }

    public function testAConversionSendsItsOptionsAndItsStages(): void
    {
        $this->server->answer('%PDF', 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="report.docx.pdf"']);
        $logo = InputFile::fromContents('logo.png', 'PNG');

        $result = $this->gotenberg->convert(
            Files::of(InputFile::fromContents('report.docx', 'DOCX')),
            new LibreOfficeOptions(paperSize: PaperSize::A4, landscape: false, nativeQuality: 80),
            new Pipeline(new StampImage($logo)),
            new RequestOptions(trace: 't-1'),
        );

        $request = $this->server->last();
        self::assertSame('http://gotenberg:3000/forms/libreoffice/convert', $request['url']);
        self::assertSame(['paperSize' => ['A4'], 'landscape' => ['false'], 'nativeQuality' => ['80'], 'stages' => ['[{"op":"stamp-image","image":"logo.png","x":0.0,"y":0.0}]']], $request['fields']);
        self::assertSame([['report.docx', 'DOCX'], ['logo.png', 'PNG']], $request['files']['files']);
        self::assertSame(['t-1'], $request['headers']['gotenberg-trace']);
        self::assertSame('report.docx.pdf', $result->filename);
    }

    public function testAConversionWithoutStagesSendsNone(): void
    {
        $this->server->answer();

        $this->gotenberg->convert(Files::of(InputFile::fromContents('a.odt', 'ODT')));

        self::assertSame([], $this->server->last()['fields']);
        self::assertSame([['a.odt', 'ODT']], $this->server->last()['files']['files']);
    }

    public function testInfoIsReadPerFile(): void
    {
        $this->server->answerJson([
            'annex.pdf' => ['pages' => 2, 'version' => '1.7', 'encrypted' => true, 'tagged' => false, 'linearized' => true, 'formFields' => false],
            'report.pdf' => ['pages' => 3, 'version' => '2.0', 'encrypted' => false, 'tagged' => true, 'linearized' => false, 'formFields' => true],
        ]);

        $info = $this->gotenberg->info(Files::of(InputFile::fromContents('report.pdf', 'R'), InputFile::fromContents('annex.pdf', 'A')), 'secret');

        self::assertEquals(['annex.pdf' => new PdfInfo(2, '1.7', true, false, true, false), 'report.pdf' => new PdfInfo(3, '2.0', false, true, false, true)], $info);
        self::assertSame('http://gotenberg:3000/forms/pdf/info', $this->server->last()['url']);
        self::assertSame(['password' => ['secret']], $this->server->last()['fields']);
        self::assertSame([], $this->server->last()['headers']['gotenberg-trace'] ?? []);
    }

    public function testMetadataIsReadPerFile(): void
    {
        $this->server->answerJson(['report.pdf' => ['title' => 'Quarterly', 'author' => 'Finance', 'subject' => 'S', 'keywords' => 'K', 'creator' => 'C', 'producer' => 'P', 'creationDate' => 'D:20260101120000Z', 'modDate' => '2026-01-02T00:00:00Z'], 'empty.pdf' => []]);

        $metadata = $this->gotenberg->metadata(Files::of(InputFile::fromContents('report.pdf', 'R')));

        self::assertEquals(['report.pdf' => new DocumentInformation('Quarterly', 'Finance', 'S', 'K', 'C', 'P', 'D:20260101120000Z', '2026-01-02T00:00:00Z'), 'empty.pdf' => new DocumentInformation()], $metadata);
        self::assertSame('http://gotenberg:3000/forms/pdf/metadata', $this->server->last()['url']);
        self::assertSame([], $this->server->last()['fields']);
    }

    public function testBookmarksAreReadNested(): void
    {
        $this->server->answerJson(['report.pdf' => [['title' => 'Summary', 'pageNumber' => 1, 'children' => [['title' => 'Figures', 'pageNumber' => 2]]], ['title' => 'Appendix', 'pageNumber' => 3]], 'none.pdf' => []]);

        $bookmarks = $this->gotenberg->bookmarks(Files::of(InputFile::fromContents('report.pdf', 'R')), 'p');

        self::assertEquals(['report.pdf' => [new Bookmark('Summary', 1, [new Bookmark('Figures', 2)]), new Bookmark('Appendix', 3)], 'none.pdf' => []], $bookmarks);
        self::assertSame('http://gotenberg:3000/forms/pdf/bookmarks', $this->server->last()['url']);
        self::assertSame(['password' => ['p']], $this->server->last()['fields']);
    }

    public function testAttachmentsAreListed(): void
    {
        $this->server->answerJson(['invoice.pdf' => [['name' => 'factur-x.xml', 'mimeType' => 'text/xml', 'relationship' => 'Alternative'], ['name' => 'raw.bin']]]);

        $attachments = $this->gotenberg->attachments(Files::of(InputFile::fromContents('invoice.pdf', 'I')));

        self::assertEquals(['invoice.pdf' => [new Attachment('factur-x.xml', 'text/xml', 'Alternative'), new Attachment('raw.bin')]], $attachments);
        self::assertSame('http://gotenberg:3000/forms/pdf/attachments', $this->server->last()['url']);
    }

    public function testFormFieldsAreListedOfThePagesGiven(): void
    {
        $this->server->answerJson(['fields.pdf' => [
            ['name' => 'agree', 'type' => 'checkbox', 'value' => 'Off', 'pages' => [1], 'states' => ['Yes']],
            ['name' => 'colors', 'type' => 'list', 'value' => ['Red', 'Green'], 'pages' => [1, 2], 'options' => ['Red', 'Green', 'Blue'], 'readOnly' => true, 'required' => true, 'multiSelect' => true, 'editable' => true, 'maxLength' => 3],
        ]]);

        $form = $this->gotenberg->form(Files::of(InputFile::fromContents('fields.pdf', 'F')), 'p', '1-2');

        self::assertEquals(['fields.pdf' => [
            new FormField('agree', FormFieldType::Checkbox, 'Off', [1], ['Yes']),
            new FormField('colors', FormFieldType::List, ['Red', 'Green'], [1, 2], [], ['Red', 'Green', 'Blue'], true, true, true, true, 3),
        ]], $form);
        self::assertSame('http://gotenberg:3000/forms/pdf/form', $this->server->last()['url']);
        self::assertSame(['pages' => ['1-2'], 'password' => ['p']], $this->server->last()['fields']);
    }

    public function testFormFieldsAreListedOfEveryPageByDefault(): void
    {
        $this->server->answerJson(['fields.pdf' => []]);
        $this->server->answerJson(['fields.pdf' => []]);

        self::assertSame(['fields.pdf' => []], $this->gotenberg->form(Files::of(InputFile::fromContents('fields.pdf', 'F'))));
        self::assertSame([], $this->server->last()['fields']);
        $this->gotenberg->form(Files::of(InputFile::fromContents('fields.pdf', 'F')), null, Pages::last());
        self::assertSame(['pages' => ['last']], $this->server->last()['fields']);
    }

    public function testAValidationReportsTheViolations(): void
    {
        $this->server->answerJson(['invoice.pdf' => ['profile' => 'PDF/A-3b', 'compliant' => false, 'violations' => [
            ['specification' => 'Factur-X', 'clause' => 'BR-1', 'testNumber' => 2, 'description' => 'Soft rule', 'severity' => 'warning'],
            ['specification' => 'ISO_19005_3', 'clause' => '6.1.3', 'testNumber' => 1, 'description' => 'Trailer ID', 'context' => 'missing ID', 'severity' => 'fatal'],
        ]]]);

        $reports = $this->gotenberg->validate(Files::of(InputFile::fromContents('invoice.pdf', 'I')), Profile::PdfA3b, 'p');

        $fatal = new Violation('ISO_19005_3', '6.1.3', 1, 'Trailer ID', 'missing ID', Severity::Fatal);
        self::assertEquals(['invoice.pdf' => new ValidationReport([Profile::PdfA3b], false, [new Violation('Factur-X', 'BR-1', 2, 'Soft rule', '', Severity::Warning), $fatal])], $reports);
        self::assertEquals([0 => $fatal], $reports['invoice.pdf']->fatal());
        self::assertSame([0], array_keys($reports['invoice.pdf']->fatal()));
        self::assertSame('http://gotenberg:3000/forms/pdf/validate', $this->server->last()['url']);
        self::assertSame(['profile' => ['PDF/A-3b'], 'password' => ['p']], $this->server->last()['fields']);
    }

    public function testAPairOfProfilesIsValidatedTogether(): void
    {
        $this->server->answerJson(['a.pdf' => ['profile' => ['PDF/A-2b', 'PDF/UA-1'], 'compliant' => true, 'violations' => []]]);

        $reports = $this->gotenberg->validate(Files::of(InputFile::fromContents('a.pdf', 'A')), [Profile::PdfA2b, Profile::PdfUa1]);

        self::assertEquals(['a.pdf' => new ValidationReport([Profile::PdfA2b, Profile::PdfUa1], true, [])], $reports);
        self::assertSame(['profile' => ['["PDF/A-2b","PDF/UA-1"]']], $this->server->last()['fields']);
    }

    /**
     * @param array<array-key, mixed>|string $json
     */
    #[DataProvider('unexpectedAnswers')]
    public function testAnAnswerOfAnotherShapeIsAnUnexpectedResponse(string $route, array|string $json, string $message): void
    {
        $this->server->answer(\is_string($json) ? $json : json_encode($json, \JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json']);
        $files = Files::of(InputFile::fromContents('a.pdf', 'A'));

        try {
            match ($route) {
                'info' => $this->gotenberg->info($files),
                'bookmarks' => $this->gotenberg->bookmarks($files),
                'form' => $this->gotenberg->form($files),
                default => $this->gotenberg->validate($files, Profile::PdfA2b),
            };
            self::fail('An unexpected answer was taken.');
        } catch (UnexpectedResponse $unexpectedResponse) {
            self::assertStringContainsString($message, $unexpectedResponse->getMessage());
            self::assertSame(200, $unexpectedResponse->status);
            self::assertFalse($unexpectedResponse->isRetryable());
        }
    }

    /**
     * @return iterable<string, array{string, array<array-key, mixed>|string, string}>
     */
    public static function unexpectedAnswers(): iterable
    {
        $info = ['pages' => 1, 'version' => '1.7', 'encrypted' => false, 'tagged' => false, 'linearized' => false, 'formFields' => false];

        yield 'not json' => ['info', '<html>', 'Gotenberg answered with something other than JSON: Syntax error'];
        yield 'a scalar' => ['info', '3', 'Gotenberg answered JSON whose $ is not an object or a list.'];
        yield 'a string page count' => ['info', ['a.pdf' => ['pages' => '1'] + $info], 'Gotenberg answered JSON whose $.a.pdf.pages is not an integer.'];
        yield 'a missing version' => ['info', ['a.pdf' => array_diff_key($info, ['version' => 0])], '$.a.pdf.version is not a string.'];
        yield 'a numeric flag' => ['info', ['a.pdf' => ['tagged' => 1] + $info], '$.a.pdf.tagged is not a boolean.'];
        yield 'bookmarks as an object' => ['bookmarks', ['a.pdf' => ['x' => ['title' => 'A', 'pageNumber' => 1]]], '$.a.pdf is not a list.'];
        yield 'children as an object' => ['bookmarks', ['a.pdf' => [['title' => 'A', 'pageNumber' => 1, 'children' => ['title' => 'B']]]], '$.a.pdf[0].children is not a list.'];
        yield 'a page as text' => ['form', ['a.pdf' => [['name' => 'a', 'type' => 'text', 'value' => 'x', 'pages' => ['1']]]], '$.a.pdf[0].pages[0] is not an integer.'];
        yield 'a state as a number' => ['form', ['a.pdf' => [['name' => 'a', 'type' => 'radio', 'value' => 'x', 'pages' => [1], 'states' => [1]]]], '$.a.pdf[0].states[0] is not a string.'];
        yield 'an unknown field type' => ['form', ['a.pdf' => [['name' => 'a', 'type' => 'slider', 'value' => 'x', 'pages' => [1]]]], 'Gotenberg listed a form field of an unknown type "slider".'];
        yield 'an unknown profile' => ['validate', ['a.pdf' => ['profile' => 'PDF/X-4', 'compliant' => true, 'violations' => []]], 'Gotenberg validated against an unknown profile "PDF/X-4".'];
        yield 'no profile' => ['validate', ['a.pdf' => ['profile' => [], 'compliant' => true, 'violations' => []]], 'Gotenberg validated against no profile.'];
        yield 'an unknown severity' => ['validate', ['a.pdf' => ['profile' => 'PDF/A-2b', 'compliant' => false, 'violations' => [['specification' => 'S', 'clause' => 'C', 'testNumber' => 1, 'description' => 'D', 'severity' => 'minor']]]], 'Gotenberg reported a violation of an unknown severity "minor".'];
    }

    /**
     * @param class-string<GotenbergFailure> $exception
     */
    #[DataProvider('failures')]
    public function testAnAnswerThatIsNotASuccessBecomesAnException(int $status, string $body, string $exception, string $message, bool $retryable): void
    {
        $this->server->answer($body, $status, ['Content-Type' => 'text/plain', 'Gotenberg-Trace' => 'trace-9']);

        try {
            $this->gotenberg->run(Files::of(InputFile::fromContents('a.pdf', 'A')), new Merge());
            self::fail('A failure was taken for a success.');
        } catch (GotenbergFailure $gotenbergFailure) {
            self::assertInstanceOf($exception, $gotenbergFailure);
            self::assertSame($message, $gotenbergFailure->getMessage());
            self::assertSame($status, $gotenbergFailure->status);
            self::assertSame($status, $gotenbergFailure->getCode());
            self::assertSame('trace-9', $gotenbergFailure->trace);
            self::assertSame($retryable, $gotenbergFailure->isRetryable());
        }
    }

    /**
     * @return iterable<string, array{int, string, class-string<GotenbergFailure>, string, bool}>
     */
    public static function failures(): iterable
    {
        yield '400' => [400, "Stage 'select' failed on 'a.pdf': invalid page range\n", RequestRefused::class, "Gotenberg answered 400: Stage 'select' failed on 'a.pdf': invalid page range", false];
        yield '401' => [401, 'Unauthorized', AccessDenied::class, 'Gotenberg answered 401: Unauthorized', false];
        yield '403' => [403, 'Forbidden', AccessDenied::class, 'Gotenberg answered 403: Forbidden', false];
        yield '404' => [404, "The route '/forms/pdf/merge' does not exist", RouteUnavailable::class, "Gotenberg answered 404: The route '/forms/pdf/merge' does not exist", false];
        yield '405' => [405, 'Method not allowed', RequestRefused::class, 'Gotenberg answered 405: Method not allowed', false];
        yield '409' => [409, 'Conflict', ConversionFailed::class, 'Gotenberg answered 409: Conflict', false];
        yield '413' => [413, '', RequestRefused::class, 'Gotenberg answered 413.', false];
        yield '429' => [429, 'Too many requests', ServiceUnavailable::class, 'Gotenberg answered 429: Too many requests', true];
        yield '500' => [500, ' ', ConversionFailed::class, 'Gotenberg answered 500.', false];
        yield '503' => [503, 'Service unavailable', ServiceUnavailable::class, 'Gotenberg answered 503: Service unavailable', true];
        yield '502' => [502, 'Bad gateway', ConversionFailed::class, 'Gotenberg answered 502: Bad gateway', false];
        yield '300' => [300, 'Multiple choices', RequestRefused::class, 'Gotenberg answered 300: Multiple choices', false];
        yield '499' => [499, 'Closed', RequestRefused::class, 'Gotenberg answered 499: Closed', false];
    }

    public function testAFailureWithoutTraceKeepsNone(): void
    {
        $this->server->answer('Bad', 400, []);

        try {
            $this->gotenberg->run(Files::of(InputFile::fromContents('a.pdf', 'A')), new Merge());
            self::fail('A failure was taken for a success.');
        } catch (RequestRefused $requestRefused) {
            self::assertNull($requestRefused->trace);
        }
    }

    public function testAGotenbergOutOfReachIsWorthRetrying(): void
    {
        $this->server->answerWith(new MockResponse('', ['error' => 'Connection refused']));

        try {
            $this->gotenberg->run(Files::of(InputFile::fromContents('a.pdf', 'A')), new Merge());
            self::fail('No answer was taken for one.');
        } catch (ServiceUnavailable $serviceUnavailable) {
            self::assertStringStartsWith('Gotenberg could not be reached at http://gotenberg:3000: Connection refused', $serviceUnavailable->getMessage());
            self::assertSame(0, $serviceUnavailable->status);
            self::assertTrue($serviceUnavailable->isRetryable());
            self::assertNotNull($serviceUnavailable->getPrevious());
        }
    }

    public function testAnAnswerCutShortWhileReadingIsWorthRetrying(): void
    {
        $this->server->answerWith(new MockResponse([new RuntimeException('Reset by peer')], ['response_headers' => ['Content-Type' => 'application/json']]));

        $this->expectException(ServiceUnavailable::class);
        $this->expectExceptionMessageIsOrContains('Gotenberg could not be reached at http://gotenberg:3000');

        $this->gotenberg->info(Files::of(InputFile::fromContents('a.pdf', 'A')));
    }

    public function testAVersionCutShortIsWorthRetrying(): void
    {
        $this->server->answerWith(new MockResponse([new RuntimeException('Reset by peer')]));

        $this->expectException(ServiceUnavailable::class);

        $this->gotenberg->version();
    }
}
