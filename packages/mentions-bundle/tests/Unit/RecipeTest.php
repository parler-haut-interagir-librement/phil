<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit;

use Phil\MentionsBundle\Application\Contract\MemberDirectory;
use Phil\MentionsBundle\PhilMentionsBundle;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The Flex recipe: it registers the bundle, and names a directory of its own that the application can boot with.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndCopiesItsFiles(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame([
            'bundles' => [PhilMentionsBundle::class => ['all']],
            'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/', 'src/' => '%SRC_DIR%/'],
        ], $manifest);
        self::assertStringContainsString("phil_mentions:\n    member_directory: App\\Mentions\\Members\n", (string) file_get_contents(self::RECIPE.'/config/packages/phil_mentions.yaml'));
    }

    public function testTheDirectoryOfTheRecipeFindsNobody(): void
    {
        // The class lives in the application once copied; here it is loaded from the recipe by its file.
        require_once self::RECIPE.'/src/Mentions/Members.php';
        $members = $this->instantiate('App\\Mentions\\Members');
        $ada = ResourceReference::of('user', 'ada');

        self::assertInstanceOf(MemberDirectory::class, $members);
        self::assertSame([[], [], [], false], [$members->resolve(['ada']), $members->members([$ada]), $members->search('', 10), $members->maySee($ada, ResourceReference::of('page', '1'))]);
    }

    /**
     * An object of a class the bundle does not know, so that static analysis reads it through the interface checked.
     */
    private function instantiate(string $class): object
    {
        return new $class();
    }
}
