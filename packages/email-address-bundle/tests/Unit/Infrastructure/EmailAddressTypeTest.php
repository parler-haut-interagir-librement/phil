<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\EmailAddress\TypoSuggester;
use Phil\EmailAddressBundle\Application\EmailAddresses;
use Phil\EmailAddressBundle\Application\EmailPolicies;
use Phil\EmailAddressBundle\Application\EmailPolicy;
use Phil\EmailAddressBundle\Infrastructure\Form\EmailAddressType;
use Phil\EmailAddressBundle\Infrastructure\Validator\Attribute\AcceptedEmailAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

#[CoversClass(EmailAddressType::class)]
final class EmailAddressTypeTest extends TestCase
{
    private const string CONTROLLER = 'phil--email-address-bundle--email-address';

    public function testTheFieldCarriesTheControllerAndItsMessages(): void
    {
        $view = $this->factory('/email-address/domains.json')->create(EmailAddressType::class, null, ['attr' => ['data-controller' => 'other', 'data-action' => 'input->other#go', 'class' => 'wide']])->createView();
        $attr = $view->vars['attr'];
        $prefixes = $view->vars['block_prefixes'];
        self::assertIsArray($prefixes);

        self::assertSame([
            'data-controller' => 'other '.self::CONTROLLER,
            'data-action' => 'input->other#go blur->'.self::CONTROLLER.'#check',
            'class' => 'wide',
            'data-'.self::CONTROLLER.'-url-value' => '/email-address/domains.json',
            'data-'.self::CONTROLLER.'-suggestion-value' => 'Did you mean %address%?',
            'data-'.self::CONTROLLER.'-use-value' => 'Use it',
            'data-'.self::CONTROLLER.'-role-value' => 'This address reaches a shared mailbox (info@, admin@…): a personal address reaches you more surely.',
        ], $attr);
        self::assertContains('phil_email_address', $prefixes);
    }

    public function testTheMessagesAreTranslatedInTheBundleDomain(): void
    {
        $attr = $this->factory('/domains.json', $this->translator())->create(EmailAddressType::class)->createView()->vars['attr'];

        self::assertIsArray($attr);
        self::assertSame('email_address.suggestion [phil_email_address]', $attr['data-'.self::CONTROLLER.'-suggestion-value']);
        self::assertSame('data-controller', array_key_first($attr));
        self::assertSame(self::CONTROLLER, $attr['data-controller']);
        self::assertSame('blur->'.self::CONTROLLER.'#check', $attr['data-action']);
    }

    public function testNoControllerWithoutTheUrlOrWhenTurnedOff(): void
    {
        self::assertSame([], $this->factory(null)->create(EmailAddressType::class)->createView()->vars['attr']);
        self::assertSame([], $this->factory('/domains.json')->create(EmailAddressType::class, null, ['suggest' => false])->createView()->vars['attr']);
    }

    public function testTheConstraintNamesTheList(): void
    {
        $factory = $this->factory(null);

        self::assertEquals([new AcceptedEmailAddress()], $factory->create(EmailAddressType::class)->getConfig()->getOption('constraints'));
        self::assertEquals([new AcceptedEmailAddress('staff')], $factory->create(EmailAddressType::class, null, ['list' => 'staff'])->getConfig()->getOption('constraints'));
        self::assertSame([], $factory->create(EmailAddressType::class, null, ['accepted' => false])->getConfig()->getOption('constraints'));
    }

    /**
     * @param array<string, mixed> $options
     */
    #[TestWith([['list' => 3]])]
    #[TestWith([['accepted' => 'yes']])]
    #[TestWith([['suggest' => 1]])]
    public function testTheOptionsHaveTheirTypes(array $options): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory(null)->create(EmailAddressType::class, null, $options);
    }

    public function testASubmittedSlipGetsTheSuggestionAsHelpWithoutJavaScript(): void
    {
        $form = $this->factory(null)->create(EmailAddressType::class);
        $form->submit('ada@gmial.com');

        $view = $form->createView();

        self::assertSame('Did you mean ada@gmail.com?', $view->vars['help']);
        self::assertSame([], $view->vars['help_translation_parameters']);
        self::assertTrue($view->vars['email_address_hint']);
        self::assertTrue($form->isValid());
    }

    public function testASubmittedRoleAddressGetsTheWarningAsHelp(): void
    {
        $form = $this->factory(null, $this->translator())->create(EmailAddressType::class);
        $form->submit('info@example.org');

        self::assertSame('email_address.role [phil_email_address]', $form->createView()->vars['help']);
    }

    public function testNothingIsSaidOfAnAddressThatNeedsNothingNorBeforeSubmission(): void
    {
        $factory = $this->factory(null);
        $form = $factory->create(EmailAddressType::class);
        $form->submit('ada@example.org');

        $empty = $factory->create(EmailAddressType::class);
        $empty->submit('  ');

        $helped = $factory->create(EmailAddressType::class, null, ['help' => 'Your address.']);
        $helped->submit('ada@gmial.com');

        self::assertNull($form->createView()->vars['help']);
        self::assertArrayNotHasKey('email_address_hint', $form->createView()->vars);
        self::assertNull($empty->createView()->vars['help']);
        self::assertNull($factory->create(EmailAddressType::class, 'ada@gmial.com')->createView()->vars['help']);
        self::assertSame('Your address.', $helped->createView()->vars['help']);
    }

    public function testTheTypeExtendsTheEmailType(): void
    {
        $type = new EmailAddressType(new EmailAddresses('key'));

        self::assertSame(EmailType::class, $type->getParent());
        self::assertSame('phil_email_address', $type->getBlockPrefix());
    }

    private function factory(?string $url, ?TranslatorInterface $translator = null): FormFactoryInterface
    {
        $type = new EmailAddressType(new EmailAddresses('key'), new TypoSuggester(), $url, $translator);

        return Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([$type], []))
            ->addExtension(new ValidatorExtension(ValidatorFactory::validator(new EmailPolicies(new EmailPolicy()))))
            ->getFormFactory();
    }

    private function translator(): TranslatorInterface
    {
        return new class implements TranslatorInterface {
            use TranslatorTrait {
                trans as private translate;
            }

            /**
             * @param array<array-key, mixed> $parameters
             */
            #[Override]
            public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return strtr((string) $id, $parameters).' ['.$domain.']';
            }
        };
    }
}
