<?php

declare(strict_types=1);

namespace Phil\UrlBundle\Tests\Unit\Infrastructure;

use Phil\Enums\Collection\EnumSet;
use Phil\Url\KnownUrlType;
use Phil\Url\TypedUrl;
use Phil\UrlBundle\Infrastructure\Form\TypedUrlTransformer;
use Phil\UrlBundle\Infrastructure\Form\TypedUrlType;
use Phil\UrlBundle\Tests\Unit\Fixtures\RegistryUrlType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;

#[CoversClass(TypedUrlType::class)]
#[CoversClass(TypedUrlTransformer::class)]
final class TypedUrlTypeTest extends TestCase
{
    public function testTheKindIsDetected(): void
    {
        $form = $this->forms()->create(TypedUrlType::class);
        $form->submit('instagram.com/shop');

        self::assertTrue($form->isValid());
        $typed = $form->getData();
        self::assertInstanceOf(TypedUrl::class, $typed);
        self::assertSame([KnownUrlType::Instagram, 'https://instagram.com/shop'], [$typed->type, $typed->url->toString()]);
        self::assertSame([UrlType::class, 'https', ['inputmode' => 'url'], 'This address is not one of the accepted kinds.'], [$form->getConfig()->getType()->getParent()?->getInnerType()::class, $form->getConfig()->getOption('default_protocol'), $form->getConfig()->getOption('attr'), $form->getConfig()->getOption('invalid_message')]);
    }

    public function testOnlyTheAcceptedKindsAreTaken(): void
    {
        $form = $this->forms()->create(TypedUrlType::class, null, ['types' => [KnownUrlType::Facebook, RegistryUrlType::class]]);
        $form->submit('https://example.com/');

        self::assertFalse($form->isValid());
        self::assertNull($form->getData());

        $registry = $this->forms()->create(TypedUrlType::class, null, ['types' => [KnownUrlType::Facebook, RegistryUrlType::class]]);
        $registry->submit('https://registry.example/listing/1');
        self::assertSame(RegistryUrlType::Listing, $registry->getData()?->type);
    }

    public function testAnAddressIsShownAndAnEmptyFieldIsNone(): void
    {
        self::assertSame('https://facebook.com/shop', $this->forms()->create(TypedUrlType::class, TypedUrl::of(KnownUrlType::Facebook, 'https://facebook.com/shop'))->createView()->vars['value']);

        $form = $this->forms()->create(TypedUrlType::class, null, ['required' => false]);
        $form->submit('');
        self::assertTrue($form->isValid());
        self::assertNull($form->getData());
    }

    public function testTheTransformer(): void
    {
        $transformer = new TypedUrlTransformer([EnumSet::of(KnownUrlType::class, KnownUrlType::X)]);

        self::assertSame('', $transformer->transform(null));
        self::assertNull($transformer->reverseTransform(null));
        self::assertNull($transformer->reverseTransform(''));
        self::assertSame(KnownUrlType::X, $transformer->reverseTransform('https://x.com/shop')?->type);

        try {
            $transformer->reverseTransform('https://example.com/');
            self::fail('An address of no accepted kind was taken.');
        } catch (TransformationFailedException $transformationFailedException) {
            self::assertSame(['https://example.com/ is not a X address.', 0], [$transformationFailedException->getMessage(), $transformationFailedException->getCode()]);
            self::assertNotNull($transformationFailedException->getPrevious());
        }
    }

    private function forms(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()->addType(new TypedUrlType())->getFormFactory();
    }
}
