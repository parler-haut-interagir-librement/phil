# LoginForm

A sign-in form in a card, for the form login of Symfony Security: the last user name written back, the error announced, the CSRF token and the links around it.

```twig {"preview":true}
<twig:LoginForm
    action="{{ path('app_login') }}"
    title="{{ 'login.title'|trans }}"
    usernameLabel="{{ 'login.email'|trans }}"
    usernameType="email"
    passwordLabel="{{ 'login.password'|trans }}"
    submitLabel="{{ 'login.submit'|trans }}"
    lastUsername="{{ last_username }}"
    :error="error ? error.messageKey|trans(error.messageData, 'security') : null"
    csrfToken="{{ csrf_token('authenticate') }}"
    forgotHref="{{ path('app_forgot_password') }}"
    forgotLabel="{{ 'login.forgot'|trans }}"
/>
```

## Installation

::: installation

## Usage

```twig
<twig:LoginForm
    action="{{ path('app_login') }}"
    title="{{ 'login.title'|trans }}"
    usernameLabel="{{ 'login.email'|trans }}"
    usernameType="email"
    passwordLabel="{{ 'login.password'|trans }}"
    submitLabel="{{ 'login.submit'|trans }}"
    lastUsername="{{ last_username }}"
    :error="error ? error.messageKey|trans(error.messageData, 'security') : null"
    csrfToken="{{ csrf_token('authenticate') }}"
    forgotHref="{{ path('app_forgot_password') }}"
    forgotLabel="{{ 'login.forgot'|trans }}"
/>
```

## Accessibility

The fields have their labels and the autocompletion password managers read: `username` or `email`, then `current-password`. The error of the last attempt is a live alert, and both fields say they were refused. The focus starts on the user name, or on the password when the user name is written back.
