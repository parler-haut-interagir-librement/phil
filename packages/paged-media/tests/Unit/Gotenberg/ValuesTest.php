<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit\Gotenberg;

use Phil\PagedMedia\Gotenberg\BasicAuth;
use Phil\PagedMedia\Gotenberg\Download;
use Phil\PagedMedia\Gotenberg\Exception\InvalidRequest;
use Phil\PagedMedia\Gotenberg\Exception\InvalidStage;
use Phil\PagedMedia\Gotenberg\Files;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\Internal\Url;
use Phil\PagedMedia\Gotenberg\Pages;
use Phil\PagedMedia\Gotenberg\Profile;
use Phil\PagedMedia\Gotenberg\RequestOptions;
use Phil\PagedMedia\Gotenberg\Webhook;
use Phil\PagedMedia\Gotenberg\WebhookMethod;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\PageSize;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pages::class)]
#[CoversClass(Profile::class)]
#[CoversClass(InputFile::class)]
#[CoversClass(Files::class)]
#[CoversClass(Download::class)]
#[CoversClass(BasicAuth::class)]
#[CoversClass(Webhook::class)]
#[CoversClass(RequestOptions::class)]
#[CoversClass(Url::class)]
#[CoversClass(InvalidRequest::class)]
#[CoversClass(InvalidStage::class)]
final class ValuesTest extends TestCase
{
    #[DataProvider('selections')]
    public function testAPageSelectionIsKeptAsGotenbergReadsIt(string $selection, string $value): void
    {
        self::assertSame($value, Pages::of($selection)->value);
        self::assertSame($value, (string) Pages::of($selection));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function selections(): iterable
    {
        yield ['3', '3'];
        yield ['2-5', '2-5'];
        yield ['1, 3, 5-7', '1,3,5-7'];
        yield ['4-4', '4-4'];
        yield ['all', 'all'];
        yield ['odd', 'odd'];
        yield ['even', 'even'];
        yield ['last', 'last'];
    }

    #[DataProvider('invalidSelections')]
    public function testAPageSelectionGotenbergWouldRefuseIsRefused(string $selection): void
    {
        $this->expectException(InvalidStage::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A page selection is a page, a range or a list of both, counted from 1 and in ascending ranges, or one of all, odd, even and last; "%s" is not.', str_replace(' ', '', $selection)));

        Pages::of($selection);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function invalidSelections(): iterable
    {
        yield [''];
        yield ['0'];
        yield ['1,0'];
        yield ['a'];
        yield ['1-'];
        yield ['-3'];
        yield ['1-2-3'];
        yield ['3-1'];
        yield ['1,,2'];
        yield ['all,1'];
        yield ['1.5'];
    }

    public function testPageSelectionsHaveNamedConstructors(): void
    {
        self::assertSame('all', Pages::all()->value);
        self::assertSame('odd', Pages::odd()->value);
        self::assertSame('even', Pages::even()->value);
        self::assertSame('last', Pages::last()->value);
        self::assertSame('7', Pages::page(7)->value);
        self::assertSame('2-9', Pages::range(2, 9)->value);
    }

    public function testAProfileKnowsItsAccessibilityPartner(): void
    {
        self::assertSame(Profile::PdfUa1, Profile::PdfA1a->accessibilityPartner());
        self::assertSame(Profile::PdfUa1, Profile::PdfA3u->accessibilityPartner());
        self::assertSame(Profile::PdfUa1, Profile::FacturX->accessibilityPartner());
        self::assertSame(Profile::PdfUa2, Profile::PdfA4->accessibilityPartner());
        self::assertSame(Profile::PdfUa2, Profile::PdfA4e->accessibilityPartner());
        self::assertSame(Profile::PdfUa2, Profile::PdfA4f->accessibilityPartner());
        self::assertNull(Profile::PdfUa1->accessibilityPartner());
        self::assertNull(Profile::PdfUa2->accessibilityPartner());
        self::assertTrue(Profile::PdfUa2->isAccessibility());
        self::assertFalse(Profile::PdfA2u->isAccessibility());
    }

    public function testProfilesPairAnArchiveWithTheAccessibilityOfItsVersion(): void
    {
        self::assertSame([Profile::PdfA2b], Profile::checked(Profile::PdfA2b));
        self::assertSame([Profile::PdfUa1], Profile::checked([Profile::PdfUa1]));
        self::assertSame([Profile::PdfA2b, Profile::PdfUa1], Profile::checked([Profile::PdfA2b, Profile::PdfUa1]));
        self::assertSame([Profile::PdfUa2, Profile::PdfA4f], Profile::checked([Profile::PdfUa2, Profile::PdfA4f]));
    }

    /**
     * @param list<Profile> $profiles
     */
    #[DataProvider('invalidPairs')]
    public function testOtherPairsAreRefused(array $profiles, string $names): void
    {
        $this->expectException(InvalidStage::class);
        $this->expectExceptionMessageIsOrContains('A profile is one standard, or a PDF/A or Factur-X profile with the PDF/UA profile of its PDF version: PDF/A-1 to PDF/A-3 and Factur-X with PDF/UA-1, PDF/A-4 with PDF/UA-2; "'.$names.'" is not.');

        Profile::checked($profiles);
    }

    /**
     * @return iterable<array{list<Profile>, string}>
     */
    public static function invalidPairs(): iterable
    {
        yield [[], ''];
        yield [[Profile::PdfA4, Profile::PdfUa1], 'PDF/A-4, PDF/UA-1'];
        yield [[Profile::PdfUa2, Profile::PdfA2b], 'PDF/UA-2, PDF/A-2b'];
        yield [[Profile::PdfUa1, Profile::PdfUa1], 'PDF/UA-1, PDF/UA-1'];
        yield [[Profile::PdfA1b, Profile::PdfA2b], 'PDF/A-1b, PDF/A-2b'];
        yield [[Profile::PdfA2b, Profile::PdfUa1, Profile::PdfUa1], 'PDF/A-2b, PDF/UA-1, PDF/UA-1'];
    }

    public function testAFileFromMemoryIsSentWhole(): void
    {
        $file = InputFile::fromContents('Report.PDF', 'bytes');

        self::assertSame('Report.PDF', $file->name);
        self::assertSame('pdf', $file->extension());
        self::assertSame(['bytes'], iterator_to_array($file->chunks()));
        self::assertNull($file->path());
        self::assertSame('', InputFile::fromContents('README', '')->extension());
    }

    public function testADocumentOfTheNativeWriterIsSentRendered(): void
    {
        $document = new Document(PageSize::a5());
        $document->addPage();

        $file = InputFile::fromDocument('ticket.pdf', $document);

        self::assertSame('ticket.pdf', $file->name);
        self::assertSame([$document->render()], iterator_to_array($file->chunks()));
    }

    public function testAFileOnDiskIsReadInChunksWhenItIsSent(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'phil-paged-media-');
        self::assertIsString($path);
        file_put_contents($path, 'abcdefg');

        try {
            $file = InputFile::fromPath($path);
            $renamed = InputFile::fromPath($path, 'annex.pdf');

            self::assertSame(basename($path), $file->name);
            self::assertSame('annex.pdf', $renamed->name);
            self::assertSame($path, $renamed->path());
            self::assertSame(['abc', 'def', 'g'], iterator_to_array($file->chunks(3), false));
            self::assertSame(['a', 'b', 'c', 'd', 'e', 'f', 'g'], iterator_to_array($file->chunks(0), false));
            self::assertSame(['abcdefg'], iterator_to_array($file->chunks(), false));
            self::assertTrue($file->sameAs(InputFile::fromPath($path)));
            self::assertFalse($file->sameAs($renamed));
            self::assertFalse($renamed->sameAs(InputFile::fromContents('annex.pdf', 'abcdefg')));
            self::assertFalse(InputFile::fromContents('a.pdf', 'x')->sameAs(InputFile::fromContents('a.pdf', 'y')));
            self::assertTrue(InputFile::fromContents('a.pdf', 'x')->sameAs(InputFile::fromContents('a.pdf', 'x')));
        } finally {
            unlink($path);
        }
    }

    public function testAFileRemovedBeforeItIsSentIsRefused(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'phil-paged-media-');
        self::assertIsString($path);
        $file = InputFile::fromPath($path);
        unlink($path);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The file "%s" cannot be read.', $path));

        iterator_to_array($file->chunks());
    }

    public function testAMissingFileIsRefused(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessageIsOrContains('The file "/nonexistent/report.pdf" cannot be read.');

        InputFile::fromPath('/nonexistent/report.pdf');
    }

    public function testADirectoryIsNotAFile(): void
    {
        $this->expectException(InvalidRequest::class);

        InputFile::fromPath(sys_get_temp_dir());
    }

    #[DataProvider('invalidNames')]
    public function testANameThatWouldBreakTheRequestIsRefused(string $name): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessageIsOrContains('A file name is not empty and holds no slash, backslash, quote or line break; "'.$name.'" does.');

        InputFile::fromContents($name, '');
    }

