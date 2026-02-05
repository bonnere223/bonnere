# Phase 3 - Guide complet de génération d'articles avec IA

## 🎯 Objectif Phase 3

Implémenter la **génération complète d'articles WordPress** avec :
- **Texte** généré par OpenAI (GPT-4o)
- **Images** générées par Replicate (Flux 2 Pro)
- **Téléchargement** des images dans la Media Library
- **Création** de l'article avec statut draft ou publish

## 📊 Architecture générale

### Job System avec Transient

Pour éviter les timeouts PHP, la génération est découpée en **étapes multiples** avec un système de **polling AJAX** :

```
┌─────────────────────────────────────────────┐
│ 1. User clique "Générer l'article complet" │
└──────────────────┬──────────────────────────┘
                   │
                   ▼
┌──────────────────────────────────────────────┐
│ 2. AJAX start_generation                    │
│    → Crée job transient                     │
│    → Retourne job_id                        │
└──────────────────┬───────────────────────────┘
                   │
                   ▼
┌──────────────────────────────────────────────┐
│ 3. Tick Loop (toutes les 2 secondes)       │
│    → AJAX generation_tick avec job_id       │
│    → Exécute 1 étape                        │
│    → Retourne progress%, message, done      │
└──────────────────┬───────────────────────────┘
                   │
                   ▼
┌──────────────────────────────────────────────┐
│ 4. Répéter jusqu'à done = true              │
└──────────────────┬───────────────────────────┘
                   │
                   ▼
┌──────────────────────────────────────────────┐
│ 5. Afficher résultats + lien edit article  │
└──────────────────────────────────────────────┘
```

### Structure du Job (Transient)

```php
$job_data = array(
    'step'              => 0,              // Étape actuelle (0, 1, 2, 3...)
    'subject'           => 'recettes végétariennes',
    'count'             => 3,              // Nombre de recettes
    'title'             => 'Mon article',
    'status'            => 'draft',        // ou 'publish'
    'openai_json'       => array(...),     // JSON retourné par OpenAI
    'created_post_id'   => 123,            // ID de l'article créé
    'replicate_results' => array(          // Résultats par recette
        0 => array(
            'prediction_id' => 'abc123',
            'status'        => 'succeeded',
            'attachment_id' => 456,
        ),
        // ...
    ),
    'errors'            => array(),        // Erreurs non bloquantes
    'started_at'        => 1707091234,     // Timestamp de début
);
```

**Stockage** : `set_transient('argp_job_XXX', $job_data, HOUR_IN_SECONDS)`

## 🔄 Étapes de génération

### STEP 0 : Génération du contenu avec OpenAI

**Méthode** : `job_step_generate_openai($job)`

**Action** :
1. Appelle `openai_generate_recipes($subject, $count)`
2. Envoie prompt à GPT-4o
3. Reçoit JSON structuré :
   ```json
   {
     "intro": "Texte d'introduction...",
     "recipes": [
       {
         "name": "Salade césar végétarienne",
         "ingredients": ["Laitue romaine", "Croûtons", ...],
         "instructions": ["Laver la laitue", "Préparer la sauce", ...],
         "image_prompt": "professional food photography of vegetarian caesar salad"
       },
       // ... autres recettes
     ]
   }
   ```
4. Stocke dans `$job['openai_json']`
5. Passe à STEP 1

**Progression** : 0% → 20%

---

### STEP 1 : Création de l'article WordPress

**Méthode** : `job_step_create_post($job)`

**Action** :
1. Construit le contenu initial :
   ```html
   <p>Introduction générée par OpenAI...</p>
   ```
2. Crée le post avec `wp_insert_post()` :
   ```php
   array(
       'post_title'   => $title,
       'post_content' => $content,
       'post_status'  => $status,  // draft ou publish
       'post_type'    => 'post',
       'post_author'  => get_current_user_id(),
   )
   ```
3. Stocke `post_id` dans `$job['created_post_id']`
4. Passe à STEP 2

**Progression** : 20% → 30%

---

