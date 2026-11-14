<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Infrastructure\Admin;

use Phil\UploadScanBundle\Application\Contract\QuarantineInventory;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * The quarantine for an operator: the files kept, the most recent first, a page at a time, each with the name it was
 * sent under, the signature that condemned it, when and how large; and the form that deletes one for good, checked
 * against a CSRF token. Nothing here shows or serves what a file holds. Every page asks the application's security for
 * the configured role, and a deletion is logged with its identifier.
 */
final readonly class QuarantineAdminController
{
    public const string LIST_ROUTE = 'phil_upload_scan_quarantine';

    public const string DELETE_ROUTE = 'phil_upload_scan_quarantine_delete';

    public const string CSRF_TOKEN_ID = 'phil_upload_scan_quarantine';

    /**
     * @param int<1, max> $pageSize
     */
    public function __construct(
        private QuarantineInventory $inventory,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private CsrfTokenManagerInterface $csrfTokens,
        private ?AuthorizationCheckerInterface $authorizationChecker,
        private string $role,
        private string $layout,
        private int $pageSize,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function list(Request $request): Response
    {
        $this->denyUnlessGranted();
        $page = max(1, $request->query->getInt('page'));
        $files = $this->inventory->files($this->pageSize + 1, ($page - 1) * $this->pageSize);

        return new Response($this->twig->render('@PhilUploadScan/admin/quarantine.html.twig', [
            'layout' => $this->layout,
            'files' => \array_slice($files, 0, $this->pageSize),
            'page' => $page,
            'more' => \count($files) > $this->pageSize,
            'deleted' => 'deleted' === $request->query->getString('done'),
            'csrf_token_id' => self::CSRF_TOKEN_ID,
        ]));
    }

    public function delete(Request $request, string $id): RedirectResponse
    {
        $this->denyUnlessGranted();

        if (!$this->csrfTokens->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $request->request->getString('_token')))) {
            throw new BadRequestHttpException('The CSRF token is not valid.');
        }

        if (!$this->inventory->delete($id)) {
            throw new NotFoundHttpException('There is no such file in the quarantine.');
        }

        $this->logger?->notice('A quarantined file was deleted.', ['id' => $id]);

        return new RedirectResponse($this->urls->generate(self::LIST_ROUTE, ['done' => 'deleted']), Response::HTTP_SEE_OTHER);
    }

    private function denyUnlessGranted(): void
    {
        if (true !== $this->authorizationChecker?->isGranted($this->role)) {
            $exception = new AccessDeniedException();
            $exception->setAttributes([$this->role]);

            throw $exception;
        }
    }
}
