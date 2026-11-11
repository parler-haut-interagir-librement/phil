<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Infrastructure\Http;

use Override;
use Phil\FeatureFlagsBundle\Application\Admin\ActorProvider;
use Phil\FeatureFlagsBundle\Application\Edit\FlagEditor;
use Phil\FeatureFlagsBundle\Application\Flag\FlagCatalog;
use Phil\FeatureFlagsBundle\Application\Toggle\FlagToggler;
use Phil\FeatureFlagsBundle\Domain\Edit\RevisedDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Infrastructure\Configuration\FlagDefinitionFormat;
use Phil\FeatureFlagsBundle\Infrastructure\Http\FlagAdminController;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\InMemoryDefinitions;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\InMemoryFlagStore;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\ListAuthorizer;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\RecordingAuditor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The creation and edit forms with the database store, rendered by a template that prints what it is given.
 */
#[CoversClass(FlagAdminController::class)]
final class FlagAdminControllerEditTest extends TestCase
{
    private InMemoryDefinitions $definitions;

    #[Override]
    protected function setUp(): void
    {
        $this->definitions = new InMemoryDefinitions();
        $this->definitions->definitions['beta'] = new RevisedDefinition(FlagDefinitionFormat::read(FlagName::of('beta'), [
            'active' => true,
            'rules' => [['attribute' => 'tenant', 'members' => ['team/a']]],
            'description' => 'The new checkout.',
            'owner' => 'checkout-team',
            'kind' => 'release',
            'expires_on' => '2027-03-31',
        ]), 1);
    }

    public function testTheCreationFormStartsEmpty(): void
    {
        $page = $this->page($this->controller()->create(Request::create('/admin/flags/_new')));

        self::assertSame(['name' => '', 'definition' => "{\n    \"rules\": []\n}", 'description' => '', 'owner' => '', 'kind' => '', 'expires_on' => ''], $page['form']);
        self::assertNull($page['revision']);
        self::assertSame('layout.html.twig', $page['layout']);
        self::assertSame('2026-01-01T00:00:00+00:00', $page['now']);
    }

    public function testTheEditFormShowsTheStoredDefinition(): void
    {
        $page = $this->page($this->controller()->edit(Request::create('/admin/flags/beta/edit'), 'beta'));

        self::assertSame([
            'name' => 'beta',
            'definition' => json_encode(['rules' => [['attribute' => 'tenant', 'members' => ['team/a']]]], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES),
            'description' => 'The new checkout.',
            'owner' => 'checkout-team',
            'kind' => 'release',
            'expires_on' => '2027-03-31',
        ], $page['form']);
        self::assertSame(1, $page['revision']);
    }

    public function testAFlagWithoutRulesIsEditedFromAnEmptyListOfRules(): void
    {
        $this->definitions->definitions['beta'] = new RevisedDefinition(FlagDefinitionFormat::read(FlagName::of('beta'), ['active' => true]), 3);

        $page = $this->page($this->controller()->edit(Request::create('/admin/flags/beta/edit'), 'beta'));

        self::assertSame(['name' => 'beta', 'definition' => "{\n    \"rules\": []\n}", 'description' => '', 'owner' => '', 'kind' => '', 'expires_on' => ''], $page['form']);
    }

    public function testAnEditPostedFromThisSiteIsWrittenWithItsFieldsAndBlankFieldsLeftOut(): void
    {
        $response = $this->controller()->edit($this->post(['definition' => '{"rules": [{"attribute": "tenant", "percentage": 20}]}', 'description' => 'Rolled out.', 'kind' => '  ', 'expires_on' => ' ']), 'beta');

        self::assertSame(Response::HTTP_SEE_OTHER, $response->getStatusCode());
        self::assertSame('phil_feature_flags_admin_show?name=beta&done=edited', $response->headers->get('Location'));
        $stored = $this->definitions->definitions['beta'];
        self::assertSame(2, $stored->revision);
        self::assertSame(
            ['active' => true, 'rules' => [['attribute' => 'tenant', 'percentage' => 20]], 'description' => 'Rolled out.', 'owner' => 'checkout-team'],
            FlagDefinitionFormat::write($stored->definition),
        );
    }

    public function testAnEditPostedFromAnotherSiteIsRefused(): void
    {
        $request = Request::create('/admin/flags/beta/edit', 'POST', ['revision' => '1', 'definition' => '{"rules": []}', 'reason' => 'Editing.']);

        $this->expectException(AccessDeniedHttpException::class);

        $this->controller()->edit($request, 'beta');
    }

