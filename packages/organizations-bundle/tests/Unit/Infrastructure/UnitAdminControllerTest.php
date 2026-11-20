<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Infrastructure;

use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Application\OrganizationAccess;
use Phil\OrganizationsBundle\Application\UnitOfWork;
use Phil\OrganizationsBundle\Application\Units;
use Phil\OrganizationsBundle\Domain\Model\Membership;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Model\Unit;
use Phil\OrganizationsBundle\Domain\Model\UnitMembership;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\OrganizationsBundle\Infrastructure\Http\UnitAdminController;
use Phil\OrganizationsBundle\Infrastructure\Security\UserIdentifierMember;
use Phil\OrganizationsBundle\Infrastructure\Twig\KitComponents;
use Phil\OrganizationsBundle\Infrastructure\Twig\OrganizationsExtension;
use Phil\OrganizationsBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Throwable;
use Twig\Environment;

#[CoversClass(UnitAdminController::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(OrganizationsExtension::class)]
#[CoversClass(KitSubdirectoryPass::class)]
final class UnitAdminControllerTest extends TestCase
{
    private Stage $stage;

    private Organization $acme;

    private Unit $sales;

    private Unit $east;

    private TokenStorage $tokens;

    /** @var list<array{string, array<string, mixed>}> */
    private array $rendered = [];

    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->acme = $this->stage->acme();
        $this->sales = $this->stage->units->create($this->acme->id, Stage::member('ann'), 'Sales');
        $this->east = $this->stage->units->create($this->acme->id, Stage::member('ann'), 'East', $this->sales->id);
        $this->stage->units->addMember($this->sales->id, Stage::member('ann'), Stage::member('cam'), ['manager']);
        $this->tokens = new TokenStorage();
        $this->as('ann');
    }

    public function testTheTree(): void
    {
        $response = $this->controller()->tree($this->acme->id->value);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('page', $response->getContent());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        [$template, $context] = $this->rendered[0];
        self::assertSame('@PhilOrganizations/units/tree.html.twig', $template);
        self::assertEquals($this->acme, $context['organization']);
        self::assertSame([$this->sales->id->value, $this->east->id->value], $this->ids($context['units']));
        $branches = $context['branches'];
        self::assertIsArray($branches);
        self::assertSame(['', $this->sales->id->value], array_keys($branches));
        self::assertSame([$this->sales->id->value], $this->ids($branches['']));
        self::assertSame([$this->east->id->value], $this->ids($branches[$this->sales->id->value]));
        self::assertTrue($context['manages']);
        self::assertNull($context['error']);
        self::assertSame('@PhilOrganizations/units/layout.html.twig', $context['layout']);
        self::assertSame('the-token', $context['token']);

        $this->as('cam');
        $this->controller('admin/units.html.twig')->tree($this->acme->id->value);
        self::assertFalse($this->rendered[1][1]['manages']);
        self::assertSame('admin/units.html.twig', $this->rendered[1][1]['layout']);
    }

    public function testTheTreeShowsArchivedUnits(): void
    {
        $this->stage->units->archive($this->east->id, Stage::member('ann'));
        $this->controller()->tree($this->acme->id->value);

        self::assertSame([$this->sales->id->value, $this->east->id->value], $this->ids($this->rendered[0][1]['units']));
    }

    public function testThePageOfAUnit(): void
    {
        $support = $this->stage->units->create($this->acme->id, Stage::member('ann'), 'Support');
        $this->as('cam');
        $response = $this->controller()->show($this->east->id->value);

        self::assertSame(200, $response->getStatusCode());
        [$template, $context] = $this->rendered[0];
        self::assertSame('@PhilOrganizations/units/unit.html.twig', $template);
        self::assertEquals($this->acme, $context['organization']);
        self::assertEquals($this->east, $context['unit']);
        self::assertSame([$this->sales->id->value], $this->ids($context['ancestors']));
        self::assertSame([], $context['children']);
        self::assertSame([], $context['members']);
        self::assertSame([$this->sales->id->value, $support->id->value], $this->ids($context['parents']));
        $parents = $context['parents'];
        self::assertIsArray($parents);
        self::assertTrue(array_is_list($parents));
        self::assertSame($this->sales->id->value, $context['parent_id']);
        self::assertTrue($context['manages']);
        self::assertTrue($context['manages_parent']);
        self::assertSame(['manager', 'member', 'instructor'], $context['roles']);
        self::assertNull($context['error']);

        $this->controller()->show($this->sales->id->value);
        $context = $this->rendered[1][1];
        self::assertSame([$this->east->id->value], $this->ids($context['children']));
        $members = $context['members'];
        self::assertIsArray($members);
        self::assertContainsOnlyInstancesOf(UnitMembership::class, $members);
        self::assertSame(['cam'], array_map(static fn (UnitMembership $membership): string => $membership->memberId->value, $members));
        self::assertSame([$support->id->value], $this->ids($context['parents']));
        self::assertNull($context['parent_id']);
        self::assertTrue($context['manages']);
        self::assertFalse($context['manages_parent']);

        $this->as('ann');
        $this->controller()->show($this->sales->id->value);
        self::assertTrue($this->rendered[2][1]['manages_parent']);

        $this->stage->units->archive($this->east->id, Stage::member('ann'));
        $this->controller()->show($this->sales->id->value);
        self::assertSame([$this->east->id->value], $this->ids($this->rendered[3][1]['children']));
        self::assertSame([$support->id->value], $this->ids($this->rendered[3][1]['parents']));
    }

    public function testThePagesAreForTheMembersSignedIn(): void
    {
        foreach ([
            [null, 'The units are shown to the members signed in.'],
            ['ann@example.org', 'The units are shown to the members signed in.'],
            ['zed', 'The units are shown to the members of the organization.'],
        ] as [$user, $message]) {
            null === $user ? $this->tokens->setToken(null) : $this->as($user);

            foreach ([fn (): Response => $this->controller()->tree($this->acme->id->value), fn (): Response => $this->controller()->show($this->east->id->value)] as $page) {
                try {
                    $page();
                    self::fail('A page showed.');
                } catch (AccessDeniedException $accessDeniedException) {
                    self::assertSame($message, $accessDeniedException->getMessage());
                }
            }
        }

        $alone = new UnitAdminController($this->stage->units, $this->stage->tree, $this->stage->access, $this->stage->unitAccess, new UserIdentifierMember(), null, $this->csrf(), $this->twig(), $this->urls());
        $this->expectException(AccessDeniedException::class);

        $alone->tree($this->acme->id->value);
    }

    public function testUnknownOrganizationsAndUnitsAreNotFound(): void
    {
        foreach ([
            fn (): Response => $this->controller()->tree('nowhere'),
            fn (): Response => $this->controller()->tree('not an id'),
            fn (): Response => $this->controller()->show('nowhere'),
            fn (): Response => $this->controller()->show('not an id'),
            fn (): Response => $this->controller()->rename($this->post(['name' => 'Mine']), 'nowhere'),
            fn (): Response => $this->controller()->move($this->post(['parent' => 'not an id']), $this->east->id->value),
            fn (): Response => $this->controller()->create($this->post(['name' => 'Mine', 'parent' => 'nowhere']), $this->acme->id->value),
        ] as $call) {
            try {
                $call();
                self::fail('An unknown unit was found.');
            } catch (NotFoundHttpException) {
            }
        }

        self::assertSame([], $this->rendered);
    }

    public function testChangesGoBackToThePage(): void
    {
        $created = $this->controller()->create($this->post(['name' => 'West', 'parent' => $this->sales->id->value]), $this->acme->id->value);
        $west = array_first(array_filter($this->stage->tree->units($this->acme->id), static fn (Unit $unit): bool => 'West' === $unit->name->value)) ?? self::fail('No unit "West".');
        self::assertInstanceOf(RedirectResponse::class, $created);
        self::assertSame(303, $created->getStatusCode());
        self::assertSame('/units/'.$west->id->value, $created->getTargetUrl());
        self::assertSame($this->sales->id->value, $west->parentId()?->value);

        $root = $this->controller()->create($this->post(['name' => 'Support', 'parent' => '']), $this->acme->id->value);
        self::assertInstanceOf(RedirectResponse::class, $root);
        self::assertCount(2, $this->stage->tree->roots($this->acme->id));

        $target = '/units/'.$this->east->id->value;
        self::assertSame($target, $this->target($this->controller()->rename($this->post(['name' => 'East coast']), $this->east->id->value)));
        self::assertSame('East coast', $this->stage->tree->get($this->east->id)->name->value);
        self::assertSame($target, $this->target($this->controller()->move($this->post(['parent' => '']), $this->east->id->value)));
        self::assertNull($this->stage->tree->get($this->east->id)->parentId());
        self::assertSame($target, $this->target($this->controller()->move($this->post(['parent' => $west->id->value]), $this->east->id->value)));
        self::assertSame($west->id->value, $this->stage->tree->get($this->east->id)->parentId()?->value);
        self::assertSame($target, $this->target($this->controller()->archive($this->post([]), $this->east->id->value)));
        self::assertFalse($this->stage->tree->get($this->east->id)->isActive());
        self::assertSame($target, $this->target($this->controller()->restore($this->post([]), $this->east->id->value)));
        self::assertTrue($this->stage->tree->get($this->east->id)->isActive());
        self::assertSame($target, $this->target($this->controller()->addMember($this->post(['member' => ' bob ', 'roles' => '  manager   instructor ']), $this->east->id->value)));
        self::assertSame(['instructor', 'manager'], $this->stage->tree->members($this->east->id)[0]->roles->names);
        self::assertSame($target, $this->target($this->controller()->removeMember($this->post([]), $this->east->id->value, 'bob')));
        self::assertSame([], $this->stage->tree->members($this->east->id));
        self::assertSame([], $this->rendered);
    }

    /**
     * @return iterable<string, array{string, array<string, string>, string}>
     */
    public static function refusals(): iterable
    {
        yield 'a cycle' => ['move', ['parent' => 'east'], 'cycle'];
        yield 'an unknown role' => ['addMember', ['member' => 'bob', 'roles' => 'owner'], 'unknown_role'];
        yield 'a member there already' => ['addMember', ['member' => 'cam', 'roles' => 'member'], 'already_member'];
        yield 'an empty name' => ['rename', ['name' => ''], 'invalid'];
        yield 'no role' => ['addMember', ['member' => 'bob', 'roles' => '  '], 'invalid'];
    }

    /**
     * @param array<string, string> $form
     */
    #[DataProvider('refusals')]
    public function testARefusedChangeShowsThePageAgainWithItsReason(string $action, array $form, string $reason): void
    {
        if (isset($form['parent'])) {
            $form['parent'] = $this->east->id->value;
        }

        $controller = $this->controller();
        $request = $this->post($form);
        $unit = $this->sales->id->value;
        $response = match ($action) {
            'move' => $controller->move($request, $unit),
            'rename' => $controller->rename($request, $unit),
            default => $controller->addMember($request, $unit),
        };

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('@PhilOrganizations/units/unit.html.twig', $this->rendered[0][0]);
        self::assertSame($reason, $this->rendered[0][1]['error']);
        self::assertEquals($this->sales->id, $this->renderedUnit(0)->id);
    }

    public function testRefusalsOfArchivedUnitsAndOrganizations(): void
    {
        $this->stage->units->archive($this->sales->id, Stage::member('ann'));
        $response = $this->controller()->rename($this->post(['name' => 'Mine']), $this->sales->id->value);
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('unit_archived', $this->rendered[0][1]['error']);
        self::assertFalse($this->renderedUnit(0)->isActive());

        $this->stage->organizations->archive($this->acme->id, Stage::member('ann'));
        $tree = $this->controller()->create($this->post(['name' => 'Mine']), $this->acme->id->value);
        self::assertSame(422, $tree->getStatusCode());
        self::assertSame('@PhilOrganizations/units/tree.html.twig', $this->rendered[1][0]);
        self::assertSame('organization_archived', $this->rendered[1][1]['error']);
    }

    public function testAChangeTheMemberMayNotMakeIsDenied(): void
    {
        $this->as('cam');

        foreach ([
            fn (): Response => $this->controller()->create($this->post(['name' => 'Mine']), $this->acme->id->value),
            fn (): Response => $this->controller()->archive($this->post([]), $this->sales->id->value),
            fn (): Response => $this->controller()->removeMember($this->post([]), $this->sales->id->value, 'ann'),
        ] as $change) {
            try {
                $change();
                self::fail('The change was made.');
            } catch (AccessDeniedException $accessDeniedException) {
                self::assertInstanceOf(Throwable::class, $accessDeniedException->getPrevious());
            }
        }
    }

    public function testAFormFromAnotherSiteOrWithoutItsTokenIsRefused(): void
    {
        $crossSite = $this->post(['name' => 'Mine']);
        $crossSite->headers->set('Sec-Fetch-Site', 'cross-site');

        $sameSite = $this->post(['name' => 'Mine'], 'forged');
        $sameSite->headers->set('Sec-Fetch-Site', 'same-origin');

        foreach ([$crossSite, $this->post(['name' => 'Mine'], 'forged'), $sameSite] as $request) {
            try {
                $this->controller()->rename($request, $this->sales->id->value);
                self::fail('A forged form was taken.');
            } catch (BadRequestHttpException $badRequestHttpException) {
                self::assertSame('The form expired: load the page again.', $badRequestHttpException->getMessage());
            }
        }

        try {
            $this->controller()->create($this->post(['name' => 'Mine'], 'forged'), $this->acme->id->value);
            self::fail('A forged form created a unit.');
        } catch (BadRequestHttpException) {
        }

        self::assertCount(2, $this->stage->tree->units($this->acme->id));

        $valid = $this->post(['name' => 'Mine']);
        $valid->headers->set('Sec-Fetch-Site', 'same-origin');
        self::assertSame(303, $this->controller()->rename($valid, $this->sales->id->value)->getStatusCode());

        try {
            $this->controller()->addMember($this->post(['member' => 'ann@example.org', 'roles' => 'member']), $this->sales->id->value);
            self::fail('A member named by an address was taken.');
        } catch (BadRequestHttpException $badRequestHttpException) {
            self::assertSame('A member is named by their identifier.', $badRequestHttpException->getMessage());
        }
    }

    public function testTheRolesOfAFormMayEndWithALineBreak(): void
    {
        $target = $this->target($this->controller()->addMember($this->post(['member' => 'bob', 'roles' => "manager\r\n"]), $this->east->id->value));

        self::assertSame('/units/'.$this->east->id->value, $target);
        self::assertSame(['manager'], $this->stage->tree->members($this->east->id)[0]->roles->names);
    }

    public function testAnOrganizationGoneDuringAChangeIsNotFound(): void
    {
        $real = $this->stage->store;
        $store = self::createStub(OrganizationStore::class);
        $store->method('organization')->willReturnCallback(static fn (OrganizationId $id, bool $lock = false): ?Organization => $lock ? null : $real->organization($id));
        $store->method('membership')->willReturnCallback(static fn (OrganizationId $organization, MemberId $member): ?Membership => $real->membership($organization, $member));
        $store->method('transactional')->willReturnCallback(static fn (callable $work): mixed => $work());
        $access = new OrganizationAccess($store, $this->stage->roles);
        $units = new Units($store, $this->stage->unitStore, new UnitOfWork($store), $access, $this->stage->unitAccess, $this->stage->clock);
        $controller = new UnitAdminController($units, $this->stage->tree, $access, $this->stage->unitAccess, new UserIdentifierMember(), $this->tokens, $this->csrf(), $this->twig(), $this->urls(), UnitAdminController::LAYOUT);

        $this->expectException(NotFoundHttpException::class);

        $controller->create($this->post(['name' => 'Mine']), $this->acme->id->value);
    }

    public function testTheNamesOfTheRecipes(): void
    {
        self::assertSame('Button', new KitComponents()->name('Button'));
        self::assertSame('ui:Button', new KitComponents('ui')->name('Button'));
        self::assertSame('ui:kit:Table:Row', new KitComponents('/ui/kit/')->name('Table:Row'));
        self::assertSame('ui:Tree', new OrganizationsExtension(new KitComponents('ui'))->kit('Tree'));

        $container = new ContainerBuilder();
        new KitSubdirectoryPass()->process($container);
        $container->register(KitComponents::class)->setArguments([null]);
        $container->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new KitSubdirectoryPass()->process($container);
        self::assertSame('ui', $container->getDefinition(KitComponents::class)->getArgument(0));

        $container->getDefinition(KitComponents::class)->setArgument(0, 'mine');
        new KitSubdirectoryPass()->process($container);
        self::assertSame('mine', $container->getDefinition(KitComponents::class)->getArgument(0));

        $plain = new ContainerBuilder();
        $plain->register(KitComponents::class)->setArguments([null]);
        new KitSubdirectoryPass()->process($plain);
        self::assertSame('', $plain->getDefinition(KitComponents::class)->getArgument(0));

        $other = new ContainerBuilder();
        $other->register(KitComponents::class)->setArguments([null]);
        $other->setParameter('.ux_toolkit.components.subdirectory', ['not', 'text']);
        new KitSubdirectoryPass()->process($other);
        self::assertSame('', $other->getDefinition(KitComponents::class)->getArgument(0));
    }

    private function as(string $user): void
    {
        $this->tokens->setToken(new UsernamePasswordToken(new InMemoryUser($user, null), 'main'));
    }

    private function controller(string $layout = UnitAdminController::LAYOUT): UnitAdminController
    {
        return new UnitAdminController($this->stage->units, $this->stage->tree, $this->stage->access, $this->stage->unitAccess, new UserIdentifierMember(), $this->tokens, $this->csrf(), $this->twig(), $this->urls(), $layout);
    }

    private function csrf(): CsrfTokenManagerInterface
    {
        $csrf = self::createStub(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->willReturn(new CsrfToken(UnitAdminController::TOKEN_ID, 'the-token'));
        $csrf->method('isTokenValid')->willReturnCallback(static fn (CsrfToken $token): bool => UnitAdminController::TOKEN_ID === $token->getId() && 'the-token' === $token->getValue());

        return $csrf;
    }

    private function twig(): Environment
    {
        $twig = self::createStub(Environment::class);
        $twig->method('render')->willReturnCallback(function (string $template, array $context): string {
            /** @var array<string, mixed> $context */
            $this->rendered[] = [$template, $context];

            return 'page';
        });

        return $twig;
    }

    private function urls(): UrlGeneratorInterface
    {
        $urls = self::createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route, array $parameters): string => UnitAdminController::UNIT_ROUTE === $route && \is_string($parameters['unit'] ?? null) ? '/units/'.$parameters['unit'] : '/'.$route);

        return $urls;
    }

    /**
     * @param array<string, string> $form
     */
    private function post(array $form, string $token = 'the-token'): Request
    {
        return new Request(request: ['_token' => $token] + $form, server: ['REQUEST_METHOD' => 'POST']);
    }

    private function renderedUnit(int $page): Unit
    {
        $unit = $this->rendered[$page][1]['unit'] ?? null;
        self::assertInstanceOf(Unit::class, $unit);

        return $unit;
    }

    private function target(Response $response): string
    {
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(303, $response->getStatusCode());

        return $response->getTargetUrl();
    }

    /**
     * @return list<string>
     */
    private function ids(mixed $units): array
    {
        self::assertIsArray($units);
        self::assertContainsOnlyInstancesOf(Unit::class, $units);

        return array_values(array_map(static fn (Unit $unit): string => $unit->id->value, $units));
    }
}
