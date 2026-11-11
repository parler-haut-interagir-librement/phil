<!-- phil-dev source-sha256=d2cd97b402aa6c81949b68cb44108812b13bc6677c1800eca3fef871b8129a43 -->
# Twig

| Filtre | Donne |
|---|---|
| `messenger_display` | L'identifiant tel que les gens le lisent, `@adalove`, `+1 514-555-0123` ; vide pour `null` |
| `messenger_link` | L'adresse qui ouvre une conversation, ou `null` quand le service n'en a pas |
| `messenger_label` | Le nom du service, d'un identifiant ou d'un service : `WhatsApp`, `XMPP` ; le nom du cas pour un service de l'application |

```twig
{% set link = shop.messenger|messenger_link %}
{% if link %}<a href="{{ link }}">{{ shop.messenger|messenger_label }}</a>{% endif %}
```
