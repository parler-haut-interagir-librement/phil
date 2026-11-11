<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Card, Empty, Alert, Avatar, Table, Breadcrumb, Pagination.
 */
#[CoversNothing]
final class ContentRecipesTest extends KitTestCase
{
    public function testACard(): void
    {
        self::assertSame(
            '<article data-slot="card" data-size="default" class="isCard"><header data-slot="card-header" class="isCardHeader"><h3 data-slot="card-title" class="isCardTitle">T</h3><p data-slot="card-description" class="isCardDescription">D</p><div data-slot="card-action" class="isCardAction">A</div></header><div data-slot="card-content" class="isCardBody">C</div><footer data-slot="card-footer" class="isCardFooter">F</footer></article>',
            $this->render('<twig:Card><twig:Card:Header><twig:Card:Title>T</twig:Card:Title><twig:Card:Description>D</twig:Card:Description><twig:Card:Action>A</twig:Card:Action></twig:Card:Header><twig:Card:Content>C</twig:Card:Content><twig:Card:Footer>F</twig:Card:Footer></twig:Card>'),
        );
    }

    public function testASmallCardItsTagAndTheLevelOfItsTitle(): void
    {
        self::assertSame('<section data-slot="card" data-size="sm" class="isCard isSmall">x</section>', $this->render('<twig:Card size="sm" as="section">x</twig:Card>'));
        self::assertStringStartsWith('<article ', $this->render('<twig:Card as="script">x</twig:Card>'));
        self::assertStringStartsWith('<h2 ', $this->render('<twig:Card:Title :level="2">x</twig:Card:Title>'));
        self::assertStringStartsWith('<h6 ', $this->render('<twig:Card:Title :level="9">x</twig:Card:Title>'));
        self::assertStringStartsWith('<h1 ', $this->render('<twig:Card:Title :level="0">x</twig:Card:Title>'));
    }

    public function testAnEmptyState(): void
    {
        self::assertSame(
            '<div data-slot="empty" class="isEmptyState"><div data-slot="empty-icon" data-variant="icon" class="isEmptyMedia">I</div><header data-slot="empty-header" class="isEmptyHeader"><p data-slot="empty-title" class="isEmptyTitle"><strong>T</strong></p><p data-slot="empty-description" class="isEmptyDescription">D</p></header><div data-slot="empty-content" class="isEmptyContent">C</div></div>',
            $this->render('<twig:Empty><twig:Empty:Media variant="icon">I</twig:Empty:Media><twig:Empty:Header><twig:Empty:Title>T</twig:Empty:Title><twig:Empty:Description>D</twig:Empty:Description></twig:Empty:Header><twig:Empty:Content>C</twig:Empty:Content></twig:Empty>'),
        );
    }

    public function testAnAlert(): void
    {
        self::assertSame(
            '<div data-slot="alert" data-variant="destructive" class="isAlert isDanger" role="alert"><p data-slot="alert-title" class="isAlertTitle"><strong>T</strong></p><div data-slot="alert-description" class="isAlertDescription">D</div><div data-slot="alert-action" class="isAlertAction">A</div></div>',
            $this->render('<twig:Alert variant="destructive"><twig:Alert:Title>T</twig:Alert:Title><twig:Alert:Description>D</twig:Alert:Description><twig:Alert:Action>A</twig:Alert:Action></twig:Alert>'),
        );
    }

    public function testAnAlertOfNoUrgencyIsAStatus(): void
    {
        self::assertSame('<div data-slot="alert" data-variant="default" class="isAlert" role="status">x</div>', $this->render('<twig:Alert role="status">x</twig:Alert>'));
    }

    public function testAnAvatarNeedsNoScript(): void
    {
        $html = $this->render('<twig:Avatar size="lg"><twig:Avatar:Fallback>PG</twig:Avatar:Fallback><twig:Avatar:Image src="/p.webp" alt="P" /><twig:Avatar:Badge>b</twig:Avatar:Badge></twig:Avatar>');

        self::assertSame('<span data-slot="avatar" data-size="lg" class="isAvatar isLarge"><span data-slot="avatar-fallback" class="isAvatarFallback">PG</span><img data-slot="avatar-image" class="isAvatarImage" alt="P" loading="lazy" decoding="async" src="/p.webp"><span data-slot="avatar-badge" class="isAvatarBadge">b</span></span>', $html);
        self::assertStringNotContainsString('onload', $html);
        self::assertStringContainsString('isAvatar isSmall', $this->render('<twig:Avatar size="sm">x</twig:Avatar>'));
        self::assertStringContainsString('alt=""', $this->render('<twig:Avatar:Image src="/p.webp" />'));
    }

    public function testAGroupOfAvatars(): void
    {
        self::assertSame(
            '<div data-slot="avatar-group" class="isAvatarGroup">a<div data-slot="avatar-group-count" class="isAvatarGroupCount">+3</div></div>',
            $this->render('<twig:Avatar:Group>a<twig:Avatar:GroupCount>+3</twig:Avatar:GroupCount></twig:Avatar:Group>'),
        );
    }

