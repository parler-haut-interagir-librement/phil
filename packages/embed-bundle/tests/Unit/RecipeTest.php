<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Tests\Unit;

use Phil\EmbedBundle\PhilEmbedBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle, names a key read from the environment, left empty, and imports the script's
 * route.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndCopiesItsConfiguration(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame([
            'bundles' => [PhilEmbedBundle::class => ['all']],
            'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/'],
            'env' => [
                '#1' => "At least 32 random bytes, hex-encoded, in .env.local or the secrets vault: php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'",
                'EMBED_KEY' => '',
            ],
        ], $manifest, 'The recipe writes no secret anyone could read in it.');
        self::assertSame(['phil_embed' => ['keys' => [['id' => '1', 'secret' => '%env(EMBED_KEY)%']], 'embeds' => []]], Yaml::parseFile(self::RECIPE.'/config/packages/phil_embed.yaml'));
        self::assertSame(['phil_embed' => ['resource' => '@PhilEmbedBundle/config/routes.php', 'prefix' => '/_embed']], Yaml::parseFile(self::RECIPE.'/config/routes/phil_embed.yaml'));
    }
}
