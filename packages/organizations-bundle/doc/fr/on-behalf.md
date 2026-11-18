<!-- phil-dev source-sha256=e9b1f876bd821838247feaa4a452dcbe00b93c2545c71e114540edfb99c2fb35 -->
# Agir au nom de l'organisation

Un membre peut agir au nom d'une organisation — publier, signer, répondre comme l'organisation — pendant que le véritable
auteur est consigné à côté :

```php
$post = $onBehalf->act($acme->id, $member, 'post.publish', fn (): Post => $posts->publish($draft, author: $acme->id->value));
```

`act()` vérifie que le membre détient `organization.act_on_behalf` dans une organisation active, exécute le travail,
puis émet `ActedOnBehalf` avec l'organisation, le véritable auteur, l'action et l'instant, pour `phil/audit-bundle` ou
n'importe quel écouteur. Rien n'est émis quand le travail lève une exception. L'action compte de 1 à 64 lettres
minuscules, chiffres, `_`, `.` ou `-`, et commence par une lettre.

Pendant l'exécution du travail, `current()` donne le contexte — l'organisation, l'auteur et l'action —, le plus interne
quand les appels s'imbriquent, de sorte que le code plus profond consigne lui aussi le véritable auteur :

```php
$context = $onBehalf->current();     // OnBehalfContext, or null outside act()
```

Le contexte ne vit que le temps de l'appel ; `reset()`, sur `kernel.reset`, l'efface entre les requêtes d'un *worker*.
