<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\Form;

use Override;
use Phil\TermsBundle\Application\Policies;
use Phil\TermsBundle\Application\VersionCatalog;
use Phil\TermsBundle\Domain\ValueObject\Policy;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;
use Psr\Clock\ClockInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The option `terms_acceptance` of a form — a signup form —: `true` for every required policy, or a list of policy
 * keys. Each policy in force adds an unmapped checkbox, never ticked in advance, and the number of the version shown in
 * a hidden field; the form is valid only when each box is ticked and the version shown is still the one in force.
 *
 * Once the member exists, `SignupAcceptances::record()` keeps the acceptances the form carries.
 */
final class TermsAcceptanceTypeExtension extends AbstractTypeExtension
{
    public const string OPTION = 'terms_acceptance';

    public const string CHECKBOX = 'phil_terms_accept_';

    public const string VERSION = 'phil_terms_version_';

    public const string DOMAIN = 'phil_terms';

    public function __construct(
        private readonly Policies $policies,
        private readonly VersionCatalog $catalog,
        private readonly ClockInterface $clock,
        private readonly ?TranslatorInterface $translator = null,
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
        $resolver->setDefault(self::OPTION, false);
        $resolver->setAllowedTypes(self::OPTION, ['bool', 'string[]']);
    }

    /**
     * @param array<string, mixed> $options
     */
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $keys = $this->keys($options[self::OPTION]);

        if ([] === $keys) {
            return;
        }

        $now = $this->clock->now();

        foreach ($keys as $key) {
            $version = $this->catalog->inForce($key, $now);

            if (!$version instanceof PolicyVersion) {
                continue;
            }

            $builder->add(self::CHECKBOX.$key, CheckboxType::class, [
                'mapped' => false,
                'required' => true,
                'data' => false,
                'translation_domain' => self::DOMAIN,
                'label' => 'signup.accept',
                'label_translation_parameters' => ['%policy%' => $this->title($key), '%version%' => (string) $version->number],
            ]);
            $builder->add(self::VERSION.$key, HiddenType::class, ['mapped' => false, 'data' => (string) $version->number]);
        }

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($keys): void {
            $this->check($event->getForm(), $keys);
        });
    }

    /**
     * @param FormInterface<mixed>     $form
     * @param array<array-key, string> $keys
     */
    private function check(FormInterface $form, array $keys): void
    {
        $now = $this->clock->now();

        foreach ($keys as $key) {
            if (!$form->has(self::CHECKBOX.$key)) {
                continue;
            }

            $checkbox = $form->get(self::CHECKBOX.$key);
            $current = $this->catalog->inForce($key, $now);

            if (true !== $checkbox->getData()) {
                $checkbox->addError(new FormError($this->translated('signup.required'), 'signup.required'));
            } elseif ((string) $current?->number !== $form->get(self::VERSION.$key)->getData()) {
                $checkbox->addError(new FormError($this->translated('signup.changed'), 'signup.changed'));
            }
        }
    }

    /**
     * @return array<array-key, string>
     */
    private function keys(mixed $option): array
    {
        if (true === $option) {
            return array_map(static fn (Policy $policy): string => $policy->key, $this->policies->required());
        }

        if (!\is_array($option)) {
            return [];
        }

        // The options resolver allows strings only.
        /** @var array<array-key, string> $option */
        return array_map(fn (string $key): string => $this->policies->get($key)->key, $option);
    }

    private function title(string $key): string
    {
        $id = 'policy.'.$key;
        $title = $this->translator?->trans($id, [], self::DOMAIN) ?? $key;

        return $title === $id ? $key : $title;
    }

    private function translated(string $id): string
    {
        return $this->translator?->trans($id, [], self::DOMAIN) ?? match ($id) {
            'signup.required' => 'Tick the box to accept.',
            default => 'This version was replaced: read the new one before accepting it.',
        };
    }
}
