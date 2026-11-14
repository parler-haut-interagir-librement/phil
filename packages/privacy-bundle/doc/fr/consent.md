<!-- phil-dev source-sha256=b9c3713add1f8cbbb3884148d7676d0a1fd4b766bb520a2c49636574a9ae647b -->
# Consentement aux témoins

Déclarez les catégories qu'utilise un site, et lisez le consentement là où il compte.

```yaml
phil_privacy:
    consent:
        version: '2026-11'
        categories:
            session: { necessary: true, labels: { en: Session, fr: Session } }
            analytics:
                labels: { en: Measurement, fr: Mesure d'audience }
                descriptions: { en: Counts visits, fr: Compte les visites }
            videos:
                labels: { en: Videos, fr: Vidéos }
```

Ce qu'écrit chaque catégorie dans le navigateur est déclaré dans l'inventaire des témoins, `config/privacy/cookies.yaml`
([cookie-inventory.md](cookie-inventory.md)) : la bannière, la page des choix et la politique des témoins en sont
tirées, et un test fonctionnel échoue sur un témoin qu'il ne déclare pas. Un cadre d'un autre site attend sa catégorie
avec `privacy_embed()` ([third-party-frames.md](third-party-frames.md)).

```twig
{{ privacy_consent_banner() }}

{% if privacy_consent('analytics') %}
    <script src="/analytics.js" defer></script>
{% else %}
    <template data-privacy-consent="analytics"><script src="/analytics.js" defer></script></template>
{% endif %}
```

| Fonction | Renvoie |
|---|---|
| `privacy_consent(category)` | Si la catégorie peut s'exécuter. Une catégorie nécessaire toujours ; une catégorie inconnue jamais. |
| `privacy_consent_pending()` | S'il faut encore demander à la personne. |
| `privacy_consent_banner()` | La bannière, s'il le faut, avec ce qu'écrit chaque catégorie. |
| `privacy_cookie_policy()` | L'inventaire des témoins par catégorie, pour la politique de confidentialité du site. |
| `privacy_embed(url, category, options)` | Un cadre d'un autre site, chargé une fois sa catégorie accordée. |

## Les règles

- **Refusé par défaut.** Sans choix, chaque catégorie qui n'est pas nécessaire est refusée, et rien n'est écrit dans le
  navigateur du visiteur avant qu'il réponde.
- **Refuser est aussi simple qu'accepter.** Les deux sont un clic sur un bouton du même formulaire, de la même classe,
  sur la bannière et sur la page.
- **Retirer en tout temps.** La page `phil_privacy_consent` est ouverte à tous, connectés ou non. Mettez-y un lien
  depuis le pied de page.
- **Le Global Privacy Control est respecté.** Un navigateur qui envoie `Sec-GPC: 1` voit refuser chaque catégorie
  facultative, quoi que dise un témoin, et son utilisateur n'est pas sollicité. La page le dit, et ses cases sont
  désactivées.
- **Un choix vaut pour une version du texte.** Changez `consent.version` et chaque choix antérieur cesse de compter :
  tout le monde est sollicité de nouveau.

## Le témoin et la preuve

Le choix est gardé dans un témoin du site (`consent.cookie_name`), pendant `consent.lifetime` : sans `Domain`,
`HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS. Il contient un identifiant aléatoire, la version, les catégories
accordées, la date, et un HMAC sous une clé dérivée du secret de l'application. Une valeur mal formée, modifiée ou trop
longue se lit comme aucun choix.

Chaque décision laisse aussi une preuve datée dans `phil_privacy_consent_proof` : l'identifiant du choix, la version du
texte, ce qui a été accordé, la date et si le signal était présent. Elle ne nomme personne et ne contient aucune
adresse. Sans base de données, la preuve est une ligne de journal. Retirez les anciennes preuves par une règle de
conservation sur le stockage `phil_privacy_consent_proof`.

Une page qui lit le consentement reçoit `Vary: Cookie, Sec-GPC`, pour qu'un cache partagé ne remette pas la page d'une
personne à une autre.

## Sans JavaScript, et avec

Chaque décision est un simple `POST` qui revient à la page par un `303` : `accept`, `refuse`, `save` avec les cases
cochées, ou `grant` avec une `category`, ajoutée à celles déjà accordées, qui est le bouton d'un cadre bloqué. Chacune
est protégée par un jeton CSRF sans état, pour qu'un visiteur à qui l'on ne demande que son avis sur les témoins ne
reçoive pas de session.

Le contrôleur Stimulus `phil--privacy-bundle--consent` envoie le même formulaire en arrière-plan. La bannière se ferme,
l'événement `phil--privacy-bundle--consent:changed` donne les catégories en vigueur, et les
`<template data-privacy-consent>` d'une catégorie nouvellement accordée sont placés dans la page. Un consentement retiré
recharge la page, la seule façon d'arrêter ce qu'un script a commencé. Une requête qui échoue retombe sur le formulaire
simple.

## Votre propre balisage

Remplacez `templates/bundles/PhilPrivacy/consent/banner.html.twig` et `consent/page.html.twig`, ou donnez aux pages
votre mise en page avec `pages.layout`. Les textes sont dans le domaine de traduction `phil_privacy`, en anglais et en
français. Un remplacement garde le refus aussi visible que l'acceptation.
