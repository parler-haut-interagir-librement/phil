<?php

declare(strict_types=1);

namespace Phil\PhoneBundle\Tests\Benchmark;

use Generator;
use Phil\Phone\PhoneNumber;
use Phil\Phone\PhoneNumberType;
use Phil\PhoneBundle\Infrastructure\Validator\ValidPhoneNumber;
use Phil\PhoneBundle\Infrastructure\Validator\ValidPhoneNumberValidator;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Validation;

/**
 * Every submitted form with a phone field runs this validator; a constraint that restricts types and regions asks the
 * number for its type and then for its region, and each question walks the plans of its country code again.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"phone"})
 */
final class ValidPhoneNumberValidatorBench
{
    private readonly ValidPhoneNumberValidator $validator;

    private readonly ExecutionContext $context;

    private ValidPhoneNumber $constraint;

    public function __construct()
    {
        $this->validator = new ValidPhoneNumberValidator('CA');
        $this->context = new ExecutionContext(Validation::createValidator(), null, new IdentityTranslator());

        $this->constraint = new ValidPhoneNumber();
    }

    /**
     * Only valid numbers are measured, so the violation list of the shared context never grows between revolutions. The
     * types travel as their values, which phpbench can serialize.
     *
     * @param array{value: string, region: ?string, types: ?list<string>, regions: ?list<string>} $params
     */
    public function setUp(array $params): void
    {
        $this->constraint = new ValidPhoneNumber(region: $params['region'], types: null === $params['types'] ? null : array_map(PhoneNumberType::from(...), $params['types']), regions: $params['regions']);

        // Loads the plans the number needs before the measure.
        PhoneNumber::parse($params['value'], $params['region'] ?? 'CA')->isValid();
        $this->validator->validateInContext($params['value'], $this->constraint, $this->context);
    }

    /**
     * @ParamProviders("values")
     *
     * @param array{value: string, region: ?string, types: ?list<string>, regions: ?list<string>} $params
     */
    public function benchValidate(array $params): void
    {
        $this->validator->validateInContext($params['value'], $this->constraint, $this->context);
    }

    /**
     * @return Generator<string, array{value: string, region: ?string, types: ?list<string>, regions: ?list<string>}>
     */
    public function values(): Generator
    {
        $phones = [PhoneNumberType::FixedLine->value, PhoneNumberType::Mobile->value, PhoneNumberType::FixedLineOrMobile->value];

        yield 'CA, no restriction' => ['value' => '(514) 555-0123', 'region' => null, 'types' => null, 'regions' => null];
        yield 'CA, types and regions' => ['value' => '(514) 555-0123', 'region' => null, 'types' => $phones, 'regions' => ['ca', 'US']];
        yield 'JM, no restriction' => ['value' => '+1 876-927-1234', 'region' => null, 'types' => null, 'regions' => null];
        yield 'JM, types and regions' => ['value' => '+1 876-927-1234', 'region' => null, 'types' => $phones, 'regions' => ['JM', 'TT', 'BB']];
        yield 'FR, types and regions' => ['value' => '06 12 34 56 78', 'region' => 'FR', 'types' => $phones, 'regions' => ['FR', 'BE']];
    }
}
