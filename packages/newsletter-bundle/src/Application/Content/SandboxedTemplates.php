<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Content;

use Override;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Extension\AbstractExtension;
use Twig\Loader\ArrayLoader;
use Twig\Sandbox\Sandbox;
use Twig\Sandbox\SecurityPolicy;
use Twig\TemplateWrapper;

/**
 * Renders the templates administrators and members write, never with the application's Twig: in a `Twig\Sandbox\Sandbox`
 * on an environment of its own, whose strict security policy allows a short list of tags and filters, no function,
 * no method and no property. The range operator is refused when a template compiles, and the output is cut at a
 * megabyte, so that a template can neither reach the application nor make a worker loop.
 *
 * The context holds strings, numbers, lists and `Twig\Markup` — the body of a campaign, already purified. Compiled
 * templates are kept for the next message; past 64 sources, or on `reset()`, the environment is built again, so that a
 * long-running worker keeps a bounded memory.
 */
final class SandboxedTemplates implements ResetInterface
{
    public const array TAGS = ['apply', 'for', 'if', 'set', 'with'];

    public const array FILTERS = ['abs', 'capitalize', 'date', 'default', 'e', 'escape', 'first', 'join', 'last', 'length', 'lower', 'nl2br', 'number_format', 'replace', 'round', 'slice', 'striptags', 'title', 'trim', 'upper', 'url_encode'];

    public const int MAX_OUTPUT_BYTES = 1_048_576;

    private const int MAX_COMPILED = 64;

    private ?Sandbox $sandbox = null;

    /** @var array<string, TemplateWrapper> */
    private array $compiled = [];

    /**
     * @param array<string, mixed> $context
     *
     * @throws InvalidNewsletterValue when the template does not compile, breaks the policy, fails or writes too much
     */
    public function render(string $source, array $context): string
    {
        try {
            $output = $this->template($source)->render($context);
        } catch (Error $error) {
            throw InvalidNewsletterValue::because('The template cannot be rendered: '.$error->getRawMessage());
        }

        if (\strlen($output) > self::MAX_OUTPUT_BYTES) {
            throw InvalidNewsletterValue::because(\sprintf('A template writes at most %d bytes.', self::MAX_OUTPUT_BYTES));
        }

        return $output;
    }

    /**
     * Compiles a source to check it, as an editor saves it.
     *
     * @throws InvalidNewsletterValue when it does not compile or breaks the policy
     */
    public function check(string $source): void
    {
        try {
            $this->template($source);
        } catch (Error $error) {
            throw InvalidNewsletterValue::because('The template does not compile: '.$error->getRawMessage());
        }
    }

    #[Override]
    public function reset(): void
    {
        $this->sandbox = null;
        $this->compiled = [];
    }

    private function template(string $source): TemplateWrapper
    {
        $key = hash('xxh128', $source);

        if (isset($this->compiled[$key])) {
            return $this->compiled[$key];
        }

        if (\count($this->compiled) >= self::MAX_COMPILED) {
            $this->reset();
        }

        return $this->compiled[$key] = $this->sandbox()->createTemplate($source);
    }

    private function sandbox(): Sandbox
    {
        if ($this->sandbox instanceof Sandbox) {
            return $this->sandbox;
        }

        $environment = new Environment(new ArrayLoader(), ['autoescape' => 'html', 'cache' => false, 'strict_variables' => false]);
        $environment->addExtension(new class extends AbstractExtension {
            #[Override]
            public function getNodeVisitors(): array
            {
                return [new RangeRefusal()];
            }
        });
        $policy = new SecurityPolicy(self::TAGS, self::FILTERS);
        $policy->setStrict(true);

        return $this->sandbox = new Sandbox($environment, $policy);
    }
}
