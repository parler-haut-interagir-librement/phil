<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Tests\Unit;

use ArrayIterator;
use Phil\PagedMedia\Gotenberg\Files;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\LibreOffice\LibreOfficeOptions;
use Phil\PagedMedia\Gotenberg\Pipeline;
use Phil\PagedMedia\Gotenberg\Signing\Certificate;
use Phil\PagedMedia\Gotenberg\Signing\CertificateSource;
use Phil\PagedMedia\Gotenberg\Signing\TimestampAuthorityUrl;
use Phil\PagedMedia\Gotenberg\Stage\Optimize;
use Phil\PagedMedia\Gotenberg\Stage\Rotate;
use Phil\PagedMedia\Gotenberg\Stage\Sign;
use Phil\PagedMedia\Gotenberg\Stage\Stage;
use Phil\PagedMedia\Gotenberg\Stage\Timestamp;
use Phil\PagedMediaBundle\Application\DocumentTemplates;
use Phil\PagedMediaBundle\Application\Event\PrintStarting;
use Phil\PagedMediaBundle\Application\Event\UnnamedPrintStarting;
use Phil\PagedMediaBundle\Application\Exception\NotConfigured;
use Phil\PagedMediaBundle\Application\Exception\UnknownTemplate;
use Phil\PagedMediaBundle\Application\Output;
use Phil\PagedMediaBundle\Application\PagedMedia;
use Phil\PagedMediaBundle\Application\PrintableHtml;
use Phil\PagedMediaBundle\Application\PrintSubject;
use Phil\PagedMediaBundle\Application\SignatureDefaults;
use Phil\PagedMediaBundle\Tests\Unit\Fixture\FakeGotenberg;
use Phil\PagedMediaBundle\Tests\Unit\Fixture\RecordingDispatcher;
use Phil\PagedMediaBundle\Tests\Unit\Fixture\RecordingEngine;
use Phil\PagedMediaBundle\Tests\Unit\Fixture\Ticket;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

#[CoversClass(PagedMedia::class)]
#[CoversClass(Output::class)]
#[CoversClass(PrintStarting::class)]
#[CoversClass(UnnamedPrintStarting::class)]
#[CoversClass(PrintSubject::class)]
#[CoversClass(DocumentTemplates::class)]
#[CoversClass(SignatureDefaults::class)]
#[CoversClass(NotConfigured::class)]
#[CoversClass(UnknownTemplate::class)]
final class PagedMediaTest extends TestCase
{
    public function testADocumentIsRenderedByTheNativeWriterAlone(): void
    {
        $document = new Ticket()->build(['seat' => 'A12']);

        $output = new PagedMedia()->render($document, 'ticket.pdf');

        self::assertEquals(new Output($document->render(), 'ticket.pdf'), $output);
        self::assertSame('document.pdf', new PagedMedia()->render($document)->filename);
    }

    public function testAPrintThatNamesItsWorkIsAnnouncedOnceBeforeItStarts(): void
    {
        $events = new RecordingDispatcher();
        $subject = new PrintSubject('book', '42');
        $gotenberg = new FakeGotenberg()->answer('%PDF')->answer('%PDF')->answer('%PDF');
        $document = new Ticket()->build(['seat' => 'A12']);
        $media = new PagedMedia(new DocumentTemplates(new ArrayIterator(['ticket' => new Ticket()])), $gotenberg->client, events: $events);
        $announced = [new PrintStarting($subject)];

        $media->render($document, 'ticket.pdf', subject: $subject);
        self::assertEquals($announced, $events->events);

        $events->events = [];
        $media->render($document, 'ticket.pdf', new Pipeline(new Rotate(90)), $subject);
        self::assertEquals($announced, $events->events);

        $events->events = [];
        $media->renderTemplate('ticket', ['seat' => 'A12'], 'ticket.pdf', subject: $subject);
        self::assertEquals($announced, $events->events);

        $events->events = [];
        $media->process(Files::of(InputFile::fromContents('a.pdf', '%PDF')), new Pipeline(new Optimize()), $subject);
        self::assertEquals($announced, $events->events);

        $events->events = [];
        $media->convert(Files::of(InputFile::fromContents('a.docx', 'x')), subject: $subject);
        self::assertEquals($announced, $events->events);
    }

