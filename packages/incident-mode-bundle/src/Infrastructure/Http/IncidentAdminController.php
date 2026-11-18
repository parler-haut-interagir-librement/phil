<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Infrastructure\Http;

use Phil\IncidentMode\Activation;
use Phil\IncidentMode\Actor;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\Exception\IncidentNotActive;
use Phil\IncidentMode\Exception\InvalidIncident;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentMode\Preset;
use Phil\IncidentModeBundle\Application\Contract\CurrentMember;
use Phil\IncidentModeBundle\Application\DurationInput;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

/**
 * The administration of the incident mode, for the members who hold the configured role: the incidents in force, each
 * with the form that extends it within its preset and the one that lifts it; the presets, each with the form that
 * starts an incident for its proposed duration or another within its longest; and the journal, a page at a time.
 *
 * Every form is posted with a stateless CSRF token, and acts in the name of the member signed in, by the identifier
 * `CurrentMember` gives — never an e-mail address. A refused form is shown again with what went wrong, with the status
 * 422. Without an authorization checker, the page is refused to everyone.
 */
final readonly class IncidentAdminController
{
    public const string INDEX_ROUTE = 'phil_incident_mode_admin';

    public const string START_ROUTE = 'phil_incident_mode_admin_start';

    public const string EXTEND_ROUTE = 'phil_incident_mode_admin_extend';

    public const string LIFT_ROUTE = 'phil_incident_mode_admin_lift';

    public const string ID = '[0-9a-f]{32}';

    public const int DEFAULT_PAGE_SIZE = 20;

    /**
     * @param positive-int $pageSize
     */
    public function __construct(
        private IncidentMode $incidents,
        private CurrentMember $member,
        private AdminRequest $request,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private ?AuthorizationCheckerInterface $authorization,
        private string $role,
        private string $layout,
        private int $pageSize = self::DEFAULT_PAGE_SIZE,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->allow();

        return $this->page(max(1, $request->query->getInt('page', 1)));
    }

    public function start(Request $request): Response
    {
        $actor = $this->actor($request);
        $preset = $request->request->getString('preset');
        $reason = trim($request->request->getString('reason'));

        if (!$this->incidents->presets()->has($preset)) {
            return $this->refused('error.unknown_preset');
        }

        if (mb_strlen($reason) > Activation::MAX_REASON_LENGTH) {
            return $this->refused('error.reason_too_long');
        }

        $duration = $this->duration($request);

        if (!$duration instanceof Duration) {
            return $this->refused('error.invalid_duration');
        }

        if ($duration->isLongerThan($this->incidents->presets()->get($preset)->maximumDuration)) {
            return $this->refused('error.too_long');
        }

        $this->incidents->start($preset, $duration, $actor, '' === $reason ? null : $reason);

        return $this->back();
    }

    public function extend(Request $request, string $id): Response
    {
        $actor = $this->actor($request);
        $duration = $this->duration($request);

        if (!$duration instanceof Duration) {
            return $this->refused('error.invalid_duration');
        }

        try {
            $this->incidents->extend($id, $duration, $actor);
        } catch (IncidentNotActive) {
            return $this->refused('error.not_active');
        } catch (InvalidIncident) {
            return $this->refused('error.too_long');
        }

        return $this->back();
    }

    public function lift(Request $request, string $id): Response
    {
        $actor = $this->actor($request);

        try {
            $this->incidents->lift($id, $actor);
        } catch (IncidentNotActive) {
            return $this->refused('error.not_active');
        }

        return $this->back();
    }

    /**
     * The page, with the status given and the key of the translation of what went wrong.
     *
     * @param positive-int $page
     */
    private function page(int $page, ?string $error = null, int $status = Response::HTTP_OK): Response
    {
        $journal = $this->incidents->journal($this->pageSize + 1, ($page - 1) * $this->pageSize);

        return new Response($this->twig->render('@PhilIncidentMode/admin/incidents.html.twig', [
            'layout' => $this->layout,
            'token' => $this->request->token(),
            'error' => $error,
            'in_force' => array_map($this->inForce(...), $this->incidents->inForceNow()),
            'presets' => array_map(static fn (Preset $preset): array => ['preset' => $preset, 'proposed' => DurationInput::split($preset->defaultDuration)], $this->incidents->presets()->all()),
            'units' => array_keys(DurationInput::UNITS),
            'journal' => \array_slice($journal, 0, $this->pageSize),
            'page' => $page,
            'has_next' => \count($journal) > $this->pageSize,
        ]), $status, ['Cache-Control' => 'no-store']);
    }

    /**
     * An incident in force, with how much longer its preset lets it last: `null` when it may not be extended at all.
     *
     * @return array{activation: Activation, extensible: ?Duration}
     */
    private function inForce(Activation $activation): array
    {
        $extensible = null;

        if ($this->incidents->presets()->has($activation->preset)) {
            $left = $this->incidents->presets()->get($activation->preset)->maximumDuration->seconds - ($activation->endsAt->getTimestamp() - $activation->startedAt->getTimestamp());
            $extensible = $left >= Duration::MINIMUM ? Duration::ofSeconds($left) : null;
        }

        return ['activation' => $activation, 'extensible' => $extensible];
    }

    private function refused(string $error): Response
    {
        return $this->page(1, $error, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function back(): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate(self::INDEX_ROUTE), Response::HTTP_SEE_OTHER);
    }

    /**
     * The duration of the form's amount and unit; `null` when they are no duration within bounds.
     */
    private function duration(Request $request): ?Duration
    {
        try {
            return DurationInput::ofAmount($request->request->getInt('amount'), $request->request->getString('unit'));
        } catch (InvalidIncident) {
            return null;
        }
    }

    /**
     * The member who posts the form, as the actor of what it does, once the role and the token are checked.
     */
    private function actor(Request $request): Actor
    {
        $member = $this->allow();

        if (!$this->request->isTrusted($request)) {
            throw new BadRequestHttpException('The form expired: load the page again.');
        }

        try {
            return Actor::of($member);
        } catch (InvalidIncident $invalidIncident) {
            throw new AccessDeniedException('The member signed in has no stable identifier to act under: the application gives the bundle a CurrentMember that names them without their e-mail address.', $invalidIncident);
        }
    }

    /**
     * @return string the identifier of the member signed in
     */
    private function allow(): string
    {
        $member = $this->member->identifier();

        if (null === $member || !$this->authorization instanceof AuthorizationCheckerInterface || !$this->authorization->isGranted($this->role)) {
            throw new AccessDeniedException('The incident mode is run by the members who hold its role.');
        }

        return $member;
    }
}
