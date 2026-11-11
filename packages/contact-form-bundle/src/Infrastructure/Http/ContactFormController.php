<?php

declare(strict_types=1);

namespace Phil\ContactFormBundle\Infrastructure\Http;

use Phil\ContactFormBundle\Application\ContactMessage;
use Phil\ContactFormBundle\Infrastructure\Form\ContactFormType;
use Phil\ContactFormBundle\Infrastructure\Mailer\ContactMailer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Twig\Environment;

/**
 * The contact page: the form, then — once the message is sent — a redirection to the page with `?sent=1`, so that
 * reloading sends nothing twice. A message the mail transport refuses leaves the form filled, with a message that
 * asks to try again, and is logged without its content.
 */
final readonly class ContactFormController
{
    public function __construct(
        private FormFactoryInterface $forms,
        private ContactMailer $mailer,
        private Environment $twig,
        private LoggerInterface $logger,
        private string $template,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $form = $this->forms->create(ContactFormType::class, new ContactMessage());
        $form->handleRequest($request);

        $failed = false;

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->mailer->send($form->getData(), $request->getLocale());

                return new RedirectResponse($request->getBaseUrl().$request->getPathInfo().'?sent=1', Response::HTTP_SEE_OTHER);
            } catch (TransportExceptionInterface $transportException) {
                $this->logger->error('A contact message could not be sent: {reason}', ['reason' => $transportException->getMessage()]);
                $failed = true;
            }
        }

        return new Response(
            $this->twig->render($this->template, ['form' => $form->createView(), 'sent' => $request->query->getBoolean('sent'), 'failed' => $failed]),
            match (true) {
                $failed => Response::HTTP_SERVICE_UNAVAILABLE,
                $form->isSubmitted() => Response::HTTP_UNPROCESSABLE_ENTITY,
                default => Response::HTTP_OK,
            },
        );
    }
}