    public function testAPrintThatNamesNoWorkIsAnnouncedAsUnnamed(): void
    {
        $events = new RecordingDispatcher();
        $media = new PagedMedia(events: $events);

        $media->render(new Ticket()->build(['seat' => 'A12']));

        self::assertEquals([new UnnamedPrintStarting()], $events->events);
        self::assertSame('document.pdf', new PagedMedia()->render(new Ticket()->build(['seat' => 'A12']), subject: new PrintSubject('book', '42'))->filename);
    }

    public function testAPrintAlreadyAnnouncedIsMadeAsBeforeAndAnnouncesNothing(): void
    {
        $events = new RecordingDispatcher();
        $gotenberg = new FakeGotenberg()->answer('%PDF');
        $signature = new SignatureDefaults(reason: 'Approved');
        $tsa = new TimestampAuthorityUrl('https://tsa.example.com');
        $media = new PagedMedia(new DocumentTemplates(new ArrayIterator(['ticket' => new Ticket()])), $gotenberg->client, $this->certificates(), $tsa, $signature, new RecordingEngine('%PDF-engine'), $events)->alreadyAnnounced();

        self::assertSame('%PDF-engine', $media->printHtml(new PrintableHtml('<p>x</p>'))->contents);
        self::assertSame(new Ticket()->build(['seat' => 'A1'])->render(), $media->renderTemplate('ticket', ['seat' => 'A1'])->contents);
        self::assertSame('%PDF', $media->process(Files::of(InputFile::fromContents('a.pdf', '%PDF')), new Pipeline(new Optimize()), new PrintSubject('book', '42'))->contents);
        self::assertSame('Approved', $media->signature()->reason);
        self::assertSame($tsa, $media->timestamp()->authority);
        self::assertSame([], $events->events);
    }

