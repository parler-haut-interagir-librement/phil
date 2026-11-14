<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Infrastructure\Http;

use Phil\CertificatesBundle\Application\CertificatePdf;
use Phil\CertificatesBundle\Domain\Contract\CertificateRepository;
use Phil\CertificatesBundle\Domain\Contract\CourseRepository;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Status\CertificateStatus;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;
use Phil\CertificatesBundle\Infrastructure\Routing\RoutedCertificateUrls;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * The public register: a certificate found by its code, with its status — the page its QR code opens —, its PDF and
 * its signed credential; each open course with its criteria and the certificates listed by their holders' choice.
 *
 * A certificate's code is what gives access to it: an unlisted certificate is on no list, and its page asks search
 * engines not to index it. A revoked certificate's page says so, and its PDF and credential are gone (`410`). A
 * certificate's page and files carry no cache directive, which HttpFoundation sends as `no-cache, private`: a
 * revocation shows at once.
 */
final readonly class RegisterController
{
    public const string INDEX_ROUTE = 'phil_certificates_register';

    public const string PDF_ROUTE = 'phil_certificates_pdf';

    public const string CREDENTIAL_ROUTE = 'phil_certificates_credential';

    public const int PER_PAGE = 50;

    public function __construct(
        private CourseRepository $courses,
        private CertificateRepository $certificates,
        private CertificatePdf $pdf,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private ClockInterface $clock,
        private string $layout,
    ) {
    }

    /**
     * The open courses and a form to find a certificate by its code; the code given, the certificate's page.
     */
    public function index(Request $request): Response
    {
        $code = trim($request->query->getString('code'));

        if ('' === $code) {
            return $this->render('register', ['courses' => $this->courses->open(), 'searched' => null]);
        }

        $certificate = $this->find($code);

        if ($certificate instanceof Certificate) {
            return new RedirectResponse($this->urls->generate(RoutedCertificateUrls::CERTIFICATE_ROUTE, ['code' => $certificate->code->value]));
        }

        return $this->render('register', ['courses' => $this->courses->open(), 'searched' => $code], Response::HTTP_NOT_FOUND);
    }

    public function course(Request $request, string $slug): Response
    {
        $course = $this->courses->findBySlug($slug) ?? throw new NotFoundHttpException(\sprintf('There is no course "%s".', $slug));
        $total = $this->certificates->countListed($course);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, $request->query->getInt('page')));

        $response = $this->render('course', [
            'course' => $course,
            'certificates' => $this->certificates->listed($course, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]);
        $response->setPublic();
        $response->setMaxAge(300);

        return $response;
    }

    public function certificate(string $code): Response
    {
        $certificate = $this->get($code);
        $response = $this->render('certificate', [
            'certificate' => $certificate,
            'code' => $certificate->code,
            'status' => $certificate->status($this->clock->now())->value,
            'renewal' => $this->certificates->findRenewalOf($certificate),
        ]);

        if (!$certificate->listed) {
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        return $response;
    }

    public function pdf(Request $request, string $code): Response
    {
        $output = $this->pdf->render($this->served($code), $request->getLocale());

        return $this->file($output->contents, $output->contentType, $output->filename, HeaderUtils::DISPOSITION_INLINE);
    }

    public function credential(string $code): Response
    {
        $certificate = $this->served($code);
        $response = $this->file($certificate->credential, $certificate->isToken() ? 'application/vc+jwt' : 'application/vc', $certificate->isToken() ? 'credential.jwt' : 'credential.json', HeaderUtils::DISPOSITION_ATTACHMENT);
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }

    /**
     * A certificate whose files are served: neither unknown nor revoked.
     */
    private function served(string $code): Certificate
    {
        $certificate = $this->get($code);

        if (CertificateStatus::Revoked === $certificate->status($this->clock->now())) {
            throw new GoneHttpException(\sprintf('The certificate %s is revoked.', $certificate->code->formatted()));
        }

        return $certificate;
    }

    private function get(string $code): Certificate
    {
        return $this->find($code) ?? throw new NotFoundHttpException(\sprintf('There is no certificate %s.', $code));
    }

    private function find(string $code): ?Certificate
    {
        try {
            return $this->certificates->findByCode(VerificationCode::fromString($code)->value);
        } catch (InvalidValue) {
            return null;
        }
    }

    private function file(string $contents, string $type, string $filename, string $disposition): Response
    {
        $response = new Response($contents, Response::HTTP_OK, ['Content-Type' => $type]);
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition($disposition, $filename));
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context, int $status = Response::HTTP_OK): Response
    {
        $response = new Response($this->twig->render('@PhilCertificates/'.$template.'.html.twig', ['layout' => $this->layout, ...$context]), $status);
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
