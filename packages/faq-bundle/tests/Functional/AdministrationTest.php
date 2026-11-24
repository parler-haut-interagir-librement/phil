<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem;
use Override;
use Phil\FaqBundle\Application\FaqEditor;
use Phil\FaqBundle\Domain\Contract\FaqRepository;
use Phil\FaqBundle\Domain\Contract\UnansweredSearchRepository;
use Phil\FaqBundle\Domain\Model\Question;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use Phil\FaqBundle\Infrastructure\EasyAdmin\FaqAdmin;
use Phil\FaqBundle\Infrastructure\Http\AdminGuard;
use Phil\FaqBundle\Infrastructure\Http\FaqAdminController;
use Phil\FaqBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The administration behind its role: the sections and questions of a language, the forms, the buttons, the totals.
 */
#[CoversClass(FaqAdminController::class)]
#[CoversClass(AdminGuard::class)]
#[CoversClass(FaqAdmin::class)]
final class AdministrationTest extends WebTestCase
{
    private const array EDITOR = ['HTTP_X_ROLES' => 'ROLE_EDITOR'];

    private KernelBrowser $client;

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $kernel = self::$kernel;
        $this->assertInstanceOf(TestKernel::class, $kernel);
        $kernel->resetDatabase();
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    public function testTheAdministrationNeedsItsRole(): void
    {
        $this->client->catchExceptions(false);

        try {
            $this->client->request('GET', '/admin/faq/', server: ['HTTP_X_ROLES' => 'ROLE_USER']);
            self::fail('The role is needed.');
        } catch (AccessDeniedException $accessDeniedException) {
            self::assertSame(['ROLE_EDITOR'], $accessDeniedException->getAttributes());
        }
    }

    public function testAFormWithoutItsTokenIsRefused(): void
    {
        $this->client->request('POST', '/admin/faq/sections/new', ['title' => 'Orders', '_token' => 'forged'], server: self::EDITOR);

        self::assertResponseStatusCodeSame(400);
    }

    public function testAnEditorWritesSectionsAndQuestions(): void
    {
        $index = $this->client->request('GET', '/admin/faq/', server: self::EDITOR);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSame('No section yet.', $index->filter('main p')->last()->text());

        $form = $this->client->click($index->selectLink('New section')->link(), self::EDITOR);
        $this->client->submit($form->selectButton('Save')->form(['title' => 'Orders & payments']), [], self::EDITOR);
        self::assertResponseRedirects('/admin/faq/?locale=en&done=saved', 303);
        $index = $this->client->followRedirect();
        self::assertSame('Saved.', $index->filter('[role=status]')->text());
        self::assertStringContainsString('#faq-orders-payments', $index->filter('h2')->text());

        $form = $this->client->click($index->selectLink('New question')->link(), self::EDITOR);
        $this->client->submit($form->selectButton('Save')->form(['text' => 'How do I pay?', 'answer' => 'By **card**.', 'format' => 'markdown']), [], self::EDITOR);
        $index = $this->client->followRedirect();
        self::assertSame('How do I pay? Published', $index->filter('tbody th')->text());
        self::assertSame('#how-do-i-pay', $index->filter('tbody code')->text());

        $this->client->submit($index->selectButton('Withdraw')->form(), [], self::EDITOR);
        $index = $this->client->followRedirect();
        self::assertSame('How do I pay? Not published', $index->filter('tbody th')->text());
        self::assertCount(1, $index->selectButton('Publish'));

        $form = $this->client->click($index->selectLink('Edit')->eq(1)->link(), self::EDITOR);
        self::assertSame('By **card**.', $form->filter('textarea')->text());
        self::assertNull($form->filter('#phil-faq-published')->attr('checked'));
        $this->client->submit($form->selectButton('Save')->form(['text' => 'Which payments do you take?', 'answer' => '<p>Cards</p>', 'format' => 'html']), [], self::EDITOR);
        $index = $this->client->followRedirect();
        self::assertSame('Which payments do you take? Not published', $index->filter('tbody th')->text());
        self::assertSame('#how-do-i-pay', $index->filter('tbody code')->text());

        $french = $this->client->request('GET', '/admin/faq/?locale=fr', server: self::EDITOR);
        self::assertSame('— Not translated', $french->filter('tbody th')->text());
        $form = $this->client->click($french->selectLink('Edit')->eq(1)->link(), self::EDITOR);
        $this->client->submit($form->selectButton('Save')->form(['text' => 'Comment payer ?', 'answer' => 'Par carte.', 'published' => '1']), [], self::EDITOR);
        self::assertResponseRedirects('/admin/faq/?locale=fr&done=saved', 303);
        self::assertSame(['Comment payer ?', true], $this->translation('how-do-i-pay', 'fr'));

        $form = $this->client->click($french->selectLink('Edit')->first()->link(), self::EDITOR);
        self::assertNotNull($form->filter('#phil-faq-key')->attr('readonly'));
        $this->client->submit($form->selectButton('Save')->form(['title' => 'Commandes']), [], self::EDITOR);
        $french = $this->client->followRedirect();
        self::assertStringContainsString('Commandes', $french->filter('h2')->text());
    }

