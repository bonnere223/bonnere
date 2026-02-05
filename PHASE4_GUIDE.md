# Phase 4 - Guide d'exportation (ZIP & TXT)

## 🎯 Objectif Phase 4

Ajouter une **metabox d'exportation** sur l'écran d'édition des articles permettant de :
1. **Télécharger un ZIP** contenant toutes les images des recettes
2. **Télécharger un TXT** contenant uniquement les noms et instructions des recettes

---

## 📦 Architecture implémentée

### Nouveau fichier créé

**`/includes/class-argp-export.php`** (600+ lignes)

Classe `ARGP_Export` avec pattern Singleton qui gère :
- Enregistrement de la metabox
- Extraction intelligente des données
- Génération des fichiers
- Streaming sécurisé des téléchargements

### Modifications

**`ai-recipe-generator-pro.php`**
- Ajout de `require_once` pour charger `class-argp-export.php`
- Initialisation de `ARGP_Export::get_instance()` dans la section admin

---

## 🎨 Interface utilisateur

### Metabox "AI Recipe Generator Pro – Export"

**Emplacement** : Sidebar droite de l'écran d'édition (post.php)

**Contenu** :
```
┌────────────────────────────────────────┐
│ AI Recipe Generator Pro – Export      │
├────────────────────────────────────────┤
│                                        │
│  [📥 Télécharger ZIP des images]     │
│                                        │
│  [📄 Télécharger TXT des recettes]   │
│                                        │
│  ℹ️ Info :                            │
│  Les images sont exportées dans       │
│  l'ordre d'apparition des recettes.   │
│  Le fichier TXT contient uniquement   │
│  les noms et instructions.            │
│                                        │
│  ⚠️ Attention : (si ZipArchive absent)│
│  ZipArchive n'est pas disponible...   │
│  Le plugin utilisera PclZip.          │
└────────────────────────────────────────┘
```

### Styles inline

La metabox inclut des styles CSS inline pour :
- Boutons en pleine largeur avec icônes
- Boîte info avec bordure bleue
- Boîte warning avec bordure rouge
- Espacement harmonieux

---

## 🔒 Sécurité

### Niveau 1 : Permissions

**Vérifications multiples** :
1. `current_user_can('edit_post', $post_id)` dans la metabox
2. `current_user_can('edit_post', $post_id)` dans les handlers
3. Optionnel : on pourrait ajouter `manage_options` pour restreindre davantage

### Niveau 2 : Nonces

**Génération** :
```php
$nonce = wp_create_nonce( 'argp_export_' . $post->ID );
```

**Vérification** :
```php
wp_verify_nonce( $nonce, 'argp_export_' . $post_id )
```

**Unicité** : Le nonce est spécifique au post (évite les attaques par réutilisation)

### Niveau 3 : Validation

- **Post ID** : `absint()` pour forcer entier positif
- **Nonce** : `sanitize_text_field()` + `wp_unslash()`
- **Existence du post** : `get_post()` avec vérification

### Niveau 4 : Streaming sécurisé

- **Pas de fichiers publics** : Génération en `get_temp_dir()`
- **Streaming direct** : Headers + `readfile()` + `exit`
- **Nettoyage** : `@unlink()` après téléchargement
- **Buffer clean** : `ob_end_clean()` avant headers

---

## 📥 Export ZIP - Fonctionnement détaillé

### Étape 1 : Extraction des images

**Méthode principale** : `extract_images_from_post($post)`

#### Stratégie A : Détecter les classes `wp-image-{ID}`

```php
preg_match_all( '/wp-image-(\d+)/i', $content, $matches );
```

**Avantages** :
- Méthode la plus fiable
- Les images insérées par `wp_get_attachment_image()` ont cette classe
- Ordre d'apparition préservé

**Traitement** :
```php
foreach ( $matches[1] as $attachment_id ) {
    $file_path = get_attached_file( $attachment_id );
    if ( file_exists( $file_path ) ) {
        $images[] = array(
            'id'   => $attachment_id,
            'path' => $file_path,
            'ext'  => pathinfo( $file_path, PATHINFO_EXTENSION ),
        );
    }
}
```

#### Stratégie B : Fallback avec `attachment_url_to_postid()`

Si aucune classe `wp-image-*` n'est trouvée :