    /**
     * @return iterable<array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield [''];
        yield ['a/b.pdf'];
        yield ['a\b.pdf'];
        yield ['a"b.pdf'];
        yield ["a\rb.pdf"];
        yield ["a\nb.pdf"];
        yield ["a\0b.pdf"];
    }

    public function testFilesGatherUploadsArchivesAndDownloads(): void
    {
        $report = InputFile::fromContents('report.pdf', 'r');
        $annex = InputFile::fromContents('annex.pdf', 'a');
        $zip = InputFile::fromContents('site.zip', 'z');
        $download = new Download('https://files.example.com/a.pdf');

        $files = Files::of($report)->with($annex)->withArchive($zip)->withDownload($download);

        self::assertSame([$report, $annex], $files->files);
        self::assertSame([$zip], $files->archives);
        self::assertSame([$download], $files->downloads);
        self::assertFalse($files->isEmpty());
        self::assertTrue(Files::of()->isEmpty());
        self::assertFalse(Files::of()->withArchive($zip)->isEmpty());
        self::assertFalse(Files::of()->withDownload($download)->isEmpty());
        self::assertSame([$report], Files::of($report)->withDownload($download)->withArchive($zip)->files);
        self::assertSame([$zip], Files::of()->withDownload($download)->withArchive($zip)->archives);
        self::assertSame([$download], Files::of()->withArchive($zip)->withDownload($download)->with($annex)->downloads);
        self::assertSame([$zip], Files::of()->withArchive($zip)->with($annex)->archives);
    }

    public function testFilesKeepTheirOrderAsAList(): void
    {
        $a = InputFile::fromContents('a.pdf', 'a');
        $b = InputFile::fromContents('b.pdf', 'b');
        $c = InputFile::fromContents('c.pdf', 'c');
        $zip = InputFile::fromContents('one.zip', '1');
        $zip2 = InputFile::fromContents('two.zip', '2');
        $one = new Download('https://files.example.com/1.pdf');
        $two = new Download('https://files.example.com/2.pdf');

        self::assertSame([$a, $b], Files::of(...['first' => $a, 'second' => $b])->files);
        self::assertSame([$a, $b, $c], Files::of($a)->with(...['b' => $b, 'c' => $c])->files);
        self::assertSame([$zip, $zip2], Files::of()->withArchive($zip)->withArchive($zip2)->archives);
        self::assertSame([$one, $two], Files::of()->withDownload($one)->withDownload($two)->downloads);
        self::assertSame([basename(__FILE__)], array_map(static fn (InputFile $file): string => $file->name, Files::fromPaths(...['self' => __FILE__])->files));
    }

    public function testFilesAreReadFromPaths(): void
    {
        $files = Files::fromPaths(__FILE__);

        self::assertSame([basename(__FILE__)], array_map(static fn (InputFile $file): string => $file->name, $files->files));
    }

    public function testADownloadKeepsItsHeaders(): void
    {
        self::assertSame(['url' => 'http://files.example.com/a.pdf'], new Download('http://files.example.com/a.pdf')->toArray());
        self::assertSame(['url' => 'HTTPS://files.example.com/a.pdf', 'extraHttpHeaders' => ['Authorization' => 'Bearer x']], new Download('HTTPS://files.example.com/a.pdf', ['Authorization' => 'Bearer x'])->toArray());
    }

    #[DataProvider('invalidUrls')]
    public function testADownloadIsAPublicHttpAddress(string $url): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessageIsOrContains('A download is a public http or https address; "'.$url.'" is not. ');

        new Download($url);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function invalidUrls(): iterable
    {
        yield ['/a.pdf'];
        yield ['ftp://files.example.com/a.pdf'];
        yield ['file:///etc/passwd'];
        yield ['https:///a.pdf'];
        yield ['httpx://a'];
        yield ['http:/files/a.pdf'];
        yield 'a bare name of the network' => ['http://storage:9000/a.pdf'];
        yield 'an IP address' => ['http://169.254.169.254/latest/meta-data'];
        yield 'credentials' => ['https://user:secret@files.example.com/a.pdf'];
    }

    #[DataProvider('serviceUrls')]
    public function testAWebhookMayCallAServiceOfTheNetwork(string $url): void
    {
        self::assertSame($url, new Webhook($url, $url)->url);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function serviceUrls(): iterable
    {
        yield ['http://app:8000/upload'];
        yield ['http://127.0.0.1:8000/upload'];
        yield ['http://[::1]/upload'];
        yield ['HTTPS://app.example.com/upload?token=a@b#x'];
        yield ['https://app.example.com'];
    }

    #[DataProvider('invalidServiceUrls')]
    public function testAWebhookRefusesWhatIsNotAServiceAddress(string $url): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessageIsOrContains('A webhook is an absolute URL with an http or https scheme, a host and no credentials; "'.$url.'" is not.');

        new Webhook($url, 'https://app.example.com/events');
    }

    /**
     * @return iterable<array{string}>
     */
    public static function invalidServiceUrls(): iterable
    {
        yield ['upload'];
        yield ['ftp://app/upload'];
        yield ['https:///upload'];
        yield ['https://\\app/upload'];
        yield ['http:/app/upload'];
        yield ['http://user:secret@app/upload'];
        yield ['http://@app/upload'];
        yield ['http://app /upload'];
        yield ['http://app/up load'];
        yield [' http://app/upload'];
        yield ['http://app/upload '];
        yield ['http://app:99999/upload'];
        yield ['http://:8000/upload'];
        yield ['xhttp://app/upload'];
    }

