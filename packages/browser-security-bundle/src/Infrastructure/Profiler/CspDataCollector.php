<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Infrastructure\Profiler;

use Override;
use Phil\BrowserSecurityBundle\Application\RequestState;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Throwable;

/**
 * The policy of a response in the Symfony profiler: the named policy, the headers written, the number of overrides and
 * the kinds of nonce the templates asked for.
 */
final class CspDataCollector extends DataCollector
{
    public const string TEMPLATE = '@PhilBrowserSecurity/profiler/collector.html.twig';

    #[Override]
    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $state = $request->attributes->get(RequestState::ATTRIBUTE);
        $state = $state instanceof RequestState ? $state : new RequestState();

        $this->data = [
            'policy' => $state->policyName,
            'headers' => $state->headers,
            'overrides' => \count($state->overrides),
            'nonces' => array_keys($state->nonces()),
        ];
    }

    #[Override]
    public function reset(): void
    {
        $this->data = [];
    }

    #[Override]
    public function getName(): string
    {
        return 'phil_browser_security';
    }

    public function policy(): ?string
    {
        /** @var string|null $policy */
        $policy = $this->data['policy'] ?? null;

        return $policy;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        /** @var array<string, string> $headers the headers `collect()` wrote */
        $headers = $this->data['headers'] ?? [];

        return $headers;
    }

    public function overrides(): int
    {
        /** @var int $overrides */
        $overrides = $this->data['overrides'] ?? 0;

        return $overrides;
    }

    /**
     * @return list<string>
     */
    public function nonces(): array
    {
        /** @var list<string> $nonces the kinds `collect()` wrote */
        $nonces = $this->data['nonces'] ?? [];

        return $nonces;
    }
}