```php
preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $img_matches );

foreach ( $img_matches[1] as $img_url ) {
    $attachment_id = attachment_url_to_postid( $img_url );
    // ... même traitement
}
```

**Avantages** :
- Fonctionne même si les classes WP sont absentes
- Supporte les images insérées manuellement

#### Dédoublonnage

```php
// Éviter les doublons par ID
$unique_images = array();
$seen_ids      = array();

foreach ( $images as $image ) {
    if ( ! in_array( $image['id'], $seen_ids ) ) {
        $unique_images[] = $image;
        $seen_ids[]      = $image['id'];
    }
}
```

---

### Étape 2 : Création du ZIP

**Méthode** : `create_zip_from_images($images, $post_id)`

#### Option A : ZipArchive (natif PHP)

```php
$zip = new ZipArchive();
$zip->open( $zip_path, ZipArchive::CREATE );

foreach ( $images as $index => $image ) {
    $new_name = 'recette-' . ( $index + 1 ) . '.' . $image['ext'];
    $zip->addFile( $image['path'], $new_name );
}

$zip->close();
```

**Avantages** :
- Rapide et performant
- Gestion native de la compression

#### Option B : PclZip (fallback WordPress)

Si `ZipArchive` n'est pas disponible :

```php
require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';

$zip = new PclZip( $zip_path );

$file_list = array();
foreach ( $images as $index => $image ) {
    $file_list[] = array(
        PCLZIP_ATT_FILE_NAME       => $image['path'],
        PCLZIP_ATT_FILE_NEW_FULL_NAME => 'recette-' . ( $index + 1 ) . '.' . $image['ext'],
    );
}

$result = $zip->create( $file_list );
```

**Avantages** :
- Inclus dans WordPress (pas de dépendance externe)
- Fonctionne sur tous les hébergeurs

---

### Étape 3 : Streaming du ZIP

**Méthode** : `stream_file_download($file_path, $file_name, $mime_type)`

```php
// Nettoyer le buffer
if ( ob_get_level() ) {
    ob_end_clean();
}

// Headers HTTP
header( 'Content-Type: application/zip' );
header( 'Content-Disposition: attachment; filename="images-recettes-123.zip"' );
header( 'Content-Length: ' . filesize( $file_path ) );
header( 'Cache-Control: no-cache, must-revalidate' );
header( 'Expires: 0' );

// Envoyer le fichier
readfile( $file_path );

// Supprimer le temporaire
@unlink( $file_path );

exit;
```

**Pourquoi `exit` ?** : Empêche WordPress d'ajouter du contenu après le fichier

---

## 📝 Export TXT - Fonctionnement détaillé

### Étape 1 : Extraction des recettes

**Méthode principale** : `extract_recipes_from_post($post)`

#### Stratégie A : DOMDocument (parsing HTML propre)

```php
$dom = new DOMDocument();
$dom->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ) );

$h2_list = $dom->getElementsByTagName( 'h2' );

foreach ( $h2_list as $h2 ) {
    $recipe_name = trim( $h2->textContent );
    
    // Chercher le prochain <ol> après ce H2
    $next_node = $h2->nextSibling;
    
    while ( $next_node ) {
        if ( 'ol' === $next_node->nodeName ) {
            $li_list = $next_node->getElementsByTagName( 'li' );
            foreach ( $li_list as $li ) {
                $instructions[] = trim( $li->textContent );
            }
            break;
        }
        $next_node = $next_node->nextSibling;
    }
    
    $recipes[] = array(
        'name'         => $recipe_name,
        'instructions' => $instructions,
    );
}
```

**Avantages** :
- Parsing robuste et précis
- Gère les HTML malformés (avec `libxml_use_internal_errors`)
- Extraction de texte sans balises

#### Stratégie B : Regex (fallback)

Si DOMDocument échoue ou retourne vide :

```php
// Extraire les H2
preg_match_all( '/<h2[^>]*>(.*?)<\/h2>/is', $content, $h2_matches );

foreach ( $h2_matches[1] as $h2_content ) {
    $recipe_name = wp_strip_all_tags( $h2_content );
    
    // Chercher le <ol> suivant
    if ( preg_match( '/<ol[^>]*>(.*?)<\/ol>/is', $content, $ol_matches ) ) {
        preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $ol_matches[1], $li_matches );
        
        $instructions = array();
        foreach ( $li_matches[1] as $li_content ) {
            $instructions[] = wp_strip_all_tags( $li_content );
        }
    }
}
```