    public function testARefusedFormSaysWhyAndKeepsWhatWasTyped(): void
    {
        $form = $this->client->request('GET', '/admin/faq/sections/new', server: self::EDITOR);
        $this->client->submit($form->selectButton('Save')->form(['title' => 'Orders', 'key' => 'Not a key']), [], self::EDITOR);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('A key or an anchor is lowercase letters and digits, in words joined by hyphens.', $this->client->getCrawler()->filter('[role=alert]')->text());
        self::assertSame('Orders', $this->client->getCrawler()->filter('#phil-faq-title')->attr('value'));

        $section = $this->editor()->addSection(Locale::fromString('en'), 'Orders');
        $form = $this->client->request('GET', '/admin/faq/sections/'.$section->id.'/questions/new', server: self::EDITOR);
        $this->client->submit($form->selectButton('Save')->form(['text' => str_repeat('a', 301), 'answer' => 'x']), [], self::EDITOR);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('The wording of a question is 1 to 300 characters.', $this->client->getCrawler()->filter('[role=alert]')->text());

        $question = $this->editor()->addQuestion($section->id, Locale::fromString('en'), 'Q?', 'a');
        $form = $this->client->request('GET', '/admin/faq/questions/'.$question->id.'/edit', server: self::EDITOR);
        $edit = $form->selectButton('Save')->form()->disableValidation();
        $this->client->submit($edit, ['text' => '', 'answer' => 'x', 'format' => 'klingon'], self::EDITOR);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('markdown', $this->client->getCrawler()->filter('#phil-faq-format option[selected]')->attr('value'));

        $form = $this->client->request('GET', '/admin/faq/sections/'.$section->id.'/edit', server: self::EDITOR);
        $this->client->submit($form->selectButton('Save')->form(['title' => ' ']), [], self::EDITOR);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('The title of a section is 1 to 150 characters.', $this->client->getCrawler()->filter('[role=alert]')->text());
    }

    public function testTheButtonsMoveAndDelete(): void
    {
        $editor = $this->editor();
        $en = Locale::fromString('en');
        $first = $editor->addSection($en, 'First');
        $second = $editor->addSection($en, 'Second');
        $editor->addQuestion($first->id, $en, 'A?', 'a');
        $editor->addQuestion($first->id, $en, 'B?', 'b');
        $editor->addQuestion($first->id, $en, 'C?', 'c');

        $index = $this->client->request('GET', '/admin/faq/', server: self::EDITOR);
        $this->press($index->filter('section')->first()->filter('tbody tr')->eq(2)->selectButton('Move up'));
        $index = $this->client->followRedirect();
        self::assertSame('Moved.', $index->filter('[role=status]')->text());
        self::assertSame(['#a', '#c', '#b'], $this->anchors($index));

        $this->press($index->filter('tbody tr')->first()->selectButton('Move down'));
        $index = $this->client->followRedirect();
        self::assertSame(['#c', '#a', '#b'], $this->anchors($index));

        $this->press($index->filter('tbody tr')->first()->selectButton('Move up'));
        $this->press($this->client->followRedirect()->filter('section')->first()->filter('tbody tr')->last()->selectButton('Move down'));
        $index = $this->client->followRedirect();
        self::assertSame(['#c', '#a', '#b'], $this->anchors($index));

        $this->press($index->filter('section')->last()->selectButton('Move up'));
        $index = $this->client->followRedirect();
        self::assertSame(['Second', 'First'], $index->filter('h2')->each(static fn (Crawler $one): string => trim(explode('#', $one->text())[0])));

        $this->press($index->filter('section')->last()->selectButton('Move down'));
        $this->press($this->client->followRedirect()->filter('section')->first()->selectButton('Move down'));
        $index = $this->client->followRedirect();
        self::assertSame(['First', 'Second'], $index->filter('h2')->each(static fn (Crawler $one): string => trim(explode('#', $one->text())[0])));

        $this->press($index->filter('tbody tr')->first()->selectButton('Delete'));
        $index = $this->client->followRedirect();
        self::assertSame('Deleted.', $index->filter('[role=status]')->text());
        self::assertSame(['#a', '#b'], $this->anchors($index));

        $this->press($index->filter('section')->first()->filter('.phil-faq-admin__actions')->selectButton('Delete'));
        $index = $this->client->followRedirect();
        self::assertCount(1, $index->filter('section'));
        self::assertSame('No question in this section yet.', $index->filter('tbody td')->text());
        self::assertSame($second->id, $this->repository()->sections()[0]->id);
    }

