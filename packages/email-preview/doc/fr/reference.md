<!-- phil-dev source-sha256=fecba365acf4bd4680099515688e1721b67b4484ccc4b82117098f567810901e -->
# Référence

Chaque type public du paquet, avec son rôle.

## Vérification

| Type | Rôle |
|---|---|
| `Phil\EmailPreview\EmailChecker` | Exécute ses règles sur un courriel — `DefaultRules::all()` s'il n'en reçoit aucune — et renvoie un `Report`. Ne garde aucun état entre deux courriels. |
| `Phil\EmailPreview\CheckedEmail` | Un courriel tel que les règles le lisent : `subject`, `html`, `text`, `sending` et `document`, son HTML analysé une fois en `Dom\HTMLDocument`. `fromRendered()` lit un `RenderedEmail` de phil/email-templates-bundle, `fromMime()` un `Symfony\Component\Mime\Email`. `MAX_HTML_BYTES` est le plus grand HTML analysé. |
| `Phil\EmailPreview\Sending` | La façon dont le courriel est envoyé : `bulk`, `headers` par nom en minuscules, `recipientTokens`. `bulk()` construit un envoi en masse ; `header()` lit un en-tête quelle que soit la casse de son nom. |
| `Phil\EmailPreview\Report` | Ce qu'une vérification a trouvé : les `rules` exécutées et leurs `findings`, dans l'ordre. `passes()`, `errors()`, `warnings()`, `ofRule()`, `toText()`, `toHtml()`, `toArray()` ; dénombrable et sérialisable en JSON. |
| `Phil\EmailPreview\Finding` | Un problème : `rule`, `severity`, `message` et le `subject` dont il s'agit, quand il porte sur une seule chose. `error()` et `warning()` en construisent un ; `toText()` l'écrit sur une ligne. |
| `Phil\EmailPreview\Severity` | `Error`, à corriger avant l'envoi du courriel, ou `Warning`, qui demande un coup d'œil. |

## Aperçus

| Type | Rôle |
|---|---|
| `Phil\EmailPreview\Preview\PreviewChecks` | Rend chaque variante d'un gabarit avec le `PreviewRenderer` de phil/email-templates-bundle et la vérifie, comme elle serait envoyée. |
| `Phil\EmailPreview\Preview\CheckedPreview` | Un aperçu vérifié : sa `variant`, le courriel rendu `email` et son `report`. |

## Règles

| Type | Rôle |
|---|---|
| `Phil\EmailPreview\Contract\Rule` | Une vérification d'un courriel : `name()` et `check()`, qui produit ses constats. |
| `Phil\EmailPreview\Rule\DefaultRules` | `all()` : les six règles ci-dessous, dans l'ordre du rapport. |
| `Phil\EmailPreview\Rule\TextPart` | `text-part` : une partie en texte brut qui donne chaque adresse `http:`, `https:` et `mailto:` du HTML ; une adresse `mailto:` est cherchée sans son schéma. |
| `Phil\EmailPreview\Rule\OneClickUnsubscribe` | `one-click-unsubscribe` : sur un envoi en masse, `List-Unsubscribe` avec une adresse `https:` entre chevrons, et `List-Unsubscribe-Post` égal à `POST_VALUE`, `List-Unsubscribe=One-Click`. |
| `Phil\EmailPreview\Rule\TrackingPixel` | `tracking-pixel` : aucune image distante d'un pixel ou moins dans les deux sens, par ses attributs `width` et `height` ou son style, et aucune adresse d'image distante qui contient un jeton du destinataire, tel quel ou encodé par `rawurlencode()`. Les images `cid:` et `data:` ne sont pas distantes. |
| `Phil\EmailPreview\Rule\NoStyleElement` | `no-style-element` : aucun élément `<style>` ; un seul constat donne leur nombre. |
| `Phil\EmailPreview\Rule\MaxWidth` | `max-width` : aucun élément plus large que `pixels`, 600 par défaut, par son attribut `width` ou par le `width`, le `min-width` ou le `max-width` de son style. Les pourcentages et les autres unités ne sont pas lus. |
| `Phil\EmailPreview\Rule\SubjectLength` | `subject-length` : un avertissement quand l'objet rogné compte plus de `length` graphèmes, 50 par défaut ; un objet qui n'est pas de l'UTF-8 valide est compté par point de code. |

## Exceptions

| Type | Rôle |
|---|---|
| `Phil\EmailPreview\Exception\EmailPreviewException` | Toute exception de la bibliothèque. |
| `Phil\EmailPreview\Exception\EmailTooLarge` | Un HTML plus grand que `CheckedEmail::MAX_HTML_BYTES`, refusé avant d'être analysé. |