**Avantages** :
- Fonctionne même si DOMDocument est désactivé
- Utilise `wp_strip_all_tags()` pour nettoyer

---

### Étape 2 : Génération du TXT

**Méthode** : `generate_txt_from_recipes($recipes)`

**Format produit** :
```
SALADE CÉSAR VÉGÉTARIENNE
==========================

1) Laver la laitue romaine et la couper en morceaux
2) Préparer la sauce césar avec yaourt grec et parmesan
3) Ajouter les croûtons et mélanger délicatement
4) Servir immédiatement


TARTE AUX LÉGUMES
==================

1) Préchauffer le four à 180°C
2) Étaler la pâte feuilletée dans un moule
3) Disposer les légumes en rosace
4) Enfourner 30 minutes


SMOOTHIE VERT DÉTOX
====================

1) Mixer les épinards avec la banane
2) Ajouter le lait d'amande et le miel
3) Servir frais dans un grand verre
```

**Code** :
```php
$txt = '';

foreach ( $recipes as $index => $recipe ) {
    // Titre en majuscules
    $txt .= strtoupper( $recipe['name'] ) . "\n";
    $txt .= str_repeat( '=', mb_strlen( $recipe['name'] ) ) . "\n\n";
    
    // Instructions numérotées
    foreach ( $recipe['instructions'] as $step_index => $instruction ) {
        $txt .= ( $step_index + 1 ) . ') ' . $instruction . "\n";
    }
    
    // Ligne vide entre recettes
    if ( $index < count( $recipes ) - 1 ) {
        $txt .= "\n\n";
    }
}

return $txt;
```

---

### Étape 3 : Streaming du TXT

```php
// Créer fichier temporaire
$temp_dir = get_temp_dir();
$txt_path = $temp_dir . 'argp-recettes-123.txt';
file_put_contents( $txt_path, $txt_content );

// Streamer
header( 'Content-Type: text/plain; charset=utf-8' );
header( 'Content-Disposition: attachment; filename="recettes-123.txt"' );
header( 'Content-Length: ' . filesize( $txt_path ) );

readfile( $txt_path );

@unlink( $txt_path );
exit;
```

---

## 🔗 URLs et Endpoints

### Format des URLs

**Export ZIP** :
```
/wp-admin/admin-post.php?action=argp_export_zip&post_id=123&_wpnonce=abc123
```

**Export TXT** :
```
/wp-admin/admin-post.php?action=argp_export_txt&post_id=123&_wpnonce=abc123
```

### Handlers WordPress

**Enregistrement** :
```php
add_action( 'admin_post_argp_export_zip', array( $this, 'handle_export_zip' ) );
add_action( 'admin_post_argp_export_txt', array( $this, 'handle_export_txt' ) );
```

**Note** : `admin_post_` est un hook WordPress natif pour les actions admin POST/GET

---

## 🧪 Tests recommandés

### Test 1 : Export ZIP (article Phase 3)

**Étapes** :
1. Générer un article avec 3 recettes (Phase 3)
2. Éditer l'article créé
3. Dans la sidebar droite, trouver la metabox "AI Recipe Generator Pro – Export"
4. Cliquer sur "Télécharger ZIP des images"

**Résultats attendus** :
- ✅ Téléchargement immédiat d'un fichier `images-recettes-123.zip`
- ✅ Le ZIP contient :
  - `recette-1.jpg` (ou .png/.webp selon l'original)
  - `recette-2.jpg`
  - `recette-3.jpg`
- ✅ Les images sont dans l'ordre d'apparition dans l'article
- ✅ Les images s'ouvrent correctement

---

### Test 2 : Export TXT (article Phase 3)

**Étapes** :
1. Sur le même article
2. Cliquer sur "Télécharger TXT des recettes"

**Résultats attendus** :
- ✅ Téléchargement immédiat d'un fichier `recettes-123.txt`
- ✅ Le fichier contient :
  - Nom de chaque recette en MAJUSCULES
  - Ligne de séparation (===)
  - Instructions numérotées (1), 2), 3)...)
  - Ligne vide entre recettes
