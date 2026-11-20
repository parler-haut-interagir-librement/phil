<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Functional;

use Override;
use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Application\Members;
use Phil\OrganizationsBundle\Application\Organizations;
use Phil\OrganizationsBundle\Application\UnitTree;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Model\Unit;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Infrastructure\Memory\InMemoryOrganizationStore;
use Phil\OrganizationsBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The administration pages of the units in an application, drawn with the recipes of the kit: the tree of an
 * organization, a unit created at the root and under another, a manager named, a unit below renamed by that manager,
 * a cycle refused with its reason, a unit archived and restored, a member removed; strangers, guests and forged forms
 * refused.
 */
#[CoversNothing]
final class UnitPagesTest extends WebTestCase
{
    private const array ANN = ['HTTP_X_USER' => 'ann'];

    private const array CAM = ['HTTP_X_USER' => 'cam'];

    private KernelBrowser $client;

    private Organization $acme;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $store = self::getContainer()->get(OrganizationStore::class);
        self::assertInstanceOf(InMemoryOrganizationStore::class, $store);
        $store->reset();
        $organizations = self::getContainer()->get(Organizations::class);
        $members = self::getContainer()->get(Members::class);
        self::assertInstanceOf(Organizations::class, $organizations);
        self::assertInstanceOf(Members::class, $members);
        $this->acme = $organizations->create('Acme Guild', MemberId::fromString('ann'));
        $members->add($this->acme->id, MemberId::fromString('ann'), MemberId::fromString('cam'), ['member']);
        $members->add($this->acme->id, MemberId::fromString('ann'), MemberId::fromString('dan'), ['member']);
    }

    public function testTheTreeIsBuiltAndManaged(): void
    {
        $crawler = $this->client->request('GET', $this->treeUrl(), server: self::ANN);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertSelectorTextContains('h1', 'Units of Acme Guild');
        self::assertSelectorTextContains('.phil-organizations__none', 'No unit yet.');

        $this->client->submit($crawler->filter('form.phil-organizations__create')->form(['name' => 'Sales']), [], self::ANN);
        $sales = $this->unit('Sales');
        self::assertResponseRedirects('/admin/organizations/units/'.$sales->id->value, 303);

        $crawler = $this->client->request('GET', $this->treeUrl(), server: self::ANN);
        self::assertSelectorExists('select[data-slot="native-select"] option[value="'.$sales->id->value.'"]');
        $this->client->submit($crawler->filter('form.phil-organizations__create')->form(['name' => 'East', 'parent' => $sales->id->value]), [], self::ANN);
        $east = $this->unit('East');

        $this->client->request('GET', $this->treeUrl(), server: self::ANN);
        self::assertSelectorTextContains('ul[role="tree"] > li[role="treeitem"][aria-expanded="true"] > .isTreeRow', 'Sales');
        self::assertSelectorTextContains('ul[role="tree"] > li[role="treeitem"] > ul[role="group"] > li[role="treeitem"] a', 'East');
        self::assertSelectorExists('a[href="/admin/organizations/units/'.$east->id->value.'"]');

        $crawler = $this->client->request('GET', $this->unitUrl($sales), server: self::ANN);
        self::assertSelectorTextContains('h1', 'Sales');
        self::assertSelectorTextContains('[data-slot="badge"]', 'Active');
        self::assertSelectorTextContains('.phil-organizations__children', 'East');
        self::assertSelectorTextContains('.phil-organizations__none', 'No member in this unit.');
        $this->client->submit($crawler->filter('form.phil-organizations__add-member')->form(['member' => 'cam', 'roles' => 'manager']), [], self::ANN);
        self::assertResponseRedirects($this->unitUrl($sales), 303);

        $crawler = $this->client->request('GET', $this->unitUrl($east), server: self::CAM);
        self::assertSelectorTextContains('.phil-organizations__ancestors', 'Acme Guild');
        self::assertSelectorTextContains('.phil-organizations__ancestors', 'Sales');
        self::assertSelectorExists('form.phil-organizations__rename');
        self::assertSelectorExists('form.phil-organizations__archive');
        $this->client->submit($crawler->filter('form.phil-organizations__rename')->form(['name' => 'East coast']), [], self::CAM);
        self::assertResponseRedirects($this->unitUrl($east), 303);
        self::assertSame('East coast', $this->tree()->get($east->id)->name->value);

        $this->client->request('GET', $this->unitUrl($sales), server: self::CAM);
        self::assertSelectorTextContains('table[data-slot="table"]', 'cam');
        self::assertSelectorTextContains('table[data-slot="table"]', 'manager');
        self::assertSelectorNotExists('form.phil-organizations__move');
        self::assertSelectorNotExists('form.phil-organizations__archive');

        $crawler = $this->client->request('GET', $this->unitUrl($sales), server: self::ANN);
        self::assertSelectorNotExists('form.phil-organizations__move option[value="'.$east->id->value.'"]');
        $token = (string) $crawler->filter('form.phil-organizations__move input[name="_token"]')->attr('value');
        $this->client->request('POST', $this->unitUrl($sales).'/move', ['_token' => $token, 'parent' => $east->id->value], server: self::ANN);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'A unit cannot move under itself or under a unit below it.');

        $this->client->submit($crawler->filter('form.phil-organizations__archive')->form(), [], self::ANN);
        self::assertResponseRedirects($this->unitUrl($sales), 303);
        $crawler = $this->client->request('GET', $this->unitUrl($sales), server: self::ANN);
        self::assertSelectorTextContains('[data-slot="badge"]', 'Archived');
        self::assertSelectorTextContains('.phil-organizations__children', 'East coast (archived)');
        self::assertSelectorNotExists('form.phil-organizations__rename');
        $this->client->submit($crawler->filter('form.phil-organizations__restore')->form(), [], self::ANN);
        self::assertTrue($this->tree()->get($east->id)->isActive());

        $crawler = $this->client->request('GET', $this->unitUrl($sales), server: self::ANN);
        $this->client->submit($crawler->filter('form[action$="/members/cam/remove"]')->form(), [], self::ANN);
        self::assertResponseRedirects($this->unitUrl($sales), 303);
        self::assertSame([], $this->tree()->members($sales->id));
    }

    public function testARefusedChangeShowsItsReason(): void
    {
        $crawler = $this->client->request('GET', $this->treeUrl(), server: self::ANN);
        $token = (string) $crawler->filter('form.phil-organizations__create input[name="_token"]')->attr('value');

        $this->client->request('POST', $this->treeUrl(), ['_token' => $token, 'name' => ''], server: self::ANN);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'This change is not possible');

        $this->client->request('POST', $this->treeUrl(), ['_token' => $token, 'name' => 'Sales'], server: self::ANN);
        $sales = $this->unit('Sales');

        foreach ([
            [['member' => 'cam', 'roles' => 'owner'], 'One of these roles does not exist.'],
            [['member' => 'cam', 'roles' => 'manager'], null],
            [['member' => 'cam', 'roles' => 'manager'], 'This member is already in this unit'],
        ] as [$form, $reason]) {
            $this->client->request('POST', $this->unitUrl($sales).'/members', ['_token' => $token] + $form, server: self::ANN);

            if (null === $reason) {
                self::assertResponseRedirects($this->unitUrl($sales), 303);
            } else {
                self::assertResponseStatusCodeSame(422);
                self::assertSelectorTextContains('[role="alert"]', $reason);
            }
        }

        $this->client->request('POST', $this->unitUrl($sales).'/archive', ['_token' => $token], server: self::ANN);
        $this->client->request('POST', $this->unitUrl($sales).'/rename', ['_token' => $token, 'name' => 'Other'], server: self::ANN);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'This unit, or the one it goes under, is archived.');
    }

    public function testStrangersGuestsAndForgedFormsAreRefused(): void
    {
        $crawler = $this->client->request('GET', $this->treeUrl(), server: self::ANN);
        $token = (string) $crawler->filter('form.phil-organizations__create input[name="_token"]')->attr('value');
        $this->client->request('POST', $this->treeUrl(), ['_token' => $token, 'name' => 'Sales'], server: self::ANN);
        $sales = $this->unit('Sales');

        foreach ([['HTTP_X_USER' => 'zed'], [], ['HTTP_X_USER' => 'ann@example.org']] as $server) {
            $this->client->request('GET', $this->treeUrl(), server: $server);
            self::assertResponseStatusCodeSame(403);
            $this->client->request('GET', $this->unitUrl($sales), server: $server);
            self::assertResponseStatusCodeSame(403);
        }

        $this->client->request('POST', $this->treeUrl(), ['_token' => $token, 'name' => 'Mine'], server: self::CAM);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', $this->unitUrl($sales).'/rename', ['_token' => $token, 'name' => 'Mine'], server: self::CAM);
        self::assertResponseStatusCodeSame(403);

        foreach ([
            ['/admin/organizations/nowhere/units', 'GET', [], 404],
            ['/admin/organizations/units/nowhere', 'GET', [], 404],
            [$this->unitUrl($sales).'/move', 'POST', ['_token' => $token, 'parent' => 'nowhere'], 404],
            [$this->unitUrl($sales).'/members', 'POST', ['_token' => $token, 'member' => 'a@b', 'roles' => 'member'], 400],
            [$this->unitUrl($sales).'/rename', 'POST', ['_token' => 'forged', 'name' => 'Mine'], 400],
            [$this->unitUrl($sales).'/rename', 'POST', ['name' => 'Mine'], 400],
        ] as [$url, $method, $form, $status]) {
            $this->client->request($method, $url, $form, server: self::ANN);
            self::assertResponseStatusCodeSame($status, $url);
        }

        $this->client->request('POST', $this->unitUrl($sales).'/rename', ['_token' => $token, 'name' => 'Mine'], server: self::ANN + ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
        self::assertResponseStatusCodeSame(400);
        self::assertSame('Sales', $this->tree()->get($sales->id)->name->value);
    }

    private function treeUrl(): string
    {
        return '/admin/organizations/'.$this->acme->id->value.'/units';
    }

    private function unitUrl(Unit $unit): string
    {
        return '/admin/organizations/units/'.$unit->id->value;
    }

    private function tree(): UnitTree
    {
        $tree = self::getContainer()->get(UnitTree::class);
        self::assertInstanceOf(UnitTree::class, $tree);

        return $tree;
    }

    private function unit(string $name): Unit
    {
        foreach ($this->tree()->units($this->acme->id, true) as $unit) {
            if ($unit->name->value === $name) {
                return $unit;
            }
        }

        self::fail(\sprintf('No unit "%s".', $name));
    }
}
