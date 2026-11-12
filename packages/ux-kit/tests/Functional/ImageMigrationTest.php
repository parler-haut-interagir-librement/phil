<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use Phil\UxKit\Application\ImageMigration;
use Phil\UxKit\Infrastructure\Console\MigrateImagesCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;

/**
 * The proposals of the migration, rendered by the recipes: each writes the image the element wrote, under the
 * recipe's own attributes, and the command is there in an application that has the Console component.
 */
#[CoversClass(ImageMigration::class)]
#[CoversClass(MigrateImagesCommand::class)]
final class ImageMigrationTest extends KitTestCase
{
    public function testTheProposalsRenderTheImagesTheElementsWrote(): void
    {
        $template = <<<'TWIG'
            <header><img class="site-logo" src="/images/logo.svg" alt="" width="658" height="214"></header>
            <img src="{{ base ~ 'qr.svg' }}" alt="The code of the address" width="64" height="64" loading="lazy" decoding="async">
            <img width="42px" height="42px" alt="Logo" src='{{ base ~ "logo.svg" }}'>
            <picture>
                <source type="image/avif" srcset="{{ base ~ '1x/logo.avif' }}, {{ base ~ '2x/logo.avif' }} 2x">
                <source type="image/webp" srcset="{{ base ~ 'main.webp' }}">
                <img src="{{ base ~ 'main.png' }}" alt="The hall" width="2906" height="1317" decoding="async">
            </picture>
            TWIG;
        $migration = new ImageMigration();
        $migrated = $migration->apply($template, $migration->find($template));

        self::assertStringNotContainsString('<img', $migrated);
        self::assertSame(
            '<header><img class="isImage site-logo" src="/images/logo.svg" alt="" width="658" height="214" loading="lazy" decoding="async"></header>'
            .'<img class="isImage" src="/images/qr.svg" alt="The code of the address" width="64" height="64" loading="lazy" decoding="async">'
            .'<img class="isImage" src="/images/logo.svg" alt="Logo" width="42" height="42" loading="lazy" decoding="async">'
            .'<picture><source type="image/avif" srcset="/images/1x/logo.avif, /images/2x/logo.avif 2x"><source type="image/webp" srcset="/images/main.webp">'
            .'<img class="isImage" src="/images/main.png" alt="The hall" width="2906" height="1317" loading="lazy" decoding="async"></picture>',
            $this->render($migrated, ['base' => '/images/']),
        );
    }

    public function testTheCommandIsRegistered(): void
    {
        $loader = self::getContainer()->get('console.command_loader');
        self::assertInstanceOf(CommandLoaderInterface::class, $loader);

        $command = $loader->get('phil:ux-kit:migrate-images');

        self::assertInstanceOf(MigrateImagesCommand::class, $command instanceof LazyCommand ? $command->getCommand() : $command);
    }
}
