<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Http;

use Phil\PrivacyBundle\Application\Contract\CurrentDataSubject;
use Phil\PrivacyBundle\Application\Erasure\ErasureRequests;
use Phil\PrivacyBundle\Domain\Erasure\ErasureProgress;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The progress of an erasure request, as a page or as JSON, module by module.
 *
 * Shown only to the person it is about, to an operator — a user granted the operator's role —, and, for a request made
 * without an account, to whoever holds the secret of its progress link. Anyone else gets the same 404 as for a request
 * that does not exist, so that an identifier tells nothing. The answer is never kept by a cache, sends no referrer, and
 * is not indexed.
 */
final readonly class ErasureProgressController
{
    public function __construct(
        private PrivacyPages $pages,
        private ?ErasureRequests $requests,
        private ?CurrentDataSubject $current,
        private ?AuthorizationCheckerInterface $authorization,
        private string $operatorRole,
    ) {
    }

    public function progress(Request $request, string $id, string $_format = 'html'): Response
    {
        $progress = $this->requests?->progress($id);

        if (!$progress instanceof ErasureProgress || !$this->mayRead($progress, $request->query->getString('token'))) {
            throw new NotFoundHttpException();
        }

        $response = 'json' === $_format
            ? new JsonResponse($progress->toArray())
            : $this->pages->render('erasure/progress', ['progress' => $progress, 'counts' => $progress->counts()]);

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    private function mayRead(ErasureProgress $progress, string $token): bool
    {
        if ('' !== $token) {
            return $progress->request->isOpenedBy($token);
        }

        $subject = $this->current?->current();

        if ($subject instanceof DataSubject && $progress->request->isAbout($subject)) {
            return true;
        }

        return $this->authorization?->isGranted($this->operatorRole) ?? false;
    }
}