    public function testNobodyAuthenticatedIsRefused(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessageIsOrContains('The flag admin needs an authenticated operator.');

        $this->controller(actor: null)->list();
    }

    public function testADefinitionWithOtherKeysIsShownAgainWithTheFlagsName(): void
    {
        $response = $this->controller()->edit($this->post(['definition' => '{"rules": [], "active": false}']), 'beta');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $page = $this->page($response);
        self::assertSame('The definition is a JSON object with "rules" and, for an A/B test, "experiment".', $page['error']);
        self::assertSame('beta', $page['form']['name']);
        self::assertSame(1, $page['revision']);
        self::assertSame(1, $this->definitions->definitions['beta']->revision);
    }

    public function testADefinitionThatIsNotJsonIsShownAgain(): void
    {
        $response = $this->controller()->edit($this->post(['definition' => '{"rules": [']), 'beta');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringStartsWith('Syntax error', (string) $this->page($response)['error'], 'PHP 8.6 adds where the error is');
    }

    /**
     * Sixteen levels are read, then refused as a definition; seventeen are not read at all.
     */
    public function testADefinitionIsReadSixteenLevelsDeep(): void
    {
        foreach ([11 => false, 12 => true] as $nesting => $tooDeep) {
            $definition = '{"rules": [{"attribute": "tenant", "members": ['.str_repeat('[', $nesting).str_repeat(']', $nesting).']}]}';
            $response = $this->controller()->edit($this->post(['definition' => $definition]), 'beta');

            self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
            self::assertStringStartsWith($tooDeep ? 'Maximum stack depth exceeded' : '"members" is a list of identifiers.', (string) $this->page($response)['error']);
        }
    }

    public function testAnOperatorWithoutThePermissionIsShownTheFormAgain(): void
    {
        $response = $this->controller(authorized: 'someone else')->edit($this->post(['definition' => '{"rules": []}']), 'beta');

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $page = $this->page($response);
        self::assertStringContainsString('the actor lacks the permission.', (string) $page['error']);
        self::assertSame('beta', $page['form']['name']);
    }

    public function testAnEditMadeFromAnEarlierRevisionIsAConflict(): void
    {
        $this->definitions->definitions['beta'] = new RevisedDefinition($this->definitions->definitions['beta']->definition, 2);

        $response = $this->controller()->edit($this->post(['definition' => '{"rules": []}']), 'beta');

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $page = $this->page($response);
        self::assertSame('The flag "beta" changed since revision 1; read it again before editing it.', $page['error']);
        self::assertSame('beta', $page['form']['name']);
    }

    /**
     * @param array<string, string> $fields
     */
    private function post(array $fields): Request
    {
        $request = Request::create('/admin/flags/beta/edit', 'POST', $fields + ['revision' => '1', 'description' => '', 'owner' => 'checkout-team', 'kind' => '', 'expires_on' => '', 'reason' => 'Editing.']);
        $request->headers->set('Sec-Fetch-Site', 'same-origin');

        return $request;
    }

    /**
     * @return array{form: array<string, string>, revision: int|null, error: string|null, layout: string, now: string}
     */
    private function page(Response $response): array
    {
        /** @var array{form: array<string, string>, revision: int|null, error: string|null, layout: string, now: string} */
        return json_decode((string) $response->getContent(), true, 8, \JSON_THROW_ON_ERROR);
    }

    private function controller(?string $actor = 'operator', string $authorized = 'operator'): FlagAdminController
    {
        $actors = new readonly class($actor) implements ActorProvider {
            public function __construct(
                private ?string $actor,
            ) {
            }

            #[Override]
            public function current(): ?string
            {
                return $this->actor;
            }
        };
        $authorizer = new ListAuthorizer([$authorized]);
        $urls = self::createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route, array $parameters): string => $route.'?'.http_build_query($parameters));
        $twig = new Environment(new ArrayLoader([
            '@PhilFeatureFlags/admin/form.html.twig' => '{{ {form: form, revision: revision, error: error, layout: layout, now: now|date("c")}|json_encode|raw }}',
            '@PhilFeatureFlags/admin/list.html.twig' => 'list',
        ]));

        return new FlagAdminController(
            new FlagCatalog([]),
            new FlagToggler($authorizer, new InMemoryFlagStore(), new RecordingAuditor(), new FrozenClock()),
            $actors,
            $twig,
            $urls,
            new FrozenClock(),
            'layout.html.twig',
            new FlagEditor($authorizer, $this->definitions, new InMemoryFlagStore(), new RecordingAuditor(), new FrozenClock(), new FlagCatalog([])),
            $this->definitions,
        );
    }
}
