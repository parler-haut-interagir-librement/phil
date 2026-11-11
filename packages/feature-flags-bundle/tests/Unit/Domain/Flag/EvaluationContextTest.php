<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Domain\Flag;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Phil\FeatureFlagsBundle\Domain\Flag\TargetingAttribute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EvaluationContext::class)]
final class EvaluationContextTest extends TestCase
{
    public function testEachAttributeIsReadBack(): void
    {
        $context = EvaluationContext::of(tenant: 't', project: 'p', subject: 's');

        self::assertSame('t', $context->get(TargetingAttribute::Tenant));
        self::assertSame('p', $context->get(TargetingAttribute::Project));
        self::assertSame('s', $context->get(TargetingAttribute::Subject));
    }

    public function testAnAnonymousContextHasNoAttribute(): void
    {
        $context = EvaluationContext::anonymous();

        foreach (TargetingAttribute::cases() as $attribute) {
            self::assertNull($context->get($attribute));
        }
    }

    public function testAnEmptyAttributeIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The project of an evaluation context is null when absent');

        EvaluationContext::of(tenant: 't', project: '');
    }

    public function testAnEmptyTenantIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The tenant of an evaluation context is null when absent');

        EvaluationContext::of(tenant: '');
    }
}
