<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Functional\App;

use Phil\IncidentModeBundle\Attribute\SuspendedDuring;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The pages of the community that an incident suspends, through the checkpoints of phil/incident-mode-bundle: a
 * sign-up refused by its attribute, an invitation refused by its route's defaults, and a new post whose form is
 * refused by its option.
 */
final readonly class CommunityController
{
    public function __construct(
        private FormFactoryInterface $forms,
    ) {
    }

    #[SuspendedDuring('signups', methods: ['POST'])]
    public function signUp(): Response
    {
        return new Response('Welcome');
    }

    public function invite(): Response
    {
        return new Response('Invited');
    }

    public function newPost(Request $request): Response
    {
        $form = $this->forms->createNamedBuilder('post', FormType::class, null, ['csrf_protection' => false, 'suspended_during' => ['new-posts']])
            ->add('body', TextareaType::class)
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return new Response('Posted');
        }

        return new Response(implode("\n", array_map(static fn (FormError $error): string => $error->getMessage(), iterator_to_array($form->getErrors(true), false))), $form->isSubmitted() ? 422 : 200);
    }
}
