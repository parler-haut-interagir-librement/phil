# Forms

The bundle limits the submissions of a form by its name, with symfony/form installed.

```yaml
phil_rate_limit:
    limiters:
        contact: { policy: token_bucket, limit: 3, rate: { interval: '10 minutes' } }
    forms:
        contact: contact
```

```php
$form = $formFactory->createNamed('contact', ContactType::class);
$form = $formFactory->createNamed('signup', SignupType::class, options: ['rate_limit' => [new RateLimit('signups', key: 'ip_route')]]);
$form = $formFactory->createNamed('contact', ContactType::class, options: ['rate_limit' => false]);
```

The `rate_limit` option holds the rules of the form, written as a route default is; `null` or `true` takes those the
`forms` section gives the form's name, and `false` none. A form limited by a limiter nothing declares fails the
container build.

Every submission of the root form consumes, valid or not, under the key of the rule followed by the form's name. A
refusal is not an error page: the form gets an error, translated in the `phil_rate_limit` domain, and is not valid, so
the controller renders it again with its message.

```text
Too many submissions of this form. Try again in 412 seconds.
```

The error's cause is the `TooManyRequests` exception, and `RateLimitExceeded` is dispatched as for any refusal.
