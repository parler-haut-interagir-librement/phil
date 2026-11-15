<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\Http;

use Phil\ModerationBundle\Application\Exception\ModerationRefused;
use Phil\ModerationBundle\Application\Exception\UnknownTarget;
use Phil\ModerationBundle\Application\Reports;
use Phil\ModerationBundle\Application\TemporaryHides;
use Phil\ModerationBundle\Domain\Exception\InvalidModeration;
use Phil\ModerationBundle\Domain\ValueObject\TargetReference;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The forms a page posts to report a content, or — for a trusted member — to hide it until the moderation reviews it.
 * Each is posted with a CSRF token and goes back to the page named by `_target_path`, a path of the same site, or to
 * the home page.
 */
final readonly class ReportController
{
    public const string REPORT_ROUTE = 'phil_moderation_report';

    public const string HIDE_ROUTE = 'phil_moderation_hide';

    public const string CSRF_TOKEN_ID = 'phil_moderation_report';

    public function __construct(
        private Reports $reports,
        private TemporaryHides $hides,
        private CsrfTokenManagerInterface $csrfTokens,
    ) {
    }

    public function report(Request $request): RedirectResponse
    {
        $target = $this->target($request);

        try {
            $this->reports->report($target, $request->request->getString('reason'), $request->request->getString('comment'));
        } catch (ModerationRefused $refused) {
            throw new AccessDeniedHttpException($refused->getMessage(), $refused);
        } catch (UnknownTarget) {
            throw new NotFoundHttpException('No such target.');
        } catch (InvalidModeration $invalid) {
            throw new BadRequestHttpException($invalid->getMessage(), $invalid);
        }

        return $this->back($request);
    }

    public function hide(Request $request): RedirectResponse
    {
        $target = $this->target($request);

        try {
            $this->hides->hide($target, $request->request->getString('comment'));
        } catch (ModerationRefused $refused) {
            throw new AccessDeniedHttpException($refused->getMessage(), $refused);
        } catch (UnknownTarget) {
            throw new NotFoundHttpException('No such target.');
        } catch (InvalidModeration $invalid) {
            throw new BadRequestHttpException($invalid->getMessage(), $invalid);
        }

        return $this->back($request);
    }

    private function target(Request $request): TargetReference
    {
        if (!$this->csrfTokens->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $request->request->getString('_token')))) {
            throw new BadRequestHttpException('The CSRF token is not valid.');
        }

        try {
            return TargetReference::of($request->request->getString('type'), $request->request->getString('id'));
        } catch (InvalidModeration) {
            throw new NotFoundHttpException('No such target.');
        }
    }

    /**
     * Back to the path the form named, when it is a path of this site — one slash, no scheme, no host —, else home.
     */
    private function back(Request $request): RedirectResponse
    {
        $path = $request->request->getString('_target_path');
        $local = 1 === preg_match('#^/(?![/\\\\])[^\s]*$#D', $path);

        return new RedirectResponse($local ? $path : $request->getBasePath().'/', Response::HTTP_SEE_OTHER);
    }
}
