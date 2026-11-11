<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Functional;

use Doctrine\DBAL\ParameterType;
use Phil\FeatureFlagsBundle\Infrastructure\Doctrine\FlagTables;
use Phil\FeatureFlagsBundle\Infrastructure\Http\FlagAdminController;
use Phil\FeatureFlagsBundle\Infrastructure\Http\SameOriginGuard;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\RecordingAuditor;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The admin screens over the database store: the current configuration, the toggle of a flag declared in the
 * configuration, and the creation and edit of a flag of the database, each audited, and each refused when the
 * operator lacks the permission, gives no reason, or posts from another site.
 */
#[CoversClass(FlagAdminController::class)]
#[CoversClass(SameOriginGuard::class)]
final class AdminTest extends FunctionalTestCase
{
    public function testTheListShowsTheCurrentConfiguration(): void
    {
        $response = $this->request('GET', '/admin/flags/', ['X-Operator' => 'operator']);
        $content = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('new-checkout', $content);
        self::assertStringContainsString('The one-page checkout.', $content);
        self::assertStringContainsString('checkout-team', $content);
        self::assertStringContainsString('A/B by tenant: one-column 50 %, two-columns 50 %', $content);
        self::assertStringContainsString('2020-01-31 <strong>expired</strong>', $content);
        self::assertStringContainsString('New flag', $content);
    }

    public function testTheAdminNeedsAnOperator(): void
    {
        self::assertSame(403, $this->request('GET', '/admin/flags/')->getStatusCode());
        self::assertSame(403, $this->request('GET', '/admin/flags/new-checkout')->getStatusCode());
    }

    public function testAnUnknownFlagIsNotFound(): void
    {
        self::assertSame(404, $this->request('GET', '/admin/flags/nothing-here', ['X-Operator' => 'operator'])->getStatusCode());
    }

    /**
     * A flag declared in the configuration is toggled from its declared state: the toggle stores its first state,
     * and the next request sees it.
     */
    public function testAConfiguredFlagIsToggledAndStored(): void
    {
        $response = $this->post('/admin/flags/new-checkout/toggle', ['active' => '0', 'reason' => 'Payment provider incident.']);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/admin/flags/new-checkout?done=toggled', $response->headers->get('Location'));
        self::assertSame(404, $this->request('GET', '/checkout', ['X-Tenant' => 'tenant-a'])->getStatusCode());
        self::assertSame([false], array_map(boolval(...), $this->connection()->fetchFirstColumn('SELECT active FROM '.FlagTables::STATES." WHERE name = 'new-checkout'")));

        $audit = $this->service(RecordingAuditor::class, RecordingAuditor::class)->records;
        self::assertCount(1, $audit);
        self::assertSame('operator', $audit[0]->actor);
        self::assertTrue($audit[0]->before);
        self::assertFalse($audit[0]->after);

        $this->post('/admin/flags/new-checkout/toggle', ['active' => '1', 'reason' => 'Incident resolved.']);
        self::assertSame(200, $this->request('GET', '/checkout', ['X-Tenant' => 'tenant-a'])->getStatusCode());
    }

    public function testThePageReportsOnlyAKnownOutcome(): void
    {
        $page = (string) $this->request('GET', '/admin/flags/new-checkout?done=toggled', ['X-Operator' => 'operator'])->getContent();
        self::assertStringContainsString('The flag was toggled.', $page);

        $crafted = (string) $this->request('GET', '/admin/flags/new-checkout?done=deleted%20by%20security', ['X-Operator' => 'operator'])->getContent();
        self::assertStringNotContainsString('deleted by security', $crafted);
        self::assertStringNotContainsString('The flag was', $crafted);
    }

    public function testAToggleWithoutReasonIsRefused(): void
    {
        $response = $this->post('/admin/flags/new-checkout/toggle', ['active' => '0', 'reason' => '  ']);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('A toggle needs a reason', (string) $response->getContent());
    }

    public function testAToggleByAnOperatorWithoutPermissionIsRefused(): void
    {
        self::assertSame(403, $this->post('/admin/flags/new-checkout/toggle', ['active' => '0', 'reason' => 'Trying.'], 'intern')->getStatusCode());
        self::assertSame([], $this->service(RecordingAuditor::class, RecordingAuditor::class)->records);
    }

