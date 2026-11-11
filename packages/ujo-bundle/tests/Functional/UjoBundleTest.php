<?php

declare(strict_types=1);

namespace Phil\UjoBundle\Tests\Functional;

use Override;
use Phil\Ujo\Ujo;
use Phil\UjoBundle\Infrastructure\Form\UjoType;
use Phil\UjoBundle\Infrastructure\Validator\ValidUjo;
use Phil\UjoBundle\PhilUjoBundle;
use Phil\UjoBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;

#[CoversClass(PhilUjoBundle::class)]
final class UjoBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
    }

    public function testDocumentsAreRenderedInTemplates(): void
    {
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);

        self::assertSame(<<<'HTML'
            <article><p class="ujo-paragraph">a <b>b</b></p>
            <div class="ujo-raw"><p>r</p></div></article>
            <aside><p class="ujo-paragraph">a <b>b</b></p>
            <div class="ujo-raw"><p>r</p></div></aside>
            <p>a b

            r</p>
            <footer></footer>

            HTML, $twig->render('page.html.twig', [
            'body' => '{"blocks":[{"id":"a","type":"paragraph","data":{"text":"a <strong>b</strong><script>x</script>"}},{"id":"r","type":"raw","data":{"html":"<p onclick=\"x\">r</p>"}}]}',
            'none' => null,
        ]));
    }

    public function testTheFieldKeepsTheCanonicalJsonOrSaysWhyNot(): void
    {
        $forms = $this->service('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $forms);

        $form = $forms->create(UjoType::class);
        $form->submit('{"blocks":[{"id":"a","type":"paragraph","data":{"text":"<em>t</em>"}}]}');
        self::assertTrue($form->isValid());
        self::assertSame('{"blocks":[{"id":"a","type":"paragraph","data":{"text":"<i>t</i>"}}]}', $form->getData());

        $invalid = $forms->create(UjoType::class, null, ['document' => true]);
        $invalid->submit('{"blocks":[{"id":"a","type":"header","data":{"text":"t","level":0}}]}');
        self::assertFalse($invalid->isValid());
        self::assertSame('Cette valeur n’est pas un document UJO valide : The UJO document is invalid at blocks[0].data.level: 0 is below the minimum 1.', $invalid->getErrors()[0]->getMessage());
    }

    public function testTheConstraintSpeaksTheLocale(): void
    {
        $validator = $this->service('validator');
        self::assertInstanceOf(ValidatorInterface::class, $validator);

        $violations = $validator->validate('{"blocks":[{"id":"a","type":"raw","data":{"html":"x"}}]}', new ValidUjo(['paragraph']));

        self::assertSame('Les blocs de type "raw" ne sont pas acceptés ici.', $violations->get(0)->getMessage());
        self::assertInstanceOf(Ujo::class, $this->service(Ujo::class));
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }
}
