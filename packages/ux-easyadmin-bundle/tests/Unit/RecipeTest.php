<?php

declare(strict_types=1);

namespace Phil\UxEasyAdminBundle\Tests\Unit;

use Phil\UxEasyAdminBundle\PhilUxEasyAdminBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The Flex recipe: it registers the bundle in every environment and writes its configuration, the theme on.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndCopiesItsConfiguration(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame([
            'bundles' => [PhilUxEasyAdminBundle::class => ['all']],
            'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/'],
        ], $manifest);
        self::assertSame(['phil_ux_easy_admin.yaml'], array_values(array_diff((array) scandir(self::RECIPE.'/config/packages'), ['.', '..'])));
        self::assertStringEndsWith("\nphil_ux_easy_admin:\n    theme: true\n", (string) file_get_contents(self::RECIPE.'/config/packages/phil_ux_easy_admin.yaml'));
        self::assertStringContainsString(PhilUxEasyAdminBundle::STYLESHEET, (string) file_get_contents(self::RECIPE.'/config/packages/phil_ux_easy_admin.yaml'));
    }
}
