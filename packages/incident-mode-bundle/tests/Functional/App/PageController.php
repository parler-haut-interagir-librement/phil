<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Functional\App;

use Phil\IncidentModeBundle\Application\Checkpoint;
use Phil\IncidentModeBundle\Attribute\SuspendedDuring;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The pages of the application under test: a sign-up refused while sign-ups are suspended, on posting only; an
 * invitation refused by its route's defaults; a post refused by the checkpoint in its code; a contact form refused by
 * its option; and a page with the banner.
 */
#[SuspendedDuring('read-only', methods: ['DELETE'])]
final readonly class PageController
{
    public function __construct(
        private Checkpoint $checkpoint,
        private FormFactoryInterface $forms,
        private Environment $twig,
    ) {
    }

    #[SuspendedDuring('signups', methods: ['post'])]
    public function signUp(): Response
    {
        return new Response('Welcome');
    }

    public function invite(): Response
    {
        return new Response('Invited');
    }

    public function post(): Response
    {
        $this->checkpoint->guard('new-posts');

        return new Response('Posted');
    }

    public function contact(Request $request): Response
    {
        $form = $this->forms->createNamedBuilder('contact', FormType::class, null, ['csrf_protection' => false, 'suspended_during' => ['new-messages']])
            ->add('message', TextType::class)
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return new Response('Sent');
        }

        return new Response(implode("\n", array_map(static fn (FormError $error): string => $error->getMessage(), iterator_to_array($form->getErrors(true), false))), $form->isSubmitted() ? 422 : 200);
    }

    public function banner(): Response
    {
        return new Response($this->twig->render('banner.html.twig'));
    }
}
