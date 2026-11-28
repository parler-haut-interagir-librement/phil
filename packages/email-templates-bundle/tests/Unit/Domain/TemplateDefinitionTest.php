<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit\Domain;

use Phil\EmailTemplatesBundle\Domain\Event\TemplateDraftDiscarded;
use Phil\EmailTemplatesBundle\Domain\Event\TemplatePublished;
use Phil\EmailTemplatesBundle\Domain\Event\TemplateReset;
use Phil\EmailTemplatesBundle\Domain\Event\TemplateSaved;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\RenderingFailed;
use Phil\EmailTemplatesBundle\Domain\Exception\UnknownTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\VersionConflict;
use Phil\EmailTemplatesBundle\Domain\Schema\VariableSchema;
use Phil\EmailTemplatesBundle\Domain\Status\BodyFormat;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TemplateDefinition::class)]
#[CoversClass(InvalidTemplate::class)]
#[CoversClass(UnknownTemplate::class)]
#[CoversClass(VersionConflict::class)]
#[CoversClass(RenderingFailed::class)]
#[CoversClass(TemplateSaved::class)]
#[CoversClass(TemplateReset::class)]
final class TemplateDefinitionTest extends TestCase
{
    public function testADefinitionKeepsItsFieldsTrimmed(): void
    {
        $schema = VariableSchema::fromArray(['name' => ['type' => 'string']]);
        $definition = TemplateDefinition::create('  Hello {{ name }}  ', 'Hi **{{ name }}**', BodyFormat::Markdown, '  Soon  ', "Hi\n", $schema, ['vip', 'vip', 'new'], ['logo' => 'img/logo.png']);

        self::assertSame('Hello {{ name }}', $definition->subject);
        self::assertSame('Soon', $definition->preheader);
        self::assertSame(BodyFormat::Markdown, $definition->format);
        self::assertSame("Hi\n", $definition->text);
        self::assertSame(['vip', 'new'], $definition->segments);
        self::assertSame(['logo' => 'img/logo.png'], $definition->embeds);
        self::assertSame($schema, $definition->schema);
        self::assertTrue($definition->hasSegment('vip'));
        self::assertFalse($definition->hasSegment('old'));
    }

    public function testTheDefaultsOfADefinition(): void
    {
        $definition = TemplateDefinition::create('Hi', '<p>Hi</p>', text: "  \n ");

        self::assertSame(BodyFormat::Html, $definition->format);
        self::assertSame('', $definition->preheader);
        self::assertNull($definition->text);
        self::assertSame([], $definition->segments);
        self::assertSame([], $definition->embeds);
        self::assertSame([], $definition->schema->variables);
    }

