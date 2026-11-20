<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Infrastructure\Http;

use Closure;
use Phil\OrganizationsBundle\Application\Contract\MemberOfUser;
use Phil\OrganizationsBundle\Application\Exception\AlreadyMember;
use Phil\OrganizationsBundle\Application\Exception\NotAMember;
use Phil\OrganizationsBundle\Application\Exception\OrganizationArchived;
use Phil\OrganizationsBundle\Application\Exception\PermissionDenied;
use Phil\OrganizationsBundle\Application\Exception\UnitArchived;
use Phil\OrganizationsBundle\Application\Exception\UnitCycle;
use Phil\OrganizationsBundle\Application\Exception\UnknownOrganization;
use Phil\OrganizationsBundle\Application\Exception\UnknownRole;
use Phil\OrganizationsBundle\Application\Exception\UnknownUnit;
use Phil\OrganizationsBundle\Application\OrganizationAccess;
use Phil\OrganizationsBundle\Application\Permission;
use Phil\OrganizationsBundle\Application\UnitAccess;
use Phil\OrganizationsBundle\Application\Units;
use Phil\OrganizationsBundle\Application\UnitTree;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Exception\OrganizationsException;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Model\Unit;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitId;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * The administration pages of the units, for the members of the organization signed in: the tree of an organization,
 * with the form of a new unit, and the page of a unit — its ancestors, its children, its members, and the forms that
 * rename, move, archive or restore it and add or remove a member.
 *
 * The pages show to every member of the organization; each change is made by `Units` in the name of the member signed
 * in, which checks their permissions. A change refused for its content — a cycle, an archived unit, a member already
 * there, an unknown role — shows the page again with the reason and a `422`; a change the member may not make is a
 * `403`, an unknown unit a `404`. Every form is posted with Symfony's stateless CSRF token `phil_organizations_units`
 * from this site, and every change answers with a `303` to the page.
 */
