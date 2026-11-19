<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Http;

use Phil\LibraryBundle\Application\Exception\LoanRefused;
use Phil\LibraryBundle\Application\Exception\NotFound;
use Phil\LibraryBundle\Application\Loans;
use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;
use Phil\LibraryBundle\Domain\Model\Loan;
use Phil\LibraryBundle\Domain\Status\CopyCondition;
use Phil\LibraryBundle\Infrastructure\Form\LendType;
use Phil\LibraryBundle\Infrastructure\Form\ReturnType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The desk, for the staff: a copy lent by its barcode to a member's identifier, and a copy taken back with the
 * condition it is in. A refusal is said on the form, in the reader's language.
 */
final readonly class DeskController
{
    public const string ROUTE = 'phil_library_desk';

    public function __construct(
        private Pages $pages,
        private FormFactoryInterface $forms,
        private Loans $loans,
        private TranslatorInterface $translator,
        private string $role,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->pages->denyUnlessGranted($this->role);
        $lend = $this->forms->createNamed('lend', LendType::class);
        $return = $this->forms->createNamed('return', ReturnType::class);
        $lend->handleRequest($request);
        $return->handleRequest($request);
        $done = null;

        if ($lend->isSubmitted() && $lend->isValid()) {
            $done = $this->attempt($lend, fn (array $data): Loan => $this->loans->lend($this->text($data['barcode'] ?? null), $this->text($data['member'] ?? null)));
        }

        if ($return->isSubmitted() && $return->isValid()) {
            $done = $this->attempt($return, function (array $data): Loan {
                $condition = $data['condition'] ?? null;
                $note = $data['note'] ?? null;

                return $this->loans->giveBack($this->text($data['barcode'] ?? null), $condition instanceof CopyCondition ? $condition : null, \is_string($note) ? $note : null);
            });
        }

        $failed = ($lend->isSubmitted() && !$lend->isValid()) || ($return->isSubmitted() && !$return->isValid());

        if ($done instanceof Loan) {
            $lend = $this->forms->createNamed('lend', LendType::class);
            $return = $this->forms->createNamed('return', ReturnType::class);
        }

        return $this->pages->render('desk', ['lend' => $lend->createView(), 'return' => $return->createView(), 'done' => $done], $failed ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }

    /**
     * Runs the desk's work on the form's data, the reason of a refusal added to the form.
     *
     * @param FormInterface<array<string, mixed>|null> $form
     * @param callable(array<array-key, mixed>): Loan  $work
     */
    private function attempt(FormInterface $form, callable $work): ?Loan
    {
        $data = $form->getData();

        try {
            return $work(\is_array($data) ? $data : []);
        } catch (LoanRefused $refused) {
            $form->addError(new FormError($this->translator->trans('refusal.'.$refused->reason, $this->parameters($refused->parameters), 'phil_library')));
        } catch (NotFound|InvalidLibraryData $error) {
            $form->addError(new FormError($error->getMessage()));
        }

        return null;
    }

    /**
     * @param array<string, int> $parameters
     *
     * @return array<string, int>
     */
    private function parameters(array $parameters): array
    {
        $named = [];

        foreach ($parameters as $name => $value) {
            $named['%'.$name.'%'] = $value;
        }

        return $named;
    }

    private function text(mixed $value): string
    {
        return \is_string($value) ? trim($value) : '';
    }
}