    public function testTheDeclarationReadsBack(): void
    {
        $data = [
            'subject' => 'Hi {{ name }}',
            'preheader' => 'Soon',
            'format' => 'markdown',
            'body' => 'Hi',
            'text' => 'Hi',
            'segments' => ['vip'],
            'embeds' => ['logo' => 'logo.png'],
            'variables' => ['name' => ['type' => 'string', 'required' => true]],
        ];
        $definition = TemplateDefinition::fromArray($data);

        self::assertSame($data, $definition->toArray());
        self::assertSame(BodyFormat::Html, TemplateDefinition::fromArray(['subject' => 'a', 'body' => 'b'])->format);
        self::assertSame('', TemplateDefinition::fromArray(['subject' => 'a', 'body' => 'b', 'preheader' => null])->preheader);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function refusedDeclarations(): iterable
    {
        yield 'unknown key' => [['subject' => 'a', 'body' => 'b', 'title' => 'c'], 'A template has no key "title"'];
        yield 'unknown format' => [['subject' => 'a', 'body' => 'b', 'format' => 'mjml'], 'The format is "html" or "markdown".'];
        yield 'format not text' => [['subject' => 'a', 'body' => 'b', 'format' => 1], 'The format is "html" or "markdown".'];
        yield 'no subject' => [['body' => 'b'], 'The subject is a text.'];
        yield 'no body' => [['subject' => 'a'], 'The body is a text.'];
        yield 'preheader not text' => [['subject' => 'a', 'body' => 'b', 'preheader' => 1], 'The preheader is a text.'];
        yield 'text not text' => [['subject' => 'a', 'body' => 'b', 'text' => []], 'The text is a text.'];
        yield 'segments a map' => [['subject' => 'a', 'body' => 'b', 'segments' => ['a' => 'vip']], 'The segments are a list of names.'];
        yield 'segments of numbers' => [['subject' => 'a', 'body' => 'b', 'segments' => [1]], 'The segments are a list of names.'];
        yield 'segments a text' => [['subject' => 'a', 'body' => 'b', 'segments' => 'vip'], 'The segments are a list of names.'];
        yield 'embeds a list' => [['subject' => 'a', 'body' => 'b', 'embeds' => ['logo.png']], 'The embeds are a map of content identifiers to paths.'];
        yield 'embeds of numbers' => [['subject' => 'a', 'body' => 'b', 'embeds' => ['logo' => 1]], 'The embeds are a map of content identifiers to paths.'];
        yield 'embeds a text' => [['subject' => 'a', 'body' => 'b', 'embeds' => 'logo'], 'The embeds are a map of content identifiers to paths.'];
        yield 'variables a text' => [['subject' => 'a', 'body' => 'b', 'variables' => 'name'], 'The variables are a map of names to their declarations.'];
        yield 'bad variable' => [['subject' => 'a', 'body' => 'b', 'variables' => ['A' => ['type' => 'string']]], '"A" is not a variable name'];
        yield 'empty subject' => [['subject' => ' ', 'body' => 'b'], 'The subject is one line of 1 to 2000 characters.'];
        yield 'long subject' => [['subject' => str_repeat('a', 2001), 'body' => 'b'], 'The subject is one line'];
        yield 'subject on two lines' => [['subject' => "a\nb", 'body' => 'b'], 'The subject is one line'];
        yield 'subject with a return' => [['subject' => "a\rb", 'body' => 'b'], 'The subject is one line'];
        yield 'long preheader' => [['subject' => 'a', 'body' => 'b', 'preheader' => str_repeat('a', 2001)], 'The preheader is at most 2000 characters.'];
        yield 'empty body' => [['subject' => 'a', 'body' => "  \n"], 'The body is 1 byte to 256 KiB.'];
        yield 'large body' => [['subject' => 'a', 'body' => str_repeat('a', 262_145)], 'The body is 1 byte to 256 KiB.'];
        yield 'large text' => [['subject' => 'a', 'body' => 'b', 'text' => str_repeat('a', 262_145)], 'The plain-text part is at most 256 KiB.'];
        yield 'subject not UTF-8' => [['subject' => "a\xFF", 'body' => 'b'], 'The subject is not valid UTF-8.'];
        yield 'body not UTF-8' => [['subject' => 'a', 'body' => "\xFF"], 'The body is not valid UTF-8.'];
        yield 'text not UTF-8' => [['subject' => 'a', 'body' => 'b', 'text' => "\xFF"], 'The text is not valid UTF-8.'];
        yield 'preheader not UTF-8' => [['subject' => 'a', 'body' => 'b', 'preheader' => "\xFF"], 'The preheader is not valid UTF-8.'];
        yield 'bad segment' => [['subject' => 'a', 'body' => 'b', 'segments' => ['VIP']], 'A segment is a lowercase letter'];
        yield 'too many segments' => [['subject' => 'a', 'body' => 'b', 'segments' => array_map(static fn (int $i): string => 's'.$i, range(1, 33))], 'A template tests at most 32 segments.'];
        yield 'bad embed name' => [['subject' => 'a', 'body' => 'b', 'embeds' => ['my logo' => 'logo.png']], 'The content identifier of an image is 1 to 64'];
        yield 'embed leaving' => [['subject' => 'a', 'body' => 'b', 'embeds' => ['logo' => 'a/../../etc/passwd']], 'The image "logo" is a relative path'];
        yield 'embed absolute' => [['subject' => 'a', 'body' => 'b', 'embeds' => ['logo' => '/etc/passwd']], 'The image "logo" is a relative path'];
        yield 'embed double slash' => [['subject' => 'a', 'body' => 'b', 'embeds' => ['logo' => 'a//b.png']], 'The image "logo" is a relative path'];
        yield 'too many embeds' => [['subject' => 'a', 'body' => 'b', 'embeds' => array_combine(array_map(static fn (int $i): string => 'i'.$i, range(1, 17)), array_fill(0, 17, 'a.png'))], 'A template embeds at most 16 images.'];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('refusedDeclarations')]
    public function testARefusedDeclaration(array $data, string $error): void
    {
        try {
            TemplateDefinition::fromArray($data);
            self::fail('The declaration is refused.');
        } catch (InvalidTemplate $invalidTemplate) {
            self::assertNotSame([], array_filter($invalidTemplate->errors, static fn (string $message): bool => str_starts_with($message, $error)), implode("\n", $invalidTemplate->errors));
        }
    }

    public function testTheLimitsAreInclusive(): void
    {
        $definition = TemplateDefinition::fromArray([
            'subject' => str_repeat('é', 2000),
            'preheader' => str_repeat('é', 2000),
            'body' => str_repeat('a', 262_144),
            'text' => str_repeat('a', 262_144),
            'segments' => array_map(static fn (int $i): string => 's'.$i, range(1, 32)),
            'embeds' => array_combine(array_map(static fn (int $i): string => 'i'.$i, range(1, 16)), array_fill(0, 16, 'dir/a-b_c.png')),
        ]);

        self::assertCount(32, $definition->segments);
        self::assertCount(16, $definition->embeds);
    }

    public function testTheExceptionsSayWhatHappened(): void
    {
        self::assertSame('No email template "ticket" exists for the locale "fr", nor a default for it.', UnknownTemplate::named('ticket', 'fr')->getMessage());
        self::assertSame('The email template "ticket" has no published version 4 for the locale "fr".', UnknownTemplate::version('ticket', 'fr', 4)->getMessage());
        self::assertSame('The email template "ticket" has no draft for the locale "fr": save one first.', UnknownTemplate::draft('ticket', 'fr')->getMessage());
        self::assertSame('The email template "ticket" (fr) was saved over revision 2, but revision 3 is the latest: reload it and save again.', VersionConflict::expected('ticket', 'fr', 2, 3)->getMessage());
        $previous = new RuntimeException('cause');
        $failed = RenderingFailed::because('Broken.', $previous);
        self::assertSame('Broken.', $failed->getMessage());
        self::assertSame($previous, $failed->getPrevious());
        self::assertSame(['One.', 'Two.'], InvalidTemplate::because('One.', 'Two.')->errors);
        self::assertSame('One. Two.', InvalidTemplate::because('One.', 'Two.')->getMessage());
    }

    public function testTheEventsCarryIdentifiersOnly(): void
    {
        $saved = new TemplateSaved('ticket', 'main', 'fr', 3, 5, 'ada', 1);
        self::assertSame(['ticket', 'main', 'fr', 3, 5, 'ada', 1], [$saved->key, $saved->site, $saved->locale, $saved->version, $saved->revision, $saved->author, $saved->revertedFrom]);
        $published = new TemplatePublished('ticket', 'main', 'fr', 3);
        self::assertSame(['ticket', 'main', 'fr', 3, null], [$published->key, $published->site, $published->locale, $published->version, $published->author]);
        $discarded = new TemplateDraftDiscarded('ticket', 'main', 'fr', 4, 'ada');
        self::assertSame(['ticket', 'main', 'fr', 4, 'ada'], [$discarded->key, $discarded->site, $discarded->locale, $discarded->version, $discarded->author]);
        $reset = new TemplateReset('ticket', '', 'fr');
        self::assertSame(['ticket', '', 'fr', null], [$reset->key, $reset->site, $reset->locale, $reset->author]);
    }
}