    public function testBasicAuthGivesItsCredentials(): void
    {
        self::assertSame(['gotenberg', 's3cr:t'], new BasicAuth('gotenberg', 's3cr:t')->credentials());
    }

    #[DataProvider('invalidUsers')]
    public function testABasicAuthUserHasNoColon(string $user): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessageIsOrContains('A basic authentication user name is not empty and holds no colon.');

        new BasicAuth($user, 'p');
    }

    /**
     * @return iterable<array{string}>
     */
    public static function invalidUsers(): iterable
    {
        yield [''];
        yield ['a:b'];
    }

    public function testAWebhookBecomesItsHeaders(): void
    {
        self::assertSame(
            ['Gotenberg-Webhook-Url' => 'https://app.example.com/upload', 'Gotenberg-Webhook-Events-Url' => 'https://app.example.com/events', 'Gotenberg-Webhook-Method' => 'POST'],
            new Webhook('https://app.example.com/upload', 'https://app.example.com/events')->headers(),
        );
        self::assertSame(
            ['Gotenberg-Webhook-Url' => 'http://a.example/u', 'Gotenberg-Webhook-Events-Url' => 'http://a.example/e', 'Gotenberg-Webhook-Method' => 'PATCH', 'Gotenberg-Webhook-Extra-Http-Headers' => '{"Authorization":"Bearer a/b"}'],
            new Webhook('http://a.example/u', 'http://a.example/e', WebhookMethod::Patch, ['Authorization' => 'Bearer a/b'])->headers(),
        );
    }

    public function testAWebhookIsSentToAbsoluteUrls(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessageIsOrContains('A webhook events URL is an absolute URL');

        new Webhook('https://app.example.com/upload', '/events');
    }

    public function testAWebhookUploadsToAnAbsoluteUrl(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessageIsOrContains('A webhook is an absolute URL');

        new Webhook('upload', 'https://app.example.com/events');
    }

    public function testRequestOptionsBecomeHeaders(): void
    {
        self::assertSame([], new RequestOptions()->headers());
        self::assertSame(['Gotenberg-Output-Filename' => 'facture-été', 'Gotenberg-Trace' => 'r-42'], new RequestOptions('facture-été', 'r-42')->headers());
        self::assertSame(
            ['Gotenberg-Trace' => 't', 'Gotenberg-Webhook-Url' => 'https://a.example/u', 'Gotenberg-Webhook-Events-Url' => 'https://a.example/e', 'Gotenberg-Webhook-Method' => 'PUT'],
            new RequestOptions(trace: 't', webhook: new Webhook('https://a.example/u', 'https://a.example/e', WebhookMethod::Put))->headers(),
        );
    }

    #[DataProvider('unsafeHeaders')]
    public function testAHeaderValueHasNoLineBreak(?string $filename, ?string $trace, string $message): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessageIsOrContains($message);

        new RequestOptions($filename, $trace);
    }

    /**
     * @return iterable<array{?string, ?string, string}>
     */
    public static function unsafeHeaders(): iterable
    {
        yield ["a\r\nX-Evil: 1", null, 'An output file name is a non-empty text without a line break.'];
        yield ['', null, 'An output file name is a non-empty text without a line break.'];
        yield [null, "t\n", 'A trace identifier is a non-empty text without a line break.'];
        yield [null, "t\0", 'A trace identifier is a non-empty text without a line break.'];
        yield [null, "\r", 'A trace identifier'];
    }
}
