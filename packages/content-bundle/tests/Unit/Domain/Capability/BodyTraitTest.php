<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Domain\Capability;

use Phil\ContentBundle\Domain\Capability\BodyTrait;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\ValueObject\Body;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use Phil\Markup\MarkupFormat;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversTrait(BodyTrait::class)]
final class BodyTraitTest extends TestCase
{
    public function testANewRowHasNoBody(): void
    {
        self::assertNull($this->row()->getBody());
    }

    public function testTheSourceTheFormatAndTheHtmlAreReadBackTogether(): void
    {
        $row = $this->row();

        $row->setBody(Body::of('<p>Bonjour</p>', MarkupFormat::Html, '<p>Bonjour</p>'));

        $body = $row->getBody();

        self::assertInstanceOf(Body::class, $body);
        self::assertSame('<p>Bonjour</p>', $body->source);
        self::assertSame('html', $body->format);
        self::assertSame('<p>Bonjour</p>', $body->html);
        self::assertFalse($body->rawHtml);
    }

    /**
     * Whether the author held the raw HTML permission is kept with the body, and forgotten with it.
     */
    public function testTheRawHtmlPermissionOfTheAuthorIsKeptWithTheBody(): void
    {
        $row = $this->row();

        $row->setBody(Body::of('<div>Bonjour</div>', MarkupFormat::Markdown, '<div>Bonjour</div>', true));
        self::assertTrue($row->getBody()?->rawHtml);

        $row->setBody(null);
        $row->setBody(Body::of('Bonjour', MarkupFormat::Markdown, '<p>Bonjour</p>'));
        self::assertFalse($row->getBody()?->rawHtml);

        $row->setBody(Body::of('<div>Bonjour</div>', MarkupFormat::Markdown, '<div>Bonjour</div>', true));
        $row->setBody(null);
        self::assertNull($row->getBody());
        self::assertFalse(new ReflectionProperty($row, 'bodyRawHtml')->getValue($row), 'cleared with the body');
    }

    public function testTheBodyIsReplacedAndCleared(): void
    {
        $row = $this->row();
        $row->setBody(Body::of('<p>Bonjour</p>', MarkupFormat::Html, '<p>Bonjour</p>'));

        $row->setBody(Body::of('*Salut*', MarkupFormat::Markdown, '<p><em>Salut</em></p>'));
        self::assertSame('markdown', $row->getBody()?->format);
        self::assertSame('<p><em>Salut</em></p>', $row->getBody()->html);

        $row->setBody(null);
        self::assertNull($row->getBody());
    }

    private function row(): ExampleDimensionContent
    {
        return new ExampleRoot()->createDimensionContent(DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::first()));
    }
}
