<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Infrastructure\Console;

use Phil\StructuredData\Exception\InvalidStructuredData;
use Phil\StructuredData\Extraction;
use Phil\StructuredData\JsonLdReader;
use Phil\StructuredData\Validation\Report;
use Phil\StructuredData\Validation\Severity;
use Phil\StructuredData\Validation\Validator;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads the JSON-LD of a page — fetched from its address, or read from a saved file — and reports what its graph lacks
 * or holds wrongly. It fails on an error, or on a warning with `--strict`, so that a deployment can check its pages.
 */
#[AsCommand(name: 'phil:structured-data:check', description: 'Checks the schema.org structured data of a page, at an address or in a file')]
final readonly class CheckStructuredDataCommand
{
    public function __construct(
        private Validator $validator,
        private ?HttpClientInterface $client = null,
        private float $timeout = 10.0,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'An http:// or https:// address, or the path of a saved HTML page')]
        string $page,
        #[Option(description: 'Fail on warnings too')]
        bool $strict = false,
        #[Option(description: 'The output: text or json')]
        string $format = 'text',
    ): int {
        if (!\in_array($format, ['text', 'json'], true)) {
            $io->error(\sprintf('The format is "text" or "json"; "%s" is not.', $format));

            return Command::INVALID;
        }

        try {
            $extraction = JsonLdReader::fromHtml($this->read($page));
        } catch (InvalidStructuredData|ExceptionInterface $failure) {
            $io->error(\sprintf('The page "%s" cannot be read: %s', $page, $failure->getMessage()));

            return Command::FAILURE;
        }

        $report = $this->validator->validate($extraction->graph);
        $failed = [] !== $extraction->errors || !$report->isValid() || ($strict && [] !== $report->warnings());

        'json' === $format ? $this->json($io, $page, $extraction, $report) : $this->text($io, $extraction, $report);

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @throws InvalidStructuredData|ExceptionInterface
     */
    private function read(string $page): string
    {
        if (1 === preg_match('~^https?://~i', $page)) {
            return $this->fetch($page);
        }

        // Another scheme is refused before PHP could open it through a stream wrapper, phar:// among them.
        $html = 1 !== preg_match('~^[a-z][a-z0-9+.-]*://~i', $page) && is_file($page) && is_readable($page) ? file_get_contents($page, length: JsonLdReader::MAX_HTML_BYTES + 1) : false;

        return false === $html ? throw InvalidStructuredData::because('it is neither an http:// or https:// address nor a readable file.') : $html;
    }

    /**
     * @throws InvalidStructuredData|ExceptionInterface
     */
    private function fetch(string $url): string
    {
        if (!$this->client instanceof HttpClientInterface) {
            throw InvalidStructuredData::because('fetching an address needs symfony/http-client.');
        }

        $response = $this->client->request('GET', $url, ['timeout' => $this->timeout, 'max_redirects' => 5, 'headers' => ['Accept' => 'text/html,application/xhtml+xml']]);
        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            $response->cancel();

            throw InvalidStructuredData::because(\sprintf('the server answered %d.', $status));
        }

        $html = '';

        foreach ($this->client->stream($response) as $chunk) {
            $html .= $chunk->getContent();

            if (\strlen($html) > JsonLdReader::MAX_HTML_BYTES) {
                $response->cancel();

                throw InvalidStructuredData::because(\sprintf('the page is larger than %d bytes.', JsonLdReader::MAX_HTML_BYTES));
            }
        }

        return $html;
    }

    private function text(SymfonyStyle $io, Extraction $extraction, Report $report): void
    {
        foreach ($extraction->errors as $error) {
            $io->writeln('<error>[ERROR]</error> '.$error);
        }

        foreach ($report->issues as $issue) {
            $io->writeln((Severity::Error === $issue->severity ? '<error>[ERROR]</error> ' : '<comment>[WARNING]</comment> ').$issue->message);
        }

        $summary = \sprintf('%d nodes in %d JSON-LD elements: %d errors, %d warnings.', \count($extraction->graph), $extraction->blocks, \count($report->errors()) + \count($extraction->errors), \count($report->warnings()));
        [] === $extraction->errors && $report->isValid() ? $io->success($summary) : $io->error($summary);
    }

    private function json(SymfonyStyle $io, string $page, Extraction $extraction, Report $report): void
    {
        $io->writeln(json_encode([
            'page' => $page,
            'elements' => $extraction->blocks,
            'nodes' => \count($extraction->graph),
            'unreadable' => $extraction->errors,
            'issues' => $report->toArray(),
        ], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE), SymfonyStyle::OUTPUT_RAW);
    }
}
