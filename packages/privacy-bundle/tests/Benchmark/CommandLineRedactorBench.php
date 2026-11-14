<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Benchmark;

use Generator;
use Phil\PrivacyBundle\Domain\Redaction\CommandLineRedactor;
use Phil\PrivacyBundle\Domain\Redaction\Denylist;

/**
 * Every logged string goes through the command line pattern, whose value alternation can backtrack, and the value of
 * an option that is not denied is read again recursively. Long lines show whether the cost stays linear.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"privacy", "log-redaction"})
 */
final class CommandLineRedactorBench
{
    private readonly CommandLineRedactor $redactor;

    private readonly CommandLineRedactor $withShortOptions;

    /**
     * @var array<string, string>
     */
    private array $texts = [];

    public function __construct()
    {
        $denylist = Denylist::fromList(['password', 'token', 'secret', '*token*', '*password*']);
        $this->redactor = new CommandLineRedactor($denylist);
        $this->withShortOptions = new CommandLineRedactor($denylist, ['p' => 'password', 't' => 'token']);
    }

    public function setUp(): void
    {
        $this->texts['plain'] = 'User 42 updated the shipping address of order 1001 and the cart was recalculated.';
        $this->texts['short'] = 'Error thrown while running command "app:import --password=hunter2". Message: "failed"';
        $this->texts['typical'] = 'Error thrown while running command "app:import --source=/var/data/export.csv --api-token abc123 --batch-size=500 --dry-run --locale fr_CA --db-password=\'it\'\\\'\'s\' -v --no-interaction". Message: "Import failed"';

        $options = [];

        for ($i = 0; $i < 400; ++$i) {
            $options[] = 0 === $i % 10 ? '--token=t'.$i : \sprintf('--option-%d=value-%d', $i, $i);
        }

        $this->texts['long (400 options, ~8 KB)'] = 'app:run '.implode(' ', $options);
        $this->texts['8 KB quoted value'] = 'app:run --message "'.str_repeat('words and \\"escapes\\" ', 400).'" --password=x';
        // Past roughly 10 KB in one value, PCRE exhausts its JIT stack and the whole text becomes the placeholder.
        $this->texts['20 KB quoted value'] = 'app:run --message "'.str_repeat('words and \\"escapes\\" ', 1000).'" --password=x';

        $nested = 'end';

        for ($i = 0; $i < 6; ++$i) {
            $nested = '--message "'.addcslashes($nested, '"\\').'"';
        }

        $this->texts['nested kept values (6 levels)'] = 'app:run '.$nested.' --password=x';
    }

    /**
     * @ParamProviders("texts")
     *
     * @param array{text: string} $params
     */
    public function benchRedact(array $params): void
    {
        $this->redactor->redact($this->texts[$params['text']]);
    }

    /**
     * @ParamProviders("texts")
     *
     * @param array{text: string} $params
     */
    public function benchRedactWithShortOptions(array $params): void
    {
        $this->withShortOptions->redact($this->texts[$params['text']]);
    }

    /**
     * @return Generator<string, array{text: string}>
     */
    public function texts(): Generator
    {
        yield 'plain sentence' => ['text' => 'plain'];
        yield 'short' => ['text' => 'short'];
        yield 'typical console error' => ['text' => 'typical'];
        yield 'long (400 options, ~8 KB)' => ['text' => 'long (400 options, ~8 KB)'];
        yield '8 KB quoted value' => ['text' => '8 KB quoted value'];
        yield '20 KB quoted value (JIT stack limit)' => ['text' => '20 KB quoted value'];
        yield 'nested kept values (6 levels)' => ['text' => 'nested kept values (6 levels)'];
    }
}
