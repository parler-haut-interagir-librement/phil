<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Tests\Unit\Infrastructure;

use Generator;
use LogicException;
use Phil\StructuredData\JsonLdReader;
use Phil\StructuredData\Validation\Validator;
use Phil\StructuredDataBundle\Infrastructure\Console\CheckStructuredDataCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(CheckStructuredDataCommand::class)]
final class CheckStructuredDataCommandTest extends TestCase
{
    private const string GOOD = '<html><head><script type="application/ld+json">{"@context":"https://schema.org","@type":"Organization","name":"Example Press","url":"https://example.com","logo":"https://example.com/logo.png"}</script></head></html>';

    private const string WARNED = '<script type="application/ld+json">{"@type":"Organization","name":"Example Press"}</script>';

    private const string WRONG = '<script type="application/ld+json">{"@type":"Event","@id":"urn:été","name":"Tournoi"}</script><script type="application/ld+json">{oops</script>';

    private string $file = '';

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir().'/phil-structured-data-'.bin2hex(random_bytes(4)).'.html';
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testAGoodPageSucceeds(): void
    {
        file_put_contents($this->file, self::GOOD);
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['page' => $this->file]));
        self::assertStringContainsString('[OK] 1 nodes in 1 JSON-LD elements: 0 errors, 0 warnings.', $this->display($tester));
        self::assertStringNotContainsString('[WARNING]', $this->display($tester));
    }

    public function testASavedPageMayHoldASchemeLikeFolderInItsPath(): void
    {
        $folder = sys_get_temp_dir().'/phil-structured-data-'.bin2hex(random_bytes(4)).'-saved:';
        mkdir($folder);
        file_put_contents($folder.'/page.html', self::GOOD);

        try {
            self::assertSame(Command::SUCCESS, $this->tester()->execute(['page' => $folder.'//page.html']));
        } finally {
            unlink($folder.'/page.html');
            rmdir($folder);
        }
    }

    public function testWarningsFailOnlyWhenStrict(): void
    {
        file_put_contents($this->file, self::WARNED);
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['page' => $this->file]));
        self::assertStringContainsString('[WARNING] Organization @graph[0] should have url.', $this->display($tester));
        self::assertStringContainsString('[OK] 1 nodes in 1 JSON-LD elements: 0 errors, 2 warnings.', $this->display($tester));
        self::assertSame(Command::FAILURE, $tester->execute(['page' => $this->file, '--strict' => true]));
    }

    public function testErrorsAndUnreadableElementsFail(): void
    {
        file_put_contents($this->file, self::WRONG);
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['page' => $this->file]));
        $display = $this->display($tester);
        self::assertStringContainsString('[ERROR] JSON-LD element 2: The JSON cannot be read: Syntax error', $display);
        self::assertStringContainsString('[ERROR] urn:été needs startDate.', $display);
        self::assertStringContainsString('1 nodes in 2 JSON-LD elements: 3 errors, 6 warnings.', $display);
        self::assertStringNotContainsString('[OK]', $display);

        file_put_contents($this->file, '<script type="application/ld+json">{oops</script>');
        self::assertSame(Command::FAILURE, $tester->execute(['page' => $this->file]));
        self::assertStringContainsString('0 nodes in 1 JSON-LD elements: 1 errors, 0 warnings.', $this->display($tester));
        self::assertStringNotContainsString('[OK]', $this->display($tester));
    }

    public function testTheReportIsJsonOnDemand(): void
    {
        file_put_contents($this->file, self::WRONG);
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['page' => $this->file, '--format' => 'json']));
        $report = json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($report);
        self::assertSame($this->file, $report['page']);
        self::assertSame(2, $report['elements']);
        self::assertSame(1, $report['nodes']);
        self::assertIsArray($report['unreadable']);
        self::assertCount(1, $report['unreadable']);
        self::assertIsString($report['unreadable'][0]);
        self::assertStringStartsWith('JSON-LD element 2: The JSON cannot be read: Syntax error', $report['unreadable'][0]);
        self::assertSame(['severity' => 'error', 'code' => 'missing-property', 'node' => 'urn:été', 'property' => 'startDate', 'message' => 'urn:été needs startDate.'], \is_array($report['issues']) ? $report['issues'][0] : null);
        self::assertStringContainsString("\n    \"page\": ", $tester->getDisplay());
        self::assertStringNotContainsString('\/', $this->display($tester));
        self::assertStringContainsString('"node": "urn:été"', $tester->getDisplay());

        file_put_contents($this->file, self::GOOD);
        self::assertSame(Command::SUCCESS, $tester->execute(['page' => $this->file, '--format' => 'json']));
        self::assertStringContainsString('"issues": []', $this->display($tester));
    }

    public function testAnUnknownFormatIsRefused(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute(['page' => $this->file, '--format' => 'xml']));
        self::assertStringContainsString('The format is "text" or "json"; "xml" is not.', $this->display($tester));
    }

    public function testAPageThatCannotBeReadFails(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['page' => $this->file]));
        self::assertStringContainsString('cannot be read: it is neither an http:// or https:// address nor a readable file.', $this->display($tester));

        self::assertSame(Command::FAILURE, $tester->execute(['page' => sys_get_temp_dir()]));
        self::assertStringContainsString('nor a readable file.', $this->display($tester));

        file_put_contents($this->file, str_repeat(' ', JsonLdReader::MAX_HTML_BYTES + 1));
        self::assertSame(Command::FAILURE, $tester->execute(['page' => $this->file]));
        self::assertStringContainsString('A page read for its structured data is 5000000 bytes at most.', $this->display($tester));

        self::assertSame(Command::FAILURE, $this->tester(withClient: false)->execute(['page' => 'https://example.com/']));

        // An address of another scheme is never opened as a file, through a stream wrapper such as phar://.
        foreach (['saved-http://example.com', 'phar://archive.phar/page.html', 'FILE:///etc/hosts'] as $page) {
            self::assertSame(Command::FAILURE, $tester->execute(['page' => $page]));
            self::assertStringContainsString('nor a readable file.', $this->display($tester));
        }
    }

    public function testAnAddressIsFetched(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $headers = $options['headers'];
            self::assertIsArray($headers);
            $requests[] = [$method, $url, $options['timeout'], $options['max_redirects'], $headers];

            return new MockResponse(self::GOOD, ['http_code' => 200]);
        });
        $tester = $this->tester($client);

        self::assertSame(Command::SUCCESS, $tester->execute(['page' => 'HTTPS://example.com/a']));
        self::assertSame('GET', $requests[0][0]);
        self::assertSame('https://example.com/a', $requests[0][1]);
        self::assertSame(2.5, $requests[0][2]);
        self::assertSame(5, $requests[0][3]);
        self::assertSame(['Accept: text/html,application/xhtml+xml'], array_values(array_filter($requests[0][4], static fn (mixed $header): bool => \is_string($header) && str_starts_with($header, 'Accept:'))));
        self::assertStringContainsString('[OK] 1 nodes in 1 JSON-LD elements', $this->display($tester));
    }

    public function testAFetchThatFailsFails(): void
    {
        $tester = $this->tester(new MockHttpClient(new MockResponse('gone', ['http_code' => 404])));
        self::assertSame(Command::FAILURE, $tester->execute(['page' => 'http://example.com/a']));
        self::assertStringContainsString('The page "http://example.com/a" cannot be read: the server answered 404.', $this->display($tester));

        $tester = $this->tester(new MockHttpClient(new MockResponse(self::GOOD, ['http_code' => 199])));
        self::assertSame(Command::FAILURE, $tester->execute(['page' => 'http://example.com/a']));
        self::assertStringContainsString('the server answered 199.', $this->display($tester));

        $tester = $this->tester(new MockHttpClient(new MockResponse(self::GOOD, ['http_code' => 299])));
        self::assertSame(Command::SUCCESS, $tester->execute(['page' => 'http://example.com/a']));

        $tester = $this->tester(new MockHttpClient(new MockResponse(self::GOOD, ['http_code' => 300])));
        self::assertSame(Command::FAILURE, $tester->execute(['page' => 'http://example.com/a']));

        $tester = $this->tester(new MockHttpClient(new MockResponse('', ['error' => 'Connection refused'])));
        self::assertSame(Command::FAILURE, $tester->execute(['page' => 'http://example.com/a']));
        self::assertStringContainsString('Connection refused', $this->display($tester));

        $tester = $this->tester(withClient: false);
        self::assertSame(Command::FAILURE, $tester->execute(['page' => 'http://example.com/a']));
        self::assertStringContainsString('fetching an address needs symfony/http-client.', $this->display($tester));
    }

    public function testAPageTooLargeIsCutAndRefused(): void
    {
        $body = (static function (): Generator {
            for ($index = 0; $index < 6; ++$index) {
                yield str_repeat(' ', 1_000_000);
            }

            throw new LogicException('The fetch stops once the page is too large.');
        })();
        $tester = $this->tester(new MockHttpClient(new MockResponse($body, ['http_code' => 200])));

        self::assertSame(Command::FAILURE, $tester->execute(['page' => 'http://example.com/big']));
        self::assertStringContainsString('cannot be read: the page is larger than 5000000 bytes.', $this->display($tester));

        $exact = str_repeat(' ', JsonLdReader::MAX_HTML_BYTES);
        self::assertSame(Command::SUCCESS, $this->tester(new MockHttpClient(new MockResponse($exact)))->execute(['page' => 'http://example.com/exact']));
    }

    private function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }

    private function tester(?HttpClientInterface $client = null, bool $withClient = true): CommandTester
    {
        $application = new Application();
        $application->addCommand(new CheckStructuredDataCommand(new Validator(), $withClient ? ($client ?? new MockHttpClient()) : null, 2.5));

        return new CommandTester($application->find('phil:structured-data:check'));
    }
}
