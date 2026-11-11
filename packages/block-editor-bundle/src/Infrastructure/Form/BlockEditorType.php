<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Infrastructure\Form;

use Override;
use Phil\BlockEditorBundle\Application\Exception\InvalidToolset;
use Phil\BlockEditorBundle\Application\Toolset;
use Phil\BlockEditorBundle\Infrastructure\Http\EditorRequest;
use Phil\BlockEditorBundle\Infrastructure\Http\Endpoints;
use Phil\BlockEditorBundle\Infrastructure\Ujo\ToolsetNormalizers;
use Phil\Ujo\Document;
use Phil\Ujo\Ujo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The block editor as a form field: a hidden input holding the document's UJO JSON, and the element the editor's
 * Stimulus controller turns into blocks, rendered by the bundle's form theme. The submitted JSON is checked by
 * `phil/ujo` and narrowed to the field's toolset; the model is the canonical JSON, or a `Document` with
 * `document: true`. The editor is given the name of the cookie that carries its CSRF token to the endpoints.
 *
 * @extends AbstractType<Document|string|null>
 */
final class BlockEditorType extends AbstractType
{
    /**
     * @param list<string> $colors the colour tokens of the text colour tool
     */
    public function __construct(
        private readonly Ujo $ujo,
        private readonly ToolsetNormalizers $normalizers,
        private readonly Toolset $toolset,
        private readonly Endpoints $endpoints = new Endpoints(),
        private readonly array $colors = [],
        private readonly ?RequestStack $requests = null,
        private readonly ?EditorRequest $editor = null,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $normalizer = $this->normalizers->normalizer($this->toolset($options), true === $options['keep_foreign_blocks']);
        $builder->addModelTransformer(new BlockEditorTransformer($this->ujo, $normalizer, true === $options['document']));
    }

    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $toolset = $this->toolset($options);
        $view->vars['editor'] = [
            'tools' => $toolset->blockTools,
            'inlineTools' => $toolset->inlineTools,
            'readOnly' => true === $options['read_only'] || $form->isDisabled(),
            'placeholder' => $options['placeholder'],
            'locale' => $options['locale'] ?? $this->requests?->getCurrentRequest()?->getLocale() ?? 'en',
            'messages' => $options['messages'],
            'colors' => $options['colors'] ?? $this->colors,
            'uploadUrl' => $options['upload_url'],
            'mentionsUrl' => $options['mentions_url'],
            'linksUrl' => $options['links_url'],
            'csrfCookie' => $this->editor?->cookieName(),
        ];
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'invalid_message' => 'This value is not a valid UJO document.',
        ]);

        $resolver->define('preset')->default(null)->allowedValues(null, 'full', 'reduced')
            ->info('A preset of tools, "full" or "reduced"; the configured toolset by default.');
        $resolver->define('tools')->default(null)->allowedTypes('null', 'string[]')
            ->info('The block types offered, replacing those of the preset or of the configuration.');
        $resolver->define('inline_tools')->default(null)->allowedTypes('null', 'string[]')
            ->info('The inline tools offered, replacing those of the preset or of the configuration.');
        $resolver->define('read_only')->default(false)->allowedTypes('bool')
            ->info('Whether the document is shown without being editable; a disabled field is read-only too.');
        $resolver->define('placeholder')->default(null)->allowedTypes('null', 'string')
            ->info("The hint of an empty block, already translated; the editor's own by default.");
        $resolver->define('locale')->default(null)->allowedTypes('null', 'string')
            ->info("The language of the editor's labels; the request's by default.");
        $resolver->define('messages')->default([])->allowedTypes('string[]')
            ->info('Labels of the editor replaced, by key.');
        $resolver->define('colors')->default(null)->allowedTypes('null', 'string[]')
            ->info('The colour tokens of the text colour tool; the configured ones by default.');
        $resolver->define('upload_url')->default($this->endpoints->url(Endpoints::IMAGE))->allowedTypes('null', 'string')
            ->info("Where images are uploaded; the bundle's route when an uploader is configured, else none.");
        $resolver->define('mentions_url')->default($this->endpoints->url(Endpoints::MENTIONS))->allowedTypes('null', 'string')
            ->info("Where `@` mentions are searched; the bundle's route when a search is configured, else none.");
        $resolver->define('links_url')->default($this->endpoints->url(Endpoints::LINKS))->allowedTypes('null', 'string')
            ->info("Where `[[` page links are searched; the bundle's route when a search is configured, else none.");
        $resolver->define('keep_foreign_blocks')->default(false)->allowedTypes('bool')
            ->info('Whether blocks of types outside the toolset, already in the document, are kept rather than refused.');
        $resolver->define('document')->default(false)->allowedTypes('bool')
            ->info('Whether the model is a Document rather than its canonical JSON.');
    }

    #[Override]
    public function getParent(): string
    {
        return TextType::class;
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'phil_block_editor';
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws InvalidToolset
     */
    private function toolset(array $options): Toolset
    {
        /** @var array{preset: ?string, tools: ?list<string>, inline_tools: ?list<string>} $options */
        if (null !== $options['preset']) {
            return Toolset::preset($options['preset'], $options['tools'], $options['inline_tools']);
        }

        return new Toolset($options['tools'] ?? $this->toolset->blockTools, $options['inline_tools'] ?? $this->toolset->inlineTools);
    }
}
