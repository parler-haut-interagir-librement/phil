<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\DataMapper;

use Phil\ContentBundle\Application\DataMapper\TemplateDataMapper;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Tests\Unit\Fixture\BareDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

#[CoversClass(TemplateDataMapper::class)]
final class TemplateDataMapperTest extends TestCase
{
    public function testTheKeyGoesOnTheSharedRowAndTheFieldsOnTheLocalizedRow(): void
    {
        [$shared, $localized] = $this->rows();

        new TemplateDataMapper()->map($shared, $localized, ['templateKey' => ' news ', 'templateData' => ['title' => 'Bonjour', 'subtitle' => ' Salut ']]);

        self::assertSame('news', $shared->getTemplateKey());
        self::assertSame([], $shared->getTemplateData());
        self::assertNull($localized->getTemplateKey());
        self::assertSame(['title' => 'Bonjour', 'subtitle' => ' Salut '], $localized->getTemplateData(), 'Field values are kept as submitted.');
    }

    public function testTheFieldsAreReplacedWholeAndNullEmptiesThem(): void
    {
        [$shared, $localized] = $this->rows();
        $localized->setTemplateData(['title' => 'Bonjour', 'subtitle' => 'Salut']);
        $shared->setTemplateKey('news');

        new TemplateDataMapper()->map($shared, $localized, ['templateData' => ['title' => 'Allô']]);
        self::assertSame(['title' => 'Allô'], $localized->getTemplateData());
        self::assertSame('news', $shared->getTemplateKey(), 'An absent key changes nothing.');

        new TemplateDataMapper()->map($shared, $localized, ['templateData' => null, 'templateKey' => null]);
        self::assertSame([], $localized->getTemplateData());
        self::assertNull($shared->getTemplateKey());
    }

    public function testFieldsWithoutNamesAreRefused(): void
    {
        [$shared, $localized] = $this->rows();

        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('"templateData" maps field names to values; got the key 0.');

        new TemplateDataMapper()->map($shared, $localized, ['templateData' => ['Bonjour']]);
    }

    #[DoesNotPerformAssertions]
    public function testRowsWithoutTheCapabilityAreLeftAlone(): void
    {
        $bare = new BareDimensionContent(new ExampleRoot(), DimensionKey::unlocalized(Stage::Draft, Version::first()));

        new TemplateDataMapper()->map($bare, $bare, ['templateKey' => 'news', 'templateData' => ['title' => 'x']]);
    }

    /**
     * @return array{ExampleDimensionContent, ExampleDimensionContent}
     */
    private function rows(): array
    {
        $root = new ExampleRoot();

        return [
            new ExampleDimensionContent($root, DimensionKey::unlocalized(Stage::Draft, Version::first())),
            new ExampleDimensionContent($root, DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::first())),
        ];
    }
}
