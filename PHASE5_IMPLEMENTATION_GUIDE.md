# Phase 5 - Guide d'implémentation Sécurité & Performance

## 🎯 Objectif Phase 5

Durcir la sécurité et optimiser les performances pour rendre le plugin **production-ready** sur des hébergements "moyens".

---

## ✅ Checklist d'implémentation

### A) Sécurité (CRITIQUE)

#### 1. Capabilities renforcées
- [ ] Admin pages : `manage_options` partout
- [ ] AJAX endpoints : `manage_options` pour génération
- [ ] Exports : `edit_post` minimum (ou `manage_options` si restriction)
- [ ] Cohérence dans tout le code

#### 2. Nonces distincts par action
- [ ] `argp_diagnostics_nonce` pour diagnostics
- [ ] `argp_suggest_nonce` pour suggestions
- [ ] `argp_generate_nonce` pour génération (start/tick/cancel)
- [ ] `argp_export_nonce_{post_id}` pour exports (déjà OK)
- [ ] Vérification avec `check_ajax_referer()` ou `wp_verify_nonce()`

#### 3. Sanitation/Validation renforcée
- [ ] Nombre recettes : `max(1, min(10, absint($count)))`
- [ ] Statut : `in_array($status, ['draft', 'publish'], true) ? $status : 'draft'`
- [ ] Sujet : `sanitize_text_field()` + limite 200 caractères
- [ ] Titre : `sanitize_text_field()` + limite 200 caractères

#### 4. Chiffrement des clés API
- [ ] Fonction `encrypt_api_key($key)` avec `openssl_encrypt()`
- [ ] Fonction `decrypt_api_key($encrypted)` avec `openssl_decrypt()`
- [ ] Utiliser `AUTH_KEY` + `SECURE_AUTH_KEY` comme base
- [ ] Warning si openssl indisponible
- [ ] Ne jamais renvoyer clé dans AJAX

#### 5. Protection CSRF/XSS
- [ ] Échapper toutes sorties : `esc_html()`, `esc_attr()`
- [ ] Logs échappés côté JS : `escapeHtml()`
- [ ] `wp_kses_post()` pour contenu riche si nécessaire

#### 6. Protection SSRF
- [ ] Vérifier URL Replicate : `https` + domaine whitelist
- [ ] Refuser IP locales (127.0.0.1, 192.168.*, 10.*)
- [ ] Fonction `validate_image_url($url)`

#### 7. Rate Limiting
- [ ] Max 2 jobs actifs par user
- [ ] Cooldown 30s entre `start_generation`
- [ ] Transients : `argp_user_{user_id}_jobs` (array de job_ids)
- [ ] Transient : `argp_user_{user_id}_last_start` (timestamp)

---

### B) Performance & Fiabilité

#### 1. Optimisation ticks
- [ ] Travail court : ≤ 5-8s par tick
- [ ] 1 seule requête Replicate par tick
- [ ] Refresh TTL transient à chaque tick (30 min)
- [ ] Pas de boucle bloquante

#### 2. Système de reprise
- [ ] Endpoint `wp_ajax_argp_get_current_job`
- [ ] Au chargement page : vérifier job existant
- [ ] Si job trouvé : reprendre tick loop automatiquement
- [ ] UI : message "Reprise de la génération en cours..."

#### 3. Nettoyage automatique
- [ ] Cron quotidien : `argp_daily_cleanup`
- [ ] Nettoyer transients expirés (`argp_job_*`)
- [ ] Nettoyer fichiers temp (`/tmp/argp-*`)
- [ ] Hook : `wp_schedule_event()`

#### 4. Timeouts optimisés
- [ ] OpenAI : 30s (génération), 20s (suggestions)
- [ ] Replicate : 20s (start), 15s (check)
- [ ] Gestion `WP_Error` systématique

#### 5. Mode Debug
- [ ] Option "Activer logs" dans Réglages
- [ ] `error_log()` si activé
- [ ] Stockage événements dans job : `events[]`
- [ ] Affichage récap en fin de génération

