<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Twig;

use Override;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * Serves the sources of the templates under a name made of their hash, `<sha256>.html` or `<sha256>.txt`: the same
 * source always has the same name, so it compiles once and its compiled class serves every render, and two sources
 * never share a name. The suffix chooses the escaping: HTML for a body, none for a subject, a preheader or a
 * plain-text part. Nothing else can be loaded: a template has no name to include.
 *
 * It keeps at most `capacity` sources, the oldest forgotten first.
 */
final class SourceLoader implements LoaderInterface
{
    /** @var array<string, string> */
    private array $sources = [];

    /**
     * @param int<1, max> $capacity
     */
    public function __construct(
        private readonly int $capacity = 512,
    ) {
    }

    public function add(string $source, bool $html): string
    {
        $name = hash('sha256', $source).($html ? '.html' : '.txt');

        if (!isset($this->sources[$name]) && \count($this->sources) >= $this->capacity) {
            array_shift($this->sources);
        }

        $this->sources[$name] = $source;

        return $name;
    }

    #[Override]
    public function getSourceContext(string $name): Source
    {
        return new Source($this->sources[$name] ?? throw new LoaderError(\sprintf('No template is named "%s".', $name)), $name);
    }

    #[Override]
    public function getCacheKey(string $name): string
    {
        return $name;
    }

    #[Override]
    public function isFresh(string $name, int $time): bool
    {
        return true;
    }

    #[Override]
    public function exists(string $name): bool
    {
        return isset($this->sources[$name]);
    }

    public function clear(): void
    {
        $this->sources = [];
    }
}
