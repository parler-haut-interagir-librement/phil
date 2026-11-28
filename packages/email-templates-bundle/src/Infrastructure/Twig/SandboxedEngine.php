<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Twig;

use Override;
use Phil\EmailTemplatesBundle\Application\Contract\MarkdownRenderer;
use Phil\EmailTemplatesBundle\Application\Contract\TemplateEngine;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderContext;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderedParts;
use Phil\EmailTemplatesBundle\Application\Rendering\Theme;
use Phil\EmailTemplatesBundle\Domain\Exception\RenderingFailed;
use Phil\EmailTemplatesBundle\Domain\Schema\VariableSchema;
use Phil\EmailTemplatesBundle\Domain\Status\BodyFormat;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Extension\AttributeExtension;
use Twig\Extension\ExtensionInterface;
use Twig\Extra\Intl\IntlExtension;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\Sandbox\Sandbox;
use Twig\Sandbox\SecurityPolicy;
use Twig\Source;

/**
 * Renders the sources of a template with a Twig environment of its own, sandboxed in strict mode by `SandboxRules`: the
 * application's own Twig, its globals, its extensions and its templates are out of reach. Variables are strict — a
 * name the context lacks is an error —, a body is escaped as HTML, a subject, a preheader and a plain-text part are
 * not escaped and are rendered as text. A Markdown body is turned into HTML once its variables are in, its components
 * kept aside meanwhile.
 *
 * The compiled templates are cached under `cacheDirectory` when one is given, named by the hash of their source. The
 * environment holds nothing of a render; `reset()` builds it again, between two requests of a worker.
 */
final class SandboxedEngine implements TemplateEngine, ResetInterface
{
    /** The names a template reads without declaring them. */
    private const array BUILT_IN = ['email', 'loop', '_self', '_context', '_charset', RenderStash::CONTEXT_NAME];

    private ?Environment $environment = null;

    private ?Sandbox $sandbox = null;

    private ?SecurityPolicy $policy = null;

    private ?SourceLoader $loader = null;

    private ?LimitsVisitor $limits = null;

    /**
     * @param iterable<ExtensionInterface> $extensions extensions the application gives the sandbox, whose filters and
     *                                                 functions are allowed when listed in `$filters` and `$functions`
     * @param list<string>                 $filters
     * @param list<string>                 $functions
     * @param int<1, max>                  $maxBytes   the largest body rendered, in bytes
     */
    public function __construct(
        private readonly Theme $theme,
        private readonly ?MarkdownRenderer $markdown = null,
        private readonly string|false $cacheDirectory = false,
        private readonly iterable $extensions = [],
        private readonly array $filters = [],
        private readonly array $functions = [],
        private readonly int $maxBytes = 524_288,
    ) {
    }

    #[Override]
    public function render(TemplateDefinition $definition, array $variables, RenderContext $context): RenderedParts
    {
        $stash = new RenderStash($context, $definition, $this->markdown);
        $values = $variables;
        $values['email'] = $context->toArray();
        $values[RenderStash::CONTEXT_NAME] = $stash;

        $subject = $this->line($this->source('subject', $definition->subject, false, RenderMode::Text, $stash, $values));
        $preheader = $this->line($this->source('preheader', $definition->preheader, false, RenderMode::Text, $stash, $values));
        $markdown = BodyFormat::Markdown === $definition->format;
        $html = $this->source('body', $definition->body, true, $markdown ? RenderMode::Markdown : RenderMode::Html, $stash, $values);

        if ($markdown) {
            $html = $stash->restore($this->markdown?->toHtml($html) ?? $html);
        }

        $text = null === $definition->text ? null : trim($this->source('text', $definition->text, false, RenderMode::Text, $stash, $values))."\n";

        return new RenderedParts($subject, $preheader, $html, $text);
    }

