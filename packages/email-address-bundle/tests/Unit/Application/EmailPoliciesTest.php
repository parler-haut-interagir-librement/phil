<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Application;

use Override;
use Phil\EmailAddressBundle\Application\Contract\CurrentSite;
use Phil\EmailAddressBundle\Application\EmailPolicies;
use Phil\EmailAddressBundle\Application\EmailPolicy;
use Phil\EmailAddressBundle\Application\Exception\UnknownEmailList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailPolicies::class)]
#[CoversClass(UnknownEmailList::class)]
final class EmailPoliciesTest extends TestCase
{
    public function testTheListIsLaidOverTheSiteWhichIsLaidOverTheBundle(): void
    {
        $policies = new EmailPolicies(
            new EmailPolicy(checkMailServer: false, deniedDomains: ['a.example']),
            ['shop' => new EmailPolicy(checkMailServer: true, deniedDomains: ['b.example'])],
            ['staff' => new EmailPolicy(deniedDomains: ['c.example'], allowedDomains: ['example.org'])],
            $this->site('shop'),
        );

        self::assertEquals(new EmailPolicy(true, null, null, null, ['a.example', 'b.example']), $policies->for());
        self::assertEquals(new EmailPolicy(true, null, null, null, ['a.example', 'b.example', 'c.example'], ['example.org']), $policies->for('staff'));
    }

    public function testASiteTheConfigurationDoesNotNameAddsNothing(): void
    {
        $defaults = new EmailPolicy(checkMailServer: true);
        $sites = ['shop' => new EmailPolicy(checkMailServer: false)];

        self::assertEquals($defaults, new EmailPolicies($defaults, $sites, [], $this->site('blog'))->for());
        self::assertEquals($defaults, new EmailPolicies($defaults, $sites, [], $this->site(null))->for());
        self::assertEquals($defaults, new EmailPolicies($defaults, $sites)->for());
        self::assertEquals(new EmailPolicy(), new EmailPolicies()->for());
    }

    public function testAListTheConfigurationDoesNotNameIsAnError(): void
    {
        $this->expectException(UnknownEmailList::class);
        $this->expectExceptionMessageIsOrContains('The email list "news" is not configured under phil_email_address.lists; the lists are: staff, members.');

        new EmailPolicies(lists: ['staff' => new EmailPolicy(), 'members' => new EmailPolicy()])->for('news');
    }

    public function testTheErrorSaysWhenNoListIsConfigured(): void
    {
        $this->expectExceptionMessageIsOrContains('the lists are: none.');

        new EmailPolicies()->for('news');
    }

    private function site(?string $key): CurrentSite
    {
        return new readonly class($key) implements CurrentSite {
            public function __construct(
                private ?string $key,
            ) {
            }

            #[Override]
            public function key(): ?string
            {
                return $this->key;
            }
        };
    }
}
