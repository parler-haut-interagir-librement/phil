<!-- phil-dev source-sha256=8dbf98f8711a6faa5ed6a240abc358b6d7c9287227193376af02357c9ae62f3e -->
# L'inventaire des témoins

Tout ce qu'un site, et les services qu'il intègre, écrivent dans le navigateur du visiteur est déclaré une fois, dans
`config/privacy/cookies.yaml`. La bannière, la page des choix et la politique des témoins en sont tirées, et un test
fonctionnel échoue sur une réponse qui pose un témoin qu'il ne déclare pas.

```yaml
# config/privacy/cookies.yaml — each consent category to what it writes
session:
    PHPSESSID:
        purpose: { en: Keeps you signed in, fr: Garde votre session ouverte }
        duration: session
analytics:
    '_pk_id.*':
        purpose: { en: Tells one visitor from another, fr: Distingue un visiteur d’un autre }
        duration: P13M
        provider: Matomo
    '_pk_ses.*':
        purpose: { en: Groups the pages of one visit, fr: Regroupe les pages d’une visite }
        duration: PT30M
        provider: Matomo
videos:
    yt-player-bandwidth:
        purpose: { en: Adapts the video to your connection, fr: Adapte la vidéo à votre connexion }
        duration: session
        provider: YouTube
        storage: local_storage
```

| Clé | Règle |
|---|---|
| catégorie | Une catégorie de `phil_privacy.consent.categories` ; toute autre arrête la construction, pour que rien ne soit déclaré sous un choix qu'on ne demande à personne. |
| nom | Les caractères d'un nom de témoin, au plus 128. Un nom qui finit par `*` couvre une famille : `_pk_id.*` couvre `_pk_id.1.1fff`. C'est un préfixe, jamais un motif. |
| `purpose` | Obligatoire. Un texte, ou des textes par locale ; la politique montre la locale du lecteur, puis l'anglais, puis le premier. Une ligne d'au plus 500 caractères chacun. |
| `duration` | Obligatoire. `session`, jusqu'à la fermeture du navigateur, ou une durée ISO 8601 comme `P13M`. |
| `provider` | Qui l'écrit, quand ce n'est pas le site lui-même : le nom du service. |
| `storage` | `cookie` (par défaut), `local_storage` ou `session_storage`. |

Toute autre clé arrête la construction, comme un témoin déclaré deux fois dans le même stockage. Le fichier est lu une
fois, à la construction du conteneur, et il est une ressource du conteneur : changez-le, et le conteneur est
reconstruit. Un fichier qui n'existe pas ne déclare rien. Un autre chemin se met sous `phil_privacy.cookies.inventory`.

Le bundle déclare son propre témoin, celui qui garde le choix (`consent.cookie_name`), pour `consent.lifetime`. Il
n'appartient à aucune catégorie, puisqu'aucun choix ne peut le refuser, et la politique le montre en premier, sous
« Votre choix ».

## La bannière, la page et la politique

- La **bannière** liste, sous « Ce qui est écrit dans votre navigateur », chaque catégorie avec les noms qu'elle écrit,
  et renvoie à la politique.
- La **page des choix** donne à chaque catégorie les noms qu'elle écrit.
- La **politique des témoins**, `phil_privacy_cookies` à `/privacy-choices/cookies`, a un tableau par catégorie : le
  nom, qui l'écrit, pourquoi, pour combien de temps et où. Une catégorie qui n'écrit rien le dit : on demande tout de
  même son avis à la personne. `privacy_cookie_policy()` rend les mêmes tableaux dans la politique de confidentialité du
  site.

Leurs textes sont dans le domaine `phil_privacy` ; remplacez `@PhilPrivacy/cookies/_inventory.html.twig` et
`cookies/policy.html.twig` pour votre balisage.

## Un test qui échoue sur un témoin non déclaré

```php
use Phil\PrivacyBundle\Infrastructure\Testing\AssertsDeclaredCookies;

final class PagesTest extends WebTestCase
{
    use AssertsDeclaredCookies;

    public function testTheHomePage(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        self::assertResponseSetsDeclaredCookiesOnly($client->getResponse());
    }
}
```

L'assertion lit l'inventaire dans le conteneur de test et échoue avec les noms qu'il ne déclare pas. Un témoin que la
réponse efface n'est pas un témoin qu'elle pose. Le trait demande PHPUnit et un `KernelTestCase` ;
`UndeclaredCookies::in()` donne les mêmes noms à tout autre cadre de test.

En développement, `UndeclaredCookieListener` journalise un avertissement pour chaque réponse qui pose un témoin non
déclaré, pour qu'un nouveau témoin se montre avant qu'un test soit écrit pour lui. Il est actif quand `kernel.debug`
l'est, et `cookies.watch` l'active ou le désactive partout ; il ne change jamais la réponse.

L'inventaire ne voit pas ce qu'un script écrit dans le navigateur après le chargement de la page. Déclarez-le tout de
même, sous la catégorie dont le consentement laisse le script s'exécuter.
