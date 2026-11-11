<?php

declare(strict_types=1);

namespace Phil\InstantMessenger\Tests\Benchmark;

use Generator;
use Phil\Enums\Collection\EnumSet;
use Phil\InstantMessenger\KnownMessenger;
use Phil\InstantMessenger\Messengers;

/**
 * A field that accepts every service tries each in turn on what was typed: an address parse for each service that
 * reads links and a phone parse for the services that know numbers, until one reads it.
 */
final readonly class MessengersBench
{
    /** @var EnumSet<KnownMessenger> */
    private EnumSet $all;

    public function __construct()
    {
        $this->all = EnumSet::all(KnownMessenger::class);
    }

    /**
     * @ParamProviders("inputs")
     *
     * @Revs(200)
     *
     * @param array{input: string} $params
     */
    public function benchDetect(array $params): void
    {
        Messengers::detect($params['input'], $this->all);
    }

    /**
     * @return Generator<string, array{input: string}>
     */
    public function inputs(): Generator
    {
        yield 'phone, first service' => ['input' => '+1 514 555 0123'];
        yield 'telegram link, second' => ['input' => 'https://t.me/ada_lovelace'];
        yield 'line link, ninth' => ['input' => 'https://line.me/R/ti/p/@acme'];
        yield 'session id, last' => ['input' => '05'.str_repeat('a1b2c3d4', 8)];
        yield 'unknown link, none' => ['input' => 'https://www.example.com/contact'];
    }
}
