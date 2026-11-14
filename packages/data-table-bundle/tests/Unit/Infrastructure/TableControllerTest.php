<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Unit\Infrastructure;

use Phil\DataTableBundle\Application\Exception\AccessDenied;
use Phil\DataTableBundle\Infrastructure\Http\TableController;
use Phil\DataTableBundle\Infrastructure\Http\TableGuard;
use Phil\DataTableBundle\Tests\Fixtures\Kit;
use Phil\DataTableBundle\Tests\Fixtures\Shelf;
use Phil\DataTableBundle\Tests\Fixtures\Tokens;
use Phil\DataTableBundle\Tests\Functional\App\BooksTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(TableController::class)]
final class TableControllerTest extends TestCase
{
    private Shelf $shelf;

    private TableController $controller;

    private TableGuard $guard;

    protected function setUp(): void
    {
        $kit = new Kit();
        $this->shelf = new Shelf(3);
        $this->guard = new TableGuard(new Tokens(), 'secret');
        $twig = new Environment(new ArrayLoader(['@PhilDataTable/table/stopped.html.twig' => '{{ back }}|{{ messages|join(",") }}']));
        $this->controller = new TableController($kit->tables(new BooksTable($this->shelf)), $kit->operations, $this->guard, $twig, $kit->translator);
    }

    public function testAnActionRunsAndComesBack(): void
    {
        $response = $this->controller->action($this->post(['ids' => ['2', 'x' => ['y']], 'back' => '/books?page=2']), 'books', 'archive');

        self::assertSame([303, '/books?page=2'], [$response->getStatusCode(), $response->headers->get('Location')]);
        self::assertSame([2], $this->shelf->archived);
    }

    public function testARefusedActionSaysSo(): void
    {
        $response = $this->controller->action($this->post(['ids' => ['2'], 'back' => '/books']), 'books', 'refuse');

        self::assertSame([422, '/books|Not this one.'], [$response->getStatusCode(), $response->getContent()]);
    }

    public function testAnEditIsSavedAndComesBack(): void
    {
        $response = $this->controller->edit($this->post(['row' => '1', 'value' => 'New', 'version' => '1', 'back' => '/books']), 'books', 'title');

        self::assertSame([303, 'New'], [$response->getStatusCode(), $this->shelf->books[0]->title]);
    }

    public function testAnInvalidEditOrAConflictSaysSo(): void
    {
        $invalid = $this->controller->edit($this->post(['row' => '1', 'value' => '', 'version' => '1', 'back' => '/books']), 'books', 'title');
        self::assertSame(422, $invalid->getStatusCode());

        $conflict = $this->controller->edit($this->post(['row' => '1', 'value' => 'New', 'version' => '9', 'back' => '/books']), 'books', 'title');
        self::assertSame([409, '/books|table.edit.conflict'], [$conflict->getStatusCode(), $conflict->getContent()]);
    }

    public function testAnUntrustedRequestIsRefused(): void
    {
        foreach ([['_token' => 'bad'], ['seal' => 'bad'], ['context' => '{"limit":1}']] as $forged) {
            try {
                $this->controller->action($this->post(['ids' => ['1'], ...$forged]), 'books', 'archive');
                self::fail('The action ran.');
            } catch (AccessDenied) {
                self::assertSame([], $this->shelf->archived);
            }
        }
    }

    public function testTheContextReachesTheSource(): void
    {
        $this->expectExceptionMessageIsOrContains('does not hold every row');

        $this->controller->action($this->post(['ids' => ['3']], ['limit' => 1]), 'books', 'archive');
    }

    #[DataProvider('backs')]
    public function testThePageToComeBackToIsOfThisSite(string $back, string $expected): void
    {
        $response = $this->controller->action($this->post(['ids' => ['1'], 'back' => $back]), 'books', 'archive');

        self::assertSame($expected, $response->headers->get('Location'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function backs(): iterable
    {
        yield 'a path' => ['/a/b?c=d', '/a/b?c=d'];
        yield 'the root' => ['/', '/'];
        yield 'another host' => ['https://evil.test/', '/'];
        yield 'protocol relative' => ['//evil.test/', '/'];
        yield 'backslash' => ['/\\evil.test/', '/'];
        yield 'inner backslash' => ['/a\\b', '/'];
        yield 'space' => ['/a b', '/'];
        yield 'line break' => ["/a\n", '/'];
        yield 'nothing' => ['', '/'];
    }

    /**
     * @param array<string, mixed>       $fields
     * @param array<string, scalar|null> $context
     */
    private function post(array $fields, array $context = []): Request
    {
        $encoded = $this->guard->encode($context);

        return Request::create('/', 'POST', [...['_token' => 'good', 'context' => $encoded, 'seal' => $this->guard->seal('books', $encoded)], ...$fields]);
    }
}
