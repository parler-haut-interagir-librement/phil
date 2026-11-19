<?php

declare(strict_types=1);

namespace Phil\MimeReader\Tests\Benchmark;

use Generator;
use Phil\MimeReader\MimeReader;

/**
 * A mailbox is read in batches: each message is read once, its text and attachments taken from the parts. A report, a
 * nested message with an attachment, and messages of hostile shape — a megabyte of text, thousands of parts, nesting
 * deeper than the limit, a boundary never closed — must each cost a time bounded by the limits, growing with the
 * octets, never with their square.
 *
 * @BeforeMethods("setUp")
 */
final class MimeReaderBench
{
    private readonly MimeReader $reader;

    private string $message = '';

    public function __construct()
    {
        $this->reader = new MimeReader();
    }

    /**
     * @param array{shape: string} $params
     */
    public function setUp(array $params): void
    {
        $fixtures = \dirname(__DIR__).'/Fixtures/';

        $this->message = match ($params['shape']) {
            'report' => (string) file_get_contents($fixtures.'report.eml'),
            'nested' => (string) file_get_contents($fixtures.'nested.eml'),
            'attachment' => "Content-Type: multipart/mixed; boundary=b\n\n--b\n\nhello\n--b\nContent-Disposition: attachment; filename=a.bin\nContent-Transfer-Encoding: base64\n\n"
                .chunk_split(base64_encode(str_repeat('0123456789', 400_000)), 76, "\r\n")."--b--\n",
            'megabyte' => "From: a@example.com\n\n".str_repeat("A line of text that is only text, nothing else.\n", 22_000),
            'parts' => "Content-Type: multipart/mixed; boundary=b\n\n".str_repeat("--b\n\nx\n", 50_000).'--b--',
            'deep' => implode('', array_map(static fn (int $i): string => "Content-Type: multipart/mixed; boundary=b{$i}\n\n--b{$i}\n", range(1, 1_000))),
            default => str_repeat("Content-Type: multipart/mixed; boundary=never\n\n--never\n", 200).str_repeat('x', 1_000_000),
        };
    }

    /**
     * @ParamProviders("messages")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchRead(): void
    {
        $message = $this->reader->read($this->message);
        $message->textBody();

        foreach ($message->attachments() as $attachment) {
            $stream = fopen('php://memory', 'w+');
            $attachment->writeTo($stream);
        }
    }

    /**
     * @return Generator<string, array{shape: string}>
     */
    public function messages(): Generator
    {
        foreach (['report', 'nested', 'attachment', 'megabyte', 'parts', 'deep', 'unclosed'] as $shape) {
            yield $shape => ['shape' => $shape];
        }
    }
}
