<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Pipeline;

use LogicException;
use Phil\ContentBundle\Application\Normalizer\TemplateNormalizer;
use Phil\ContentBundle\Application\Normalizer\WorkflowNormalizer;
use Phil\ContentBundle\Application\Pipeline\ContentNormalizer;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentNormalizer::class)]
final class ContentNormalizerTest extends TestCase
{
    public function testTheFragmentsOfEveryNormalizerAreCombined(): void
    {
        $row = $this->row();
        $row->setTemplateKey('article');
        $row->setWorkflowPlace('draft');

        $normalized = new ContentNormalizer([new TemplateNormalizer(), new WorkflowNormalizer()])->normalize($row);

        self::assertSame(
            ['templateKey' => 'article', 'templateData' => [], 'workflowPlace' => 'draft', 'workflowPublished' => null],
            $normalized,
        );
    }

    /**
     * A generator of normalizers is read once, at construction, and still serves every row.
     */
    public function testNormalizersGivenAsAGeneratorServeEveryRow(): void
    {
        $normalizer = new ContentNormalizer((static function (): iterable {
            yield new TemplateNormalizer();
        })());

        self::assertArrayHasKey('templateKey', $normalizer->normalize($this->row()));
        self::assertArrayHasKey('templateKey', $normalizer->normalize($this->row()));
    }

    public function testNoNormalizerGivesAnEmptyArray(): void
    {
        self::assertSame([], new ContentNormalizer([])->normalize($this->row()));
    }

    /**
     * Two normalizers writing one key: neither silently hides the other.
     */
    public function testTwoNormalizersWritingOneKeyAreRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The key "templateKey" is written by two normalizers; the second is '.TemplateNormalizer::class.'.');

        new ContentNormalizer([new TemplateNormalizer(), new WorkflowNormalizer(), new TemplateNormalizer()])->normalize($this->row());
    }

    private function row(): ExampleDimensionContent
    {
        return new ExampleRoot()->createDimensionContent(DimensionKey::unlocalized(Stage::Live, Version::first()));
    }
}
