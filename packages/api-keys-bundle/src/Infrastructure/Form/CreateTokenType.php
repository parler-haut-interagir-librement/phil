<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Infrastructure\Form;

use Override;
use Phil\ApiKeysBundle\Application\ScopeCatalogue;
use Phil\ApiKeysBundle\Application\ScopeDefinition;
use Phil\ApiKeysBundle\Domain\Model\ApiToken;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * The form a token is created with: its name, its scopes among the declared ones — each by its translated label,
 * only those of the `scopes` option when it is given —, and its lifetime among the configured choices. Its data is an array of `name`, `scopes` and `lifetime`, in
 * days; CSRF-protected under the id `phil_api_keys_create`.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class CreateTokenType extends AbstractType
{
    public const string CSRF_TOKEN_ID = 'phil_api_keys_create';

    /**
     * @param list<positive-int> $lifetimes       the lifetimes offered, in days
     * @param positive-int       $defaultLifetime the lifetime chosen at first
     */
    public function __construct(
        private readonly ScopeCatalogue $scopes,
        private readonly array $lifetimes,
        private readonly int $defaultLifetime,
        private readonly string $scopeTranslationDomain,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string>|null $allowed */
        $allowed = $options['scopes'];
        $scopes = $this->definitions($allowed);

        $builder
            ->add('name', TextType::class, [
                'label' => 'api_keys.form.name',
                'attr' => ['maxlength' => ApiToken::NAME_MAX_LENGTH, 'autocomplete' => 'off'],
                'constraints' => [new NotBlank(), new Length(max: ApiToken::NAME_MAX_LENGTH)],
            ])
            ->add('scopes', ChoiceType::class, [
                'label' => 'api_keys.form.scopes',
                'choices' => array_keys($scopes),
                'choice_label' => static fn (string $name): string => $scopes[$name]->label,
                'choice_attr' => static fn (string $name): array => ['title' => $scopes[$name]->description],
                'choice_translation_domain' => $this->scopeTranslationDomain,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->add('lifetime', ChoiceType::class, [
                'label' => 'api_keys.form.lifetime',
                'choices' => $this->lifetimes,
                'choice_label' => static fn (int $days): TranslatableMessage => new TranslatableMessage('api_keys.form.days', ['%count%' => $days], 'phil_api_keys'),
                'data' => \in_array($this->defaultLifetime, $this->lifetimes, true) ? $this->defaultLifetime : null,
                'constraints' => [new NotBlank()],
            ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'phil_api_keys',
            'csrf_token_id' => self::CSRF_TOKEN_ID,
            'scopes' => null,
        ]);
        $resolver->setAllowedTypes('scopes', ['null', 'string[]']);
    }

    /**
     * @param list<string>|null $allowed the scopes offered, null for every declared one
     *
     * @return array<string, ScopeDefinition>
     */
    private function definitions(?array $allowed): array
    {
        $scopes = [];

        foreach ($this->scopes->all() as $scope) {
            if (null === $allowed || \in_array($scope->name, $allowed, true)) {
                $scopes[$scope->name] = $scope;
            }
        }

        return $scopes;
    }
}