    public function testATable(): void
    {
        self::assertSame(
            '<div class="isScrollable" data-slot="table-container"><table data-slot="table" class="isTable"><caption data-slot="table-caption" class="isTableCaption">C</caption><thead data-slot="table-header" class="isTableHeader"><tr data-slot="table-row" class="isTableRow"><th data-slot="table-head" class="isTableHead" scope="col">H</th></tr></thead><tbody data-slot="table-body" class="isTableBody"><tr data-slot="table-row" class="isTableRow"><td data-slot="table-cell" class="isTableCell">D</td></tr></tbody><tfoot data-slot="table-footer" class="isTableFooter"><tr data-slot="table-row" class="isTableRow"><th data-slot="table-head" class="isTableHead" scope="row">F</th></tr></tfoot></table></div>',
            $this->render('<twig:Table><twig:Table:Caption>C</twig:Table:Caption><twig:Table:Header><twig:Table:Row><twig:Table:Head>H</twig:Table:Head></twig:Table:Row></twig:Table:Header><twig:Table:Body><twig:Table:Row><twig:Table:Cell>D</twig:Table:Cell></twig:Table:Row></twig:Table:Body><twig:Table:Footer><twig:Table:Row><twig:Table:Head scope="row">F</twig:Table:Head></twig:Table:Row></twig:Table:Footer></twig:Table>'),
        );
    }

    public function testABreadcrumb(): void
    {
        self::assertSame(
            '<nav data-slot="breadcrumb" class="isBreadcrumb" aria-label="Fil"><ol data-slot="breadcrumb-list" class="isBreadcrumbList"><li data-slot="breadcrumb-item" class="isBreadcrumbItem"><a data-slot="breadcrumb-link" class="isBreadcrumbLink" href="/">H</a></li><li data-slot="breadcrumb-separator" role="presentation" aria-hidden="true" class="isBreadcrumbSeparator">&rsaquo;</li><li data-slot="breadcrumb-item" class="isBreadcrumbItem"><span data-slot="breadcrumb-ellipsis" class="isBreadcrumbEllipsis"><span aria-hidden="true">&hellip;</span><span class="isVisuallyHidden">More</span></span></li><li data-slot="breadcrumb-item" class="isBreadcrumbItem"><span data-slot="breadcrumb-page" aria-current="page" class="isBreadcrumbPage">P</span></li></ol></nav>',
            $this->render('<twig:Breadcrumb label="Fil"><twig:Breadcrumb:List><twig:Breadcrumb:Item><twig:Breadcrumb:Link href="/">H</twig:Breadcrumb:Link></twig:Breadcrumb:Item><twig:Breadcrumb:Separator /><twig:Breadcrumb:Item><twig:Breadcrumb:Ellipsis /></twig:Breadcrumb:Item><twig:Breadcrumb:Item><twig:Breadcrumb:Page>P</twig:Breadcrumb:Page></twig:Breadcrumb:Item></twig:Breadcrumb:List></twig:Breadcrumb>'),
        );
        self::assertStringContainsString('>/</li>', $this->render('<twig:Breadcrumb:Separator>/</twig:Breadcrumb:Separator>'));
    }

    public function testThePagination(): void
    {
        $html = $this->render('<twig:Pagination><twig:Pagination:Content><twig:Pagination:Item><twig:Pagination:Link href="?p=2" :active="true">2</twig:Pagination:Link></twig:Pagination:Item><twig:Pagination:Item><twig:Pagination:Link href="?p=3">3</twig:Pagination:Link></twig:Pagination:Item></twig:Pagination:Content></twig:Pagination>');

        self::assertSame(
            '<nav data-slot="pagination" class="isPagination" aria-label="Pagination"><ul data-slot="pagination-content" class="isPaginationList"><li data-slot="pagination-item" class="isPaginationItem"><a data-slot="pagination-link" data-variant="outline" data-size="icon" class="isButton isOutline hasIcon isIconOnly isPaginationLink" aria-current="page" data-active="true" href="?p=2">2</a></li><li data-slot="pagination-item" class="isPaginationItem"><a data-slot="pagination-link" data-variant="ghost" data-size="icon" class="isButton isGhost hasIcon isIconOnly isPaginationLink" aria-current="false" data-active="false" href="?p=3">3</a></li></ul></nav>',
            $html,
        );
    }

    public function testTheTextsOfThePaginationAreProps(): void
    {
        self::assertStringContainsString('aria-label="Pages"', $this->render('<twig:Pagination label="Pages">x</twig:Pagination>'));
        self::assertSame(
            '<span data-slot="pagination-ellipsis" class="isPaginationEllipsis"><span aria-hidden="true">&hellip;</span><span class="isVisuallyHidden">Plus</span></span>',
            $this->render('<twig:Pagination:Ellipsis text="Plus" />'),
        );

        $previous = $this->render('<twig:Pagination:Previous href="?p=1" text="Avant" label="Page précédente" />');
        $next = $this->render('<twig:Pagination:Next href="?p=3" />');

        self::assertSame('<a data-slot="pagination-link" data-variant="ghost" data-size="default" class="isButton isGhost isPaginationLink" aria-current="false" data-active="false" aria-label="Page précédente" href="?p=1"><span aria-hidden="true">&lsaquo;</span><span>Avant</span></a>', $previous);
        self::assertStringContainsString('aria-label="Go to next page"', $next);
        self::assertStringEndsWith('<span>Next</span><span aria-hidden="true">&rsaquo;</span></a>', $next);
    }
}
