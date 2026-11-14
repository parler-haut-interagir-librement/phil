<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Fixtures;

use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Domain\ValueObject\Field;

/**
 * The form the tests fill: a tournament's registration.
 */
final class Forms
{
    public static function registration(string $key = 'inscription'): Form
    {
        return Form::create($key, 'Inscription au tournoi', [
            Field::of('name', 'Nom', FieldType::Text, true),
            Field::of('email', 'Courriel', FieldType::Email, true),
            Field::of('site', 'Site', FieldType::Url),
            Field::of('born', 'Date de naissance', FieldType::Date),
            Field::of('team', 'Équipe', FieldType::Choice, choices: ['Bleue', 'Verte']),
            Field::of('message', 'Message', FieldType::Textarea),
            Field::of('consent', 'J’accepte le règlement', FieldType::Checkbox, true),
        ], 30);
    }

    /**
     * @return array<string, string|bool>
     */
    public static function values(): array
    {
        return ['name' => 'Ada', 'email' => 'ada@example.com', 'site' => 'https://ada.example.com', 'team' => 'Bleue', 'message' => "Bonjour,\nà bientôt.", 'consent' => true];
    }
}