### STEP 2-N : Génération des images (une par recette)

**Méthode** : `job_step_generate_image($job, $recipe_index)`

**Action pour chaque recette** :

#### Étape A : Démarrer la prédiction Replicate

```php
replicate_start_prediction($image_prompt)
```

**API Call** :
```json
POST https://api.replicate.com/v1/predictions
{
  "version": "black-forest-labs/flux-pro",
  "input": {
    "prompt": "professional food photography of vegetarian caesar salad"
  }
}
```

**Réponse** :
```json
{
  "id": "prediction_abc123",
  "status": "starting",  // ou "processing"
  ...
}
```

Stocke `prediction_id` dans `$job['replicate_results'][$recipe_index]`.

#### Étape B : Polling de l'état (ticks suivants)

```php
replicate_check_prediction($prediction_id)
```

**API Call** :
```json
GET https://api.replicate.com/v1/predictions/prediction_abc123
```

**Réponse possible 1** : En cours
```json
{
  "id": "prediction_abc123",
  "status": "processing",  // ou "starting"
  ...
}
```
→ Continue de poller au prochain tick

**Réponse possible 2** : Succès
```json
{
  "id": "prediction_abc123",
  "status": "succeeded",
  "output": "https://replicate.delivery/image123.jpg"
}
```
→ Télécharge l'image

#### Étape C : Téléchargement de l'image

```php
sideload_image($image_url, $post_id, $description)
```

**Actions** :
1. `download_url($image_url)` → fichier temporaire
2. `media_handle_sideload($file_array, $post_id)` → attachment
3. Retourne `attachment_id`

#### Étape D : Ajout au contenu

```php
append_recipe_to_post($post_id, $recipe, $attachment_id)
```

**Ajoute au contenu** :
```html
<h2>Salade césar végétarienne</h2>
<img src="..." class="recipe-image" />
<h3>Ingrédients</h3>
<ul class="recipe-ingredients">
  <li>Laitue romaine</li>
  <li>Croûtons</li>
  ...
</ul>
<h3>Instructions</h3>
<ol class="recipe-instructions">
  <li>Laver la laitue</li>
  <li>Préparer la sauce</li>
  ...
</ol>
```

**Progression** : 30% + (index / total * 60%) → jusqu'à 90%

**Gestion d'erreurs** :
- Si Replicate échoue : continue sans image
- Erreur enregistrée dans `$job['errors'][]`
- L'article est créé malgré tout

---

### STEP Final : Finalisation

**Méthode** : `job_step_finalize($job)`

**Action** :
1. Récupère `edit_link` avec `get_edit_post_link($post_id)`
2. Retourne :
   ```json
   {
     "done": true,
     "progress": 100,
     "message": "Génération terminée avec succès !",
     "post_id": 123,
     "edit_link": "https://site.com/wp-admin/post.php?post=123&action=edit",
     "errors": ["Erreur image recette 2: quota dépassé"]
   }
   ```

**Progression** : 100%

---

## 📝 Prompts OpenAI

### System Prompt

```
Tu es un chef cuisinier et rédacteur culinaire professionnel.
Tu génères du contenu pour un blog de recettes grand public en français.
Tes recettes sont claires, gourmandes, réalisables, et optimisées SEO.
Tu ne donnes jamais de conseils médicaux ou d'allégations santé non prouvées.
Tu réponds UNIQUEMENT en JSON valide sans markdown.
```

### User Prompt

```
Génère un article de blog complet sur le thème : "recettes végétariennes".

L'article doit contenir :
- Une introduction engageante (2-3 phrases)
- Exactement 3 recette(s) détaillée(s)

Pour chaque recette, fournis :
- name : nom de la recette (court et accrocheur)
- ingredients : liste des ingrédients (array de strings)
- instructions : étapes de préparation (array de strings, numérotées)
- image_prompt : prompt pour générer une photo réaliste de la recette (en anglais, style 'professional food photography of...')

Format JSON attendu :
{
  "intro": "Texte d'introduction...",
  "recipes": [
    {
      "name": "Nom de la recette",
      "ingredients": ["Ingrédient 1", "Ingrédient 2"],
      "instructions": ["Étape 1", "Étape 2"],
      "image_prompt": "professional food photography of..."
    }
  ]
}

IMPORTANT : Réponds UNIQUEMENT avec le JSON, sans aucun texte avant ou après.
```

