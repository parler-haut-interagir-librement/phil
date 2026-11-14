<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Infrastructure\DependencyInjection;

use LogicException;
use Override;
use Phil\OpenBadgesBundle\Infrastructure\Http\VerificationRateLimit;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Stops the build when the limiter the verification page counts against is not one `phil/rate-limit-bundle` knows:
 * every limiter of that bundle, its own or another package's, is a service tagged `phil_rate_limit.limiter` with its
 * name.
 */
final class VerificationRateLimitPass implements CompilerPassInterface
{
    public const string LIMITER_TAG = 'phil_rate_limit.limiter';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(VerificationRateLimit::class)) {
            return;
        }

        $limiter = $container->getDefinition(VerificationRateLimit::class)->getArgument(0);
        $names = [];

        foreach ($container->findTaggedServiceIds(self::LIMITER_TAG) as $tags) {
            foreach ($tags as $tag) {
                $names[] = \is_array($tag) && \is_string($tag['name'] ?? null) ? $tag['name'] : '';
            }
        }

        if (!\in_array($limiter, $names, true)) {
            $declared = array_filter($names, static fn (string $name): bool => '' !== $name);
            sort($declared);

            throw new LogicException(\sprintf('phil_open_badges.verification.rate_limit names the limiter "%s", which phil/rate-limit-bundle does not declare; it declares %s.', \is_string($limiter) ? $limiter : '', [] === $declared ? 'none' : '"'.implode('", "', $declared).'"'));
        }
    }
}