final readonly class UnitAdminController
{
    public const string TREE_ROUTE = 'phil_organizations_units';

    public const string CREATE_ROUTE = 'phil_organizations_units_create';

    public const string UNIT_ROUTE = 'phil_organizations_unit';

    public const string TOKEN_ID = 'phil_organizations_units';

    public const string LAYOUT = '@PhilOrganizations/units/layout.html.twig';

    /** The members a unit page lists. */
    public const int MEMBERS_SHOWN = 100;

    public function __construct(
        private Units $units,
        private UnitTree $tree,
        private OrganizationAccess $access,
        private UnitAccess $unitAccess,
        private MemberOfUser $members,
        private ?TokenStorageInterface $tokenStorage,
        private CsrfTokenManagerInterface $csrf,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private string $layout = self::LAYOUT,
    ) {
    }

    public function tree(string $organization): Response
    {
        return $this->treePage($this->organization($organization), $this->member());
    }

    public function create(Request $request, string $organization): Response
    {
        $member = $this->member();
        $organizationModel = $this->organization($organization);
        $this->trust($request);
        $parent = $request->request->getString('parent');

        return $this->change(
            fn (): Unit => $this->units->create($organizationModel->id, $member, $request->request->getString('name'), '' === $parent ? null : $this->unitId($parent)),
            fn (string $error): Response => $this->treePage($organizationModel, $member, $error),
            fn (Unit $unit): string => $this->urls->generate(self::UNIT_ROUTE, ['unit' => $unit->id->value]),
        );
    }

    public function show(string $unit): Response
    {
        return $this->unitPage($this->unit($unit), $this->member());
    }

    public function rename(Request $request, string $unit): Response
    {
        return $this->onUnit($request, $unit, fn (Unit $model, MemberId $member): mixed => $this->units->rename($model->id, $member, $request->request->getString('name')));
    }

    public function move(Request $request, string $unit): Response
    {
        $parent = $request->request->getString('parent');

        return $this->onUnit($request, $unit, fn (Unit $model, MemberId $member): mixed => $this->units->move($model->id, $member, '' === $parent ? null : $this->unitId($parent)));
    }

    public function archive(Request $request, string $unit): Response
    {
        return $this->onUnit($request, $unit, fn (Unit $model, MemberId $member): mixed => $this->units->archive($model->id, $member));
    }

    public function restore(Request $request, string $unit): Response
    {
        return $this->onUnit($request, $unit, fn (Unit $model, MemberId $member): mixed => $this->units->restore($model->id, $member));
    }

    public function addMember(Request $request, string $unit): Response
    {
        $roles = array_values(array_filter(explode(' ', trim($request->request->getString('roles'))), static fn (string $role): bool => '' !== $role));

        return $this->onUnit($request, $unit, fn (Unit $model, MemberId $member): mixed => $this->units->addMember($model->id, $member, $this->memberId($request->request->getString('member')), $roles));
    }

    public function removeMember(Request $request, string $unit, string $member): Response
    {
        return $this->onUnit($request, $unit, function (Unit $model, MemberId $actor) use ($member): null {
            $this->units->removeMember($model->id, $actor, $this->memberId($member));

            return null;
        });
    }

    /**
     * @param Closure(Unit, MemberId): mixed $work
     */
    private function onUnit(Request $request, string $unit, Closure $work): Response
    {
        $member = $this->member();
        $model = $this->unit($unit);
        $this->trust($request);

        return $this->change(
            static fn (): mixed => $work($model, $member),
            fn (string $error): Response => $this->unitPage($this->unit($unit), $member, $error),
            fn (): string => $this->urls->generate(self::UNIT_ROUTE, ['unit' => $model->id->value]),
        );
    }

    /**
     * @template T
     *
     * @param Closure(): T              $work
     * @param Closure(string): Response $refused the page again, with the reason, and a `422`
     * @param Closure(T): string        $next    where to go once it is done
     */
    private function change(Closure $work, Closure $refused, Closure $next): Response
    {
        try {
            $result = $work();
        } catch (PermissionDenied|NotAMember $exception) {
            throw new AccessDeniedException($exception->getMessage(), $exception);
        } catch (UnknownUnit|UnknownOrganization $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        } catch (OrganizationsException $exception) {
            return $refused($this->reason($exception));
        }

        return new RedirectResponse($next($result), Response::HTTP_SEE_OTHER);
    }

    private function reason(OrganizationsException $exception): string
    {
        return match (true) {
            $exception instanceof UnitCycle => 'cycle',
            $exception instanceof UnitArchived => 'unit_archived',
            $exception instanceof OrganizationArchived => 'organization_archived',
            $exception instanceof AlreadyMember => 'already_member',
            $exception instanceof UnknownRole => 'unknown_role',
            default => 'invalid',
        };
    }

    private function treePage(Organization $organization, MemberId $member, ?string $error = null): Response
    {
        $this->view($organization->id, $member);
        $units = $this->tree->units($organization->id, true);
        $branches = [];

        foreach ($units as $unit) {
            $branches[$unit->parentId()->value ?? ''][] = $unit;
        }

        return $this->render('tree', [
            'organization' => $organization,
            'units' => $units,
            'branches' => $branches,
            'manages' => $this->access->can($organization->id, $member, Permission::UNITS),
            'error' => $error,
        ], $error);
    }

    private function unitPage(Unit $unit, MemberId $member, ?string $error = null): Response
    {
        $organization = $this->organization($unit->organizationId->value);
        $this->view($organization->id, $member);
        $parentId = $unit->parentId();
        $managesParent = $parentId instanceof UnitId ? $this->unitAccess->can($parentId, $member, Permission::UNIT_MANAGE) : $this->access->can($organization->id, $member, Permission::UNITS);

        return $this->render('unit', [
            'organization' => $organization,
            'unit' => $unit,
            'ancestors' => $this->tree->ancestors($unit->id),
            'children' => $this->tree->children($unit->id, true),
            'members' => $this->tree->members($unit->id, self::MEMBERS_SHOWN),
            'parents' => array_values(array_filter($this->tree->units($organization->id), static fn (Unit $other): bool => !$other->path->isWithin($unit->path))),
            'parent_id' => $parentId?->value,
            'manages' => $this->unitAccess->can($unit, $member, Permission::UNIT_MANAGE),
            'manages_parent' => $managesParent,
            'roles' => $this->unitAccess->roles()->names(),
            'error' => $error,
        ], $error);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $page, array $context, ?string $error): Response
    {
        return new Response($this->twig->render('@PhilOrganizations/units/'.$page.'.html.twig', $context + [
            'layout' => $this->layout,
            'token' => $this->csrf->getToken(self::TOKEN_ID)->getValue(),
        ]), null === $error ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY, ['Cache-Control' => 'no-store']);
    }

    /**
     * @throws AccessDeniedException when the member is not a member of the organization
     */
    private function view(OrganizationId $organizationId, MemberId $member): void
    {
        if (!$this->access->can($organizationId, $member, Permission::VIEW)) {
            throw new AccessDeniedException('The units are shown to the members of the organization.');
        }
    }

    /**
     * @throws AccessDeniedException when nobody is signed in, or the user is no member
     */
    private function member(): MemberId
    {
        $user = $this->tokenStorage?->getToken()?->getUser();
        $member = $user instanceof UserInterface ? $this->members->memberId($user) : null;

        return $member ?? throw new AccessDeniedException('The units are shown to the members signed in.');
    }

    /**
     * @throws BadRequestHttpException when the form was sent from another site or its token is not valid
     */
    private function trust(Request $request): void
    {
        if ('cross-site' === $request->headers->get('Sec-Fetch-Site') || !$this->csrf->isTokenValid(new CsrfToken(self::TOKEN_ID, $request->request->getString('_token')))) {
            throw new BadRequestHttpException('The form expired: load the page again.');
        }
    }

    private function organization(string $id): Organization
    {
        try {
            return $this->access->organization(OrganizationId::fromString($id));
        } catch (InvalidOrganization|UnknownOrganization $exception) {
            throw new NotFoundHttpException('No such organization.', $exception);
        }
    }

    private function unit(string $id): Unit
    {
        try {
            return $this->tree->get($this->unitId($id));
        } catch (UnknownUnit $unknownUnit) {
            throw new NotFoundHttpException('No such unit.', $unknownUnit);
        }
    }

    private function unitId(string $id): UnitId
    {
        try {
            return UnitId::fromString($id);
        } catch (InvalidOrganization $invalidOrganization) {
            throw new NotFoundHttpException('No such unit.', $invalidOrganization);
        }
    }

    private function memberId(string $id): MemberId
    {
        try {
            return MemberId::fromString(trim($id));
        } catch (InvalidOrganization $invalidOrganization) {
            throw new BadRequestHttpException('A member is named by their identifier.', $invalidOrganization);
        }
    }
}
