<?php

declare(strict_types=1);

namespace Phil\BounceParser\Tests\Benchmark;

use Generator;
use Phil\BounceParser\BounceKind;
use Phil\BounceParser\BounceParser;
use Phil\BounceParser\Rule\Field;
use Phil\BounceParser\Rule\Rule;
use Phil\BounceParser\Rule\Rules;
use Phil\BounceParser\Verp;

/**
 * A bounce mailbox is read in batches: each message is parsed once. A notification, a report, a text bounce and an
 * unrecognised message tried against ten rules must each cost a fraction of a millisecond, and a message of hostile
 * shape — a megabyte of text, thousands of parts — a time bounded by the limits.
 *
 * @BeforeMethods("setUp")
 */
final class BounceParserBench
{
    private readonly BounceParser $parser;

    private string $message = '';

    public function __construct()
    {
        $rules = new Rules();

        for ($i = 0; $i < 10; ++$i) {
            $rules = $rules->with(Rule::containing('rule '.$i, Field::Body, 'phrase number '.$i, BounceKind::Soft));
        }

        $this->parser = new BounceParser($rules, new Verp('bounces', 'mail.example.net'));
    }

    /**
     * @param array{shape: string} $params
     */
    public function setUp(array $params): void
    {
        $fixtures = \dirname(__DIR__).'/Fixtures/';

        $this->message = match ($params['shape']) {
            'notification' => (string) file_get_contents($fixtures.'postfix-hard.eml'),
            'report' => (string) file_get_contents($fixtures.'arf.eml'),
            'text' => (string) file_get_contents($fixtures.'exim-plain.eml'),
            'unrecognised' => (string) file_get_contents($fixtures.'person.eml'),
            'megabyte' => "From: a@example.com\n\n".str_repeat("A line of text that no rule matches at all.\n", 25_000),
            default => "Content-Type: multipart/mixed; boundary=b\n\n".str_repeat("--b\n\nx\n", 5_000).'--b--',
        };
    }

    /**
     * @ParamProviders("messages")
     *
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchParse(): void
    {
        $this->parser->parse($this->message);
    }

    /**
     * @return Generator<string, array{shape: string}>
     */
    public function messages(): Generator
    {
        foreach (['notification', 'report', 'text', 'unrecognised', 'megabyte', 'parts'] as $shape) {
            yield $shape => ['shape' => $shape];
        }
    }
}
