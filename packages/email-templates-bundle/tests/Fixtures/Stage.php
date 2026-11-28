<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Fixtures;

use Phil\EmailTemplatesBundle\Application\Contract\EmbedLoader;
use Phil\EmailTemplatesBundle\Application\Contract\StyleInliner;
use Phil\EmailTemplatesBundle\Application\Contract\TemplateStore;
use Phil\EmailTemplatesBundle\Application\EmailTemplates;
use Phil\EmailTemplatesBundle\Application\FixedSiteScope;
use Phil\EmailTemplatesBundle\Application\Preview\SamplePreviews;
use Phil\EmailTemplatesBundle\Application\Rendering\EmailRenderer;
use Phil\EmailTemplatesBundle\Application\Rendering\Theme;
use Phil\EmailTemplatesBundle\Application\TemplateResolver;
use Phil\EmailTemplatesBundle\Application\TemplateValidator;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateVersion;
use Phil\EmailTemplatesBundle\Infrastructure\Filesystem\DirectoryEmbedLoader;
use Phil\EmailTemplatesBundle\Infrastructure\Markup\MarkupMarkdown;
use Phil\EmailTemplatesBundle\Infrastructure\Memory\InMemoryTemplateStore;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\CssInlinerStyles;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\SandboxedEngine;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\TwigLayout;
use Phil\EmailTemplatesBundle\Infrastructure\Yaml\YamlDefaultTemplates;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Markup;
use Symfony\Component\Clock\MockClock;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * The whole bundle without a container: the store in memory unless one is given, the default files of the fixtures
 * before the bundle's, the sandboxed engine with Markdown, the house layout, the inliner, the images of the fixtures,
 * a clock at a fixed moment and a dispatcher that records.
 */
final class Stage
{
    public const string DEFAULTS = __DIR__.'/defaults';

    public const string IMAGES = __DIR__.'/images';

    public const string TEMPLATES = __DIR__.'/../../templates';

    public readonly Theme $theme;

    public readonly TemplateStore $store;

    public readonly YamlDefaultTemplates $defaults;

    public readonly TemplateResolver $resolver;

    public readonly SandboxedEngine $engine;

    public readonly EmailRenderer $renderer;

    public readonly TemplateValidator $validator;

    public readonly EmailTemplates $templates;

    public readonly SamplePreviews $previews;

    public readonly MockClock $clock;

    public readonly RecordingDispatcher $events;

    public function __construct(?TemplateStore $store = null, ?Site $site = null, ?StyleInliner $inliner = null, ?EmbedLoader $embeds = null, int $maxHtmlBytes = 1_048_576)
    {
        $this->theme = Theme::create();
        $this->clock = new MockClock('2026-11-18 10:00:00 UTC');
        $this->store = $store ?? new InMemoryTemplateStore($this->clock);
        $this->defaults = new YamlDefaultTemplates([self::DEFAULTS, __DIR__.'/../../defaults']);
        $this->resolver = new TemplateResolver($this->store, $this->defaults, Locale::fromString('en'));
        $this->engine = new SandboxedEngine($this->theme, self::markdown());
        $this->renderer = new EmailRenderer($this->resolver, $this->engine, self::layout($this->theme), $inliner ?? new CssInlinerStyles(), new FixedSiteScope($site ?? Site::fromString('main')), $embeds ?? new DirectoryEmbedLoader(self::IMAGES), $maxHtmlBytes);
        $this->validator = new TemplateValidator($this->engine, $this->renderer);
        $this->events = new RecordingDispatcher();
        $this->templates = new EmailTemplates($this->store, $this->resolver, $this->validator, $this->defaults, new FixedSiteScope($site ?? Site::fromString('main')), $this->clock, $this->events);
        $this->previews = new SamplePreviews($this->resolver, $this->renderer);
    }

    /**
     * Saves the draft over its latest revision and publishes it: what the sites send from then on.
     */
    public function publish(string $key, string $locale, TemplateDefinition $definition, ?Site $site = null, ?string $author = null): TemplateVersion
    {
        $this->templates->save($key, $locale, $definition, $this->templates->draft($key, $locale, $site)->revision ?? 0, $site, $author);

        return $this->templates->publish($key, $locale, $site, $author);
    }

    public static function markdown(): MarkupMarkdown
    {
        return new MarkupMarkdown(new Markup(new DefaultCompiler(), SanitizerPurifier::defaultPolicies()));
    }

    public static function layout(Theme $theme, string $template = '@PhilEmailTemplates/email/layout.html.twig', array $brand = ['name' => null, 'logo' => null]): TwigLayout
    {
        $loader = new FilesystemLoader();
        $loader->addPath(self::TEMPLATES, 'PhilEmailTemplates');
        $loader->addPath(__DIR__.'/layouts', 'Fixtures');

        return new TwigLayout(new Environment($loader, ['strict_variables' => true]), $theme, $template, $brand);
    }
}