---

### C) UX & Messages

#### 1. Progress bar précise
- [ ] OpenAI : 0% → 15%
- [ ] Création post : 15% → 20%
- [ ] Chaque recette : 20% + (index / total * 75%) → 95%
- [ ] Finalisation : 95% → 100%

#### 2. Messages d'erreur clairs
- [ ] Image échoue : "⚠️ Image non générée (quota) – Recette X"
- [ ] OpenAI échoue : "❌ Erreur OpenAI : [détail sans clé API]"
- [ ] Rate limit : "⏳ Veuillez patienter 30s avant de relancer"

#### 3. Bouton Annuler amélioré
- [ ] Delete transient job
- [ ] Clear user jobs counter
- [ ] Reset UI complètement
- [ ] Message : "Génération annulée. Vous pouvez recommencer."

#### 4. Accessibilité
- [ ] `aria-live="polite"` sur zone logs
- [ ] `aria-busy="true"` sur boutons en cours
- [ ] States disabled visuels clairs
- [ ] Focus management

---

## 🔐 Implémentation Sécurité Détaillée

### Chiffrement des clés API

**Fichier** : `class-argp-settings.php`

```php
/**
 * Chiffre une clé API
 */
private function encrypt_api_key( $key ) {
    if ( empty( $key ) || ! function_exists( 'openssl_encrypt' ) ) {
        return $key; // Fallback : stockage clair
    }
    
    $method = 'AES-256-CBC';
    $secret_key = substr( AUTH_KEY . SECURE_AUTH_KEY, 0, 32 );
    $iv = substr( NONCE_KEY, 0, 16 );
    
    $encrypted = openssl_encrypt( $key, $method, $secret_key, 0, $iv );
    
    if ( false === $encrypted ) {
        return $key; // Fallback
    }
    
    return base64_encode( $encrypted );
}

/**
 * Déchiffre une clé API
 */
private function decrypt_api_key( $encrypted ) {
    if ( empty( $encrypted ) || ! function_exists( 'openssl_decrypt' ) ) {
        return $encrypted; // Assume clair
    }
    
    // Vérifier si déjà déchiffré (commence par sk- ou r8_)
    if ( preg_match( '/^(sk-|r8_)/', $encrypted ) ) {
        return $encrypted;
    }
    
    $method = 'AES-256-CBC';
    $secret_key = substr( AUTH_KEY . SECURE_AUTH_KEY, 0, 32 );
    $iv = substr( NONCE_KEY, 0, 16 );
    
    $decrypted = openssl_decrypt( base64_decode( $encrypted ), $method, $secret_key, 0, $iv );
    
    if ( false === $decrypted ) {
        return $encrypted; // Fallback
    }
    
    return $decrypted;
}

/**
 * Sanitize avec chiffrement
 */
public function sanitize_settings( $input ) {
    $sanitized = array();
    
    if ( isset( $input['openai_api_key'] ) ) {
        $key = sanitize_text_field( $input['openai_api_key'] );
        $sanitized['openai_api_key'] = $this->encrypt_api_key( $key );
    }
    
    if ( isset( $input['replicate_api_key'] ) ) {
        $key = sanitize_text_field( $input['replicate_api_key'] );
        $sanitized['replicate_api_key'] = $this->encrypt_api_key( $key );
    }
    
    // ... autres champs
    
    return $sanitized;
}

/**
 * Récupère une clé déchiffrée
 */
public static function get_decrypted_key( $key_name ) {
    $options = get_option( 'argp_settings', array() );
    
    if ( ! isset( $options[ $key_name ] ) ) {
        return '';
    }
    
    $instance = self::get_instance();
    return $instance->decrypt_api_key( $options[ $key_name ] );
}
```

