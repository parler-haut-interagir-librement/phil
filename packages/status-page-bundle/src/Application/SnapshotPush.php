<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Application;

use Phil\StatusPageBundle\Application\Contract\PageRenderer;
use Phil\StatusPageBundle\Application\Contract\SnapshotPublisher;
use Phil\StatusPageBundle\Application\Render\AtomFeed;
use Phil\StatusPageBundle\Application\Render\JsonDocument;
use Phil\StatusPageBundle\Application\Render\PageLinks;
use Phil\StatusPageBundle\Application\Snapshot\PushReport;
use Phil\StatusPageBundle\Application\Snapshot\Snapshot;
use Phil\StatusPageBundle\Domain\Exception\UnknownSnapshotTarget;
use Phil\Url\Url;
use Psr\Log\LoggerInterface;
use Throwable;
use Uri\WhatWg\Url as WhatWgUrl;

/**
 * Writes the static copy of the page — HTML, JSON and Atom — and gives it to each publisher, by its name. The HTML
 * links to the other two files beside it; the JSON and the feed carry absolute addresses under `$baseUrl`, the address
 * the copy is read at. A publisher that fails does not stop the others: its failure is logged and reported.
 */
final readonly class SnapshotPush
{
    /**
     * @param array<string, SnapshotPublisher> $publishers
     */
    public function __construct(
        private StatusPageBuilder $builder,
        private PageRenderer $renderer,
        private JsonDocument $json,
        private AtomFeed $atom,
        private Url $baseUrl,
        private array $publishers,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function snapshot(): Snapshot
    {
        $page = $this->builder->build();
        $base = $this->baseUrl->toString();
        $absolute = new PageLinks($base, $this->resolve(Snapshot::JSON, $base), $this->resolve(Snapshot::ATOM, $base));

        return Snapshot::of(
            $page->generatedAt,
            $this->renderer->render($page, new PageLinks('./', Snapshot::JSON, Snapshot::ATOM), true),
            $this->json->render($page, $absolute),
            $this->atom->render($page, $absolute),
        );
    }

    /**
     * Publishes one snapshot to `$target`, or to every target in the order they were declared.
     */
    public function push(?string $target = null): PushReport
    {
        if (null !== $target && !isset($this->publishers[$target])) {
            throw UnknownSnapshotTarget::named($target, array_keys($this->publishers));
        }

        $publishers = null === $target ? $this->publishers : [$target => $this->publishers[$target]];
        $snapshot = $this->snapshot();
        $published = [];
        $failed = [];

        foreach ($publishers as $name => $publisher) {
            try {
                $publisher->publish($snapshot);
                $published[] = $name;
            } catch (Throwable $throwable) {
                $this->logger?->error('The status page snapshot was not published to {target}.', ['target' => $name, 'exception' => $throwable]);
                $failed[$name] = substr((string) strrchr('\\'.$throwable::class, '\\'), 1);
            }
        }

        return new PushReport($snapshot, $published, $failed);
    }

    private function resolve(string $file, string $base): string
    {
        return new WhatWgUrl($base)->resolve($file)->toAsciiString();
    }
}
