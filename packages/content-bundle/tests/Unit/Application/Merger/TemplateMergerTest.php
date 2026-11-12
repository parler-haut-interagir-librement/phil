<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Merger;

use Phil\ContentBundle\Application\Merger\TemplateMerger;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Tests\Unit\Fixture\BareDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TemplateMerger::class)]
final class TemplateMergerTest extends TestCase
{
    public function testTheSourceKeyReplacesTheTargetKey(): void
    {
        $target = $this->row('page', []);

        new TemplateMerger()->merge($target, $this->row('article', []));

        self::assertSame('article', $target->getTemplateKey());
    }

    /**
     * A source without a key keeps the key the shared row set: a localized row rarely repeats the layout.
     */
    public function testASourceWithoutAKeyKeepsTheTargetKey(): void
    {
        $target = $this->row('article', []);

        new TemplateMerger()->merge($target, $this->row(null, []));

        self::assertSame('article', $target->getTemplateKey());
    }

    public function testTheSourceFieldsReplaceTheTargetFieldsKeyByKey(): void
    {
        $target = $this->row(null, ['image' => 'cover.png', 'title' => 'shared']);

        new TemplateMerger()->merge($target, $this->row(null, ['title' => 'Bonjour', 'lead' => 'Salut']));

        self::assertSame(['image' => 'cover.png', 'title' => 'Bonjour', 'lead' => 'Salut'], $target->getTemplateData());
    }

    /**
     * The replacement is shallow: a nested block is one value, replaced whole, never mixed item by item.
     */
    public function testANestedFieldIsReplacedWhole(): void
    {
        $target = $this->row(null, ['blocks' => [['type' => 'text'], ['type' => 'image']]]);

        new TemplateMerger()->merge($target, $this->row(null, ['blocks' => [['type' => 'quote']]]));

        self::assertSame(['blocks' => [['type' => 'quote']]], $target->getTemplateData());
    }

    /**
     * A field the source sets to `null` is set: an explicit empty value is not an absent one.
     */
    public function testANullFieldInTheSourceIsKept(): void
    {
        $target = $this->row(null, ['lead' => 'shared']);

        new TemplateMerger()->merge($target, $this->row(null, ['lead' => null]));

        self::assertSame(['lead' => null], $target->getTemplateData());
    }

    public function testARowWithoutTheCapabilityIsLeftAlone(): void
    {
        $target = $this->row('article', ['title' => 'shared']);
        $bare = new BareDimensionContent(new ExampleRoot(), DimensionKey::unlocalized(Stage::Draft, Version::first()));

        new TemplateMerger()->merge($target, $bare);
        new TemplateMerger()->merge($bare, $target);

        self::assertSame('article', $target->getTemplateKey());
        self::assertSame(['title' => 'shared'], $target->getTemplateData());
    }

    /**
     * @param array<string, mixed> $templateData
     */
    private function row(?string $templateKey, array $templateData): ExampleDimensionContent
    {
        $row = new ExampleRoot()->createDimensionContent(DimensionKey::unlocalized(Stage::Draft, Version::first()));
        $row->setTemplateKey($templateKey);
        $row->setTemplateData($templateData);

        return $row;
    }
}
