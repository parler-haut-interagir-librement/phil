<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Functional\App;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * A page with a long form kept as a draft: a title that may not be empty, a body, a password and a secret field.
 */
final readonly class PostController
{
    public function __construct(
        private FormFactoryInterface $forms,
        private Environment $twig,
    ) {
    }

    public function __invoke(Request $request, int $id): Response
    {
        $form = $this->forms->createNamed('post', FormType::class, null, ['autosave' => ['subject' => $id]])
            ->add('title', TextType::class, ['required' => false])
            ->add('body', TextareaType::class, ['required' => false])
            ->add('pin', PasswordType::class, ['required' => false])
            ->add('note', TextType::class, ['required' => false, 'autosave' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && \in_array($form->get('title')->getData(), [null, ''], true)) {
            $form->get('title')->addError(new FormError('A title is needed.'));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            return new RedirectResponse('/done');
        }

        return new Response($this->twig->render('post.html.twig', ['form' => $form->createView()]));
    }
}