- ✅ Pas de HTML parasite (pas de `<p>`, `<li>`, etc.)
- ✅ Encodage UTF-8 correct (accents préservés)

**Exemple attendu** :
```
SALADE CÉSAR VÉGÉTARIENNE
==========================

1) Laver la laitue romaine
2) Préparer la sauce
3) Ajouter les croûtons

TARTE AUX LÉGUMES
==================

1) Préchauffer le four
2) Étaler la pâte
3) Enfourner 30 minutes
```

---

### Test 3 : Article sans images

**Étapes** :
1. Créer un article WordPress standard sans images
2. Éditer l'article
3. Cliquer sur "Télécharger ZIP des images"

**Résultat attendu** :
- ❌ Message d'erreur : "Aucune image trouvée dans cet article."
- ❌ Pas de téléchargement

---

### Test 4 : Article sans recettes structurées

**Étapes** :
1. Créer un article avec du contenu normal (pas de H2 + OL)
2. Cliquer sur "Télécharger TXT des recettes"

**Résultat attendu** :
- ❌ Message d'erreur : "Aucune recette trouvée dans cet article."
- ❌ Pas de téléchargement

---

### Test 5 : Permissions

**Étapes** :
1. Se connecter avec un compte "Contributeur" (pas admin)
2. Essayer d'accéder à l'URL d'export directement

**Résultat attendu** :
- ❌ Message : "Vous n'avez pas les permissions nécessaires."
- ❌ Pas de téléchargement

---

### Test 6 : Nonce invalide

**Étapes** :
1. Copier l'URL d'export
2. Modifier le paramètre `_wpnonce` avec une valeur aléatoire
3. Accéder à l'URL modifiée

**Résultat attendu** :
- ❌ Message : "Erreur de sécurité : nonce invalide."
- ❌ Pas de téléchargement

---

### Test 7 : Serveur sans ZipArchive

**Simulation** (pour tester le fallback PclZip) :
1. Temporairement, commenter `class_exists('ZipArchive')` dans le code
2. Tester l'export ZIP

**Résultat attendu** :
- ⚠️ Warning dans la metabox : "ZipArchive n'est pas disponible..."
- ✅ Le ZIP se télécharge quand même (via PclZip)
- ✅ Les images sont présentes et correctes

---

## 📊 Cas d'usage avancés

### Images multiples pour une recette

**Situation** : Une recette a plusieurs photos (étapes visuelles)

**Comportement** :
- Toutes les images sont exportées dans l'ordre
- Nommage : `recette-1.jpg`, `recette-2.jpg`, `recette-3.jpg`...
- Si recette 1 a 2 images et recette 2 a 1 image :
  - `recette-1.jpg` (recette 1, image 1)
  - `recette-2.jpg` (recette 1, image 2)
  - `recette-3.jpg` (recette 2, image 1)

### Extensions d'images variées

**Situation** : Images en JPG, PNG, WEBP mélangées

**Comportement** :
- Extension originale préservée
- `recette-1.jpg`
- `recette-2.png`
- `recette-3.webp`

### HTML malformé

**Situation** : Article avec HTML non standard

**Comportement** :
- DOMDocument avec `libxml_use_internal_errors(true)` tente de parser
- Si échec : fallback regex
- Si toujours vide : message "Aucune recette trouvée"

### Blocs Gutenberg

**TODO Phase 5** : Support avancé des blocs

Les blocs Gutenberg utilisent des commentaires HTML :
```html
<!-- wp:image {"id":123} -->
<figure class="wp-block-image">
  <img src="..." class="wp-image-123" />
</figure>
<!-- /wp:image -->
```

**Amélioration possible** :
- Parser les commentaires `<!-- wp:image -->`
- Extraire l'ID depuis le JSON `{"id":123}`
- Plus robuste que regex

---

## ⚙️ Configuration requise

### PHP

- **Minimum** : PHP 7.4
- **Recommandé** : PHP 8.0+
- **Extension optionnelle** : `zip` (pour ZipArchive)
  - Si absente : fallback PclZip automatique

### WordPress

- **Minimum** : 5.8
- **Classe requise** : PclZip (fournie par WordPress)

### Serveur

- **Permissions** :
  - Lecture des fichiers attachments
  - Écriture dans `get_temp_dir()`
- **Memory** : Suffisant pour charger les images en mémoire (généralement OK)

