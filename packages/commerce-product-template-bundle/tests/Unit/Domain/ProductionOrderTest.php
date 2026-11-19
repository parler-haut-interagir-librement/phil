<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Domain;

use Phil\CommerceProductTemplateBundle\Domain\ValueObject\PrintFile;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\ProductionLine;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\ProductionOrder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductionOrder::class)]
#[CoversClass(ProductionLine::class)]
#[CoversClass(PrintFile::class)]
final class ProductionOrderTest extends TestCase
{
    public function testItIsWrittenAsJsonWithItsVersion(): void
    {
        $order = $this->order();

        self::assertSame(5, $order->units());
        self::assertFalse($order->isEmpty());
        self::assertTrue(new ProductionOrder('R-1', [])->isEmpty());
        self::assertSame(0, new ProductionOrder('R-1', [])->units());
        self::assertSame([
            'version' => 1,
            'order' => 'R-1',
            'lines' => [
                [
                    'sku' => 'NAQ001FLMBLK',
                    'quantity' => 2,
                    'language' => 'F',
                    'values' => ['version' => ['code' => 'L', 'label' => 'Loot box', 'supplier' => null], 'colour' => ['code' => 'BLK', 'label' => 'Noir', 'supplier' => 'jet black']],
                    'options' => ['epitaph' => '=1+1, "quoted"', 'loot' => 'A9'],
                    'dice' => ['loot' => 'A9'],
                    'files' => [['face' => 'front', 'zone' => 'chest', 'ink' => 'colour', 'width' => 280.0, 'height' => 350.0, 'dpi' => 300, 'path' => 'print-files/a-colour.png']],
                ],
                [
                    'sku' => 'MUG01OMWH',
                    'quantity' => 3,
                    'language' => null,
                    'values' => ['size' => ['code' => 'M', 'label' => 'M', 'supplier' => null]],
                    'options' => [],
                    'dice' => [],
                    'files' => [],
                ],
            ],
        ], json_decode($order->json(), true, 512, \JSON_THROW_ON_ERROR));
        self::assertStringEndsWith("\n", $order->json());
        self::assertStringContainsString('"width": 280.0', $order->json());
        self::assertStringContainsString('"path": "print-files/a-colour.png"', $order->json());
        self::assertStringContainsString('"label": "Noir"', $order->json());
        self::assertStringContainsString("\n    \"order\"", $order->json());
    }

    public function testALineAsAnArrayHoldsItsFilesAsArrays(): void
    {
        $line = $this->order()->lines[0]->toArray();

        self::assertSame([['face' => 'front', 'zone' => 'chest', 'ink' => 'colour', 'width' => 280.0, 'height' => 350.0, 'dpi' => 300, 'path' => 'print-files/a-colour.png']], $line['files']);
    }

    public function testItIsWrittenAsCsvWithAColumnPerAxisAndNoFormula(): void
    {
        self::assertSame(
            "order,sku,quantity,version,colour,size,language,dice,options,files\r\n"
            ."R-1,NAQ001FLMBLK,2,L,BLK (jet black),,F,loot: A9,\"epitaph: =1+1, \"\"quoted\"\" | loot: A9\",print-files/a-colour.png\r\n"
            ."R-1,MUG01OMWH,3,,,M,,,,\r\n",
            $this->order()->csv(),
        );
    }

    public function testACellAFormulaWouldReadStartsWithAnApostrophe(): void
    {
        foreach (['=1', '+1', '-1', '@A1', "\t1", "\r1"] as $text) {
            $csv = new ProductionOrder($text, [])->csv();

            self::assertSame("order,sku,quantity,language,dice,options,files\r\n", $csv);
            $line = new ProductionLine($text, 1, null, [], [], [], []);
            $row = explode("\r\n", new ProductionOrder('R', [$line])->csv())[1];
            self::assertStringStartsWith("R,'".$text, str_replace('"', '', $row));
        }

        self::assertSame("order,sku,quantity,language,dice,options,files\r\nR,a\nb,1,,,,\r\n", str_replace('"a'."\n".'b"', "a\nb", new ProductionOrder('R', [new ProductionLine("a\nb", 1, null, [], [], [], [])])->csv()));
    }

    /**
     * @param array<string, string> $options
     */
    private function order(array $options = ['epitaph' => '=1+1, "quoted"', 'loot' => 'A9']): ProductionOrder
    {
        return new ProductionOrder('R-1', [
            new ProductionLine(
                'NAQ001FLMBLK',
                1,
                'F',
                ['version' => ['code' => 'L', 'label' => 'Loot box', 'supplier' => null], 'colour' => ['code' => 'BLK', 'label' => 'Noir', 'supplier' => 'jet black']],
                $options,
                ['loot' => 'A9'],
                [new PrintFile('front', 'chest', 'colour', 280, 350, 300, 'print-files/a-colour.png')],
            )->plusOne(),
            new ProductionLine('MUG01OMWH', 3, null, ['size' => ['code' => 'M', 'label' => 'M', 'supplier' => null]], [], [], []),
        ]);
    }
}
