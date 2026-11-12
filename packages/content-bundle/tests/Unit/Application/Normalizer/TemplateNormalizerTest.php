<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Normalizer;

use Phil\ContentBundle\Application\Normalizer\TemplateNormalizer;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Tests\Unit\Fixture\BareDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TemplateNormalizer::class)]
final class TemplateNormalizerTest extends TestCase
{
    public function testTheKeyAndTheValuesAreNormalizedAsStored(): void
    {
        $row = new ExampleRoot()->createDimensionContent(DimensionKey::unlocalized(Stage::Live, Version::first()));
        $row->setTemplateKey('article');
        $row->setTemplateData(['title' => 'Bonjour', 'blocks' => [['type' => 'text']]]);

        self::assertSame(
            ['templateKey' => 'article', 'templateData' => ['title' => 'Bonjour', 'blocks' => [['type' => 'text']]]],
            new TemplateNormalizer()->normalize($row),
        );
    }

    public function testARowWithoutTheCapabilityGivesNothing(): void
    {
        self::assertSame([], new TemplateNormalizer()->normalize(
            new BareDimensionContent(new ExampleRoot(), DimensionKey::unlocalized(Stage::Live, Version::first())),
        ));
    }
}
