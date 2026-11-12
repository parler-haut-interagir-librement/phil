<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Infrastructure\Form;

use Override;
use Phil\RateLimitBundle\Application\Exception\TooManyRequests;
use Phil\RateLimitBundle\Application\RateLimitEnforcer;
use Phil\RateLimitBundle\Application\RateLimitRules;
use Phil\RateLimitBundle\Domain\Attribute\RateLimit;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Limits the submissions of a form by its name: the rules of its `rate_limit` option, else those the `forms` section
 * gives its name. Each submission of the root form consumes, valid or not, under the key of the rule and the form's
 * name; a refusal is an error on the form, which then is not valid, rather than an error page.
 *
 * ```php
 * $builder->create('contact', ContactType::class, options: ['rate_limit' => 'forms']);
 * ```
 */
final class RateLimitTypeExtension extends AbstractTypeExtension
{
    public const string MESSAGE = 'Too many submissions of this form. Try again in %seconds% seconds.';

    /** @var array<string, list<RateLimit>> */
    private readonly array $forms;

    /**
     * @param array<string, mixed> $forms the rules of the `forms` section, by form name
     */
    public function __construct(
        private readonly RateLimitEnforcer $enforcer,
        private readonly RequestStack $requests,
        array $forms = [],
        private readonly ?TranslatorInterface $translator = null,
    ) {
        $this->forms = array_map(RateLimitRules::from(...), $forms);
    }

    #[Override]
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('rate_limit')
            ->default(null)
            ->allowedTypes('null', 'bool', 'string', 'array', RateLimit::class)
            ->info("The rate limits of the form's submissions: a limiter's name, a rule, a list of them, or false for none.");
    }

    /**
     * @param array<string, mixed> $options
     */
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (false === $options['rate_limit']) {
            return;
        }

        $name = $builder->getName();
        $rules = true === $options['rate_limit'] || null === $options['rate_limit'] ? $this->forms[$name] ?? [] : RateLimitRules::from($options['rate_limit']);

        if ([] === $rules) {
            return;
        }

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($rules, $name): void {
            $form = $event->getForm();
            $request = $this->requests->getMainRequest();

            if (!$form->isRoot() || !$request instanceof Request) {
                return;
            }

            try {
                $this->enforcer->enforce($request, $rules, $name);
            } catch (TooManyRequests $tooManyRequests) {
                $parameters = ['%seconds%' => (string) $tooManyRequests->decision->retryAfter];
                $message = $this->translator?->trans(self::MESSAGE, $parameters, 'phil_rate_limit') ?? strtr(self::MESSAGE, $parameters);
                $form->addError(new FormError($message, self::MESSAGE, $parameters, cause: $tooManyRequests));
            }
        });
    }
}
