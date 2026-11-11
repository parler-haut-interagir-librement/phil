<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ReactionRecipesTest extends KitTestCase
{
    private const string C = 'phil--reactions-bundle--picker';

    public function testAButtonWithAnEmojiAndACounter(): void
    {
        self::assertSame(
            '<button type="submit" aria-pressed="true" class="isReaction" name="withdraw"><span aria-hidden="true">👍</span><span class="isVisuallyHidden">Like</span><span aria-hidden="true">3</span><span class="isVisuallyHidden">3 reactions</span></button>',
            $this->render('<twig:ReactionButton label="Like" emoji="👍" :count="3" countLabel="3 reactions" :pressed="true" name="withdraw" />'),
        );
    }

    public function testAButtonNotPressedAndAnImage(): void
    {
        self::assertSame(
            '<button type="submit" aria-pressed="false" class="isReaction"><img src="/g.webp" alt="A gnome" width="20" height="20" loading="lazy"></button>',
            $this->render('<twig:ReactionButton label="Gnome" image="/g.webp" imageAlt="A gnome" />'),
        );
        self::assertStringContainsString('alt="Gnome"', $this->render('<twig:ReactionButton label="Gnome" image="/g.webp" />'));
    }

    public function testNoCounterAtZeroOrWhenHidden(): void
    {
        self::assertStringNotContainsString('0', $this->render('<twig:ReactionButton label="Like" emoji="👍" :count="0" countLabel="0 reactions" />'));
        self::assertSame(1, substr_count($this->render('<twig:ReactionButton label="Like" emoji="👍" />'), 'isVisuallyHidden'));
    }

    public function testThePlainTextOfAViewerWhoCannotPress(): void
    {
        self::assertSame(
            '<span class="isReaction wide"><span aria-hidden="true">👍</span><span class="isVisuallyHidden">Like</span><span aria-hidden="true">3</span><span class="isVisuallyHidden">3</span></span>',
            $this->render('<twig:ReactionButton label="Like" emoji="👍" :count="3" :interactive="false" class="wide" />'),
        );
    }

    public function testThePicker(): void
    {
        $c = self::C;
        $html = $this->render('<twig:ReactionPicker label="More" :groups="groups" />', ['groups' => [['label' => 'Emojis', 'options' => [
            ['label' => '<Spark>', 'emoji' => '✨', 'image' => null, 'imageAlt' => null, 'attributes' => ['name' => 'react', 'value' => 'sp"ark']],
            ['label' => 'Gnome', 'emoji' => null, 'image' => '/g.webp', 'imageAlt' => null, 'attributes' => []],
        ]]]]);

        self::assertStringStartsWith('<details class="isReactionPicker" data-controller="'.$c.'" data-action="toggle-&gt;'.$c.'#toggled keydown-&gt;'.$c.'#keydown click-&gt;'.$c.'#choose click@window-&gt;'.$c.'#outside" data-'.$c.'-results-value="Reactions shown: {count}"><summary class="isReaction" data-'.$c.'-target="summary">More</summary>', $html);
        self::assertStringContainsString('<input type="search" hidden autocomplete="off" aria-label="Search reactions by name" placeholder="Search reactions by name" data-'.$c.'-target="search" data-action="input->'.$c.'#filter">', $html);
        self::assertStringContainsString('<p class="isMuted" hidden data-'.$c.'-target="empty">No reaction has this name.</p>', $html);
        self::assertStringContainsString('<div role="group" aria-label="Emojis" data-'.$c.'-target="group">', $html);
        self::assertStringContainsString('<button class="isReaction" type="submit" data-'.$c.'-target="option" data-search="&lt;Spark&gt;" name="react" value="sp&quot;ark"><span aria-hidden="true">✨</span><span class="isVisuallyHidden">&lt;Spark&gt;</span></button>', $html);
        self::assertStringContainsString('alt="Gnome"', $html);
    }

    public function testThePickerWritesOnlyTheAttributesOfAFormButton(): void
    {
        $html = $this->render('<twig:ReactionPicker label="More" :groups="groups" />', ['groups' => [['label' => 'Emojis', 'options' => [
            ['label' => 'Spark', 'emoji' => '✨', 'image' => null, 'imageAlt' => null, 'attributes' => [
                'name' => 'react',
                'value' => 'spark',
                'title' => 'Spark',
                'data-live-action-param' => 'react',
                'aria-describedby' => 'help',
                'onclick=alert(1) x' => 'y',
                'onmouseover' => 'alert(2)',
                'formaction' => 'javascript:alert(3)',
                'style' => 'color: red',
            ]],
        ]]]]);

        self::assertStringContainsString(' data-search="Spark" name="react" value="spark" title="Spark" data-live-action-param="react" aria-describedby="help">', $html);
        self::assertStringNotContainsString('alert', $html);
        self::assertStringNotContainsString('style=', $html);
    }

    public function testTheTextsAreProps(): void
    {
        $html = $this->render('<twig:ReactionPicker label="Autres" :groups="[]" searchLabel="Chercher" emptyLabel="Rien" resultsLabel="N : {count}" />');

        self::assertStringContainsString('aria-label="Chercher" placeholder="Chercher"', $html);
        self::assertStringContainsString('>Rien</p>', $html);
        self::assertStringContainsString('-results-value="N : {count}"', $html);
    }
}
