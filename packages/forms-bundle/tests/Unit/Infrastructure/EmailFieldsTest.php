<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Domain\ValueObject\Field;
use Phil\FormsBundle\Infrastructure\Mailer\EmailNotifier;
use Phil\FormsBundle\Tests\Unit\Fixtures\RecordingMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

#[CoversClass(EmailNotifier::class)]
final class EmailFieldsTest extends TestCase
{
    public function testTheFieldsGivenToTheTemplatesAreAListWhateverFieldIsNotAsked(): void
    {
        $form = Form::create('a', 'A', [
            Field::of('first', 'First', FieldType::Text),
            Field::of('hidden', 'Hidden', FieldType::Text, shownWhen: ['==' => [['var' => 'first'], 'never']]),
            Field::of('last', 'Last', FieldType::Text),
        ]);
        $form->notify(['info@example.com']);

        $mailer = new RecordingMailer();

        new EmailNotifier($mailer, 'site@example.com')->notify($form->submit(['first' => 'x', 'last' => 'y'], new DateTimeImmutable()));

        $email = $mailer->sent[0] ?? null;
        self::assertInstanceOf(TemplatedEmail::class, $email);
        $fields = $email->getContext()['fields'] ?? null;
        self::assertIsArray($fields);
        self::assertSame([0, 1], array_keys($fields));
    }
}
