<?php

declare(strict_types=1);

namespace Phil\SvgBlocksBundle\Tests\Unit;

use Phil\SvgBlocks\BlockMarkup;
use Phil\SvgBlocks\Renderer\MathRenderer;
use Phil\SvgBlocks\Signer;
use Phil\SvgBlocksBundle\Infrastructure\Form\StoredMarkupTransformer;
use Phil\SvgBlocksBundle\Infrastructure\Form\StoredMarkupTypeExtension;
use Phil\Ujo\Document;
use Phil\Ujo\Ujo;
use Phil\UjoBundle\Infrastructure\Form\UjoType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;

#[CoversClass(StoredMarkupTransformer::class)]
#[CoversClass(StoredMarkupTypeExtension::class)]
final class FormTest extends TestCase
{
    public function testDrawsTheBlocksOfASubmittedDocument(): void
    {
        $transformer = new StoredMarkupTransformer($this->markup(), new Ujo());
        $json = $transformer->reverseTransform('{"blocks":[{"id":"m","type":"math","data":{"latex":"x"}}]}');

        self::assertIsString($json);
        self::assertStringContainsString('"rendered":{"markup":"<math', $json);
        self::assertSame($json, $transformer->reverseTransform($json), 'an unchanged document is returned as it came');

        $document = $transformer->reverseTransform(Document::fromArray(['blocks' => [['id' => 'm', 'type' => 'math', 'data' => ['latex' => 'x']]]]));
        self::assertInstanceOf(Document::class, $document);
        self::assertArrayHasKey('rendered', $document->blocks[0]->data);

        self::assertNull($transformer->reverseTransform(null));
        self::assertSame('', $transformer->reverseTransform(''));
        self::assertSame('raw', $transformer->transform('raw'));
    }

    public function testExtendsTheUjoFieldsAndCanBeTurnedOff(): void
    {
        self::assertSame([UjoType::class, StoredMarkupTypeExtension::BLOCK_EDITOR_TYPE], [...StoredMarkupTypeExtension::getExtendedTypes()]);

        $ujo = new Ujo();
        $factory = Forms::createFormFactoryBuilder()
            ->addType(new UjoType($ujo))
            ->addTypeExtension(new StoredMarkupTypeExtension($this->markup(), $ujo))
            ->getFormFactory();

        $drawn = $factory->create(UjoType::class);
        $drawn->submit('{"blocks":[{"id":"m","type":"math","data":{"latex":"x","rendered":{"markup":"<b>","key":"v1.forged"}}}]}');
        self::assertIsString($drawn->getData());
        self::assertStringNotContainsString('forged', $drawn->getData());
        self::assertStringContainsString('<math', $drawn->getData());

        $left = $factory->create(UjoType::class, null, ['svg_blocks' => false]);
        $left->submit('{"blocks":[{"id":"m","type":"math","data":{"latex":"x"}}]}');
        self::assertSame('{"blocks":[{"id":"m","type":"math","data":{"latex":"x"}}]}', $left->getData());
    }

    private function markup(): BlockMarkup
    {
        return new BlockMarkup(new Signer(str_repeat('s', 32)), ['math' => new MathRenderer()]);
    }
}
