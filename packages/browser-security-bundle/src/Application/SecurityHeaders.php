<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application;

use Phil\BrowserSecurityBundle\Domain\Enum\Protection;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use Phil\BrowserSecurityBundle\Domain\Syntax\ProtectionSyntax;

/**
 * The headers that go with a content security policy, each one only when the configuration gives it a value, with the
 * `X-Frame-Options` of each path and the overrides of a response.
 *
 * Everything is written once and never changed: a worker shares it between requests, and a response without override
 * pays only for choosing the frame option of its path.
 *
 * @phpstan-import-type Value from ProtectionSyntax
 */
final readonly class SecurityHeaders
{
    /** @var array<string, string> */
    public array $headers;

    /** @var array<string, Value> */
    private array $values;

    /** @var array<string, ?string> the frame option by prefix of a path, the longest prefixes first */
    private array $framePaths;

    /**
     * @param array<string, mixed>   $values      the value of each header, by its name
     * @param array<string, ?string> $framePaths  `X-Frame-Options` by prefix of a path, null for no header
     * @param string|null            $reportGroup the endpoint the reporting headers name, or none
     *
     * @throws InvalidPolicy when a value is not one its header takes
     */
    public function __construct(
        array $values = [],
        array $framePaths = [],
        private ?string $reportGroup = null,
    ) {
        $normalized = [];
        $headers = [];

        foreach ($values as $name => $value) {
            $protection = Protection::named($name);
            $normalized[$name] = ProtectionSyntax::normalize($protection, $value);
            $headers[$name] = $this->render($protection, $normalized[$name]);
        }

        foreach ($framePaths as $value) {
            if (null !== $value) {
                ProtectionSyntax::normalize(Protection::FrameOptions, $value);
            }
        }

        uksort($framePaths, static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a));
        $this->values = $normalized;
        $this->headers = array_filter($headers, static fn (string $header): bool => '' !== $header);
        $this->framePaths = $framePaths;
    }

    /**
     * The headers of a response.
     *
     * @param list<ProtectionOverride> $overrides the overrides of its request, in order
     * @param bool                     $secure    whether the request came over HTTPS: `Strict-Transport-Security` is sent on no other
     */
    public function resolve(string $path, array $overrides = [], bool $secure = true): ResolvedHeaders
    {
        $defaults = $this->headers;
        $frame = $this->frameOption($path);
        unset($defaults[Protection::FrameOptions->value]);

        if (null !== $frame) {
            $defaults[Protection::FrameOptions->value] = $frame;
        }

        $forced = [];

        foreach ($overrides as $override) {
            foreach ($override->protections() as $protection) {
                if ($protection->isHeader()) {
                    $name = $protection->value;
                    unset($defaults[$name]);
                    $forced[$name] = $this->overridden($protection, Protection::FrameOptions === $protection ? $frame : ($this->values[$name] ?? null), $overrides);
                }
            }
        }

        if (!$secure) {
            unset($defaults[Protection::StrictTransportSecurity->value], $forced[Protection::StrictTransportSecurity->value]);
        }

        return new ResolvedHeaders($defaults, $forced, null !== $this->reportGroup && array_any(Protection::cases(), static fn (Protection $protection): bool => $protection->reports() && (isset($defaults[$protection->value]) || isset($forced[$protection->value]))));
    }

    /**
     * @param Value|null               $value
     * @param list<ProtectionOverride> $overrides
     */
    private function overridden(Protection $protection, string|bool|array|null $value, array $overrides): ?string
    {
        foreach ($overrides as $override) {
            $value = $override->apply($protection, $value);
        }

        $header = null === $value ? '' : $this->render($protection, $value);

        return '' === $header ? null : $header;
    }

    /**
     * @param Value $value
     */
    private function render(Protection $protection, string|bool|array $value): string
    {
        return ProtectionSyntax::render($protection, $value, $this->reportGroup);
    }

    /**
     * The rule of the longest prefix of the path, on the boundary of a segment, or the configured value.
     */
    private function frameOption(string $path): ?string
    {
        foreach ($this->framePaths as $prefix => $value) {
            if ($path === $prefix || str_starts_with($path, rtrim($prefix, '/').'/')) {
                return $value;
            }
        }

        return $this->headers[Protection::FrameOptions->value] ?? null;
    }
}
