<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Tests\Unit\Infrastructure;

use Phil\BlockEditorBundle\Application\Exception\InvalidToolset;
use Phil\BlockEditorBundle\Application\Toolset;
use Phil\BlockEditorBundle\Infrastructure\Form\BlockEditorType;
use Phil\BlockEditorBundle\Infrastructure\Http\EditorRequest;
use Phil\BlockEditorBundle\Infrastructure\Http\Endpoints;
use Phil\BlockEditorBundle\Infrastructure\Ujo\ToolsetNormalizers;
use Phil\BlockEditorBundle\Tests\Unit\Fixture\FixedRouter;
use Phil\Ujo\Document;
use Phil\Ujo\Type\BlockTypeRegistry;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Security\Csrf\SameOriginCsrfTokenManager;

#[CoversClass(BlockEditorType::class)]
final class BlockEditorTypeTest extends TestCase
{
    private function forms(?Toolset $toolset = null, ?RequestStack $requests = null, ?Endpoints $endpoints = null): FormFactoryInterface
    {
        $editor = $requests instanceof RequestStack ? new EditorRequest(new SameOriginCsrfTokenManager($requests, null, null, [EditorRequest::TOKEN_ID], cookieName: 'my-cookie')) : null;
        $type = new BlockEditorType(new Ujo(), new ToolsetNormalizers(BlockTypeRegistry::default()), $toolset ?? Toolset::full(), $endpoints ?? new Endpoints(), ['accent', 'danger'], $requests, $editor);

        return Forms::createFormFactoryBuilder()->addType($type)->getFormFactory();
    }

    /**
     * @return array<array-key, mixed>
     */
    private function editor(FormView $view): array
    {
        $editor = $view->vars['editor'];
        self::assertIsArray($editor);

        return $editor;
    }

    public function testTheViewCarriesTheEditorsConfiguration(): void
    {
        $view = $this->forms()->createNamed('body', BlockEditorType::class)->createView();

        self::assertSame([
            'tools' => Toolset::BLOCK_TOOLS,
            'inlineTools' => Toolset::INLINE_TOOLS,
            'readOnly' => false,
            'placeholder' => null,
            'locale' => 'en',
            'messages' => [],
            'colors' => ['accent', 'danger'],
            'uploadUrl' => null,
            'mentionsUrl' => null,
            'linksUrl' => null,
            'csrfCookie' => null,
        ], $view->vars['editor']);
        self::assertSame(['form', 'text', 'phil_block_editor', '_body'], $view->vars['block_prefixes']);
        self::assertSame('phil_block_editor', new BlockEditorType(new Ujo(), new ToolsetNormalizers(BlockTypeRegistry::default()), Toolset::full())->getBlockPrefix());
        self::assertSame(TextType::class, new BlockEditorType(new Ujo(), new ToolsetNormalizers(BlockTypeRegistry::default()), Toolset::full())->getParent());
    }

