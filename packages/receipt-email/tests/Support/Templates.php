<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Support;

use Phil\EmailTemplatesBundle\Application\Contract\EmbedLoader;
use Phil\EmailTemplatesBundle\Application\FixedSiteScope;
use Phil\EmailTemplatesBundle\Application\Rendering\EmailRenderer;
use Phil\EmailTemplatesBundle\Application\Rendering\Theme;
use Phil\EmailTemplatesBundle\Application\TemplateResolver;
use Phil\EmailTemplatesBundle\Application\TemplateValidator;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Infrastructure\Memory\InMemoryTemplateStore;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\NoStyleInliner;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\SandboxedEngine;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\TwigLayout;
use Phil\EmailTemplatesBundle\Infrastructure\Yaml\YamlDefaultTemplates;
use Phil\EmailTemplatesBundle\PhilEmailTemplatesBundle;
use Phil\ReceiptEmail\Infrastructure\EmailTemplates\EmailTemplatesRenderer;
use ReflectionClass;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * The renderer of `phil/email-templates-bundle`, wired by hand on the package's default templates.
 */
final class Templates
{
    public static function renderer(?SandboxedEngine $engine = null, ?InMemoryTemplateStore $store = null, ?EmbedLoader $embeds = null): EmailRenderer
    {
        $theme = Theme::create();
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname((string) new ReflectionClass(PhilEmailTemplatesBundle::class)->getFileName(), 2).'/templates', 'PhilEmailTemplates');

        $resolver = new TemplateResolver($store ?? new InMemoryTemplateStore(), new YamlDefaultTemplates([EmailTemplatesRenderer::DEFAULTS]), Locale::fromString('en'));

        return new EmailRenderer(
            $resolver,
            $engine ?? new SandboxedEngine($theme),
            new TwigLayout(new Environment($loader, ['strict_variables' => true]), $theme, brand: ['name' => 'The Shop', 'logo' => null]),
            new NoStyleInliner(),
            new FixedSiteScope(Site::fromString('main')),
            $embeds,
        );
    }

    /**
     * What a site's save and publication check: the sources compile, read only what the schema declares, and render.
     */
    public static function validator(): TemplateValidator
    {
        $engine = new SandboxedEngine(Theme::create());

        return new TemplateValidator($engine, self::renderer($engine));
    }

    public static function defaults(): YamlDefaultTemplates
    {
        return new YamlDefaultTemplates([EmailTemplatesRenderer::DEFAULTS]);
    }
}
