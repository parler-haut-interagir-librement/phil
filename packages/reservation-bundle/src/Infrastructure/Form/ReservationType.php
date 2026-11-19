<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Infrastructure\Form;

use DateTimeImmutable;
use Override;
use Phil\ReservationBundle\Application\ReservationDraft;
use Phil\ReservationBundle\Application\Reservations;
use Phil\ReservationBundle\Domain\Model\BookableResource;
use Phil\ReservationBundle\Infrastructure\Translation\ViolationMessages;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A reservation of one resource, given as the `resource` option: its start and end on the resource's clock, and the
 * units asked for when it holds more than one. Once submitted, the request is checked as `Reservations::check()`
 * does, and each reason it would be refused becomes an error of the form, in the reader's language. The form books
 * nothing: the controller holds or books the draft once the form is valid, and catches a refusal raced meanwhile.
 *
 * `replacing` names the reservation the form moves, left out of the conflicts.
 *
 * @extends AbstractType<ReservationDraft>
 */
final class ReservationType extends AbstractType
{
    public function __construct(
        private readonly Reservations $reservations,
        private readonly ViolationMessages $messages,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $resource = $options['resource'];
        \assert($resource instanceof BookableResource);
        $zone = $resource->timezone()->getName();
        $moment = ['input' => 'datetime_immutable', 'model_timezone' => $zone, 'view_timezone' => $zone, 'with_seconds' => false];

        $builder
            ->add('start', DateTimeType::class, ['label' => 'form.start', ...$moment])
            ->add('end', DateTimeType::class, ['label' => 'form.end', ...$moment]);

        if ($resource->capacity() > 1) {
            $builder->add('quantity', IntegerType::class, ['label' => 'form.quantity', 'attr' => ['min' => 1, 'max' => $resource->capacity()]]);
        }

        /** @var ?string $replacing the resolver allows a string or null only */
        $replacing = $options['replacing'];

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($resource, $replacing): void {
            $draft = $event->getData();

            if (!$draft instanceof ReservationDraft || !$draft->start instanceof DateTimeImmutable || !$draft->end instanceof DateTimeImmutable) {
                return;
            }

            if ($draft->end <= $draft->start) {
                $event->getForm()->addError(new FormError($this->translator->trans('form.order', [], ViolationMessages::DOMAIN)));

                return;
            }

            $decision = $this->reservations->check($resource->id, $draft->start, $draft->end, $draft->quantity, $replacing);

            foreach ($this->messages->messages($decision) as $message) {
                $event->getForm()->addError(new FormError($message));
            }
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(['data_class' => ReservationDraft::class, 'replacing' => null, 'translation_domain' => ViolationMessages::DOMAIN])
            ->setRequired('resource')
            ->setAllowedTypes('resource', BookableResource::class)
            ->setAllowedTypes('replacing', ['null', 'string']);
    }
}
