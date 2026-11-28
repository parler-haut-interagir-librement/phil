<?php

declare(strict_types=1);

/*
 * A template nobody wrote, rendered with values nobody chose: the input is a body, then, after the first byte 0, the
 * JSON of the values, after the second, the JSON of the variable schema. Whatever it holds, the checks, the sandbox and
 * the render end in a result or in an exception of the bundle — never another error, never an output past the ceiling.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\EmailTemplatesBundle\Application\Rendering\RenderContext;
use Phil\EmailTemplatesBundle\Application\Rendering\Theme;
use Phil\EmailTemplatesBundle\Domain\Exception\EmailTemplatesException;
use Phil\EmailTemplatesBundle\Domain\Schema\VariableSchema;
use Phil\EmailTemplatesBundle\Domain\Status\BodyFormat;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Phil\EmailTemplatesBundle\Infrastructure\Markup\MarkupMarkdown;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\SandboxedEngine;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Markup;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$ceiling = 65_536;
$engine = new SandboxedEngine(Theme::create(), new MarkupMarkdown(new Markup(new DefaultCompiler(), SanitizerPurifier::defaultPolicies())), maxBytes: $ceiling);
$context = new RenderContext(TemplateKey::fromString('fuzz'), Site::shared(), Locale::fromString('en'), ['vip']);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($engine, $context, $ceiling): void {
    [$body, $values, $schema] = array_pad(explode("\0", $input, 3), 3, '');

    try {
        $declared = json_decode($schema, true, 8);
        $definition = TemplateDefinition::create('Subject', '' === $body ? ' ' : $body, 0 === strlen($input) % 2 ? BodyFormat::Html : BodyFormat::Markdown, schema: VariableSchema::fromArray(is_array($declared) ? $declared : []), segments: ['vip']);
        $engine->check($definition);
        $given = json_decode($values, true, 8);
        $parts = $engine->render($definition, $definition->schema->validate(is_array($given) ? $given : []), $context);

        if (strlen($parts->html) > $ceiling * 2) {
            throw new LogicException('The body renders past the ceiling.');
        }
    } catch (EmailTemplatesException) {
        // A template or values refused: what the bundle promises.
    }
});