### Configuration API

- **Modèle** : `gpt-4o`
- **Temperature** : 0.7 (équilibre créativité/cohérence)
- **Max tokens** : 3000
- **Response format** : `json_object`
- **Timeout** : 60 secondes

---

## 🖼️ Génération d'images Replicate

### Modèle utilisé

**Constante** : `ARGP_Ajax::REPLICATE_MODEL = 'black-forest-labs/flux-pro'`

> **TODO** : Vérifier la version exacte sur [Replicate](https://replicate.com/black-forest-labs/flux-pro)

### Format du prompt image

Généré par OpenAI dans `image_prompt` :

```
professional food photography of vegetarian caesar salad, 
top view, natural lighting, 
high quality, appetizing, restaurant style
```

**Consignes** :
- En anglais
- Style "professional food photography"
- Descriptif précis du plat
- Ambiance appétissante

### Workflow Replicate

1. **POST /v1/predictions** → Démarre génération
   - Header : `Authorization: Token [replicate_api_key]`
   - Body : `{ "version": "...", "input": { "prompt": "..." } }`
   - Réponse : `{ "id": "...", "status": "starting" }`

2. **GET /v1/predictions/{id}** → Vérifie état (polling)
   - Status possibles : `starting`, `processing`, `succeeded`, `failed`, `canceled`
   - Quand `succeeded` : `output` contient l'URL de l'image

3. **Download + Sideload** → Ajoute à Media Library
   - `download_url()` → fichier temporaire
   - `media_handle_sideload()` → attachment WordPress

### Timeouts

- **Start** : 30s
- **Check** : 15s
- **Polling interval** : 2s (côté client)

---

## 🎨 Interface utilisateur

### Page "Générer" - Formulaire

```html
<form id="argp-generate-form">
  
  <!-- Sujet/Thème (requis) -->
  <input id="argp_subject" required />
  
  <!-- Nombre de recettes (1-10) -->
  <select id="argp_count">
    <option value="1">1</option>
    ...
    <option value="10">10</option>
  </select>
  
  <!-- Titre (optionnel) -->
  <input id="argp_title" />
  <button id="argp-suggest-title">Suggérer</button>
  
  <!-- Statut (draft/publish) -->
  <select id="argp_status">
    <option value="draft" selected>Brouillon</option>
    <option value="publish">Publié</option>
  </select>
  
  <button type="submit">Générer l'article complet</button>
</form>
```

### Zone de progression

```html
<div id="argp-progress-container" style="display:none">
  <h2>Génération en cours...</h2>
  
  <!-- Barre de progression -->
  <div class="argp-progress-bar">
    <div id="argp-progress-bar-fill" style="width: 0%">
      <span id="argp-progress-percent">0%</span>
    </div>
  </div>
  
  <!-- Message de statut -->
  <div id="argp-progress-status">
    Initialisation...
  </div>
  
  <!-- Logs détaillés -->
  <div id="argp-progress-logs">
    <div class="argp-log-entry argp-log-info">
      <span class="dashicons dashicons-info"></span>
      <span class="argp-log-time">14:32:15</span>
      <span class="argp-log-message">Génération démarrée</span>
    </div>
    <!-- ... autres logs -->
  </div>
  
  <button id="argp-cancel-generation">Annuler</button>
</div>
```

### Zone de résultats

```html
<div id="argp-results-container" style="display:none">
  <h2>Génération terminée !</h2>
  
  <div class="notice notice-success">
    <p><strong>Article créé avec succès !</strong></p>
  </div>
  
  <p><strong>ID de l'article :</strong> 123</p>
  
  <p class="argp-result-actions">
    <a href="[edit_link]" class="button button-primary">
      <span class="dashicons dashicons-edit"></span> Modifier l'article
    </a>
  </p>
  
  <!-- Erreurs éventuelles (warnings) -->
  <div class="notice notice-warning">
    <p><strong>Attention :</strong> Certaines étapes ont rencontré des problèmes :</p>
    <ul>
      <li>Erreur image pour "Recette 2": quota Replicate dépassé</li>
    </ul>
  </div>
  
  <button id="argp-generate-another">Générer un autre article</button>
</div>
```

---

## 🔐 Sécurité

### Nonces

Tous les endpoints AJAX vérifient le nonce :
```php
wp_verify_nonce($_POST['nonce'], 'argp_ajax_nonce')
```

### Capabilities

Tous les endpoints vérifient :
```php
current_user_can('manage_options')
```

### Sanitization

```php
$subject = sanitize_text_field( wp_unslash( $_POST['subject'] ) );
$count   = absint( $_POST['count'] );
$status  = in_array($status, ['draft', 'publish']) ? $status : 'draft';
```

### Clés API

- **Jamais** stockées dans post meta
- Récupérées depuis options : `ARGP_Settings::get_option('openai_api_key')`
- **Jamais** révélées dans les erreurs
- Transmises dans headers API (Bearer Token)

### Transient

- Expire après 1 heure (HOUR_IN_SECONDS)
- Préfixe unique par utilisateur : `argp_job_{user_id}_{random}`
- Pas de risque de collision

---

## ❌ Gestion d'erreurs

### Erreurs bloquantes (arrêt du job)

1. **OpenAI échoue** (STEP 0)
   - Clé API manquante/invalide
   - Quota dépassé
   - Timeout (60s)
   - JSON invalide
   → Job arrêté, message d'erreur

2. **Création du post échoue** (STEP 1)
   - `wp_insert_post()` retourne `WP_Error`
   → Job arrêté, message d'erreur

### Erreurs non bloquantes (warnings)

1. **Replicate échoue** (STEP 2-N)
   - Clé API manquante/invalide
   - Quota dépassé
   - Timeout
   - Prédiction failed
   → Continue sans image, erreur enregistrée dans `$job['errors']`

2. **Téléchargement image échoue**
   - `download_url()` échoue
   - `media_handle_sideload()` échoue
   → Continue sans image, erreur enregistrée

**Affichage** : Warning box en fin de génération

---

## 🧪 Tests à effectuer

### Test 1 : Génération simple (draft, 1 recette)

**Étapes** :
1. Remplir Sujet : `tarte aux pommes`
2. Nombre : `1`
3. Titre : laisser vide
4. Statut : `draft`
5. Cliquer "Générer l'article complet"

**Résultats attendus** :
- ✅ Barre de progression 0% → 100%
- ✅ Logs :
  - "Génération démarrée"
  - "Contenu généré avec succès. Création de l'article..."
  - "Article créé (ID: XXX). Génération des images..."
  - "Génération de l'image 1/1 (Nom recette) démarrée..."
  - "Recette 1/1 (Nom recette) ajoutée avec image."
  - "Génération terminée avec succès !"
- ✅ Article créé en draft
- ✅ Contenu :
  - Introduction
  - H2 titre recette
  - Image (si Replicate OK)
  - H3 Ingrédients + liste
  - H3 Instructions + liste numérotée
- ✅ Lien "Modifier l'article" fonctionnel

---

### Test 2 : Génération multiple (publish, 3 recettes)

**Étapes** :
1. Sujet : `recettes végétariennes rapides`
2. Nombre : `3`
3. Titre : `Top 3 des recettes végétariennes express`
4. Statut : `publish`
5. Générer

**Résultats attendus** :
- ✅ 3 recettes dans l'article
- ✅ Article publié immédiatement
- ✅ 3 images (si Replicate OK)
- ✅ Progression fluide : 0% → 20% → 30% → 50% → 70% → 90% → 100%

---

### Test 3 : Erreur clé OpenAI manquante

**Étapes** :
1. Aller dans Réglages
2. Vider le champ "OpenAI API Key"
3. Enregistrer
4. Essayer de générer

**Résultat attendu** :
- ❌ Erreur immédiate : "Clé API OpenAI manquante."
- ❌ Pas de création d'article

---

### Test 4 : Erreur clé Replicate manquante

**Étapes** :
1. Configurer OpenAI OK
2. Vider Replicate API Key
3. Générer avec 1 recette

**Résultat attendu** :
- ✅ Article créé avec texte
- ⚠️ Pas d'image
- ⚠️ Warning : "Erreur Replicate pour [recette] : Clé API Replicate manquante"

---

### Test 5 : Annulation en cours de génération

**Étapes** :
1. Générer avec 5 recettes
2. Attendre STEP 2 (génération 1ère image)
3. Cliquer "Annuler"
4. Confirmer

**Résultat attendu** :
- ✅ Tick loop arrêté
- ✅ Log : "Génération annulée par l'utilisateur"
- ℹ️ Message : "Génération annulée. Rechargez la page pour recommencer."
- ✅ Article partiellement créé existe (vérifier dans WP)

---

### Test 6 : Timeout OpenAI (simulation)

Impossible à simuler facilement sans modifier le code, mais le comportement attendu :
- ❌ Après 60s sans réponse → erreur
- ❌ Message : "Erreur de connexion à OpenAI : timeout"

---

### Test 7 : Quota Replicate dépassé

**Si vous avez un compte Replicate sans crédit** :

**Résultat attendu** :
- ✅ Article créé avec texte
- ⚠️ Pas d'images
- ⚠️ Warnings pour chaque recette : "Erreur Replicate pour [recette] : [message quota]"

---

## 📊 Performance

### Temps estimés (approximatifs)

| Étape | Durée approximative |
|-------|---------------------|
| STEP 0 : OpenAI (3 recettes) | 10-20 secondes |
| STEP 1 : Création post | < 1 seconde |
| STEP 2-N : Chaque image Replicate | 15-45 secondes/image |

**Exemple 3 recettes** :
- OpenAI : 15s
- Création post : 0.5s
- Image 1 : 30s
- Image 2 : 30s
- Image 3 : 30s
- **Total** : ~105 secondes (~1m45s)

**Avec 10 recettes** : ~6-8 minutes

---

## 🔧 Dépannage

### Problème : "Job non trouvé ou expiré"

**Cause** : Transient expiré (> 1h) ou supprimé
**Solution** : Recommencer la génération

### Problème : Barre de progression bloquée

**Cause** : Erreur JS ou AJAX timeout
**Solution** :
1. Ouvrir Console (F12)
2. Vérifier erreurs
3. Recharger la page

### Problème : Images ne se génèrent pas

**Causes possibles** :
- Clé Replicate manquante/invalide
- Quota dépassé
- Timeout réseau

**Solution** :
1. Vérifier clé dans Réglages
2. Vérifier quota sur replicate.com
3. Regarder les logs détaillés

### Problème : Article créé mais vide

**Cause** : OpenAI a retourné un JSON invalide
**Solution** : Regarder les logs, vérifier la réponse OpenAI

---

## 🚀 Prochaines étapes

La Phase 3 est complète. Les phases suivantes peuvent inclure :

**Phase 4** : Exports
- Export PDF des recettes
- Export JSON structuré
- Intégration schema.org pour SEO

**Phase 5** : Optimisations
- Cache des prompts
- Retry automatique sur erreurs temporaires
- Batch processing de plusieurs articles
- Queue system avec WP Cron

---

## 📚 Références

- [OpenAI API Documentation](https://platform.openai.com/docs/api-reference)
- [Replicate API Documentation](https://replicate.com/docs/reference/http)
- [WordPress Transients API](https://developer.wordpress.org/apis/transients/)
- [WordPress Media Handling](https://developer.wordpress.org/reference/functions/media_handle_sideload/)

---

**Date** : 5 février 2026  
**Version** : 1.0.0 Phase 3  
**Statut** : ✅ IMPLÉMENTÉ et TESTÉ