**Utilisation** :
```php
// Au lieu de :
$key = ARGP_Settings::get_option( 'openai_api_key', '' );

// Utiliser :
$key = ARGP_Settings::get_decrypted_key( 'openai_api_key' );
```

---

### Rate Limiting

**Fichier** : `class-argp-ajax.php`

```php
/**
 * Vérifie le rate limit
 */
private function check_rate_limit() {
    $user_id = get_current_user_id();
    
    // Vérifier cooldown (30s entre générations)
    $last_start = get_transient( 'argp_user_' . $user_id . '_last_start' );
    
    if ( false !== $last_start && ( time() - $last_start ) < 30 ) {
        $wait = 30 - ( time() - $last_start );
        wp_send_json_error(
            array(
                'message' => sprintf(
                    __( 'Veuillez patienter %d secondes avant de relancer une génération.', 'ai-recipe-generator-pro' ),
                    $wait
                ),
            )
        );
    }
    
    // Vérifier nombre de jobs actifs (max 2)
    $active_jobs = get_transient( 'argp_user_' . $user_id . '_jobs' );
    
    if ( false === $active_jobs ) {
        $active_jobs = array();
    }
    
    // Nettoyer les jobs expirés
    $active_jobs = array_filter( $active_jobs, function( $job_id ) {
        return false !== get_transient( $job_id );
    });
    
    if ( count( $active_jobs ) >= 2 ) {
        wp_send_json_error(
            array(
                'message' => __( 'Vous avez déjà 2 générations en cours. Veuillez attendre qu\'elles se terminent.', 'ai-recipe-generator-pro' ),
            )
        );
    }
    
    return true;
}

/**
 * Enregistre le démarrage d'un job
 */
private function register_job_start( $job_id ) {
    $user_id = get_current_user_id();
    
    // Enregistrer timestamp
    set_transient( 'argp_user_' . $user_id . '_last_start', time(), HOUR_IN_SECONDS );
    
    // Ajouter à la liste des jobs actifs
    $active_jobs = get_transient( 'argp_user_' . $user_id . '_jobs' );
    
    if ( false === $active_jobs ) {
        $active_jobs = array();
    }
    
    $active_jobs[] = $job_id;
    
    set_transient( 'argp_user_' . $user_id . '_jobs', $active_jobs, HOUR_IN_SECONDS );
}

/**
 * Désenregistre un job terminé
 */
private function unregister_job( $job_id ) {
    $user_id = get_current_user_id();
    
    $active_jobs = get_transient( 'argp_user_' . $user_id . '_jobs' );
    
    if ( false !== $active_jobs ) {
        $active_jobs = array_filter( $active_jobs, function( $id ) use ( $job_id ) {
            return $id !== $job_id;
        });
        
        set_transient( 'argp_user_' . $user_id . '_jobs', $active_jobs, HOUR_IN_SECONDS );
    }
}
```

---

### Protection SSRF

**Fichier** : `class-argp-ajax.php`

```php
/**
 * Valide une URL d'image Replicate
 */
private function validate_image_url( $url ) {
    // Vérifier que c'est une URL
    if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
        return false;
    }
    
    // Parser l'URL
    $parsed = wp_parse_url( $url );
    
    // Vérifier protocole HTTPS
    if ( ! isset( $parsed['scheme'] ) || 'https' !== $parsed['scheme'] ) {
        return false;
    }
    
    // Whitelist des domaines Replicate
    $allowed_hosts = array(
        'replicate.delivery',
        'replicate.com',
        'pbxt.replicate.delivery',
    );
    
    $host = isset( $parsed['host'] ) ? strtolower( $parsed['host'] ) : '';
    
    // Vérifier si le host est dans la whitelist ou sous-domaine
    $allowed = false;
    foreach ( $allowed_hosts as $allowed_host ) {
        if ( $host === $allowed_host || str_ends_with( $host, '.' . $allowed_host ) ) {
            $allowed = true;
            break;
        }
    }
    
    if ( ! $allowed ) {
        return false;
    }
    
    // Vérifier que ce n'est pas une IP locale
    $ip = gethostbyname( $host );
    
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
        return false;
    }
    
    return true;
}

/**
 * Sideload avec validation
 */
private function sideload_image( $image_url, $post_id, $description = '' ) {
    // Valider l'URL
    if ( ! $this->validate_image_url( $image_url ) ) {
        return new WP_Error( 'invalid_url', __( 'URL d\'image non autorisée.', 'ai-recipe-generator-pro' ) );
    }
    
    // ... reste du code existant
}
```

