<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Infrastructure\Form;

use Override;
use Phil\EmailAddress\EmailAddress;
use Phil\EmailAddress\TypoSuggester;
use Phil\EmailAddressBundle\Application\EmailAddresses;
use Phil\EmailAddressBundle\Infrastructure\Validator\Attribute\AcceptedEmailAddress;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * An email field checked by the configured policy, which helps the person without refusing what may be right:
 *
 * - in the browser, the bundle's Stimulus controller suggests the address meant when its domain is a slip of a known
 *   one, and warns about a role address, in a live region screen readers announce;
 * - without JavaScript, once the form is submitted, the same suggestion or warning is the field's help text.
 *
 * Options: `list`, the list whose policy applies; `accepted`, `false` to leave the constraint out; `suggest`, `false`
 * to leave the controller out.
 *
 * @extends AbstractType<string|null>
 */
final class EmailAddressType extends AbstractType
{
    public const string CONTROLLER = 'phil--email-address-bundle--email-address';

    public function __construct(
        private readonly EmailAddresses $addresses,
        private readonly TypoSuggester $suggester = new TypoSuggester(),
        private readonly ?string $domainsUrl = null,
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['list' => null, 'accepted' => true, 'suggest' => true]);
        $resolver->setAllowedTypes('list', ['null', 'string']);
        $resolver->setAllowedTypes('accepted', 'bool');
        $resolver->setAllowedTypes('suggest', 'bool');
        $resolver->setDefault('constraints', static fn (Options $options): array => true === $options['accepted'] ? [new AcceptedEmailAddress(\is_string($options['list']) ? $options['list'] : null)] : []);
    }

    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        if (true !== $options['suggest'] || null === $this->domainsUrl) {
            return;
        }

        $attr = \is_array($view->vars['attr'] ?? null) ? $view->vars['attr'] : [];
        $controllers = trim((\is_string($attr['data-controller'] ?? null) ? $attr['data-controller'] : '').' '.self::CONTROLLER);
        $actions = trim((\is_string($attr['data-action'] ?? null) ? $attr['data-action'] : '').' blur->'.self::CONTROLLER.'#check');
        $prefix = 'data-'.self::CONTROLLER;

        $view->vars['attr'] = [
            ...$attr,
            'data-controller' => $controllers,
            'data-action' => $actions,
            $prefix.'-url-value' => $this->domainsUrl,
            $prefix.'-suggestion-value' => $this->trans('email_address.suggestion'),
            $prefix.'-use-value' => $this->trans('email_address.use_suggestion'),
            $prefix.'-role-value' => $this->trans('email_address.role'),
        ];
    }

    #[Override]
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $value = $form->getViewData();

        if (!$form->isSubmitted() || !\is_string($value) || null !== ($view->vars['help'] ?? null)) {
            return;
        }

        $suggestion = $this->suggester->suggest($value);

        if ($suggestion instanceof EmailAddress) {
            $view->vars['help'] = $this->trans('email_address.suggestion', ['%address%' => $suggestion->toString()]);
        } elseif ($this->addresses->isRole($value)) {
            $view->vars['help'] = $this->trans('email_address.role');
        } else {
            return;
        }

        $view->vars['help_translation_parameters'] = [];
        $view->vars['email_address_hint'] = true;
    }

    #[Override]
    public function getParent(): string
    {
        return EmailType::class;
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'phil_email_address';
    }

    /**
     * @param array<string, string> $parameters
     */
    private function trans(string $key, array $parameters = []): string
    {
        return $this->translator?->trans($key, $parameters, 'phil_email_address') ?? strtr(match ($key) {
            'email_address.suggestion' => 'Did you mean %address%?',
            'email_address.use_suggestion' => 'Use it',
            default => 'This address reaches a shared mailbox (info@, admin@…): a personal address reaches you more surely.',
        }, $parameters);
    }
}
