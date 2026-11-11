<!-- phil-dev source-sha256=d6f8f5003afa7861d1261149918a047e9d1596b41183da4e59890936b5c6155f -->
# Documents UJO

Quand `phil/ujo-bundle` est installé et que `ujo.enabled` est vrai (la valeur par défaut), le bundle décore le service
`Phil\Ujo\Html\HtmlRenderer`. Son `withFilter()` ajoute un `TypographyHtmlFilter`, qui applique le typographe de
`ujo.locale` au HTML de chaque document ; si elle est nulle, c'est la locale courante, que les
[filtres Twig](twig.md#la-locale-dun-gabarit) prennent dans le `LocaleSwitcher` de Symfony. Tout ce qui passe par ce
service pour le rendu est corrigé : `Ujo::toHtml()`, le filtre et la fonction `ujo_html`, et le code qui injecte le
moteur de rendu.

```twig
{{ article.body|ujo_html }}
{# <p class="ujo-paragraph">«&nbsp;Bonjour&nbsp;»&nbsp;: voici…</p> #}
```

Le filtre reçoit le HTML purifié de `phil/ujo` et ne réécrit que son texte : le HTML reste aussi purifié qu'avant. Le
code des blocs `code`, le contenu des blocs `raw` à l'intérieur de `pre`, et tout autre élément protégé gardent leur
texte.

Un document n'a pas de locale propre : celle de la page sert à chaque document qu'elle affiche. Une application dont
les documents ne sont pas dans la langue de la page désactive la passe et corrige chaque document avec le typographe
de sa langue :

```yaml
phil_typography:
    ujo: false
```

```twig
{{ article.body|ujo_html|typography(article.locale) }}
```

Sans `phil/ujo-bundle`, le service du filtre est retiré à la compilation du conteneur, et rien n'est décoré.
