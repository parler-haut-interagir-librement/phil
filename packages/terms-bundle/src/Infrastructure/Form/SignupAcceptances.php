<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\Form;

use Phil\TermsBundle\Application\Terms;
use Phil\TermsBundle\Domain\AcceptanceChannel;
use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Domain\ValueObject\Acceptance;
use Symfony\Component\Form\FormInterface;

/**
 * Keeps the acceptances a valid signup form carries, once the member it created has an identifier:
 *
 * ```php
 * if ($form->isSubmitted() && $form->isValid()) {
 *     $member = $members->register($form->getData());
 *     $signupAcceptances->record($form, $member->id, $request->getClientIp());
 * }
 * ```
 */
final readonly class SignupAcceptances
{
    public function __construct(
        private Terms $terms,
    ) {
    }

    /**
     * @param FormInterface<mixed> $form
     *
     * @return list<Acceptance>
     *
     * @throws InvalidTerms when the form is not valid, or a version can no longer be accepted
     */
    public function record(FormInterface $form, string $member, ?string $address = null): array
    {
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw InvalidTerms::because('form_not_valid', 'Only a submitted and valid form carries acceptances.');
        }

        $acceptances = [];

        foreach ($form->all() as $child) {
            $name = $child->getName();

            if (!str_starts_with($name, TermsAcceptanceTypeExtension::CHECKBOX) || true !== $child->getData()) {
                continue;
            }

            $policy = substr($name, \strlen(TermsAcceptanceTypeExtension::CHECKBOX));
            $version = $form->get(TermsAcceptanceTypeExtension::VERSION.$policy)->getData();
            $acceptances[] = $this->terms->accept($member, $policy, \is_string($version) ? (int) $version : throw InvalidTerms::because('form_not_valid', 'The form carries no version.'), AcceptanceChannel::Signup, $address);
        }

        return $acceptances;
    }
}