---

## 🐛 Dépannage

### Problème : "Aucune image trouvée"

**Causes possibles** :
1. Les images ne sont pas des attachments WordPress
2. Les images ont été insérées manuellement sans classe `wp-image-*`
3. Les images sont externes (URL http://)

**Solutions** :
1. Vérifier que les images sont dans la Media Library
2. Réinsérer les images depuis la Media Library
3. Pour images externes : TODO Phase 5 (support URL externes)

### Problème : "Aucune recette trouvée"

**Causes possibles** :
1. L'article n'a pas été généré par Phase 3
2. Structure HTML différente (pas de H2 + OL)
3. HTML trop malformé

**Solutions** :
1. Utiliser des articles générés par le plugin
2. Adapter manuellement la structure : `<h2>Titre</h2>...<ol><li>Étape</li></ol>`

### Problème : ZIP corrompu

**Causes possibles** :
1. Buffer output non nettoyé (`ob_end_clean()` échoue)
2. Erreur PHP affichée avant le stream
3. Timeout durant la génération

**Solutions** :
1. Vérifier `error_log` WordPress
2. Désactiver temporairement autres plugins
3. Augmenter `max_execution_time` si beaucoup d'images

### Problème : Accents cassés dans TXT

**Cause** : Encodage incorrect

**Solution** :
- Le code utilise déjà `charset=utf-8` dans le header
- Vérifier que l'éditeur de texte supporte UTF-8
- Windows Notepad : utiliser Notepad++ ou VS Code

---

## 🚀 Améliorations possibles (Phase 5)

### 1. Support images externes

Télécharger et inclure les images hébergées hors WordPress :
```php
if ( ! $attachment_id && filter_var( $img_url, FILTER_VALIDATE_URL ) ) {
    $temp_image = download_url( $img_url );
    // Ajouter au ZIP
}
```

### 2. Export JSON structuré

Format machine-readable pour intégrations :
```json
{
  "recipes": [
    {
      "name": "Salade césar",
      "ingredients": [...],
      "instructions": [...],
      "image": "recette-1.jpg"
    }
  ]
}
```

### 3. Export PDF

Générer un PDF élégant avec images :
- Utiliser TCPDF ou mPDF
- Layout professionnel
- Table des matières

### 4. Batch export

Exporter plusieurs articles en un seul ZIP :
- Sélection multiple dans liste articles
- Bulk action "Exporter ZIP/TXT"

### 5. Personnalisation du format TXT

Options admin pour choisir :
- Inclure/exclure ingrédients
- Format Markdown au lieu de TXT simple
- Séparateurs personnalisés

---

## 📝 Checklist de validation

Avant de considérer Phase 4 terminée :

- [ ] ✅ Metabox visible sur écran d'édition
- [ ] ✅ Boutons "ZIP" et "TXT" fonctionnels
- [ ] ✅ Export ZIP télécharge un fichier valide
- [ ] ✅ Images nommées recette-1, recette-2, etc.
- [ ] ✅ Export TXT télécharge un fichier valide
- [ ] ✅ TXT contient noms + instructions sans HTML
- [ ] ✅ Nonces vérifiés (test URL modifiée échoue)
- [ ] ✅ Permissions vérifiées (contributeur échoue)
- [ ] ✅ Message erreur si aucune image/recette
- [ ] ✅ Fallback PclZip fonctionne (si ZipArchive absent)
- [ ] ✅ Aucune erreur PHP dans debug.log
- [ ] ✅ Fichiers temporaires supprimés après download

---

## 🎉 Conclusion

La **Phase 4** ajoute des fonctionnalités d'exportation professionnelles au plugin, permettant aux utilisateurs de :
- **Sauvegarder** les images des recettes en local
- **Partager** les recettes en format texte lisible
- **Réutiliser** le contenu hors WordPress

L'implémentation est :
- ✅ **Sécurisée** (nonces, permissions, pas de fichiers publics)
- ✅ **Robuste** (fallback PclZip, parsing DOM + regex)
- ✅ **Propre** (nettoyage des fichiers temporaires)
- ✅ **Extensible** (prête pour Phase 5)

---

**Date** : 5 février 2026  
**Version** : 1.0.0 Phase 4  
**Statut** : ✅ IMPLÉMENTÉ
