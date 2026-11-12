<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Markup;

use Override;
use Phil\ContentBundle\Application\Contract\RawHtmlPermission;
use Phil\ContentBundle\Application\DataMapper\BodyDataMapper;
use Phil\ContentBundle\Application\Editing\AuthorRawHtml;
use Phil\ContentBundle\Application\Editing\WorkingCopies;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Checks the raw HTML permission on the author of each revision, once the container has a `RawHtmlPermission` —
 * `RawHtmlRole` with `phil_content.raw_html_role`, or the application's own alias:
 *
 * - `AuthorRawHtml` wraps it, and is the permission `BodyDataMapper` records on each body and `WorkingCopies` on each
 *   working copy;
 * - `UjoBodyConverter` learns that raw HTML is configured, and publishes it for the bodies whose author held it.
 *
 * Without a `RawHtmlPermission`, nothing changes: nobody writes raw HTML.
 */
final class RawHtmlAuthorPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(RawHtmlPermission::class)) {
            return;
        }

        $container->register(AuthorRawHtml::class, AuthorRawHtml::class)
            ->setArguments([new Reference(RawHtmlPermission::class)]);

        foreach ([BodyDataMapper::class, WorkingCopies::class] as $service) {
            if ($container->hasDefinition($service)) {
                $container->getDefinition($service)->setArgument('$rawHtml', new Reference(AuthorRawHtml::class));
            }
        }

        if ($container->hasDefinition(UjoBodyConverter::class)) {
            $container->getDefinition(UjoBodyConverter::class)->setArgument('$rawHtml', true);
        }
    }
}