    public function testAListenerThatThrowsStopsThePrintBeforeAnythingIsSent(): void
    {
        $gotenberg = new FakeGotenberg()->answer('%PDF');
        $refused = new RuntimeException('Refused.');
        $events = new readonly class($refused) implements EventDispatcherInterface {
            public function __construct(
                private RuntimeException $refused,
            ) {
            }

            public function dispatch(object $event): object
            {
                throw $this->refused;
            }
        };

        try {
            new PagedMedia(gotenberg: $gotenberg->client, events: $events)->process(Files::of(InputFile::fromContents('a.pdf', '%PDF')), new Pipeline(new Optimize()), new PrintSubject('book', '42'));
            self::fail('The print was not stopped.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame($refused, $runtimeException);
        }

        self::assertSame([], $gotenberg->requests);
    }

    public function testHtmlGoesToThePrintEngine(): void
    {
        $engine = new RecordingEngine('%PDF-engine');
        $events = new RecordingDispatcher();
        $page = new PrintableHtml('<p>x</p>');
        $subject = new PrintSubject('book', '42');

        $output = new PagedMedia(engine: $engine, events: $events)->printHtml($page, 'book.pdf', subject: $subject);

        self::assertEquals(new Output('%PDF-engine', 'book.pdf'), $output);
        self::assertSame([$page], $engine->pages);
        self::assertEquals([new PrintStarting($subject)], $events->events);
        self::assertSame('document.pdf', new PagedMedia(engine: $engine)->printHtml($page)->filename);
    }

    public function testThePdfOfTheEngineGoesThroughTheStages(): void
    {
        $gotenberg = new FakeGotenberg()->answer('%PDF-optimized', 200, ['Content-Type' => 'application/pdf']);
        $events = new RecordingDispatcher();

        $output = new PagedMedia(gotenberg: $gotenberg->client, engine: new RecordingEngine('%PDF-engine'), events: $events)->printHtml(new PrintableHtml('<p>x</p>'), 'book.pdf', new Pipeline(new Optimize()), new PrintSubject('book', '42'));

        self::assertSame('%PDF-optimized', $output->contents);
        self::assertSame('book.pdf', $output->filename);
        self::assertStringContainsString('%PDF-engine', $gotenberg->requests[0]['body']);
        self::assertStringContainsString('filename="book.pdf"', $gotenberg->requests[0]['body']);
        self::assertCount(1, $events->events);
    }

    public function testHtmlWithoutAnEngineIsNotConfigured(): void
    {
        $this->expectExceptionObject(NotConfigured::printEngine());

        new PagedMedia()->printHtml(new PrintableHtml('<p>x</p>'));
    }

    public function testADocumentWithStagesGoesThroughGotenberg(): void
    {
        $gotenberg = new FakeGotenberg()->answer('%PDF-rotated', 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="ticket.pdf"', 'Gotenberg-Remediation-Residual' => '0', 'Gotenberg-Remediation-Rolled-Back' => '2']);
        $document = new Ticket()->build(['seat' => 'B3']);

        $output = new PagedMedia(gotenberg: $gotenberg->client)->render($document, 'ticket.pdf', new Pipeline(new Rotate(90)));

        self::assertEquals(new Output('%PDF-rotated', 'ticket.pdf', 'application/pdf', 0, 2), $output);
        self::assertSame('http://gotenberg:3000/forms/pdf/pipe', $gotenberg->requests[0]['url']);
        self::assertStringContainsString('filename="ticket.pdf"', $gotenberg->requests[0]['body']);
        self::assertStringContainsString($document->render(), $gotenberg->requests[0]['body']);
    }

    public function testATemplateIsBuiltFromItsParameters(): void
    {
        $media = new PagedMedia(new DocumentTemplates(['ticket' => new Ticket()]));

        $output = $media->renderTemplate('ticket', ['seat' => 'C7'], 'c7.pdf');

        self::assertSame(new Ticket()->build(['seat' => 'C7'])->render(), $output->contents);
        self::assertSame('c7.pdf', $output->filename);
        self::assertSame(new Ticket()->build([])->render(), $media->renderTemplate('ticket')->contents);
        self::assertSame('document.pdf', $media->renderTemplate('ticket')->filename);
    }

    public function testATemplateWithStagesGoesThroughGotenberg(): void
    {
        $gotenberg = new FakeGotenberg()->answer();

        $output = new PagedMedia(new DocumentTemplates(['ticket' => new Ticket()]), $gotenberg->client)->renderTemplate('ticket', [], 'a.pdf', new Pipeline(new Optimize()));

        self::assertSame('%PDF-out', $output->contents);
        self::assertStringContainsString('filename="a.pdf"', $gotenberg->requests[0]['body']);
    }

    public function testAnUnknownTemplateNamesTheKnownOnes(): void
    {
        $templates = new DocumentTemplates(new ArrayIterator(['ticket' => new Ticket(), 'receipt' => new Ticket()]));

        self::assertSame(['ticket', 'receipt'], $templates->names());
        self::assertInstanceOf(Ticket::class, $templates->get('receipt'));

        $this->expectException(UnknownTemplate::class);
        $this->expectExceptionMessageIsOrContains('No document template is named "badge"; the templates are: ticket, receipt.');

        $templates->get('badge');
    }

    public function testWithoutTemplatesNoneIsKnown(): void
    {
        $this->expectException(UnknownTemplate::class);
        $this->expectExceptionMessageIsOrContains('No document template is named "badge"; the templates are: none.');

        new DocumentTemplates()->get('badge');
    }

    public function testPdfsAreProcessedByGotenberg(): void
    {
        $gotenberg = new FakeGotenberg()->answer('%PDF', 200, ['Content-Type' => 'application/pdf']);
        $media = new PagedMedia(gotenberg: $gotenberg->client);

        self::assertSame('report.pdf', $media->process(Files::of(InputFile::fromContents('report.pdf', '%PDF')), new Pipeline(new Optimize()))->filename);
        $gotenberg->answer('%PDF', 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="merged.pdf"']);
        self::assertSame('merged.pdf', $media->process(Files::of(InputFile::fromContents('report.pdf', '%PDF')), new Pipeline(new Optimize()))->filename);
        $gotenberg->answer('%PDF', 200, []);
        $downloaded = $media->process(Files::of()->withArchive(InputFile::fromContents('a.zip', 'PK')), new Pipeline(new Optimize()));
        self::assertSame('document.pdf', $downloaded->filename);
        self::assertSame('application/octet-stream', $downloaded->contentType);
    }

    public function testOfficeDocumentsAreConvertedByGotenberg(): void
    {
        $gotenberg = new FakeGotenberg()->answer('%PDF', 200, ['Content-Type' => 'application/pdf']);
        $media = new PagedMedia(gotenberg: $gotenberg->client);

        $output = $media->convert(Files::of(InputFile::fromContents('report.docx', 'DOCX')), new LibreOfficeOptions(landscape: true), new Pipeline(new Optimize()));

        self::assertSame('report.docx.pdf', $output->filename);
        self::assertSame('http://gotenberg:3000/forms/libreoffice/convert', $gotenberg->requests[0]['url']);
        self::assertStringContainsString("name=\"landscape\"\r\n\r\ntrue", $gotenberg->requests[0]['body']);
        self::assertStringContainsString('"op":"optimize"', $gotenberg->requests[0]['body']);
        $gotenberg->answer('%PDF', 200, []);
        self::assertSame('document.pdf', $media->convert(Files::of()->withArchive(InputFile::fromContents('docs.zip', 'PK')))->filename);
        self::assertStringNotContainsString('stages', $gotenberg->requests[1]['body']);
    }

    public function testWithoutGotenbergNothingIsSent(): void
    {
        $this->expectException(NotConfigured::class);
        $this->expectExceptionMessageIsOrContains('Gotenberg is not configured: set phil_paged_media.gotenberg.url.');

        new PagedMedia()->render(new Ticket()->build([]), 'a.pdf', new Pipeline(new Optimize()));
    }

    public function testTheClientOfTheConfigurationIsGiven(): void
    {
        $gotenberg = new FakeGotenberg();

        self::assertSame($gotenberg->client, new PagedMedia(gotenberg: $gotenberg->client)->gotenberg());
    }

    public function testASignatureSaysWhatTheConfigurationSays(): void
    {
        $tsa = new TimestampAuthorityUrl('https://tsa.example.com');
        $media = new PagedMedia(certificates: $this->certificates(), timestampAuthority: $tsa, signature: new SignatureDefaults('Approval', 'Montréal', 'a@example.com', 'Ada', true));

        self::assertSame(
            ['certificate' => 'signer.p12', 'reason' => 'Approval', 'location' => 'Montréal', 'contactInfo' => 'a@example.com', 'name' => 'Ada', 'pades' => true, 'timestampUrl' => 'https://tsa.example.com'],
            $media->signature()->options(),
        );
        self::assertSame(
            ['certificate' => 'signer.p12', 'reason' => 'Receipt', 'location' => 'Québec', 'contactInfo' => 'b@example.com', 'name' => 'Bob', 'timestampUrl' => 'https://tsa.example.com'],
            $media->signature('Receipt', 'Québec', 'b@example.com', 'Bob', false)->options(),
        );
        self::assertSame(['certificate' => 'signer.p12'], new PagedMedia(certificates: $this->certificates())->signature()->options());
        self::assertSame(['timestampUrl' => 'https://tsa.example.com'], $media->timestamp()->options());
    }

    public function testASignatureNeedsACertificate(): void
    {
        $this->expectException(NotConfigured::class);
        $this->expectExceptionMessageIsOrContains('Signing is not configured: set phil_paged_media.signing.certificate.');

        new PagedMedia()->signature();
    }

    public function testATimestampNeedsAnAuthority(): void
    {
        $this->expectException(NotConfigured::class);
        $this->expectExceptionMessageIsOrContains('Timestamping is not configured: set phil_paged_media.signing.timestamp_url.');

        new PagedMedia(certificates: $this->certificates())->timestamp();
    }

    public function testTheStagesAreFinishedBySignatureAndTimestampWhenAsked(): void
    {
        $media = new PagedMedia(certificates: $this->certificates(), timestampAuthority: new TimestampAuthorityUrl('https://tsa.example.com'));
        $pipeline = new Pipeline(new Optimize());

        self::assertNull($media->finish(null));
        self::assertSame($pipeline, $media->finish($pipeline));
        self::assertSame([Optimize::class, Sign::class, Timestamp::class], $this->classes($media->finish($pipeline, true, true)));
        self::assertSame([Sign::class], $this->classes($media->finish(null, true)));
        self::assertSame([Timestamp::class], $this->classes($media->finish(null, false, true)));
        self::assertSame([Optimize::class, Timestamp::class], $this->classes($media->finish($pipeline, false, true)));
    }

    /**
     * @return list<class-string<Stage>>
     */
    private function classes(?Pipeline $pipeline): array
    {
        return array_map(static fn (Stage $stage): string => $stage::class, $pipeline->stages ?? []);
    }

    private function certificates(): CertificateSource
    {
        return new class implements CertificateSource {
            public function certificate(): Certificate
            {
                return Certificate::pkcs12(InputFile::fromContents('signer.p12', 'p12'));
            }
        };
    }
}
