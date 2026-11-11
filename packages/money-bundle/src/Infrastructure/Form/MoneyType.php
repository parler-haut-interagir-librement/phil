<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Infrastructure\Form;

use Override;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\MoneyBundle\Application\AcceptedCurrencies;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A `Money` in a form: an `amount` text field in the request's locale and, when more than one currency is offered,
 * a `currency` choice.
 *
 * ```php
 * $builder->add('price', MoneyType::class);                          // the accepted currencies
 * $builder->add('price', MoneyType::class, ['currency' => 'CAD']);   // one currency, no choice
 * $builder->add('price', MoneyType::class, ['currencies' => ['CAD', 'USD']]);
 * ```
 *
 * The amount is read as a string, never a float, to the precision of its currency: `12.345` in dollars is refused
 * rather than rounded. Without a `currency` option, the offered currencies are `currencies`, or the accepted ones;
 * a single one is fixed, and a submitted amount is in it whatever the currency of the data the form was filled
 * from. An empty field starts on the default accepted currency when it is offered, on the first
 * offered one otherwise.
 *
 * @extends AbstractType<Money|null>
 */
final class MoneyType extends AbstractType
{
    public function __construct(
        private readonly AcceptedCurrencies $accepted,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $offered */
        $offered = $options['currencies'];
        $accepted = $this->accepted->default();
        $default = $accepted instanceof Currency && \in_array($accepted->code, $offered, true) ? $accepted : Currency::of($offered[0]);

        $builder->add('amount', TextType::class, [
            'label' => false,
            'required' => $options['required'],
            'attr' => ['inputmode' => 'decimal'],
        ]);

        if (1 < \count($offered)) {
            $builder->add('currency', ChoiceType::class, [
                'label' => false,
                'choices' => array_combine($offered, $offered),
                'choice_label' => static fn (string $code): string => Currencies::getName($code),
                'choice_translation_domain' => false,
            ]);
        }

        $builder->addModelTransformer(new MoneyTransformer($default, 1 === \count($offered)));
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'compound' => true,
            'error_bubbling' => false,
            'invalid_message' => 'This value is not a valid money amount.',
        ]);

        $resolver
            ->define('currency')
            ->default(null)
            ->allowedTypes('null', 'string')
            ->info('One currency, offered without a choice.');

        $resolver
            ->define('currencies')
            ->default(null)
            ->allowedTypes('null', 'string[]')
            ->info('The currencies offered; the accepted ones by default.')
            ->normalize(function (Options $options, ?array $codes): array {
                $currency = $options['currency'];

                if (\is_string($currency)) {
                    return [Currency::of($currency)->code];
                }

                /** @var list<string>|null $codes */
                $currencies = null === $codes ? $this->accepted->all() : array_map(Currency::of(...), $codes);

                return [] !== $currencies
                    ? array_values(array_unique(array_map(static fn (Currency $currency): string => $currency->code, $currencies)))
                    : throw new InvalidOptionsException('The "currencies" of a money field offer one currency or more.');
            });
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'phil_money';
    }
}
