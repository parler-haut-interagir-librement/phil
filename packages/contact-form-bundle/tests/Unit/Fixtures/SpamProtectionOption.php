<?php

declare(strict_types=1);

namespace Phil\ContactFormBundle\Tests\Unit\Fixtures;

use Override;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The option `phil/spam-protection-bundle` gives every form, without its protection: for unit tests of the form.
 */
final class SpamProtectionOption extends AbstractTypeExtension
{
    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('spam_protection', false);
    }

    #[Override]
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
