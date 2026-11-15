<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Tests\Unit\Infrastructure;

use Phil\AccessibilityStatement\StatementReader;
use Phil\AccessibilityStatementBundle\Infrastructure\LegalNotices\StatementFooterLink;
use Phil\AccessibilityStatementBundle\Tests\Unit\Fixture\MemoryStatements;
use Phil\AccessibilityStatementBundle\Tests\Unit\Fixture\Statements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(StatementFooterLink::class)]
final class StatementFooterLinkTest extends TestCase
{
    public function testTheLinkOfThePublishedStatement(): void
    {
        $routes = new RouteCollection();
        $routes->add('phil_accessibility_statement', new Route('/accessibility'));

        self::assertSame(
            [['url' => '/accessibility', 'label' => 'link', 'domain' => 'phil_accessibility_statement']],
            new StatementFooterLink($this->published(), new UrlGenerator($routes, new RequestContext()))->footerLinks(),
        );
    }

    public function testNoStatementNoLink(): void
    {
        $routes = new RouteCollection();
        $routes->add('phil_accessibility_statement', new Route('/accessibility'));

        self::assertSame([], new StatementFooterLink(new MemoryStatements(), new UrlGenerator($routes, new RequestContext()))->footerLinks());
    }

    public function testARouteNotImportedGivesNoLink(): void
    {
        self::assertSame([], new StatementFooterLink($this->published(), new UrlGenerator(new RouteCollection(), new RequestContext()))->footerLinks());
    }

    private function published(): MemoryStatements
    {
        $statements = new MemoryStatements();
        $statements->add(StatementReader::fromArray(Statements::minimal()));

        return $statements;
    }
}
