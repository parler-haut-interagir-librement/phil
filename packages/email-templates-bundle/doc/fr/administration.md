<!-- phil-dev source-sha256=93a219d2c6416a48cd3fcfd2a580550179057b75db169b38fb5397a3f9341be8 -->
# Administration et aperçu

## L'activer

```yaml
# config/packages/phil_email_templates.yaml
phil_email_templates:
    admin:
        enabled: true
        role: ROLE_EDITOR
        layout: admin/email_layout.html.twig   # a template of the back office, with "title" and "content" blocks

# config/routes/phil_email_templates_admin.yaml
phil_email_templates_admin:
    resource: '@PhilEmailTemplatesBundle/config/routes_admin.php'
    prefix: /admin/email-templates
```

Les routes se placent derrière le pare-feu de l'application ; chaque page redemande `admin.role`. L'administration
exige SecurityBundle, la protection CSRF de FrameworkBundle — le conteneur s'arrête avec un message quand elle est
désactivée —, Symfony UX Twig Component avec [les recettes du kit](installation.md#les-recettes-du-kit), et
`symfony/translation` pour ses libellés, dans le domaine `phil_email_templates`, en anglais et en français.

## Les pages

| Route | Chemin sous le préfixe | Fait |
|---|---|---|
| `phil_email_templates_admin_index` | `/` | Chaque gabarit que le site envoie ou dont il conserve un brouillon, par clé et par locale : d'où vient ce qu'il envoie — par défaut, partagé, celui du site, ou une autre locale —, la version envoyée, et l'état du brouillon du site |
| `phil_email_templates_admin_edit` | `/{key}/{locale}` | La page d'un gabarit : le formulaire de son brouillon, son aperçu, ses versions publiées ; `POST` enregistre, publie, rétablit, abandonne ou réinitialise |
| `phil_email_templates_admin_frame` | `/{key}/{locale}/frame` | Une variante produite avec ses valeurs d'exemple, comme le site l'envoie, comme son brouillon avec `?draft=1`, ou à une version publiée avec `?version=2` ; `?segments=vip,new` |

Une page agit pour le site courant ; `?scope=shared` agit pour la portée partagée, et la liste mène de l'une à l'autre.

La page d'un gabarit montre d'où vient ce que le site envoie et sa version, l'état du brouillon du site — non publié,
changements non publiés, ou publié — avec sa version et sa révision, ses autres locales, et un formulaire des champs du
brouillon, ou de ce que le site envoie tant qu'il ne conserve pas de brouillon — objet, pré-en-tête, format, corps,
partie en texte brut, segments séparés par des virgules, schéma des variables et images intégrées en JSON.
**Enregistrer le brouillon** l'enregistre ; **Publier** l'envoie, une fois qu'un brouillon contient quelque chose qui
n'est pas encore envoyé ; **Abandonner les changements** remet la version publiée dans le brouillon ; **Mettre hors
ligne** envoie de nouveau le gabarit partagé ou par défaut, en gardant le brouillon. Chaque version publiée, sauf celle
qui est envoyée, peut être copiée dans le brouillon. Un refus reçoit la réponse 422 avec chaque raison dans une alerte,
ce qui a été saisi étant conservé ; une révision enregistrée entre-temps reçoit la réponse 409 ; publier sans brouillon
reçoit la réponse 404 ; un changement effectué redirige vers la page avec son message.

## L'aperçu

L'aperçu dessine chaque variante du gabarit — aucun segment, chaque segment, tous — avec son objet, son pré-en-tête, un
avertissement quand Gmail le tronquerait, sa partie en texte brut, et le courriel dans un `<iframe sandbox="">` : aucun
script, aucun formulaire, aucune navigation de la page, et une origine propre. Le cadre lui-même est servi avec :

```http
Content-Security-Policy: default-src 'none'; img-src https: data:; style-src 'unsafe-inline'; font-src https: data:; base-uri 'none'; form-action 'none'; frame-ancestors 'self'; sandbox
X-Content-Type-Options: nosniff
Referrer-Policy: no-referrer
Cache-Control: no-store
```

Les images intégrées sont affichées sous forme d'adresses `data:`. Une variante dont le rendu échoue montre pourquoi,
avec la réponse 422.

L'aperçu passe par le port `PreviewRenderer` : `SamplePreviews` produit le rendu avec les valeurs d'exemple du schéma ;
`preview` nomme un autre service, comme un paquet d'aperçu dédié ou des données propres à l'application.

## La sécurité des formulaires

Chaque formulaire porte le jeton CSRF `phil_email_templates_admin`, que le bundle déclare sans état : Symfony vérifie
l'origine de la requête, ou un témoin à double soumission, et aucune session n'est nécessaire. Un formulaire sans jeton,
ou envoyé depuis une autre origine, reçoit la réponse 400. Chaque réponse est `no-store` et `noindex`.

## Le kit

Les pages dessinent avec les recettes `Alert`, `Badge`, `Button`, `Field`, `Input`, `NativeSelect`, `Table` et
`Textarea`, nommées par `phil_email_templates_kit()` d'après le sous-répertoire des composants, et ne leur donnent que
ce que le kit PHIL UX et le kit Shadcn lisent de la même façon. Le message d'un changement effectué est une région
dynamique polie, `role="status"`, autour de l'alerte ; un refus est l'alerte elle-même.
