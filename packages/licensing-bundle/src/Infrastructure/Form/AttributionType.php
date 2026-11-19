<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Infrastructure\Form;

use Override;
use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Catalog\Requirement;
use Phil\LicensingBundle\Domain\Catalog\ResourceKind;
use Phil\LicensingBundle\Domain\ValueObject\Attribution;
use Phil\LicensingBundle\Domain\ValueObject\License;
use Phil\LicensingBundle\Infrastructure\Translation\LicenseNames;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * The credit of one resource, to and from an `Attribution`: its kind, title, license, author, address, notice and the
 * changes made to it.
 *
 * When the resource's license asks for the changes to be indicated — Creative Commons Attribution and its variants —,
 * the changes are required. The box "No modifications" fills them with a sentence that says so, in the reader's
 * language, when the form is submitted; without JavaScript, the sentence shows once the form is shown again.
 *
 * @extends AbstractType<Attribution|null>
 */
final class AttributionType extends AbstractType
{
    public const string UNCHANGED_KEY = 'form.attribution.unchanged_text';

    private const string CONTROL = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/';

    public function __construct(
        private readonly LicenseCatalog $catalog,
        private readonly LicenseNames $names,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $unchanged = $this->names->trans(self::UNCHANGED_KEY);
        $licenses = [];

        foreach ($this->catalog->all() as $license) {
            $licenses[$license->code] = $license->code;
        }

        $builder
            ->add('kind', EnumType::class, [
                'class' => ResourceKind::class,
                'label' => 'form.attribution.kind',
                'choice_label' => static fn (ResourceKind $kind): string => 'form.kind.'.$kind->value,
            ])
            ->add('title', TextType::class, [
                'label' => 'form.attribution.title',
                'constraints' => [new NotBlank(), ...$this->text(Attribution::TITLE_LENGTH)],
            ])
            ->add('license', ChoiceType::class, [
                'label' => 'form.attribution.license',
                'choices' => $licenses,
                'choice_label' => fn (string $code): string => $this->names->name($code),
                'choice_translation_domain' => false,
                'placeholder' => 'form.attribution.license_placeholder',
                'constraints' => [new NotBlank()],
            ])
            ->add('author', TextType::class, [
                'label' => 'form.attribution.author',
                'required' => false,
                'constraints' => $this->text(Attribution::AUTHOR_LENGTH),
            ])
            ->add('url', UrlType::class, [
                'label' => 'form.attribution.url',
                'required' => false,
                'default_protocol' => null,
                // The address constraint refuses control characters itself.
                'constraints' => [new Url(protocols: ['http', 'https'], requireTld: true), new Length(max: 2048)],
            ])
            ->add('notice', TextareaType::class, [
                'label' => 'form.attribution.notice',
                'required' => false,
                'constraints' => $this->text(Attribution::NOTICE_LENGTH),
            ])
            ->add('changes', TextareaType::class, [
                'label' => 'form.attribution.changes',
                'help' => 'form.attribution.changes_help',
                'required' => false,
                'constraints' => $this->text(Attribution::CHANGES_LENGTH),
            ])
            ->add('unchanged', CheckboxType::class, [
                'label' => 'form.attribution.unchanged',
                'help' => 'form.attribution.unchanged_help',
                'help_translation_parameters' => ['%changes%' => $unchanged],
                'required' => false,
            ])
            ->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) use ($unchanged): void {
                $fields = $event->getData();

                // Checked as CheckboxType reads it: any value submitted.
                if (\is_array($fields) && null !== ($fields['unchanged'] ?? null)) {
                    $fields['changes'] = $unchanged;
                    $event->setData($fields);
                }
            })
            ->addModelTransformer(new AttributionTransformer($unchanged));
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => LicenseNames::DOMAIN,
            'constraints' => [new Callback($this->changesIndicated(...))],
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'phil_licensing_attribution';
    }

    /**
     * The checks of a text the attribution keeps: its length once trimmed and its charset, UTF-8, then no control
     * characters but line breaks and tabs.
     *
     * @param positive-int $max
     *
     * @return list<Constraint>
     */
    private function text(int $max): array
    {
        return [
            new Length(max: $max, normalizer: trim(...)),
            new Callback(static function (mixed $text, ExecutionContextInterface $context): void {
                if (\is_string($text) && 1 === preg_match(self::CONTROL, $text)) {
                    $context->buildViolation('form.attribution.text')->setTranslationDomain(LicenseNames::DOMAIN)->addViolation();
                }
            }),
        ];
    }

    /**
     * The changes, required when the resource's license asks for them to be indicated.
     */
    private function changesIndicated(mixed $attribution, ExecutionContextInterface $context): void
    {
        if (!$attribution instanceof Attribution || null !== $attribution->changes) {
            return;
        }

        $license = $this->catalog->find($attribution->license);

        if ($license instanceof License && $license->requires(Requirement::IndicateChanges)) {
            $context->buildViolation('form.attribution.changes_required')
                ->setTranslationDomain(LicenseNames::DOMAIN)
                ->setParameter('%license%', $this->names->name($license))
                ->atPath('[changes]')
                ->addViolation();
        }
    }
}
