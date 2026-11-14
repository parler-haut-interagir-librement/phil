<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Unit\Infrastructure;

use Phil\DataTableBundle\Application\View\CellView;
use Phil\DataTableBundle\Infrastructure\Twig\CellBlocks;
use Phil\DataTableBundle\Infrastructure\Twig\DataTableExtension;
use Phil\DataTableBundle\Infrastructure\Twig\KitComponents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;

#[CoversClass(CellBlocks::class)]
#[CoversClass(DataTableExtension::class)]
final class CellBlocksTest extends TestCase
{
    private const array TEMPLATES = [
        'cells.html.twig' => '{% block title %}<b>{{ value }}</b>{% endblock %}{% block shelf %}{{ row.shelf }}{% endblock %}',
        'theme.html.twig' => '{% block title %}<i>{{ value|upper }}</i>{% endblock %}',
        'other.html.twig' => '{% block title %}<u>{{ value }}</u>{% endblock %}{% block shelf %}[{{ row.shelf }}]{% endblock %}',
    ];

    public function testABlockIsDrawnFromTheColumnTemplateWithTheRowAndTheValue(): void
    {
        $twig = $this->twig();
        $cells = new CellBlocks();

        self::assertSame('<b>Dune</b>', $cells->render($twig, $this->cell('title', 'Dune')));
        self::assertSame('B-12', $cells->render($twig, $this->cell('shelf', 'Dune')));
    }

    public function testTheThemesOfTheApplicationWinOverTheColumnTemplateBlockByBlock(): void
    {
        $twig = $this->twig();

        $themed = new CellBlocks(['theme.html.twig']);
        self::assertSame('<i>DUNE</i>', $themed->render($twig, $this->cell('title', 'Dune')));
        self::assertSame('B-12', $themed->render($twig, $this->cell('shelf', 'Dune')), "A block the theme leaves out is the column's.");

        $first = new CellBlocks(['other.html.twig', 'theme.html.twig']);
        self::assertSame('<u>Dune</u>', $first->render($twig, $this->cell('title', 'Dune')), 'The first theme wins over the next.');
        self::assertSame('[B-12]', $first->render($twig, $this->cell('shelf', 'Dune')));
    }

    public function testACellWithoutABlockDrawsNothing(): void
    {
        $twig = $this->twig();
        $cells = new CellBlocks(['theme.html.twig']);

        self::assertSame('', $cells->render($twig, $this->cell(null, 'Dune')));
        self::assertSame('', $cells->render($twig, $this->cell('title', 'Dune', null)));
    }

    public function testABlockFoundNowhereStopsTheRender(): void
    {
        $this->expectException(RuntimeError::class);

        new CellBlocks(['theme.html.twig'])->render($this->twig(), $this->cell('missing', 'Dune'));
    }

    public function testTheChainsAreKeptForTheRequestAndDroppedOnReset(): void
    {
        $cells = new CellBlocks();
        self::assertSame('<b>Dune</b>', $cells->render($this->twig(), $this->cell('title', 'Dune')));

        $changed = $this->twig(['cells.html.twig' => '{% block title %}<s>{{ value }}</s>{% endblock %}']);
        self::assertSame('<b>Dune</b>', $cells->render($changed, $this->cell('title', 'Dune')), 'The chain of the request is kept.');

        $cells->reset();
        self::assertSame('<s>Dune</s>', $cells->render($changed, $this->cell('title', 'Dune')));
    }

    public function testTheTwigFunctionDrawsTheCellThroughTheThemes(): void
    {
        $extension = new DataTableExtension(new KitComponents(), new CellBlocks(['theme.html.twig']));

        self::assertSame('<i>DUNE</i>', $extension->cell($this->twig(), $this->cell('title', 'Dune')));
    }

    /**
     * @param array<string, string> $templates
     */
    private function twig(array $templates = []): Environment
    {
        return new Environment(new ArrayLoader([...self::TEMPLATES, ...$templates]), ['strict_variables' => true]);
    }

    private function cell(?string $block, string $value, ?string $template = 'cells.html.twig'): CellView
    {
        return new CellView('title', 'Title', 'block', false, '', $value, ['shelf' => 'B-12'], null, $template, $block, null, null);
    }
}
