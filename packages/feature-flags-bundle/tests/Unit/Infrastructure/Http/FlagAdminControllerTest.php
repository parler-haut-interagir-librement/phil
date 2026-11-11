<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Infrastructure\Http;

use Closure;
use Override;
use Phil\FeatureFlagsBundle\Application\Admin\ActorProvider;
use Phil\FeatureFlagsBundle\Application\Edit\FlagEditor;
use Phil\FeatureFlagsBundle\Application\Edit\WritableFlagDefinitions;
use Phil\FeatureFlagsBundle\Application\Flag\FlagCatalog;
use Phil\FeatureFlagsBundle\Application\Toggle\FlagToggler;
use Phil\FeatureFlagsBundle\Domain\Edit\RevisedDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Infrastructure\Http\FlagAdminController;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\InMemoryDefinitions;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\InMemoryFlagStore;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\ListAuthorizer;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\RecordingAuditor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The admin without the database store: no form to create or edit a flag.
 */
#[CoversClass(FlagAdminController::class)]
final class FlagAdminControllerTest extends TestCase
{
    public function testWithoutAnEditorNoFlagIsCreatedOrEdited(): void
    {
        $definitions = new InMemoryDefinitions();
        $definitions->definitions['beta'] = new RevisedDefinition(new FlagDefinition(FlagName::of('beta'), true), 1);
        $controller = $this->controller(null, $definitions);

        $this->assertNotFound(static fn (): mixed => $controller->create(Request::create('/admin/flags/_new')));
        $this->assertNotFound(static fn (): mixed => $controller->edit(Request::create('/admin/flags/beta/edit'), 'beta'));
    }

    public function testWithoutDefinitionsNoFlagIsEdited(): void
    {
        $editor = new FlagEditor(new ListAuthorizer(['operator']), new InMemoryDefinitions(), new InMemoryFlagStore(), new RecordingAuditor(), new FrozenClock(), new FlagCatalog([]));
        $controller = $this->controller($editor, null);

        $this->assertNotFound(static fn (): mixed => $controller->edit(Request::create('/admin/flags/beta/edit'), 'beta'));
    }

    /**
     * The flag is gone by the time the edit is written: its page is not found.
     */
    public function testAFlagRemovedBeforeItsEditIsNotFound(): void
    {
        $read = new InMemoryDefinitions();
        $read->definitions['beta'] = new RevisedDefinition(new FlagDefinition(FlagName::of('beta'), true), 1);
        $editor = new FlagEditor(new ListAuthorizer(['operator']), new InMemoryDefinitions(), new InMemoryFlagStore(), new RecordingAuditor(), new FrozenClock(), new FlagCatalog([]));
        $request = Request::create('/admin/flags/beta/edit', 'POST', ['revision' => '1', 'definition' => '{"rules": []}', 'description' => '', 'owner' => '', 'kind' => '', 'expires_on' => '', 'reason' => 'Editing.']);
        $request->headers->set('Sec-Fetch-Site', 'same-origin');

        try {
            $this->controller($editor, $read)->edit($request, 'beta');
            self::fail('The edit was written.');
        } catch (NotFoundHttpException $notFoundHttpException) {
            self::assertStringContainsString('beta', $notFoundHttpException->getMessage());
        }
    }

    private function assertNotFound(Closure $call): void
    {
        try {
            $call();
            self::fail('The page was found.');
        } catch (NotFoundHttpException $notFoundHttpException) {
            self::assertSame('', $notFoundHttpException->getMessage());
        }
    }

    private function controller(?FlagEditor $editor, ?WritableFlagDefinitions $definitions): FlagAdminController
    {
        $actors = new class implements ActorProvider {
            #[Override]
            public function current(): string
            {
                return 'operator';
            }
        };

        return new FlagAdminController(
            new FlagCatalog([]),
            new FlagToggler(new ListAuthorizer(['operator']), new InMemoryFlagStore(), new RecordingAuditor(), new FrozenClock()),
            $actors,
            new Environment(new ArrayLoader()),
            self::createStub(UrlGeneratorInterface::class),
            new FrozenClock(),
            'layout.html.twig',
            $editor,
            $definitions,
        );
    }
}
