<?php

declare(strict_types=1);

namespace Phil\ApiContract\Tests\Benchmark;

use Generator;
use Phil\ApiContract\Deprecation\DeprecationSignal;
use Phil\ApiContract\Deprecation\LinkHeader;

/**
 * Every HTTP client response that carries Deprecation or Sunset is read here on its first chunk, and the Link lines
 * are scanned with two nested regexes until the deprecation relation turns up, so a paginated API pays it per page.
 */
final class DeprecationSignalBench
{
    private const string DEPRECATION = '@1767225600';

    private const string SUNSET = 'Wed, 30 Jun 2027 23:59:59 GMT';

    /**
     * @var array<string, list<string>>
     */
    private array $links;

    public function __construct()
    {
        $deprecation = '<https://api.example.com/docs/migrate-v2>; rel="deprecation"; type="text/html"';
        $pagination = static fn (int $count): array => array_map(
            static fn (int $page): string => \sprintf('<https://api.example.com/v1/orders?page=%d&per_page=50>; rel="page-%d"; title="Page %d"', $page, $page, $page),
            range(1, $count),
        );

        $this->links = [
            'one link' => [$deprecation],
            'pagination, deprecation last' => [implode(', ', [
                '<https://api.example.com/v1/orders?page=1>; rel="first"',
                '<https://api.example.com/v1/orders?page=4>; rel="prev"',
                '<https://api.example.com/v1/orders?page=6>; rel="next"',
                '<https://api.example.com/v1/orders?page=40>; rel="last"',
                $deprecation,
            ])],
            '50 links over 5 lines, deprecation last' => [
                ...array_map(static fn (array $chunk): string => implode(', ', $chunk), array_chunk($pagination(50), 10)),
                $deprecation,
            ],
            '200 links, no deprecation' => [implode(', ', $pagination(200))],
        ];
    }

    /**
     * @ParamProviders("linkSets")
     *
     * @param array{links: string} $params
     */
    public function benchFromHeaders(array $params): void
    {
        DeprecationSignal::fromHeaders(self::DEPRECATION, self::SUNSET, $this->links[$params['links']]);
    }

    /**
     * @ParamProviders("linkSets")
     *
     * @param array{links: string} $params
     */
    public function benchFirstWithRelation(array $params): void
    {
        LinkHeader::firstWithRelation($this->links[$params['links']], DeprecationSignal::LINK_RELATION);
    }

    /**
     * @return Generator<string, array{links: string}>
     */
    public function linkSets(): Generator
    {
        yield 'one link' => ['links' => 'one link'];
        yield 'pagination, deprecation last' => ['links' => 'pagination, deprecation last'];
        yield '50 links over 5 lines, deprecation last' => ['links' => '50 links over 5 lines, deprecation last'];
        yield '200 links, no deprecation' => ['links' => '200 links, no deprecation'];
    }
}
