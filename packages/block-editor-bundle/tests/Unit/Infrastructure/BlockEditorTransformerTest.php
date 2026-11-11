<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Tests\Unit\Infrastructure;

use Phil\BlockEditorBundle\Application\Toolset;
use Phil\BlockEditorBundle\Infrastructure\Form\BlockEditorTransformer;
use Phil\BlockEditorBundle\Infrastructure\Ujo\ToolsetNormalizers;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Type\BlockTypeRegistry;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;

#[CoversClass(BlockEditorTransformer::class)]
final class BlockEditorTransformerTest extends TestCase
{
    private function transformer(bool $document = false): BlockEditorTransformer
    {
        $ujo = new Ujo();

        return new BlockEditorTransformer($ujo, new ToolsetNormalizers(BlockTypeRegistry::default())->normalizer(Toolset::reduced()), $document);
    }

    public function testTheModelIsShownAsJson(): void
    {
        $transformer = $this->transformer();

        self::assertSame('', $transformer->transform(null));
        self::assertSame('{"blocks":[]}', $transformer->transform('{"blocks":[]}'));
        self::assertSame('{"blocks":[{"id":"a","type":"paragraph","data":{"text":"<b>x</b>"}}]}', $transformer->transform(Document::fromArray(['blocks' => [['id' => 'a', 'type' => 'paragraph', 'data' => ['text' => '<strong>x</strong>']]]])));
    }

    public function testTheJsonIsCheckedAndNarrowedToTheToolset(): void
    {
        $json = '{"time":1,"blocks":[{"id":"a","type":"paragraph","data":{"text":"<strong>x</strong> <u>u</u>"}}],"version":"2.31.7"}';

        self::assertSame('{"time":1,"blocks":[{"id":"a","type":"paragraph","data":{"text":"<b>x</b> u"}}],"version":"2.31.7"}', $this->transformer()->reverseTransform($json));

        $document = $this->transformer(true)->reverseTransform($json);
        self::assertInstanceOf(Document::class, $document);
        self::assertSame(['text' => '<b>x</b> u'], $document->blocks[0]->data);
    }

    public function testNothingIsNoValue(): void
    {
        self::assertNull($this->transformer()->reverseTransform(null));
        self::assertNull($this->transformer()->reverseTransform(''));
        self::assertNull($this->transformer()->reverseTransform('{"blocks":[]}'));
        self::assertNull($this->transformer(true)->reverseTransform('{"time":5,"blocks":[]}'));
    }

    public function testAnInvalidDocumentFailsWithTheLibrarysMessage(): void
    {
        try {
            $this->transformer()->reverseTransform('{"blocks":[{"id":"a","type":"table","data":{"content":[["x"]]}}]}');
            self::fail('A table is outside the reduced toolset.');
        } catch (TransformationFailedException $transformationFailedException) {
            self::assertInstanceOf(UnknownBlockType::class, $transformationFailedException->getPrevious());
            self::assertSame($transformationFailedException->getPrevious()->getMessage(), $transformationFailedException->getMessage());
            self::assertSame('This value is not a valid UJO document: {{ error }}', $transformationFailedException->getInvalidMessage());
            self::assertSame(['{{ error }}' => $transformationFailedException->getMessage()], $transformationFailedException->getInvalidMessageParameters());
        }

        $this->expectException(TransformationFailedException::class);
        $this->transformer()->reverseTransform('{"blocks":');
    }
}
