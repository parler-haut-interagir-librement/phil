# SignupForm

A sign-up form in a card: an optional name, the email address, the new password with its rule and its repetition, each field naming its errors.

```twig {"preview":true}
<twig:SignupForm
    action="{{ path('app_register') }}"
    title="{{ 'signup.title'|trans }}"
    nameLabel="{{ 'signup.name'|trans }}"
    emailLabel="{{ 'signup.email'|trans }}"
    passwordLabel="{{ 'signup.password'|trans }}"
    passwordHint="{{ 'signup.password_hint'|trans }}"
    submitLabel="{{ 'signup.submit'|trans }}"
    :values="values"
    :errors="errors"
    csrfToken="{{ csrf_token('register') }}"
    loginHref="{{ path('app_login') }}"
    loginLabel="{{ 'signup.login'|trans }}"
/>
```

## Installation

::: installation

## Usage

```twig
<twig:SignupForm
    action="{{ path('app_register') }}"
    title="{{ 'signup.title'|trans }}"
    nameLabel="{{ 'signup.name'|trans }}"
    emailLabel="{{ 'signup.email'|trans }}"
    passwordLabel="{{ 'signup.password'|trans }}"
    passwordHint="{{ 'signup.password_hint'|trans }}"
    submitLabel="{{ 'signup.submit'|trans }}"
    :values="values"
    :errors="errors"
    csrfToken="{{ csrf_token('register') }}"
    loginHref="{{ path('app_login') }}"
    loginLabel="{{ 'signup.login'|trans }}"
/>
```

## Accessibility

Each field has its label and its autocompletion: `name`, `email`, then `new-password`, which lets a password manager propose a password. The rule of the password and the errors of a field are tied to it by `aria-describedby`, and a refused field says so with `aria-invalid`. A password is never written back.