    public function testTheOptionsReachTheView(): void
    {
        $request = new Request();
        $request->setLocale('fr_CA');

        $requests = new RequestStack([$request]);
        $forms = $this->forms(Toolset::reduced(), $requests, new Endpoints(new FixedRouter(), [Endpoints::IMAGE, Endpoints::MENTIONS, Endpoints::LINKS]));

        $editor = $this->editor($forms->createNamed('body', BlockEditorType::class)->createView());
        self::assertSame(Toolset::REDUCED_BLOCK_TOOLS, $editor['tools'], 'the configured toolset');
        self::assertSame('fr_CA', $editor['locale']);
        self::assertSame(['/_be/phil_block_editor_image', '/_be/phil_block_editor_mentions', '/_be/phil_block_editor_links'], [$editor['uploadUrl'], $editor['mentionsUrl'], $editor['linksUrl']]);

        $view = $forms->createNamed('body', BlockEditorType::class, null, [
            'preset' => 'full',
            'inline_tools' => ['bold'],
            'read_only' => true,
            'placeholder' => 'Write',
            'locale' => 'de',
            'messages' => ['delete' => 'Weg'],
            'colors' => ['muted'],
            'upload_url' => '/up',
            'mentions_url' => null,
            'links_url' => '/links',
        ])->createView();

        self::assertSame([
            'tools' => Toolset::BLOCK_TOOLS,
            'inlineTools' => ['bold'],
            'readOnly' => true,
            'placeholder' => 'Write',
            'locale' => 'de',
            'messages' => ['delete' => 'Weg'],
            'colors' => ['muted'],
            'uploadUrl' => '/up',
            'mentionsUrl' => null,
            'linksUrl' => '/links',
            'csrfCookie' => 'my-cookie',
        ], $view->vars['editor']);

        $tools = $this->editor($forms->createNamed('body', BlockEditorType::class, null, ['tools' => ['paragraph', 'quote']])->createView());
        self::assertSame(['paragraph', 'quote'], $tools['tools']);
        self::assertSame(Toolset::REDUCED_INLINE_TOOLS, $tools['inlineTools'], 'the configured inline tools');
        self::assertSame(['paragraph'], $this->editor($forms->createNamed('body', BlockEditorType::class, null, ['preset' => 'reduced', 'tools' => ['paragraph']])->createView())['tools']);
        self::assertSame(Toolset::INLINE_TOOLS, $this->editor($forms->createNamed('body', BlockEditorType::class, null, ['inline_tools' => Toolset::INLINE_TOOLS])->createView())['inlineTools']);
        self::assertSame('en', $this->editor($this->forms(null, new RequestStack())->create(BlockEditorType::class)->createView())['locale'], 'no request');
    }

    public function testADisabledFieldIsReadOnly(): void
    {
        self::assertTrue($this->editor($this->forms()->create(BlockEditorType::class, null, ['disabled' => true])->createView())['readOnly']);
    }

    public function testTheSubmittedJsonIsCheckedAndNarrowed(): void
    {
        $form = $this->forms(Toolset::reduced())->create(BlockEditorType::class);
        $form->submit('{"blocks":[{"id":"a","type":"paragraph","data":{"text":"<strong>x</strong> <mark>m</mark>"}}]}');

        self::assertTrue($form->isSynchronized());
        self::assertSame('{"blocks":[{"id":"a","type":"paragraph","data":{"text":"<b>x</b> m"}}]}', $form->getData());

        $document = $this->forms()->create(BlockEditorType::class, null, ['document' => true]);
        $document->submit('{"blocks":[{"id":"a","type":"paragraph","data":{"text":"<mark>m</mark>"}}]}');

        $data = $document->getData();
        self::assertInstanceOf(Document::class, $data);
        self::assertSame(['text' => '<mark class="cdx-marker">m</mark>'], $data->blocks[0]->data);
    }

    public function testABlockOutsideTheToolsetIsRefusedUnlessForeignBlocksAreKept(): void
    {
        $json = '{"blocks":[{"id":"a","type":"table","data":{"content":[["x"]]}}]}';

        $strict = $this->forms(Toolset::reduced())->create(BlockEditorType::class);
        $strict->submit($json);
        self::assertFalse($strict->isSynchronized());
        self::assertSame('This value is not a valid UJO document: {{ error }}', $strict->getTransformationFailure()?->getInvalidMessage());
        self::assertSame('This value is not a valid UJO document.', $strict->getConfig()->getOption('invalid_message'));

        $kept = $this->forms(Toolset::reduced())->create(BlockEditorType::class, null, ['keep_foreign_blocks' => true]);
        $kept->submit($json);
        self::assertTrue($kept->isSynchronized());
        self::assertSame('{"blocks":[{"id":"a","type":"table","data":{"withHeadings":false,"stretched":false,"content":[["x"]]}}]}', $kept->getData());
    }

    public function testAModelIsShownAsJson(): void
    {
        $form = $this->forms()->create(BlockEditorType::class, '{"blocks":[]}');

        self::assertSame('{"blocks":[]}', $form->createView()->vars['value']);
    }

    public function testAnUnknownToolIsADevelopersMistake(): void
    {
        $this->expectException(InvalidToolset::class);

        $this->forms()->create(BlockEditorType::class, null, ['tools' => ['statblock']]);
    }

    public function testOptionsAreTyped(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->forms()->create(BlockEditorType::class, null, ['preset' => 'tiny']);
    }
}
