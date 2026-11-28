<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit\Infrastructure;

use FilesystemIterator;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderContext;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderedParts;
use Phil\EmailTemplatesBundle\Application\Rendering\Theme;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\RenderingFailed;
use Phil\EmailTemplatesBundle\Domain\Schema\VariableSchema;
use Phil\EmailTemplatesBundle\Domain\Status\BodyFormat;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Phil\EmailTemplatesBundle\Infrastructure\Filesystem\DirectoryEmbedLoader;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\CssInlinerStyles;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\EmailComponents;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\LimitsVisitor;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\RenderMode;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\RenderStash;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\SandboxedEngine;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\SandboxRules;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\SourceLoader;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\TwigLayout;
use Phil\EmailTemplatesBundle\Infrastructure\Yaml\YamlDefaultTemplates;
use Phil\EmailTemplatesBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use ReflectionProperty;
use SplFileInfo;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Extension\AbstractExtension;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;
use Twig\Source;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * The limits of the sandbox and of the readers of files, each to the byte, and what each step of a check leaves behind.
 */
#[CoversClass(SandboxedEngine::class)]
#[CoversClass(SandboxRules::class)]
#[CoversClass(LimitsVisitor::class)]
#[CoversClass(SourceLoader::class)]
#[CoversClass(RenderStash::class)]
#[CoversClass(RenderMode::class)]
#[CoversClass(EmailComponents::class)]
#[CoversClass(DirectoryEmbedLoader::class)]
#[CoversClass(YamlDefaultTemplates::class)]
#[CoversClass(TwigLayout::class)]
#[CoversClass(CssInlinerStyles::class)]
final class InfrastructureBoundariesTest extends TestCase
{
    /** @var list<string> */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
                if (!$entry instanceof SplFileInfo) {
                    continue;
                }

                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }

            rmdir($directory);
        }
    }

    public function testTheBodyMayBeAsLargeAsTheDefaultCeilingAndNoLarger(): void
    {
        $engine = new SandboxedEngine(Theme::create());
        $definition = $this->definition('Hi', '{{ big }}');

        self::assertSame(524_288, \strlen($engine->render($definition, ['big' => str_repeat('a', 524_288)], $this->context())->html));

        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessageIsOrContains('The body renders more than 512 KiB.');
        $engine->render($definition, ['big' => str_repeat('a', 524_289)], $this->context());
    }

    public function testTheCeilingIsInBytesAndItsMessageInKibibytes(): void
    {
        $limit = 1023 * 1024;
        $engine = new SandboxedEngine(Theme::create(), null, false, [], [], [], $limit);

        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessageIsOrContains('The body renders more than 1023 KiB.');
        $engine->render($this->definition('Hi', '{{ big }}'), ['big' => str_repeat('a', $limit + 1)], $this->context());
    }

    public function testACheckAllowsWhatTheApplicationAddedToTheSandbox(): void
    {
        $extension = new class extends AbstractExtension {
            public function getFilters(): array
            {
                return [new TwigFilter('shout', static fn (string $text): string => $text.'!')];
            }
        };
        $engine = new SandboxedEngine(Theme::create(), null, false, [$extension], ['shout']);

        self::assertSame([], $engine->check($this->definition('Hi', '{{ "hey"|shout }}')));
    }

    public function testAVariableReadInTwoPartsIsReportedOnce(): void
    {
        $engine = new SandboxedEngine(Theme::create());

        self::assertSame(['The template reads "who", which its schema does not declare.'], $engine->check($this->definition('Hi {{ who }}', '<p>{{ who }}</p>')));
    }

    public function testASubjectThatDoesNotCompileIsReported(): void
    {
        $engine = new SandboxedEngine(Theme::create());
        $errors = $engine->check($this->definition('Hi {{', '<p>x</p>'));

        self::assertCount(1, $errors);
        self::assertStringStartsWith('The subject (line 1): ', $errors[0]);
    }

    public function testACheckCompilesEveryPartForTheRenderThatFollows(): void
    {
        $directory = $this->directory();
        $engine = new SandboxedEngine(Theme::create(), null, $directory);
        $unique = bin2hex(random_bytes(8));
        $definition = $this->definition('Subject '.$unique, '<p>body '.$unique.'</p>', preheader: 'Preheader '.$unique, text: 'Text '.$unique);

        self::assertSame([], $engine->check($definition));
        $compiled = $this->compiled($directory);
        self::assertCount(4, $compiled);

        $engine->render($definition, [], $this->context());
        self::assertSame($compiled, $this->compiled($directory), 'The render finds each part compiled as the check left it.');
    }

    public function testAnEmptySourceIsNotGivenToTheLoader(): void
    {
        $engine = new SandboxedEngine(Theme::create());
        $engine->render($this->definition('Subject', '<p>body</p>'), [], $this->context());

        $loader = new ReflectionProperty($engine, 'loader')->getValue($engine);

        self::assertInstanceOf(SourceLoader::class, $loader);
        self::assertFalse($loader->exists(hash('sha256', '').'.txt'));
        self::assertTrue($loader->exists(hash('sha256', 'Subject').'.txt'));
    }

    public function testASubjectIsOneLineWithoutItsBlanksAround(): void
    {
        $engine = new SandboxedEngine(Theme::create());

        self::assertSame('Ada Lovelace', $engine->render($this->definition('{{ name }}', '<p>x</p>'), ['name' => "  Ada \n  Lovelace "], $this->context())->subject);
    }

    public function testAnErrorWithoutALineIsDescribedWithoutIt(): void
    {
        $extension = new class extends AbstractExtension {
            public function getFunctions(): array
            {
                return [new TwigFunction('boom', static function (): never {
                    throw new Error('Boom.', 0);
                })];
            }
        };
        $engine = new SandboxedEngine(Theme::create(), null, false, [$extension], [], ['boom']);

        try {
            $engine->render($this->definition('Hi', '{{ boom() }}'), [], $this->context());
            self::fail('The render fails.');
        } catch (RenderingFailed $renderingFailed) {
            self::assertSame('The body: Boom.', $renderingFailed->getMessage());
        }
    }

    public function testTheEnvironmentNeitherDebugsNorReloads(): void
    {
        $engine = new SandboxedEngine(Theme::create());
        $environment = new ReflectionMethod($engine, 'environment')->invoke($engine);

        self::assertInstanceOf(Environment::class, $environment);
        self::assertFalse($environment->isDebug());
        self::assertFalse($environment->isAutoReload());
        self::assertTrue($environment->isStrictVariables());
        self::assertSame($environment, new ReflectionMethod($engine, 'environment')->invoke($engine), 'The environment is built once.');
    }

    public function testTheLoaderForgetsTheOldestSourceAtItsCapacity(): void
    {
        $loader = new SourceLoader();
        $first = $loader->add('source 0', true);

        for ($index = 1; $index < 512; ++$index) {
            $loader->add('source '.$index, true);
        }

        self::assertTrue($loader->exists($first), 'A loader keeps 512 sources.');
        $loader->add('source 512', true);
        self::assertFalse($loader->exists($first));
        self::assertTrue($loader->exists($loader->add('source 1', true)));
    }

    public function testAPlaceholderIsPutBackWhateverItsLeadingZeros(): void
    {
        $stash = new RenderStash($this->context(), $this->definition('Hi', 'x'), Stage::markdown());
        $stash->mode = RenderMode::Markdown;

        $placeholder = $stash->keep('<b>kept</b>', false);

        self::assertSame('<b>kept</b>', $stash->restore($placeholder));
        self::assertSame('<b>kept</b>', $stash->restore(str_replace('o0z', 'o00z', $placeholder)));
    }

    public function testTheLoopsMayNestTwoDeepAndFollowEachOther(): void
    {
        $engine = new SandboxedEngine(Theme::create());
        $schema = ['x' => ['type' => 'list', 'items' => ['type' => 'list', 'items' => ['type' => 'string']]]];
        $nested = '{% for a in x %}{% for b in a %}{{ b }}{% endfor %}{% endfor %}';
        $sequence = str_repeat('{% for a in x %}{{ loop.index }}{% endfor %}', 3);

        self::assertSame([], $engine->check($this->definition('Hi', $nested, schema: $schema)));
        self::assertSame([], $engine->check($this->definition('Hi', $sequence, schema: $schema)));
        self::assertStringContainsString('Loops nest at most 2 deep.', $engine->check($this->definition('Hi', '{% for a in x %}{% for b in a %}{% for c in b %}{{ c }}{% endfor %}{% endfor %}{% endfor %}', schema: $schema))[0]);
    }

    public function testTheVisitorRunsFirstAndListsTheNamesSorted(): void
    {
        $visitor = new LimitsVisitor();
        $environment = new Environment(new ArrayLoader(['t' => '{{ zeta }}{{ alpha }}{{ mid }}']));
        $environment->addNodeVisitor($visitor);
        $environment->parse($environment->tokenize(new Source('{{ zeta }}{{ alpha }}{{ mid }}', 't')));

        self::assertSame(0, $visitor->getPriority());
        self::assertSame(['alpha', 'mid', 'zeta'], $visitor->lastNames());
    }

    public function testATableOfTextIsOneRowPerLineWithItsHeader(): void
    {
        $engine = new SandboxedEngine(Theme::create());
        $parts = $engine->render($this->definition('Hi', '<p>x</p>', text: "{{ table([['1', '2'], ['3', '4']], {header: ['A', 'B']}) }}"), [], $this->context());

        self::assertSame("A | B\n1 | 2\n3 | 4\n", $parts->text);
    }

    public function testAnAddressWithAMailtoLookalikeSchemeIsRefused(): void
    {
        $engine = new SandboxedEngine(Theme::create());

        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessageIsOrContains('An address is an absolute http or https address.');
        $engine->render($this->definition('Hi', "{{ button('Go', 'mailto;ada@example.com') }}"), [], $this->context());
    }

    public function testAMailtoAddressIsReadWithoutItsBlanksAround(): void
    {
        $engine = new SandboxedEngine(Theme::create());
        $html = $engine->render($this->definition('Hi', "{{ button('Write', '  mailto:ada@example.com  ') }}"), [], $this->context())->html;

        self::assertStringContainsString('href="mailto:ada@example.com"', $html);
    }

    public function testAnAddressIsReadWithoutItsBlanksAround(): void
    {
        $engine = new SandboxedEngine(Theme::create());
        $html = $engine->render($this->definition('Hi', "{{ button('Go', '  https://example.com/a  ') }}"), [], $this->context())->html;

        self::assertStringContainsString('href="https://example.com/a"', $html);
    }

    public function testAnImageInAMarkdownBodyStaysInItsParagraph(): void
    {
        $engine = new SandboxedEngine(Theme::create(), Stage::markdown());
        $html = $engine->render($this->definition('Hi', "An {{ image('https://example.com/a.png', 'icon') }} inline.", BodyFormat::Markdown), [], $this->context())->html;

        self::assertStringContainsString('<p>An <img src="https://example.com/a.png" alt="icon"', $html);
        self::assertStringContainsString('inline.</p>', $html);
    }

    public function testTheInlinerBuildsItsEnvironmentOnce(): void
    {
        $inliner = new CssInlinerStyles();
        $environment = new ReflectionProperty($inliner, 'environment');

        self::assertNull($environment->getValue($inliner));
        $inliner->inline('<p>x</p>');
        $built = $this->peek($environment, $inliner);
        $inliner->inline('<p>y</p>');

        self::assertInstanceOf(Environment::class, $built);
        self::assertSame($built, $this->peek($environment, $inliner));
    }

    public function testTheInlinerLeavesTheHtmlItInlinesAsItIs(): void
    {
        $html = new CssInlinerStyles()->inline('<html><head><style>p{color:red}</style></head><body><p class="a">&lt; &amp; é</p></body></html>');

        self::assertStringContainsString('<p class="a" style="color: red;">&lt; &amp; é</p>', $html);
    }

    public function testTheLayoutWithoutABrandShowsNone(): void
    {
        $loader = new FilesystemLoader();
        $loader->addPath(Stage::TEMPLATES, 'PhilEmailTemplates');

        $layout = new TwigLayout(new Environment($loader, ['strict_variables' => true]), Theme::create());

        $html = $layout->wrap(new RenderedParts('Hi', 'Pre', '<p>Body</p>'), $this->context());

        self::assertStringContainsString('<p>Body</p>', $html);
        self::assertStringNotContainsString('phil-email-brand" style', $html);
    }

    public function testAnImageMayBeExactlyAsLargeAsTheDefaultCeiling(): void
    {
        $directory = $this->directory();
        file_put_contents($directory.'/exact.png', "\x89PNG\r\n\x1A\n".str_repeat('a', 1_048_576 - 8));
        file_put_contents($directory.'/over.png', "\x89PNG\r\n\x1A\n".str_repeat('a', 1_048_577 - 8));
        $loader = new DirectoryEmbedLoader($directory);

        self::assertSame(1_048_576, \strlen($loader->load('logo', 'exact.png')->body));

        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessageIsOrContains('The image "logo" is larger than 1024 KiB.');
        $loader->load('logo', 'over.png');
    }

    public function testTheLimitOfAnImageIsInBytesAndItsMessageInKibibytes(): void
    {
        $directory = $this->directory();
        $limit = 1023 * 1024;
        file_put_contents($directory.'/big.png', "\x89PNG\r\n\x1A\n".str_repeat('a', $limit + 1 - 8));

        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessageIsOrContains('The image "logo" is larger than 1023 KiB.');
        new DirectoryEmbedLoader($directory, $limit)->load('logo', 'big.png');
    }

    public function testAFileOfASiblingDirectoryWithTheSamePrefixIsNotInTheDirectory(): void
    {
        $base = $this->directory();
        mkdir($base.'/images');
        mkdir($base.'/images-secret');
        file_put_contents($base.'/images-secret/a.png', "\x89PNG\r\n\x1A\nx");

        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessageIsOrContains('The image "logo" is not a file of the images directory.');
        new DirectoryEmbedLoader($base.'/images')->load('logo', '../images-secret/a.png');
    }

    public function testADirectoryThatDoesNotExistHoldsNoImage(): void
    {
        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessageIsOrContains('The image "logo" is not a file of the images directory.');
        new DirectoryEmbedLoader($this->directory().'/missing')->load('logo', 'a.png');
    }

    public function testTheDefaultFilesAreReadOnceAndAMissingDirectoryIsSkipped(): void
    {
        $directory = $this->directory();
        file_put_contents($directory.'/first.en.yaml', "subject: Hi\nbody: '<p>x</p>'\n");
        $defaults = new YamlDefaultTemplates([$directory.'/missing', $directory]);

        self::assertSame(['first' => ['en']], $defaults->catalog());
        file_put_contents($directory.'/second.en.yaml', "subject: Hi\nbody: '<p>x</p>'\n");
        self::assertSame(['first' => ['en']], $defaults->catalog());
        self::assertInstanceOf(TemplateDefinition::class, $defaults->find(TemplateKey::fromString('first'), Locale::fromString('en')));
    }

    public function testAFileThatIsNotATemplateDoesNotStopTheReadOfTheOthers(): void
    {
        $directory = $this->directory();
        mkdir($directory.'/0-folder');

        foreach (['0-notes.txt', '1-readme.md', 'Upper.en.yaml', 'a.xx.yaml.bak'] as $name) {
            file_put_contents($directory.'/'.$name, 'Not a template.');
        }

        $expected = [];

        for ($index = 10; $index < 30; ++$index) {
            file_put_contents($directory.'/t'.$index.'.en.yaml', "subject: Hi\nbody: '<p>x</p>'\n");
            $expected['t'.$index] = ['en'];
        }

        self::assertSame($expected, new YamlDefaultTemplates([$directory])->catalog());
    }

    public function testADefaultFileMayBeExactlyAsLargeAsTheCeiling(): void
    {
        $directory = $this->directory();
        $content = "subject: Hi\nbody: '<p>x</p>'\n";
        file_put_contents($directory.'/exact.en.yaml', $content.'#'.str_repeat('a', 1_048_576 - \strlen($content) - 1));
        file_put_contents($directory.'/over.en.yaml', $content.'#'.str_repeat('a', 1_048_577 - \strlen($content) - 1));
        $defaults = new YamlDefaultTemplates([$directory]);

        self::assertSame('Hi', $defaults->find(TemplateKey::fromString('exact'), Locale::fromString('en'))?->subject);

        $this->expectException(InvalidTemplate::class);
        $this->expectExceptionMessageIsOrContains('The default template "over.en.yaml" is larger than 1024 KiB.');
        $defaults->find(TemplateKey::fromString('over'), Locale::fromString('en'));
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function definition(string $subject, string $body, BodyFormat $format = BodyFormat::Html, array $schema = [], string $preheader = '', ?string $text = null): TemplateDefinition
    {
        return TemplateDefinition::create($subject, $body, $format, $preheader, $text, VariableSchema::fromArray($schema));
    }

    private function context(): RenderContext
    {
        return new RenderContext(TemplateKey::fromString('greeting'), Site::fromString('main'), Locale::fromString('en'));
    }

    private function peek(ReflectionProperty $property, object $object): mixed
    {
        return $property->getValue($object);
    }

    private function directory(): string
    {
        $directory = sys_get_temp_dir().'/phil-email-boundaries-'.bin2hex(random_bytes(4));
        mkdir($directory);

        return $this->directories[] = $directory;
    }

    /**
     * @return list<string>
     */
    private function compiled(string $directory): array
    {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof SplFileInfo) {
                $files[] = substr($file->getPathname(), \strlen($directory));
            }
        }

        sort($files);

        return $files;
    }
}
