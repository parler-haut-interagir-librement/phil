<!-- phil-dev source-sha256=65d11e6b6ec4cc36e8c07bdc5d6f56291143b4185d5543c47e35a823fdd36b21 -->
# Portée

## Ce que fait le bundle

- Répartit le stock de chaque SKU par propriétaire — la boutique, chaque consignataire — en couches par emplacement, et
  écrit chaque mouvement dans un grand livre.
- Reçoit les marchandises ligne par ligne, achetées à un coût unitaire ou consignées selon une part ou une commission,
  contre un bon de commande ou non, et adopte le stock déjà en main.
- Vend dans un ordre de sortie configurable, reprend les retours vers les couches d'origine, transfère entre
  emplacements et inventorie, pour tous les propriétaires ou pour un seul.
- Calcule la marge des unités de la boutique et la commission des unités consignées, ainsi que ce que chaque vente doit
  à chaque consignataire.
- Écrit une seule fois les ventes des commandes que le module de logistique expédie.
- Garde ce que la boutique a convenu avec chaque consignataire, version après version : la durée, le sort des unités
  invendues, la fréquence des relevés.
- Émet des relevés numérotés, lus dans le grand livre et figés, en CSV et en données pour un gabarit.
- Garde ce que chaque consignataire est en droit de recevoir et ce qui lui a été versé, avec des versements partiels et
  des contrepassations ; ne déplace aucun argent.
- Liste les unités invendues à l'échéance, les rend à leur consignataire — hors du grand livre et de la tablette — ou
  fait que la boutique les achète au coût convenu.

## Ce qu'il ne fait pas

| Partie | Où elle en est |
|---|---|
| Un relevé envoyé à son consignataire, par courriel ou autrement | L'application l'envoie : le bundle donne le CSV et les données pour un gabarit. |
| Un relevé imprimé en PDF | L'application imprime le HTML du gabarit avec le convertisseur qu'elle utilise. |
| Des relevés émis selon un calendrier, dans un worker | L'application appelle `ConsignorStatements::issue()` depuis son propre planificateur. |
| Lots et dates de péremption | Pas prévu pour ce bundle. |

## Ce qu'il laisse à l'application

- Les écrans : le bundle offre des services ; l'application dessine les siens, par exemple avec les recettes du kit PHIL
  UX, avec un CSRF sur chaque action.
- Qui est un consignataire — un fournisseur, un contact, un compte — : le bundle garde son identifiant, les modalités de
  chaque réception et les versions de son entente.
- Payer les consignataires : le bundle écrit ce qui a été versé, il ne déplace jamais d'argent.
- Qui peut lire un relevé, écrire un versement ou rendre des unités : les règles d'accès de l'application.
- Le moment où une expédition est écrite : voir [Avec le module de logistique](logistics.md#commandes-expediees).
