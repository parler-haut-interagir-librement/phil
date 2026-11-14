<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Http;

use Phil\PrivacyBundle\Application\AccountDeletion\AccountDeletions;
use Phil\PrivacyBundle\Application\Contract\CurrentDataSubject;
use Phil\PrivacyBundle\Application\EmailTracking\EmailTrackingConsents;
use Phil\PrivacyBundle\Application\Exception\AccountDeletionBusy;
use Phil\PrivacyBundle\Application\PersonalDataDownload;
use Phil\PrivacyBundle\Application\PrivacyRights;
use Phil\PrivacyBundle\Domain\AccountDeletion\AccountDeletion;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Infrastructure\Routing\PrivacyRoutes;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The rights of a member, from their preferences: their data in one archive, the deletion of their account, and, when
 * the application asks it, their consent to tracked emails.
 *
 * The confirmation of a deletion uses Symfony's sudo mode: `IS_AUTHENTICATED_RECENTLY`, on the page and on the form, so
 * that a stale session is sent to prove itself and comes back to the page, its request being a `GET`.
 */
final readonly class PreferencesController
{
    public function __construct(
        private PrivacyPages $pages,
        private CurrentDataSubject $subjects,
        private PrivacyRights $rights,
        private ClockInterface $clock,
        private ?AccountDeletions $deletions,
        private ?PersonalDataDownload $download,
        private ?EmailTrackingConsents $emailTracking = null,
    ) {
    }

    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED)]
    public function index(): Response
    {
        return $this->page($this->subject());
    }

    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED)]
    public function export(Request $request): Response
    {
        if (!$this->rights->export || !$this->download instanceof PersonalDataDownload) {
            throw new NotFoundHttpException();
        }

        $this->pages->guardForm($request);
        $subject = $this->subject();
        $document = $this->download->of($subject);

        if (null === $document) {
            return $this->page($subject, Response::HTTP_TOO_MANY_REQUESTS);
        }

        return PrivacyPages::archive($document);
    }

    /**
     * Records the member's choice about tracked emails: each box left unticked refuses, so withdrawing is the same
     * form as granting.
     */
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED)]
    public function emailTracking(Request $request): RedirectResponse
    {
        if (!$this->emailTracking instanceof EmailTrackingConsents) {
            throw new NotFoundHttpException();
        }

        $this->pages->guardForm($request);
        $this->emailTracking->decide($this->subject(), $request->request->has('opens'), $request->request->has('clicks'));

        return $this->pages->seeOther(PrivacyRoutes::PREFERENCES);
    }

    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_RECENTLY)]
    public function erasure(Request $request): Response
    {
        $deletions = $this->deletions();
        $subject = $this->subject();

        if (!$request->isMethod(Request::METHOD_POST)) {
            return $this->pages->render('preferences/erasure');
        }

        $this->pages->guardForm($request);

        try {
            $deletions->request($subject);
        } catch (AccountDeletionBusy) {
            return $this->page($subject, Response::HTTP_CONFLICT);
        }

        return $this->pages->seeOther(PrivacyRoutes::PREFERENCES);
    }

    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED)]
    public function cancel(Request $request): Response
    {
        $deletions = $this->deletions();
        $this->pages->guardForm($request);
        $subject = $this->subject();

        try {
            $deletions->cancel($subject);
        } catch (AccountDeletionBusy) {
            return $this->page($subject, Response::HTTP_CONFLICT);
        }

        return $this->pages->seeOther(PrivacyRoutes::PREFERENCES);
    }

    private function page(DataSubject $subject, int $status = Response::HTTP_OK): Response
    {
        $deletion = $this->rights->erasure ? $this->deletions?->openFor($subject) : null;

        return $this->pages->render('preferences/index', [
            'can_export' => $this->rights->export && $this->download instanceof PersonalDataDownload,
            'can_erase' => $this->rights->erasure && $this->deletions instanceof AccountDeletions,
            'deletion' => $deletion,
            'cancellable' => $deletion instanceof AccountDeletion && $deletion->isCancellable($this->clock->now()),
            'busy' => Response::HTTP_OK !== $status,
            'email_tracking' => $this->emailTracking instanceof EmailTrackingConsents,
            'email_tracking_choice' => $this->emailTracking?->current($subject),
            'email_tracking_version' => $this->emailTracking?->version(),
        ], $status);
    }

    private function deletions(): AccountDeletions
    {
        if (!$this->rights->erasure || !$this->deletions instanceof AccountDeletions) {
            throw new NotFoundHttpException();
        }

        return $this->deletions;
    }

    private function subject(): DataSubject
    {
        return $this->subjects->current() ?? throw new AccessDeniedException();
    }
}
