<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Infrastructure\Form;

use Override;
use Phil\RedirectsBundle\Domain\Enum\RedirectStatus;
use Phil\ShortLinkBundle\Application\ShortLinkDomains;
use Phil\ShortLinkBundle\Application\ShortLinkDraft;
use Phil\ShortLinkBundle\Domain\Model\ShortLink;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The form of a short link, on a `ShortLinkDraft`: the target, the code (left empty to draw one, and shown but not
 * editable once the link exists), a label, the locale when several domains have one, the status of the redirect,
 * whether the query string of the visit is kept, and when the link is in force. The rules are checked by
 * `ShortLinks` when the draft is saved; its refusals are shown on the field they concern.
 *
 * Its labels are keys of the `phil_short_link` translation domain.
 *
 * @extends AbstractType<ShortLinkDraft>
 */
final class ShortLinkType extends AbstractType
{
    public function __construct(
        private readonly ShortLinkDomains $domains,
        private readonly bool $customCodes = true,
        private readonly string $timezone = 'UTC',
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $existing = true === $options['existing'];
        $builder->add('target', UrlType::class, ['label' => 'field.target', 'default_protocol' => null, 'attr' => ['maxlength' => ShortLink::MAX_TARGET_LENGTH]]);

        if ($this->customCodes || $existing) {
            $builder->add('code', TextType::class, ['label' => 'field.code', 'required' => false, 'disabled' => $existing, 'help' => $existing ? null : 'help.code', 'attr' => ['maxlength' => ShortLink::MAX_CODE_LENGTH, 'autocomplete' => 'off', 'spellcheck' => 'false']]);
        }

        $builder->add('label', TextType::class, ['label' => 'field.label', 'required' => false, 'attr' => ['maxlength' => ShortLink::MAX_LABEL_LENGTH]]);
        $locales = $this->domains->locales();

        if ([] !== $locales) {
            $builder->add('locale', ChoiceType::class, ['label' => 'field.locale', 'required' => false, 'placeholder' => 'choice.every_locale', 'choices' => array_combine($locales, $locales), 'choice_translation_domain' => false]);
        }

        $builder
            ->add('status', EnumType::class, [
                'label' => 'field.status',
                'class' => RedirectStatus::class,
                'choices' => [RedirectStatus::Found, RedirectStatus::TemporaryRedirect, RedirectStatus::MovedPermanently, RedirectStatus::PermanentRedirect],
                'choice_label' => static fn (RedirectStatus $status): string => 'status.'.$status->value,
            ])
            ->add('keepQuery', CheckboxType::class, ['label' => 'field.keep_query', 'required' => false])
            ->add('enabled', CheckboxType::class, ['label' => 'field.enabled', 'required' => false])
            ->add('validFrom', DateTimeType::class, $this->moment('field.valid_from'))
            ->add('validUntil', DateTimeType::class, $this->moment('field.valid_until'))
            ->add('maxVisits', IntegerType::class, ['label' => 'field.max_visits', 'required' => false, 'attr' => ['min' => 1]]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ShortLinkDraft::class,
            'translation_domain' => 'phil_short_link',
            'existing' => false,
        ]);
        $resolver->setAllowedTypes('existing', 'bool');
    }

    /**
     * @return array<string, mixed>
     */
    private function moment(string $label): array
    {
        return ['label' => $label, 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'model_timezone' => 'UTC', 'view_timezone' => $this->timezone];
    }
}
