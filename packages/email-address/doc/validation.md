# Validation

`EmailValidator::validate(string $input): Validation` runs the steps in order, each only when the ones before it
passed, and never throws.

| Step | Configured by | Problem |
|---|---|---|
| 1. Syntax | `$policy`, a `SyntaxPolicy` | `Problem::Syntax`, the reason in `syntaxError` |
| 2. Top-level domain | `$topLevelDomains`, a `TopLevelDomains` | `Problem::UnknownTopLevelDomain` |
| 3. Domain rules | `$rules`, a `DomainRules` | `Problem::DeniedDomain`, `Problem::DomainNotAllowed` |
| 4. Disposable domains | `$disposable`, a `DisposableDomains` | `Problem::Disposable` |
| 5. DNS | `$resolver`, a `Contract\MailDomainResolver` | `Problem::RefusesMail` (null MX), `Problem::NoMailServer` |

A step whose argument is `null` is skipped: by default only the syntax is checked. An empty `TopLevelDomains` skips
its step too, as the bundled list does before its first import, and an IP literal has no top-level domain to check.
The top-level domain is checked before the DNS so that `ada@example.con` is refused without a lookup. The DNS is never asked for an IP
literal, nor for an address a step before refused, so that a refused address costs no lookup. A lookup that fails
(`MailDomain::Unknown`) refuses nothing: a resolver down does not close a sign-up form.

The suggestion comes from `$suggester`, a `TypoSuggester` by default, and is looked for whatever the outcome — also
for a text whose syntax fails, so that `ada@gmailcom` gets `ada@gmail.com`. `null` turns it off.

## The result

| Member | Holds |
|---|---|
| `address` | The `EmailAddress` when the syntax holds, else `null` |
| `syntaxError` | The `SyntaxError` when it does not |
| `problems` | The list of `Problem`s; empty when the address is accepted |
| `suggestion` | The `EmailAddress` the person probably meant, or `null` |
| `mailDomain` | The `MailDomain` the resolver answered, or `null` when it was not asked |
| `isValid()` | No problem |
| `has(Problem)` | That problem is in the list |

```php
$validation = $validator->validate($form['email']);

if (!$validation->isValid()) {
    $error = match ($validation->problems[0]) {
        Problem::Syntax => 'email.syntax.'.$validation->syntaxError?->value,
        Problem::Disposable => 'email.disposable',
        default => 'email.undeliverable',
    };
}

if (null !== $validation->suggestion) {
    $hint = $validation->suggestion->toString(); // "Did you mean …?"
}
```

A suggestion is a question for the person, never a correction the application makes on its own.