---

## ⚡ Implémentation Performance Détaillée

### Système de reprise

**Fichier** : `class-argp-ajax.php`

```php
/**
 * Handler : Récupère le job actuel de l'utilisateur
 */
public function handle_get_current_job() {
    $this->check_ajax_security();
    
    $user_id = get_current_user_id();
    
    // Récupérer la liste des jobs actifs
    $active_jobs = get_transient( 'argp_user_' . $user_id . '_jobs' );
    
    if ( false === $active_jobs || empty( $active_jobs ) ) {
        wp_send_json_success( array( 'has_job' => false ) );
    }
    
    // Prendre le premier job actif
    foreach ( $active_jobs as $job_id ) {
        $job = get_transient( $job_id );
        
        if ( false !== $job ) {
            wp_send_json_success(
                array(
                    'has_job'  => true,
                    'job_id'   => $job_id,
                    'step'     => $job['step'],
                    'subject'  => $job['subject'],
                    'count'    => $job['count'],
                )
            );
        }
    }
    
    wp_send_json_success( array( 'has_job' => false ) );
}
```

**Enregistrement** :
```php
add_action( 'wp_ajax_argp_get_current_job', array( $this, 'handle_get_current_job' ) );
```

**Fichier** : `admin.js`

```javascript
/**
 * Au chargement de la page, vérifier s'il y a un job en cours
 */
checkForExistingJob: function() {
    $.ajax({
        url: argpAdmin.ajaxUrl,
        type: 'POST',
        data: {
            action: 'argp_get_current_job',
            nonce: argpAdmin.nonce
        },
        success: function(response) {
            if (response.success && response.data.has_job) {
                // Il y a un job en cours
                ARGPAdmin.showNotice('info', 'Une génération est en cours. Reprise...');
                
                // Masquer le formulaire, afficher la progression
                $('#argp-generate-form').hide();
                $('#argp-progress-container').show();
                
                // Restaurer le job ID
                ARGPAdmin.currentJobId = response.data.job_id;
                
                // Démarrer le tick loop
                ARGPAdmin.startTickLoop();
            }
        }
    });
}
```

---

### Cron de nettoyage

**Fichier** : `ai-recipe-generator-pro.php`

```php
/**
 * Activation : programmer le cron
 */
public function activate() {
    // ... code existant ...
    
    // Programmer le cron de nettoyage quotidien
    if ( ! wp_next_scheduled( 'argp_daily_cleanup' ) ) {
        wp_schedule_event( time(), 'daily', 'argp_daily_cleanup' );
    }
}

/**
 * Désactivation : déprogrammer le cron
 */
public function deactivate() {
    // Supprimer le cron
    $timestamp = wp_next_scheduled( 'argp_daily_cleanup' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'argp_daily_cleanup' );
    }
    
    flush_rewrite_rules();
}

/**
 * Hook pour le cron de nettoyage
 */
private function init_hooks() {
    // ... hooks existants ...
    
    // Cron de nettoyage
    add_action( 'argp_daily_cleanup', array( $this, 'daily_cleanup' ) );
}

/**
 * Nettoyage quotidien
 */
public function daily_cleanup() {
    global $wpdb;
    
    // Nettoyer les transients expirés argp_job_*
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE %s 
            AND option_name LIKE %s",
            '%_transient_argp_job_%',
            '%_transient_timeout_argp_job_%'
        )
    );
    
    // Nettoyer les transients utilisateurs expirés
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE %s 
            AND option_name LIKE %s",
            '%_transient_argp_user_%',
            '%_transient_timeout_argp_user_%'
        )
    );
    
    // Nettoyer les fichiers temporaires
    $temp_dir = get_temp_dir();
    $files = glob( $temp_dir . 'argp-*' );
    
    if ( is_array( $files ) ) {
        foreach ( $files as $file ) {
            // Supprimer si > 24h
            if ( is_file( $file ) && ( time() - filemtime( $file ) ) > DAY_IN_SECONDS ) {
                @unlink( $file );
            }
        }
    }
}
```

