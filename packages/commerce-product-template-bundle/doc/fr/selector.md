<!-- phil-dev source-sha256=2010a53ede2fac58ab49c4c0479cc0dae9afc9807b539109dd125a656d7a8b0a -->
# Choisir un produit

Les produits d'un groupe sont présentés dans une seule page, avec un sélecteur par axe. Le contrôleur Stimulus du
module, `variant-selector`, choisit le membre des valeurs retenues, désactive les valeurs que le groupe ne vend pas
avec les autres, et affiche les maquettes du membre. Avec AssetMapper et `symfony/stimulus-bundle`, le module le sert
sous le nom que Symfony UX donne aux contrôleurs d'un paquet : aucune étape de construction, et aucun Webpack.

```twig
{% set page = groups.page('NAQ001', 'unisex', app.request.locale) %}

<form {{ stimulus_controller('phil/commerce-product-template-bundle/variant-selector', {
    members: page.members,
    language: 'F',
    images: asset('shop-images/'),
}) }} data-action="change->phil--commerce-product-template-bundle--variant-selector#update">
    {% for axis in page.axes %}
        <fieldset>
            <legend>{{ axis.label }}</legend>
            {% for value in axis.values %}
                <label>
                    <input type="radio" name="{{ axis.code }}" value="{{ value.code }}" data-axis="{{ axis.code }}">
                    {{ value.label }}
                </label>
            {% endfor %}
        </fieldset>
    {% endfor %}

    <img data-phil--commerce-product-template-bundle--variant-selector-target="mockup" data-face="front" alt="" hidden>
    <p data-phil--commerce-product-template-bundle--variant-selector-target="unavailable" hidden>Not made in this combination.</p>
    <input type="hidden" name="sku" data-phil--commerce-product-template-bundle--variant-selector-target="sku">
    <button>Add to cart</button>
</form>
```

| Partie | Rôle |
|---|---|
| Valeur `members` | Les membres du groupe, tels que les donne `GroupPage::$members` : UGS, valeurs par axe, version linguistique, maquettes par face |
| Valeur `language` | Le code de la version linguistique affichée ; vide pour un motif qui n'en a pas |
| Valeur `images` | L'URL de base sous laquelle les chemins des maquettes sont lus |
| Un élément marqué `data-axis` | Un bouton radio ou un `<select>` d'un axe, ses valeurs étant les codes de l'axe |
| Cible `sku` | Reçoit l'UGS du membre choisi, ou rien |
| Cibles `mockup` | Des images marquées `data-face` : chacune affiche la maquette du membre pour sa face, ou est masquée |
| Cible `unavailable` | Affichée quand chaque axe a une valeur et qu'aucun membre ne les a |

Les membres d'une page sont les produits que le groupe vend : une combinaison que le gabarit exclut — une couleur non
fabriquée dans une taille — n'a aucun membre ; le contrôleur désactive donc cette couleur une fois la taille choisie,
et cette taille une fois la couleur choisie. L'option vide d'un `<select>` n'est jamais désactivée.

Chaque mise à jour envoie `phil--commerce-product-template-bundle--variant-selector:change`, dont le détail donne `sku`,
ou `null`, et `values`, les codes choisis par axe.

Le contrôleur modifie des propriétés et les attributs `hidden` et `disabled`, jamais un attribut `style` : une page
garde donc une politique de sécurité du contenu sans `style-src 'unsafe-inline'`. Le serveur vérifie l'UGS qu'il reçoit
comme n'importe quelle autre : le contrôleur n'aide que l'acheteur à choisir.