    #[Override]
    public function check(TemplateDefinition $definition): array
    {
        $errors = [];
        $environment = $this->environment();
        $limits = $this->limits ?? throw RenderingFailed::because('The sandbox is not built.');
        $read = [];

        $parts = ['subject' => [$definition->subject, false], 'preheader' => [$definition->preheader, false], 'body' => [$definition->body, true], 'text' => [$definition->text, false]];

        foreach (array_filter($parts, static fn (array $part): bool => null !== $part[0]) as $field => [$source, $html]) {
            try {
                $name = $this->loader()->add($source, $html);
                $environment->parse($environment->tokenize(new Source($source, $name)));
                $read = [...$read, ...$limits->lastNames()];
                $calls = $limits->lastCalls();
                ($this->policy ?? SandboxRules::policy())->checkSecurity($calls['tags'], $calls['filters'], $calls['functions'], $calls['tests']);
                $environment->load($name);
            } catch (Error $error) {
                $errors[] = $this->describe($field, $error);
            }
        }

        $undeclared = array_diff(array_unique($read), self::BUILT_IN, $this->declared($definition->schema));
        sort($undeclared, \SORT_STRING);

        foreach ($undeclared as $name) {
            $errors[] = \sprintf('The template reads "%s", which its schema does not declare.', $name);
        }

        return $errors;
    }

    #[Override]
    public function reset(): void
    {
        $this->environment = null;
        $this->sandbox = null;
        $this->policy = null;
        $this->loader = null;
        $this->limits = null;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function source(string $field, string $source, bool $html, RenderMode $mode, RenderStash $stash, array $values): string
    {
        if ('' === $source) {
            return '';
        }

        $this->environment();
        $sandbox = $this->sandbox ?? throw RenderingFailed::because('The sandbox is not built.');
        $stash->mode = $mode;

        try {
            $output = $sandbox->render($this->loader()->add($source, $html), $values);
        } catch (Error $error) {
            throw RenderingFailed::because($this->describe($field, $error), $error);
        }

        if (\strlen($output) > $this->maxBytes) {
            throw RenderingFailed::because(\sprintf('The %s renders more than %d KiB.', $field, intdiv($this->maxBytes, 1024)));
        }

        return $output;
    }

    private function environment(): Environment
    {
        if ($this->environment instanceof Environment) {
            return $this->environment;
        }

        $this->loader = new SourceLoader();
        $this->limits = new LimitsVisitor();
        $environment = new Environment($this->loader, [
            'cache' => $this->cacheDirectory,
            'strict_variables' => true,
            'autoescape' => static fn (string $name): string|false => str_ends_with($name, '.html') ? 'html' : false,
            'auto_reload' => false,
            'debug' => false,
        ]);
        $theme = $this->theme;
        $environment->addExtension(new AttributeExtension(EmailComponents::class));
        $environment->addRuntimeLoader(new FactoryRuntimeLoader([EmailComponents::class => static fn (): EmailComponents => new EmailComponents($theme)]));
        $environment->addNodeVisitor($this->limits);

        if (class_exists(IntlExtension::class)) {
            $environment->addExtension(new IntlExtension());
        }

        foreach ($this->extensions as $extension) {
            $environment->addExtension($extension);
        }

        $this->policy = SandboxRules::policy($this->filters, $this->functions);
        $this->sandbox = new Sandbox($environment, $this->policy);

        return $this->environment = $environment;
    }

    private function loader(): SourceLoader
    {
        return $this->loader ?? new SourceLoader();
    }

    /**
     * The names a schema declares at its top level.
     *
     * @return list<string>
     */
    private function declared(VariableSchema $schema): array
    {
        return $schema->names();
    }

    private function line(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function describe(string $field, Error $error): string
    {
        $line = $error->getTemplateLine();

        return \sprintf('The %s%s: %s', $field, $line > 0 ? ' (line '.$line.')' : '', rtrim($error->getRawMessage(), '.').'.');
    }
}
