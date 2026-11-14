<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Infrastructure\Http;

use Phil\FormsBundle\Application\CurrentSubject;
use Phil\FormsBundle\Application\Submissions;
use Phil\FormsBundle\Domain\Exception\InvalidEntry;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Domain\Repository\FormRepository;
use Phil\FormsBundle\Infrastructure\Form\EntryType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * The page of a form: the form, then — once the entry is taken — a redirection to the page with `?sent=1`, so that
 * reloading sends nothing twice. A form that does not exist or takes no entry is a `404`; values the form refuses
 * come back with a message on each field — for a disqualifying choice, the form's own message —, and the choices
 * whose places are all taken are offered disabled.
 */
final readonly class FormController
{
    public function __construct(
        private FormRepository $forms,
        private Submissions $submissions,
        private FormFactoryInterface $formFactory,
        private Environment $twig,
        private string $template,
        private ?TranslatorInterface $translator = null,
        private ?CurrentSubject $subject = null,
    ) {
    }

    public function __invoke(Request $request, string $key): Response
    {
        $definition = $this->forms->byKey($key);

        if (!$definition instanceof Form || !$definition->isOpen()) {
            throw new NotFoundHttpException(\sprintf('There is no form "%s" taking entries.', $key));
        }

        $form = $this->formFactory->createNamed('phil_form', EntryType::class, null, ['phil_form' => $definition, 'full_choices' => $this->submissions->fullChoices($definition)]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $values */
            $values = $form->getData();

            try {
                $this->submissions->submit($definition, $values, $request->getLocale(), $this->subject?->current());

                return new RedirectResponse($request->getBaseUrl().$request->getPathInfo().'?sent=1', Response::HTTP_SEE_OTHER);
            } catch (InvalidEntry $invalidEntry) {
                $this->refuse($form, $definition, $values, $invalidEntry);
            }
        }

        return new Response(
            $this->twig->render($this->template, ['form' => $form->createView(), 'definition' => $definition, 'sent' => $request->query->getBoolean('sent')]),
            $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
        );
    }

    /**
     * The definition the page was built from is the one the entry was checked against: each error has its field.
     *
     * @template TData
     *
     * @param FormInterface<TData> $form
     * @param array<string, mixed> $values
     */
    private function refuse(FormInterface $form, Form $definition, array $values, InvalidEntry $invalidEntry): void
    {
        $fields = [];

        foreach ($definition->fields() as $field) {
            $fields[$field->name] = $field;
        }

        foreach ($invalidEntry->errors as $name => $error) {
            $value = $values[$name] ?? null;
            $field = $fields[$name] ?? null;
            $message = 'disqualified' === $error && \is_string($value) ? $field?->disqualification($value) : null;

            if (null === $message) {
                $message = 'phil_forms.error.'.$error;
                $message = $this->translator?->trans($message, $field?->errorParameters($error) ?? [], 'phil_forms') ?? $message;
            }

            $form->get($name)->addError(new FormError($message));
        }
    }
}
