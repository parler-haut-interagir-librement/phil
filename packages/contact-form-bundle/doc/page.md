# The page

`ContactFormController` shows the form, and once a message is valid and sent, redirects to the same page with
`?sent=1` — `303 See Other` — so that reloading sends nothing twice.

| Response | When |
|---|---|
| `200` | The page, or the page after a message left (`sent` is true) |
| `303` | The message left |
| `422` | A field is missing or wrong, or the protection held the submission |
| `503` | The mail transport refused the message: the form stays filled and asks to try again; the refusal is logged, never the message |

## The form

`ContactFormType`, to a `ContactMessage`:

| Field | Checked |
|---|---|
| `name` | required, 100 characters at most |
| `email` | required, an address by RFC 5322 without warnings, as Symfony Mime writes it in `Reply-To`, 254 characters at most |
| `subject` | optional, 150 characters at most |
| `message` | required, 10 to 5000 characters |

It carries `phil/spam-protection-bundle`'s protection, scope `contact_form`, its subject and message read by the
content rules: a held submission gets the protection's one error, which never says why.

## The templates

`@PhilContactForm/contact.html.twig` is a page of its own. An application gives its own with the key `template`,
extending its layout and including the form:

```twig
{% extends 'base.html.twig' %}
{% block body %}
    <h1>{{ 'contact_form.title'|trans({}, 'phil_contact_form') }}</h1>
    {% include '@PhilContactForm/_form.html.twig' %}
{% endblock %}
```

`_form.html.twig` says when the message left or failed, and renders the form. Labels are in the `phil_contact_form`
translation domain.
