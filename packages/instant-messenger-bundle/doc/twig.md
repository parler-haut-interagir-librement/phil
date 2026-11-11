# Twig

| Filter | Gives |
|---|---|
| `messenger_display` | The handle as people read it, `@adalove`, `+1 514-555-0123`; empty for `null` |
| `messenger_link` | The address that opens a conversation, or `null` when the service has none |
| `messenger_label` | The service's name, of a handle or a service: `WhatsApp`, `XMPP`; the case's name of an application's service |

```twig
{% set link = shop.messenger|messenger_link %}
{% if link %}<a href="{{ link }}">{{ shop.messenger|messenger_label }}</a>{% endif %}
```
