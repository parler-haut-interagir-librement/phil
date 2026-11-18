<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Infrastructure\Form;

use Override;
use Phil\IncidentModeBundle\Application\Checkpoint;
use Phil\IncidentModeBundle\Application\Suspension;
use Phil\IncidentModeBundle\Infrastructure\Translation\IncidentWording;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The option `suspended_during` of every form: a public form — sign-up, invitation, contact — names the restrictions
 * that stop it, and a submission while one applies to the member of the request is invalid, with the error that says
 * what is suspended and until when. Nothing it holds is saved, since the form is not valid.
 *
 * ```php
 * $builder->create('registration', RegistrationType::class, ['suspended_during' => 'signups']);
 * ```
 */
final class SuspendedDuringTypeExtension extends AbstractTypeExtension
{
    public const string OPTION = 'suspended_during';

    public function __construct(
        private readonly Checkpoint $checkpoint,
        private readonly IncidentWording $wording,
    ) {
    }

    #[Override]
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault(self::OPTION, []);
        $resolver->setAllowedTypes(self::OPTION, ['string', 'string[]']);
        $resolver->setNormalizer(self::OPTION, static fn (OptionsResolver $options, string|array $value): array => array_values(array_filter(\is_string($value) ? [$value] : $value, static fn (mixed $name): bool => \is_string($name) && '' !== $name)));
    }

    /**
     * @param array<string, mixed> $options
     */
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $restrictions */
        $restrictions = $options[self::OPTION];

        if ([] === $restrictions) {
            return;
        }

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($restrictions): void {
            $suspension = $this->checkpoint->suspension(...$restrictions);

            if ($suspension instanceof Suspension) {
                $event->getForm()->addError(new FormError($this->wording->sentence($suspension), null, [], null, $suspension));
            }
        });
    }
}
