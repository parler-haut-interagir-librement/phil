<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Fixture;

use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms as FormComponent;

final class Forms
{
    public static function factory(): FormFactoryInterface
    {
        return FormComponent::createFormFactoryBuilder()->addExtension(new HttpFoundationExtension())->getFormFactory();
    }
}