    public function testAFormPostedFromAnotherSiteIsRefused(): void
    {
        $form = ['active' => '0', 'reason' => 'Forged.'];

        self::assertSame(403, $this->request('POST', '/admin/flags/new-checkout/toggle', ['X-Operator' => 'operator', 'Sec-Fetch-Site' => 'cross-site'], $form)->getStatusCode());
        self::assertSame(403, $this->request('POST', '/admin/flags/new-checkout/toggle', ['X-Operator' => 'operator', 'Origin' => 'https://elsewhere.example'], $form)->getStatusCode());
        self::assertSame(403, $this->request('POST', '/admin/flags/new-checkout/toggle', ['X-Operator' => 'operator'], $form)->getStatusCode());
        self::assertSame(303, $this->request('POST', '/admin/flags/new-checkout/toggle', ['X-Operator' => 'operator', 'Origin' => 'http://localhost'], $form)->getStatusCode());

        $creation = ['name' => 'forged', 'description' => '', 'owner' => '', 'kind' => '', 'expires_on' => '', 'definition' => '{"rules": []}', 'reason' => 'Forged.'];
        self::assertSame(403, $this->request('POST', '/admin/flags/_new', ['X-Operator' => 'operator', 'Sec-Fetch-Site' => 'cross-site'], $creation)->getStatusCode());
        self::assertSame(404, $this->request('GET', '/admin/flags/forged', ['X-Operator' => 'operator'])->getStatusCode(), 'not created');
    }

    /**
     * A created flag starts inactive whatever it declares, and lives in the database store; activating it is a
     * toggle; editing it keeps its state and applies only against the revision it was read at.
     */
    public function testAFlagIsCreatedThenActivatedThenEdited(): void
    {
        $created = $this->post('/admin/flags/_new', [
            'name' => 'beta-search',
            'description' => 'The new search.',
            'owner' => 'search-team',
            'kind' => 'permission',
            'expires_on' => '2099-12-31',
            'definition' => '{"rules": [{"attribute": "tenant", "members": ["tenant-a", "eu/tenant-b"]}]}',
            'reason' => 'Opening the beta.',
        ]);

        self::assertSame(303, $created->getStatusCode());
        $show = (string) $this->request('GET', '/admin/flags/beta-search', ['X-Operator' => 'operator'])->getContent();
        self::assertStringContainsString('inactive', $show);
        self::assertStringContainsString('phil_feature_flags.provider.doctrine', $show);
        self::assertStringContainsString('tenant in tenant-a', $show);
        self::assertStringContainsString('/admin/flags/beta-search/edit', $show, 'a flag of the database is editable');

        $this->post('/admin/flags/beta-search/toggle', ['active' => '1', 'reason' => 'Go.']);

        $edit = (string) $this->request('GET', '/admin/flags/beta-search/edit', ['X-Operator' => 'operator'])->getContent();
        self::assertStringContainsString('name="revision" value="1"', $edit);
        self::assertStringContainsString('name="description" value="The new search."', $edit);
        self::assertStringContainsString('<option value="permission" selected>', $edit);
        self::assertStringContainsString('name="owner" value="search-team"', $edit);
        self::assertStringNotContainsString('&quot;active&quot;', $edit, 'the state and the metadata have fields of their own');
        self::assertStringContainsString('name="expires_on" type="date" value="2099-12-31"', $edit);
        self::assertStringContainsString('&quot;eu/tenant-b&quot;', $edit, 'the slashes are left as they are');

        $edited = $this->post('/admin/flags/beta-search/edit', [
            'revision' => '1',
            'description' => 'The new search, for everyone.',
            'owner' => 'search-team',
            'kind' => 'permission',
            'expires_on' => '2099-12-31',
            'definition' => '{"rules": [{"attribute": "tenant", "percentage": 100}]}',
            'reason' => 'Widening the beta.',
        ]);

        self::assertSame(303, $edited->getStatusCode());
        $show = (string) $this->request('GET', '/admin/flags/beta-search', ['X-Operator' => 'operator'])->getContent();
        self::assertStringContainsString('The new search, for everyone.', $show);
        self::assertStringContainsString('100 % of each tenant', $show);
        self::assertMatchesRegularExpression('#<dt>State</dt>\s*<dd>\s*active\s#', $show);

        $edits = $this->service(RecordingAuditor::class, RecordingAuditor::class)->edits;
        self::assertCount(2, $edits);
        self::assertNull($edits[0]->before);
        self::assertNotNull($edits[1]->before);

        $form = ['revision' => '2', 'description' => '', 'owner' => '', 'kind' => '', 'expires_on' => '', 'reason' => 'Trying.'];
        $malformed = $this->post('/admin/flags/beta-search/edit', [...$form, 'definition' => '{"rules": ']);
        self::assertSame(422, $malformed->getStatusCode());
        self::assertStringContainsString('Syntax error', (string) $malformed->getContent());
        $invalid = $this->post('/admin/flags/beta-search/edit', [...$form, 'definition' => '{"rules": [{"attribute": "tenant", "percentage": 120}]}']);
        self::assertSame(422, $invalid->getStatusCode());
        self::assertStringContainsString('A percentage is between 0 and 100; got 120.', (string) $invalid->getContent());

        $valid = [...$form, 'definition' => '{"rules": []}'];
        $refused = $this->post('/admin/flags/beta-search/edit', $valid, 'intern');
        self::assertSame(403, $refused->getStatusCode(), 'an operator without permission');
        self::assertStringContainsString('the actor lacks the permission.', (string) $refused->getContent());
        self::assertSame(403, $this->request('POST', '/admin/flags/beta-search/edit', ['X-Operator' => 'operator', 'Sec-Fetch-Site' => 'cross-site'], $valid)->getStatusCode(), 'from another site');
        self::assertCount(2, $this->service(RecordingAuditor::class, RecordingAuditor::class)->edits);

        $stale = $this->post('/admin/flags/beta-search/edit', [
            'revision' => '1',
            'description' => '',
            'owner' => '',
            'kind' => '',
            'expires_on' => '',
            'definition' => '{"rules": []}',
            'reason' => 'Late edit.',
        ]);
        self::assertSame(409, $stale->getStatusCode());
        self::assertStringContainsString('changed since revision 1', (string) $stale->getContent());
    }

