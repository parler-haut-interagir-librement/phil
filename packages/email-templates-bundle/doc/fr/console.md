<!-- phil-dev source-sha256=514bf5cc9eed6d3121a26d887d29fb437c68b6a76966ed4aa33bc63269d4e80d -->
# Console

Les commandes sont enregistrées quand `symfony/console` est installé.

## phil:email-templates:list

```sh
bin/console phil:email-templates:list
bin/console phil:email-templates:list --site=main
bin/console phil:email-templates:list --site=""      # the shared scope
```

Chaque gabarit que le site envoie ou dont il conserve un brouillon, par clé puis par locale : l'origine de ce qu'il
envoie — `site`, `shared` ou `default`, vide quand c'est une autre locale de la chaîne qui est envoyée —, la version
envoyée, l'état du brouillon propre au site — `unpublished`, `draft` ou `published` — et le moment de son
enregistrement.

## phil:email-templates:check

```sh
bin/console phil:email-templates:check -v
```

Vérifie chaque fichier par défaut comme le ferait un enregistrement : il est lu, ses sources se compilent dans le bac à
sable et ne lisent que ce que son schéma déclare, et chaque variante produit son rendu avec les valeurs d'exemple.
Chaque échec est listé avec ses raisons ; la commande échoue quand l'un d'eux survient, pour qu'un déploiement s'arrête.
`-v` liste chaque gabarit vérifié.

## phil:email-templates:render

```sh
bin/console phil:email-templates:render ticket --locale=fr --segment=vip --part=text
bin/console phil:email-templates:render ticket --site=main --template-version=2 --part=html > ticket.html
bin/console phil:email-templates:render ticket --site=main --draft --part=subject
```

Produit le rendu d'un gabarit avec ses valeurs d'exemple, par le `PreviewRenderer`, et écrit une partie : `subject`,
`preheader`, `html` — la valeur par défaut — ou `text`. `--site` est la portée partagée par défaut ; `--template-version`
désigne une version que le site a publiée, et `--draft` le brouillon qu'il conserve dans la locale, plutôt que ce qu'il
envoie.