    public function testAMissingSectionOrQuestionIsNotFound(): void
    {
        $missing = '00000000-0000-0000-0000-000000000000';
        $token = $this->token();

        foreach (['/admin/faq/sections/%s/edit', '/admin/faq/questions/%s/edit', '/admin/faq/sections/%s/questions/new'] as $path) {
            $this->client->request('GET', \sprintf($path, $missing), server: self::EDITOR);
            self::assertResponseStatusCodeSame(404);
        }

        foreach (['/admin/faq/sections/%s/delete', '/admin/faq/questions/%s/delete', '/admin/faq/questions/%s/publish', '/admin/faq/sections/%s/move', '/admin/faq/questions/%s/move'] as $path) {
            $this->client->request('POST', \sprintf($path, $missing), ['_token' => $token], server: self::EDITOR);
            self::assertResponseStatusCodeSame(404);
        }

        $this->client->request('POST', '/admin/faq/statistics/forget', ['_token' => $token, 'locale' => 'xx_yy', 'query' => 'a'], server: self::EDITOR);
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheTotalsOfVotesAndSearchesAreShownAndASearchForgotten(): void
    {
        $section = $this->editor()->addSection(Locale::fromString('en'), 'Orders');
        $question = $this->editor()->addQuestion($section->id, Locale::fromString('en'), 'Q?', 'a');
        $this->repository()->countVote($question->id, true);
        $searches = self::getContainer()->get(UnansweredSearchRepository::class);
        $this->assertInstanceOf(UnansweredSearchRepository::class, $searches);
        $searches->record('fr', 'cartes cadeaux', new DateTimeImmutable('2026-11-20'));
        $this->entityManager()->clear();

        $page = $this->client->request('GET', '/admin/faq/statistics', server: self::EDITOR);
        self::assertSame('Totals only: nothing says who voted or searched.', $page->filter('main p')->first()->text());
        self::assertSame(['#q', '1', '0'], $page->filter('table')->first()->filter('tbody tr')->first()->filter('th, td')->each(static fn (Crawler $one): string => $one->text()));
        self::assertSame(['cartes cadeaux', 'fr', '1', '2026-11-20'], \array_slice($page->filter('table')->last()->filter('tbody tr th, tbody tr td')->each(static fn (Crawler $one): string => trim($one->text())), 0, 4));

        $this->client->submit($page->selectButton('Forget')->form(), [], self::EDITOR);
        self::assertResponseRedirects('/admin/faq/statistics', 303);
        self::assertSame('No search without answer.', $this->client->followRedirect()->filter('table')->last()->filter('tbody td')->text());
    }

    public function testTheMenuOfAnEasyAdminBackOfficeLinksToThePages(): void
    {
        $items = FaqAdmin::menuItems();

        self::assertSame([FaqAdminController::INDEX_ROUTE, FaqAdminController::STATISTICS_ROUTE], array_map(static fn (RouteMenuItem $item): ?string => $item->getAsDto()->getRouteName(), $items));
        self::assertSame(['admin.menu.questions', 'admin.menu.statistics'], array_map(static fn (RouteMenuItem $item): string => $item->getAsDto()->getLabel() instanceof TranslatableMessage ? $item->getAsDto()->getLabel()->getMessage() : '', $items));
    }

    private function press(Crawler $button): void
    {
        $this->client->submit($button->form(), [], self::EDITOR);
    }

    /**
     * @return list<string>
     */
    private function anchors(Crawler $index): array
    {
        return $index->filter('section')->first()->filter('tbody code')->each(static fn (Crawler $one): string => $one->text());
    }

    private function token(): string
    {
        $this->editor()->addSection(Locale::fromString('en'), 'Token');

        return (string) $this->client->request('GET', '/admin/faq/', server: self::EDITOR)->filter('input[name=_token]')->first()->attr('value');
    }

    /**
     * @return array{string, bool}
     */
    private function translation(string $anchor, string $locale): array
    {
        $this->entityManager()->clear();
        $question = $this->repository()->questionByAnchor($anchor);
        $this->assertInstanceOf(Question::class, $question);
        $translation = $this->repository()->questionTranslation($question, $locale);

        return [(string) $translation?->text(), (bool) $translation?->isPublished()];
    }

    private function editor(): FaqEditor
    {
        $editor = self::getContainer()->get(FaqEditor::class);
        $this->assertInstanceOf(FaqEditor::class, $editor);

        return $editor;
    }

    private function repository(): FaqRepository
    {
        $repository = self::getContainer()->get(FaqRepository::class);
        $this->assertInstanceOf(FaqRepository::class, $repository);

        return $repository;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get('test.entity_manager');
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