---

## 📊 Améliorations UX

### Progress bar précise

```php
// Dans execute_job_step()
$step = $job['step'];
$total_recipes = isset( $job['openai_json']['recipes'] ) ? count( $job['openai_json']['recipes'] ) : $job['count'];

if ( 0 === $step ) {
    $progress = 15; // OpenAI terminé
}
elseif ( 1 === $step ) {
    $progress = 20; // Post créé
}
else {
    $recipe_index = $step - 2;
    if ( $recipe_index < $total_recipes ) {
        $progress = 20 + ( ( $recipe_index + 1 ) / $total_recipes ) * 75;
    } else {
        $progress = 95; // Finalisation
    }
}

$progress = min( 100, max( 0, $progress ) );
```

---

## 🧪 Tests de validation

### Test 1 : Chiffrement des clés

1. Sauvegarder une clé OpenAI
2. Vérifier en BDD : valeur chiffrée (base64)
3. Récupérer avec `get_decrypted_key()` : valeur déchiffrée
4. Utiliser dans appel API : doit fonctionner

### Test 2 : Rate limiting

1. Lancer 1ère génération
2. Immédiatement relancer 2ème : AUTORISER
3. Immédiatement relancer 3ème : **REFUSER** (max 2 jobs)
4. Attendre 30s : relancer : AUTORISER

### Test 3 : Reprise de job

1. Lancer génération 3 recettes
2. Rafraîchir la page à mi-parcours
3. **Résultat attendu** : Reprise automatique du job

### Test 4 : Protection SSRF

1. Modifier code temporairement pour tester URL locale
2. Essayer `http://127.0.0.1/image.jpg` : **REFUSER**
3. Essayer `https://replicate.delivery/xxx.jpg` : **ACCEPTER**

### Test 5 : Cron cleanup

1. Créer plusieurs jobs
2. Les laisser expirer (30 min)
3. Déclencher manuellement : `do_action('argp_daily_cleanup')`
4. Vérifier : transients supprimés

---

## 📝 TODO pour après Phase 5

- [ ] Support multi-langue complet (WPML/Polylang)
- [ ] Export PDF avec TCPDF
- [ ] Intégration schema.org pour SEO
- [ ] Dashboard analytics (coûts API, compteurs)
- [ ] Batch processing de plusieurs articles
- [ ] Queue system avec WP Cron
- [ ] Support blocs Gutenberg natifs
- [ ] Intégration avec services tiers (Zapier, etc.)

---

## ⚠️ Warnings importants

### Chiffrement

Si `openssl` n'est pas disponible :
- Les clés sont stockées en clair
- Un warning s'affiche dans les Réglages
- Recommandation : activer openssl sur le serveur

### Performance

Sur hébergements limités :
- Augmenter `max_execution_time` à 60s minimum
- Augmenter `memory_limit` à 128M minimum
- Vérifier que `wp_remote_*` fonctionne (pas de firewall bloquant)

### Rate Limiting

Les transients peuvent être purgés par certains plugins de cache :
- Risque : rate limiting contourné
- Solution : utiliser une table custom au lieu de transients (TODO Phase 6)

---

**Statut** : 📋 GUIDE D'IMPLÉMENTATION
**Date** : 5 février 2026
**Version** : 1.0.0 Phase 5 Planning
