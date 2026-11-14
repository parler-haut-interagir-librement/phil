<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\SettingsBundle\Application\Migration\SettingMigrator;
use Phil\SettingsBundle\Domain\Exception\InvalidSetting;
use Phil\SettingsBundle\Domain\Setting\Ordering;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Gives the migrator the migrations tagged `phil_settings.migration` — by `#[AsSettingMigration]`, or by hand with
 * `version`, `before` and `after` — by version, and the order they run in, so that a version malformed or declared
 * twice, or versions asking to run before one another in a circle, stop the container with their reason.
 */
final class SettingMigrationsPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(SettingMigrator::class)) {
            return;
        }

        $migrations = [];
        $order = [];

        /** @var array<string, list<array<string, mixed>>> $tagged */
        $tagged = $container->findTaggedServiceIds(SettingsServices::MIGRATION_TAG);

        foreach ($tagged as $id => $tags) {
            foreach ($tags as $tag) {
                $version = $tag['version'] ?? null;

                if (!\is_string($version) || 1 !== preg_match(SettingMigrator::VERSION, $version)) {
                    throw InvalidSetting::because(\sprintf('The migration "%s" has no version of letters, digits, ".", "_" and "-", of at most 190 characters.', $id));
                }

                if (isset($migrations[$version])) {
                    throw InvalidSetting::because(\sprintf('The version "%s" is given to two migrations.', $version));
                }

                $migrations[$version] = new Reference($id);
                $order[$version] = ['before' => $this->versions($tag['before'] ?? []), 'after' => $this->versions($tag['after'] ?? [])];
            }
        }

        $container->getDefinition(SettingMigrator::class)
            ->setArgument(2, ServiceLocatorTagPass::register($container, $migrations))
            ->setArgument(3, Ordering::sort($order));
    }

    /**
     * @return list<string>
     */
    private function versions(mixed $versions): array
    {
        return \is_array($versions) ? array_values(array_filter($versions, \is_string(...))) : [];
    }
}
