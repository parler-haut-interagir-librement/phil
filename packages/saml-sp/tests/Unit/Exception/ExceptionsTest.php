<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Unit\Exception;

use LogicException;
use Phil\SamlSp\Exception\DecryptionFailed;
use Phil\SamlSp\Exception\InvalidKey;
use Phil\SamlSp\Exception\InvalidMessage;
use Phil\SamlSp\Exception\InvalidMetadata;
use Phil\SamlSp\Exception\InvalidSignature;
use Phil\SamlSp\Exception\InvalidXml;
use Phil\SamlSp\Exception\MetadataUnavailable;
use Phil\SamlSp\Exception\ReplayedAssertion;
use Phil\SamlSp\Exception\SamlException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DecryptionFailed::class)]
#[CoversClass(InvalidKey::class)]
#[CoversClass(InvalidMessage::class)]
#[CoversClass(InvalidMetadata::class)]
#[CoversClass(InvalidSignature::class)]
#[CoversClass(InvalidXml::class)]
#[CoversClass(MetadataUnavailable::class)]
#[CoversClass(ReplayedAssertion::class)]
final class ExceptionsTest extends TestCase
{
    public function testEachCarriesItsReasonAndItsCause(): void
    {
        $cause = new LogicException('cause');

        foreach ([DecryptionFailed::class, InvalidKey::class, InvalidMessage::class, InvalidMetadata::class, InvalidSignature::class, InvalidXml::class, MetadataUnavailable::class, ReplayedAssertion::class] as $class) {
            $exception = $class::because('reason', $cause);

            self::assertInstanceOf(SamlException::class, $exception);
            self::assertSame('reason', $exception->getMessage());
            self::assertSame(0, $exception->getCode());
            self::assertSame($cause, $exception->getPrevious());
            self::assertNull($class::because('alone')->getPrevious());
        }
    }
}
