<!-- phil-dev source-sha256=61c188569adb38f2abfe7286696d628d49cfa1bc85f23daac8d51420c3c01519 -->
# Usages

| Méthode | Ce qu'elle donne |
|---|---|
| `usagesOf($target)` | Où une ressource est utilisée, par source puis par champ |
| `assertUnused($target, $leaving)` | Rien, ou `StillReferenced` ; les sources de `$leaving` disparaissent avec elle |
| `broken()` | Les usages dont la cible a disparu, pour les genres que connaît une cible : on demande une fois à chaque cible si elle existe, puis on lit d'un coup les usages de chaque cible disparue, par paquets de 500 cibles par genre |
| `refresh($source)`, `forget($source)` | Vers quoi pointe maintenant une source, en n'écrivant que les changements ; plus rien |
| `forgetSource($reference)` | Plus rien, pour une source connue par sa référence |
| `referenceOf($resource)`, `sourceOf($source)` | La référence qu'une cible ou un collecteur donne à un objet |

`StillReferenced` nomme les trois premières sources et combien d'autres restent : *image:hero is still used by
page:about (blocks.1.image), page:home (hero) and 2 more.* Sa propriété `usages` les contient toutes.

```bash
bin/console phil:references:check
# page:about (blocks.2.image) → image:gone, gone
```

La commande échoue tant qu'il existe un lien rompu : lancez-la dans une tâche planifiée, et corrigez les sources
qu'elle nomme.