    /**
     * The creation form sits outside the flag-name alphabet, so a flag named `new` keeps a page of its own.
     */
    public function testAFlagNamedNewHasItsOwnPage(): void
    {
        $this->post('/admin/flags/_new', [
            'name' => 'new',
            'description' => '',
            'owner' => '',
            'kind' => '',
            'expires_on' => '',
            'definition' => '{"rules": []}',
            'reason' => 'Naming test.',
        ]);

        $page = $this->request('GET', '/admin/flags/new', ['X-Operator' => 'operator']);

        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('<h1>new</h1>', (string) $page->getContent());
        self::assertStringContainsString('&quot;rules&quot;: []', (string) $this->request('GET', '/admin/flags/new/edit', ['X-Operator' => 'operator'])->getContent(), 'no rules, written out');
    }

    public function testANameAlreadyDeclaredCannotBeCreated(): void
    {
        $response = $this->post('/admin/flags/_new', [
            'name' => 'new-checkout',
            'description' => '',
            'owner' => '',
            'kind' => '',
            'expires_on' => '',
            'definition' => '{"rules": []}',
            'reason' => 'Shadowing.',
        ]);

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('A flag named &quot;new-checkout&quot; is already declared.', (string) $response->getContent());
    }

    public function testAMalformedDefinitionIsRefused(): void
    {
        $response = $this->post('/admin/flags/_new', [
            'name' => 'broken',
            'description' => '',
            'owner' => '',
            'kind' => '',
            'expires_on' => '',
            'definition' => '{"rules": [{"attribute": "tenant", "percentage": 120}]}',
            'reason' => 'Trying.',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('A percentage is between 0 and 100; got 120.', (string) $response->getContent());
    }

    public function testACreationByAnOperatorWithoutPermissionIsRefused(): void
    {
        $response = $this->post('/admin/flags/_new', ['name' => 'intern-flag', 'description' => '', 'owner' => '', 'kind' => '', 'expires_on' => '', 'definition' => '{"rules": []}', 'reason' => 'Trying.'], 'intern');

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('the actor lacks the permission.', (string) $response->getContent());
        self::assertSame(404, $this->request('GET', '/admin/flags/intern-flag', ['X-Operator' => 'operator'])->getStatusCode());
    }

    public function testTheDefinitionHoldsOnlyTheRulesAndTheExperiment(): void
    {
        foreach (['{"rules": [], "active": true}', '"rules"'] as $definition) {
            $response = $this->post('/admin/flags/_new', ['name' => 'extra', 'description' => '', 'owner' => '', 'kind' => '', 'expires_on' => '', 'definition' => $definition, 'reason' => 'Trying.']);

            self::assertSame(422, $response->getStatusCode(), $definition);
            self::assertStringContainsString('The definition is a JSON object with &quot;rules&quot;', (string) $response->getContent());
        }
    }

    public function testMetadataLeftBlankIsNone(): void
    {
        $response = $this->post('/admin/flags/_new', ['name' => 'blank', 'description' => ' ', 'owner' => ' ', 'kind' => ' ', 'expires_on' => ' ', 'definition' => '{"rules": []}', 'reason' => 'Blank.']);

        self::assertSame(303, $response->getStatusCode());
    }

    /**
     * Sixteen levels are read, then refused as a definition; seventeen are not read at all.
     */
    public function testADefinitionIsReadSixteenLevelsDeep(): void
    {
        foreach ([11 => false, 12 => true] as $nesting => $tooDeep) {
            $response = $this->post('/admin/flags/_new', [
                'name' => 'deep',
                'description' => '',
                'owner' => '',
                'kind' => '',
                'expires_on' => '',
                'definition' => '{"rules": [{"attribute": "tenant", "members": ['.str_repeat('[', $nesting).str_repeat(']', $nesting).']}]}',
                'reason' => 'Trying.',
            ]);

            self::assertSame(422, $response->getStatusCode());
            self::assertSame($tooDeep, str_contains((string) $response->getContent(), 'Maximum stack depth exceeded'));
        }
    }

    /**
     * Another process stores the flag's first state between the read and the write: the toggle is a conflict.
     */
    public function testAToggleThatLosesARaceIsAConflict(): void
    {
        $this->service(RecordingAuditor::class, RecordingAuditor::class)->meanwhile = function (): void {
            $this->connection()->insert(FlagTables::STATES, ['name' => 'new-checkout', 'active' => false, 'updated_at' => '2026-01-01 00:00:00'], ['active' => ParameterType::BOOLEAN]);
        };

        $response = $this->post('/admin/flags/new-checkout/toggle', ['active' => '0', 'reason' => 'Late.']);

        self::assertSame(409, $response->getStatusCode());
    }

    public function testAConfiguredFlagIsNotEditable(): void
    {
        self::assertStringNotContainsString('/edit', (string) $this->request('GET', '/admin/flags/new-checkout', ['X-Operator' => 'operator'])->getContent());
        self::assertSame(404, $this->request('GET', '/admin/flags/new-checkout/edit', ['X-Operator' => 'operator'])->getStatusCode());
    }

    /**
     * A row of the database that no longer reads is not absent: the list shows it with its reason, its page says why
     * it cannot be read, and it can be neither toggled, edited, nor created again under its name.
     */
    public function testAnUnreadableRowIsShownWithItsReason(): void
    {
        $this->connection()->insert(FlagTables::DEFINITIONS, ['name' => 'broken-row', 'definition' => '{"rules": "<all>"}', 'revision' => 1, 'updated_at' => '2026-01-01 00:00:00']);

        $list = (string) $this->request('GET', '/admin/flags/', ['X-Operator' => 'operator'])->getContent();
        self::assertStringContainsString('broken-row', $list);
        self::assertStringContainsString('unreadable', $list);
        self::assertStringContainsString('&quot;rules&quot; is a list of rules.', $list);
        self::assertStringContainsString('phil_feature_flags.provider.doctrine', $list);

        $page = $this->request('GET', '/admin/flags/broken-row', ['X-Operator' => 'operator']);
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('<h1>broken-row</h1>', (string) $page->getContent());
        self::assertStringContainsString('&quot;rules&quot; is a list of rules.', (string) $page->getContent());
        self::assertStringNotContainsString('/toggle', (string) $page->getContent());

        self::assertSame(404, $this->post('/admin/flags/broken-row/toggle', ['active' => '1', 'reason' => 'Trying.'])->getStatusCode());
        self::assertSame(404, $this->request('GET', '/admin/flags/broken-row/edit', ['X-Operator' => 'operator'])->getStatusCode());

        $created = $this->post('/admin/flags/_new', [
            'name' => 'broken-row',
            'description' => '',
            'owner' => '',
            'kind' => '',
            'expires_on' => '',
            'definition' => '{"rules": []}',
            'reason' => 'Again.',
        ]);
        self::assertSame(409, $created->getStatusCode());
        $auditor = $this->service(RecordingAuditor::class, RecordingAuditor::class);
        self::assertSame([], $auditor->records);
        self::assertSame([], $auditor->edits);
    }
}
