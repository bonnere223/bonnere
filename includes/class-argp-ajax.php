<?php
/**
 * Gestion des requêtes AJAX
 *
 * @package AI_Recipe_Generator_Pro
 */

// Si ce fichier est appelé directement, quitter.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe ARGP_Ajax
 * Gère tous les handlers AJAX du plugin
 */
class ARGP_Ajax {

	/**
	 * Instance unique (singleton)
	 *
	 * @var ARGP_Ajax
	 */
	private static $instance = null;

	/**
	 * Modèle Replicate à utiliser (Flux 2 Pro)
	 * TODO: Mettre à jour si nécessaire selon la documentation Replicate
	 *
	 * @var string
	 */
	const REPLICATE_MODEL = 'black-forest-labs/flux-pro';

	/**
	 * Récupère l'instance unique
	 *
	 * @return ARGP_Ajax
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructeur
	 */
	private function __construct() {
		// Hook pour les diagnostics système
		add_action( 'wp_ajax_argp_run_diagnostics', array( $this, 'handle_run_diagnostics' ) );

		// Hook pour les suggestions de titres
		add_action( 'wp_ajax_argp_suggest_titles', array( $this, 'handle_suggest_titles' ) );

		// PHASE 3: Hooks pour la génération complète
		add_action( 'wp_ajax_argp_start_generation', array( $this, 'handle_start_generation' ) );
		add_action( 'wp_ajax_argp_generation_tick', array( $this, 'handle_generation_tick' ) );
		add_action( 'wp_ajax_argp_cancel_generation', array( $this, 'handle_cancel_generation' ) );

		// PHASE 5: Hook pour récupérer job en cours
		add_action( 'wp_ajax_argp_get_current_job', array( $this, 'handle_get_current_job' ) );

		// UX PREMIUM: Hooks pour tests API et crédits
		add_action( 'wp_ajax_argp_test_api', array( $this, 'handle_test_api' ) );
		add_action( 'wp_ajax_argp_get_api_credits', array( $this, 'handle_get_api_credits' ) );
		add_action( 'wp_ajax_argp_new_theme_suggest', array( $this, 'handle_new_theme_suggest' ) );
		add_action( 'wp_ajax_argp_auto_suggest_title', array( $this, 'handle_auto_suggest_title' ) );
	}

	/**
	 * Vérifie le nonce et les permissions
	 *
	 * @return bool True si autorisé, die sinon.
	 */
	private function check_ajax_security() {
		// Vérifier le nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'argp_ajax_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Erreur de sécurité : nonce invalide.', 'ai-recipe-generator-pro' ),
				),
				403
			);
		}

		// Vérifier les permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Vous n\'avez pas les permissions nécessaires.', 'ai-recipe-generator-pro' ),
				),
				403
			);
		}

		return true;
	}

	/**
	 * Handler AJAX : Diagnostics système
	 */
	public function handle_run_diagnostics() {
		$this->check_ajax_security();

		$results = array();

		// Test 1 : allow_url_fopen
		$allow_url_fopen = ini_get( 'allow_url_fopen' );
		$results['allow_url_fopen'] = array(
			'label'  => __( 'allow_url_fopen', 'ai-recipe-generator-pro' ),
			'status' => $allow_url_fopen ? 'success' : 'error',
			'message' => $allow_url_fopen 
				? __( 'Activé', 'ai-recipe-generator-pro' )
				: __( 'Désactivé - certaines fonctionnalités peuvent ne pas fonctionner', 'ai-recipe-generator-pro' ),
		);

		// Test 2 : wp_remote_get vers une URL externe
		$test_url = 'https://www.google.com/robots.txt';
		$response = wp_remote_get(
			$test_url,
			array(
				'timeout' => 10,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			$results['wp_remote_get'] = array(
				'label'   => __( 'Connexion externe (wp_remote_get)', 'ai-recipe-generator-pro' ),
				'status'  => 'error',
				'message' => sprintf(
					/* translators: %s: Message d'erreur */
					__( 'Erreur : %s', 'ai-recipe-generator-pro' ),
					$response->get_error_message()
				),
				'details' => array(
					'url' => $test_url,
					'error' => $response->get_error_message(),
				),
			);
		} else {
			$http_code = wp_remote_retrieve_response_code( $response );
			$is_success = $http_code >= 200 && $http_code < 300;

			$results['wp_remote_get'] = array(
				'label'   => __( 'Connexion externe (wp_remote_get)', 'ai-recipe-generator-pro' ),
				'status'  => $is_success ? 'success' : 'warning',
				'message' => sprintf(
					/* translators: %d: Code HTTP */
					__( 'Code HTTP : %d', 'ai-recipe-generator-pro' ),
					$http_code
				),
				'details' => array(
					'url'       => $test_url,
					'http_code' => $http_code,
				),
			);
		}

		// Test 3 : PHP Version
		$php_version = phpversion();
		$php_required = '7.4';
		$php_ok = version_compare( $php_version, $php_required, '>=' );

		$results['php_version'] = array(
			'label'   => __( 'Version PHP', 'ai-recipe-generator-pro' ),
			'status'  => $php_ok ? 'success' : 'error',
			'message' => sprintf(
				/* translators: 1: Version actuelle, 2: Version requise */
				__( 'Version %1$s (requis : %2$s minimum)', 'ai-recipe-generator-pro' ),
				$php_version,
				$php_required
			),
		);

		// Test 4 : WordPress Version
		global $wp_version;
		$wp_required = '5.8';
		$wp_ok = version_compare( $wp_version, $wp_required, '>=' );

		$results['wp_version'] = array(
			'label'   => __( 'Version WordPress', 'ai-recipe-generator-pro' ),
			'status'  => $wp_ok ? 'success' : 'warning',
			'message' => sprintf(
				/* translators: 1: Version actuelle, 2: Version recommandée */
				__( 'Version %1$s (recommandé : %2$s minimum)', 'ai-recipe-generator-pro' ),
				$wp_version,
				$wp_required
			),
		);

		// Test 5 : Vérifier les clés API (sans les révéler)
		// PHASE 5: Utiliser clés déchiffrées
		$openai_key = ARGP_Settings::get_decrypted_key( 'openai_api_key' );
		$replicate_key = ARGP_Settings::get_decrypted_key( 'replicate_api_key' );

		$results['api_keys'] = array(
			'label'   => __( 'Clés API configurées', 'ai-recipe-generator-pro' ),
			'status'  => ( ! empty( $openai_key ) && ! empty( $replicate_key ) ) ? 'success' : 'warning',
			'message' => sprintf(
				/* translators: 1: État OpenAI, 2: État Replicate */
				__( 'OpenAI : %1$s | Replicate : %2$s', 'ai-recipe-generator-pro' ),
				! empty( $openai_key ) ? __( 'Configurée', 'ai-recipe-generator-pro' ) : __( 'Manquante', 'ai-recipe-generator-pro' ),
				! empty( $replicate_key ) ? __( 'Configurée', 'ai-recipe-generator-pro' ) : __( 'Manquante', 'ai-recipe-generator-pro' )
			),
		);

		// Envoyer la réponse
		wp_send_json_success(
			array(
				'results' => $results,
				'message' => __( 'Diagnostics terminés avec succès.', 'ai-recipe-generator-pro' ),
			)
		);
	}

	/**
	 * Handler AJAX : Suggérer des titres
	 */
	public function handle_suggest_titles() {
		$this->check_ajax_security();

		// Récupérer et valider le sujet (optionnel)
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';

		// Si pas de sujet, utiliser "recettes" par défaut
		if ( empty( $subject ) ) {
			$subject = 'recettes';
		}

		// PHASE 5: Récupérer la clé API OpenAI déchiffrée
		$openai_key = ARGP_Settings::get_decrypted_key( 'openai_api_key' );
		if ( empty( $openai_key ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'La clé API OpenAI n\'est pas configurée. Veuillez la renseigner dans les Réglages.', 'ai-recipe-generator-pro' ),
				)
			);
		}

		// Récupérer les titres manuels configurés
		$manual_titles_raw = ARGP_Settings::get_option( 'manual_titles', '' );
		$manual_titles = array_filter( array_map( 'trim', explode( "\n", $manual_titles_raw ) ) );

		// Récupérer les 15 derniers titres du blog
		$recent_titles = $this->get_recent_post_titles( 15 );

		// Appeler OpenAI pour générer les suggestions
		$result = $this->openai_suggest_titles( $subject, $recent_titles, $manual_titles );

		// Vérifier si l'appel a réussi
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
		}

		// Retourner les suggestions
		wp_send_json_success(
			array(
				'suggestions' => $result,
				'context'     => array(
					'manual_count' => count( $manual_titles ),
					'recent_count' => count( $recent_titles ),
					'subject'      => $subject,
				),
				'message'     => __( 'Suggestions générées avec succès par OpenAI.', 'ai-recipe-generator-pro' ),
			)
		);
	}

	/* ========================================
	   PHASE 3: GÉNÉRATION COMPLÈTE D'ARTICLES
	   ======================================== */

	/**
	 * Handler AJAX : Démarrer la génération
	 */
	public function handle_start_generation() {
		$this->check_ajax_security();

		// PHASE 5: Vérifier rate limiting
		$this->check_rate_limit();

		// Récupérer et valider les inputs
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$count   = isset( $_POST['count'] ) ? absint( $_POST['count'] ) : 1;
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$status  = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'draft';

		// PHASE 5: Validations renforcées
		$subject = substr( $subject, 0, 200 ); // Limite 200 caractères
		$title   = substr( $title, 0, 200 );
		$count   = max( 1, min( 40, $count ) ); // Clamp 1-40

		// Si titre rempli mais pas sujet, utiliser le titre comme sujet
		if ( empty( $subject ) && ! empty( $title ) ) {
			$subject = $title;
		}

		// Validation : au moins l'un des deux doit être rempli
		if ( empty( $subject ) && empty( $title ) ) {
			wp_send_json_error( array( 'message' => __( 'Veuillez renseigner au moins un titre ou un thème.', 'ai-recipe-generator-pro' ) ) );
		}

		// PHASE 5: Validation stricte du statut
		if ( ! in_array( $status, array( 'draft', 'publish' ), true ) ) {
			$status = 'draft';
		}

		// PHASE 5: Utiliser clé déchiffrée
		$openai_key = ARGP_Settings::get_decrypted_key( 'openai_api_key' );
		if ( empty( $openai_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Clé API OpenAI manquante.', 'ai-recipe-generator-pro' ) ) );
		}

		// Validation format
		if ( ! in_array( $format, array( 'global', 'tag' ), true ) ) {
			$format = 'tag';
		}

		// Créer le job
		$job_id = 'argp_job_' . get_current_user_id() . '_' . wp_generate_password( 12, false );
		$job_data = array(
			'step'                   => 0,
			'subject'                => $subject,
			'count'                  => $count,
			'title'                  => $title,
			'status'                 => $status,
			'openai_json'            => null,
			'created_post_id'        => null,
			'replicate_results'      => array(),
			'errors'                 => array(),
			'events'                 => array(),
			'started_at'             => time(),
			'last_replicate_call'    => 0,
			'replicate_retry_count'  => 0,
		);

		// PHASE 5: Sauvegarder avec TTL 30 min (au lieu de 1h)
		set_transient( $job_id, $job_data, 30 * MINUTE_IN_SECONDS );

		// PHASE 5: Enregistrer le démarrage (rate limiting)
		$this->register_job_start( $job_id );

		// PHASE 5: Log
		ARGP_Settings::log( "Job {$job_id} démarré - Sujet: {$subject}, Recettes: {$count}", 'info' );

		wp_send_json_success(
			array(
				'job_id'  => $job_id,
				'message' => __( 'Génération démarrée avec succès.', 'ai-recipe-generator-pro' ),
			)
		);
	}

	/**
	 * Handler AJAX : Avancer le job d'un tick
	 */
	public function handle_generation_tick() {
		$this->check_ajax_security();

		// Récupérer le job_id
		$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';

		if ( empty( $job_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Job ID manquant.', 'ai-recipe-generator-pro' ) ) );
		}

		// Récupérer le job
		$job = get_transient( $job_id );

		if ( false === $job ) {
			// PHASE 5: Unregister si expiré
			$this->unregister_job( $job_id );
			wp_send_json_error( array( 'message' => __( 'Job non trouvé ou expiré.', 'ai-recipe-generator-pro' ) ) );
		}

		// Exécuter l'étape actuelle
		$result = $this->execute_job_step( $job, $job_id );

		// PHASE 5: Refresh TTL à chaque tick (30 min)
		set_transient( $job_id, $job, 30 * MINUTE_IN_SECONDS );

		// Retourner le résultat
		wp_send_json_success( $result );
	}

	/**
	 * Handler AJAX : Annuler la génération
	 */
	public function handle_cancel_generation() {
		$this->check_ajax_security();

		$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';

		if ( ! empty( $job_id ) ) {
			// PHASE 5: Unregister avant delete
			$this->unregister_job( $job_id );
			delete_transient( $job_id );

			// PHASE 5: Log
			ARGP_Settings::log( "Job {$job_id} annulé par l'utilisateur", 'info' );
		}

		wp_send_json_success( array( 'message' => __( 'Génération annulée.', 'ai-recipe-generator-pro' ) ) );
	}

	/**
	 * Exécute une étape du job
	 *
	 * @param array  &$job    Données du job (passé par référence).
	 * @param string $job_id  ID du job.
	 * @return array Résultat de l'étape.
	 */
	private function execute_job_step( &$job, $job_id ) {
		$step = $job['step'];

		// STEP 0: Appel OpenAI pour générer le JSON
		if ( 0 === $step ) {
			return $this->job_step_generate_openai( $job );
		}

		// STEP 1: Créer le post WordPress
		if ( 1 === $step ) {
			return $this->job_step_create_post( $job );
		}

		// STEP 2+: Générer les images pour chaque recette
		$recipe_index = $step - 2;
		if ( isset( $job['openai_json']['recipes'][ $recipe_index ] ) ) {
			return $this->job_step_generate_image( $job, $recipe_index );
		}

		// STEP final: Finaliser
		return $this->job_step_finalize( $job, $job_id );
	}

	/**
	 * STEP 0: Appeler OpenAI pour générer le JSON des recettes
	 *
	 * @param array &$job Données du job.
	 * @return array Résultat de l'étape.
	 */
	private function job_step_generate_openai( &$job ) {
		// Estimer temps pour grandes générations
		$count = $job['count'];
		$estimated_time = 10 + ( $count * 3 ); // ~3s par recette
		
		// Message d'attente pour grandes générations
		if ( $count >= 10 && ! isset( $job['openai_started'] ) ) {
			$job['openai_started'] = time();
			return array(
				'done'     => false,
				'progress' => 5,
				'message'  => sprintf(
					__( '⏳ Génération du texte pour %d recettes (temps estimé : ~%d secondes)...', 'ai-recipe-generator-pro' ),
					$count,
					$estimated_time
				),
			);
		}

		$result = $this->openai_generate_recipes( $job['subject'], $job['count'] );

		if ( is_wp_error( $result ) ) {
			$job['errors'][] = $result->get_error_message();
			return array(
				'done'     => true,
				'progress' => 100,
				'message'  => __( 'Erreur lors de la génération avec OpenAI.', 'ai-recipe-generator-pro' ),
				'error'    => $result->get_error_message(),
			);
		}

		$job['openai_json'] = $result;
		$job['step'] = 1;

		$generation_time = isset( $job['openai_started'] ) ? ( time() - $job['openai_started'] ) : 0;
		
		return array(
			'done'     => false,
			'progress' => 20,
			'message'  => sprintf(
				__( 'Contenu généré avec succès en %ds. Création de l\'article...', 'ai-recipe-generator-pro' ),
				$generation_time
			),
		);
	}

	/**
	 * STEP 1: Créer le post WordPress (MODE GLOBAL uniquement)
	 *
	 * @param array &$job Données du job.
	 * @return array Résultat de l'étape.
	 */
	private function job_step_create_post( &$job ) {
		return $this->create_single_global_post( $job );
	}

	/**
	 * Mode GLOBAL : 1 article avec toutes les recettes
	 */
	private function create_single_global_post( &$job ) {
		// Vérifier si l'article n'a pas déjà été créé
		if ( ! empty( $job['created_post_id'] ) ) {
			// Article déjà créé, passer au step 2
			$job['step'] = 2;
			
			ARGP_Settings::log( "Article déjà créé (ID: {$job['created_post_id']}), skip création", 'info' );
			
			return array(
				'done'     => false,
				'progress' => 30,
				'message'  => sprintf(
					__( 'Article existant (ID: %d). Génération des images...', 'ai-recipe-generator-pro' ),
					$job['created_post_id']
				),
				'post_id'  => $job['created_post_id'],
			);
		}

		$openai_data = $job['openai_json'];
		$title = ! empty( $job['title'] ) ? $job['title'] : $job['subject'];

		$content = '<p>' . wp_kses_post( $openai_data['intro'] ) . '</p>';

		$post_id = wp_insert_post(
			array(
				'post_title'   => sanitize_text_field( $title ),
				'post_content' => $content,
				'post_status'  => $job['status'],
				'post_type'    => 'post',
				'post_author'  => get_current_user_id(),
			)
		);

		if ( is_wp_error( $post_id ) ) {
			$job['errors'][] = $post_id->get_error_message();
			return array(
				'done'     => true,
				'progress' => 100,
				'message'  => __( 'Erreur lors de la création de l\'article.', 'ai-recipe-generator-pro' ),
				'error'    => $post_id->get_error_message(),
			);
		}

		$job['created_post_id'] = $post_id;
		$job['step'] = 2;

		ARGP_Settings::log( "Article créé (ID: {$post_id}, Titre: {$title})", 'info' );

		return array(
			'done'     => false,
			'progress' => 30,
			'message'  => sprintf(
				__( 'Article créé (ID: %d). Génération des images...', 'ai-recipe-generator-pro' ),
				$post_id
			),
			'post_id'  => $post_id,
		);
	}

	/**
	 * STEP 2+: Générer l'image pour une recette
	 * BUGFIX: Séquençage Replicate avec délai anti-throttling
	 *
	 * @param array &$job          Données du job.
	 * @param int   $recipe_index  Index de la recette.
	 * @return array Résultat de l'étape.
	 */
	private function job_step_generate_image( &$job, $recipe_index ) {
		$recipe = $job['openai_json']['recipes'][ $recipe_index ];
		$total_recipes = count( $job['openai_json']['recipes'] );

		// BUGFIX: Vérifier délai minimal entre appels Replicate (12 secondes)
		$min_delay = 12;
		$last_call = isset( $job['last_replicate_call'] ) ? $job['last_replicate_call'] : 0;
		$time_since_last = time() - $last_call;

		// Vérifier si on a déjà une prédiction en cours
		if ( isset( $job['replicate_results'][ $recipe_index ]['prediction_id'] ) ) {
			$prediction_id = $job['replicate_results'][ $recipe_index ]['prediction_id'];
			$result = $this->replicate_check_prediction( $prediction_id );

			// BUGFIX: Gestion intelligente du throttling
			if ( is_wp_error( $result ) && $result->get_error_code() === 'replicate_throttled' ) {
				$retry_after = $result->get_error_data( 'replicate_throttled' );
				$retry_after = is_numeric( $retry_after ) ? $retry_after : 15;

				// Incrémenter compteur retry
				if ( ! isset( $job['replicate_results'][ $recipe_index ]['retry_count'] ) ) {
					$job['replicate_results'][ $recipe_index ]['retry_count'] = 0;
				}
				$job['replicate_results'][ $recipe_index ]['retry_count']++;

				// Max 3 retries
				if ( $job['replicate_results'][ $recipe_index ]['retry_count'] > 3 ) {
					$job['errors'][] = sprintf(
						__( 'Image non générée pour "%s" (limite API atteinte après 3 tentatives)', 'ai-recipe-generator-pro' ),
						$recipe['name']
					);
					$job['replicate_results'][ $recipe_index ] = array( 'status' => 'failed' );
					$this->append_recipe_to_post( $job['created_post_id'], $recipe, null );
					$job['step']++;
					
					ARGP_Settings::log( "Replicate throttling - abandon après 3 retries pour recette {$recipe['name']}", 'warning' );
					
					return array(
						'done'     => false,
						'progress' => 30 + ( ( $recipe_index + 1 ) / $total_recipes ) * 60,
						'message'  => sprintf(
							__( 'Recette %1$d/%2$d (%3$s) ajoutée (sans image).', 'ai-recipe-generator-pro' ),
							$recipe_index + 1,
							$total_recipes,
							$recipe['name']
						),
					);
				}

				// BUGFIX: Message utilisateur clair (pas technique)
				return array(
					'done'     => false,
					'progress' => 30 + ( $recipe_index / $total_recipes ) * 60,
					'message'  => sprintf(
						__( '⏳ L\'API d\'images est momentanément ralentie. Nouvelle tentative dans %ds... (%d/%d)', 'ai-recipe-generator-pro' ),
						$retry_after,
						$recipe_index + 1,
						$total_recipes
					),
				);
			}

			if ( is_wp_error( $result ) ) {
				// Autre erreur : on continue sans image
				$error_msg = $this->get_user_friendly_error_message( $result );
				$job['errors'][] = sprintf(
					__( 'Image non générée pour "%s": %s', 'ai-recipe-generator-pro' ),
					$recipe['name'],
					$error_msg
				);
				$job['replicate_results'][ $recipe_index ] = array( 'status' => 'failed' );
				$post_id_target = isset( $job['created_post_ids'] ) ? $job['created_post_ids'] : $job['created_post_id'];
				$format_mode = isset( $job['format'] ) ? $job['format'] : 'global';
				$this->append_recipe_to_post( $post_id_target, $recipe, null, $recipe_index, $format_mode );
				$job['step']++;
				
				ARGP_Settings::log( "Erreur Replicate pour {$recipe['name']}: " . $result->get_error_message(), 'error' );
				
				$progress = 30 + ( ( $recipe_index + 1 ) / $total_recipes ) * 60;
				return array(
					'done'     => false,
					'progress' => min( 90, $progress ),
					'message'  => sprintf(
						__( 'Recette %1$d/%2$d (%3$s) ajoutée (sans image).', 'ai-recipe-generator-pro' ),
						$recipe_index + 1,
						$total_recipes,
						$recipe['name']
					),
				);
			}

			if ( 'pending' === $result['status'] || 'processing' === $result['status'] || 'starting' === $result['status'] ) {
				// Toujours en cours
				return array(
					'done'     => false,
					'progress' => 30 + ( $recipe_index / $total_recipes ) * 60,
					'message'  => sprintf(
						__( 'Génération de l\'image %1$d/%2$d (%3$s)...', 'ai-recipe-generator-pro' ),
						$recipe_index + 1,
						$total_recipes,
						$recipe['name']
					),
				);
			}

			if ( 'succeeded' === $result['status'] && ! empty( $result['output'] ) ) {
				// Image prête, télécharger et ajouter au post
				$image_url = is_array( $result['output'] ) ? $result['output'][0] : $result['output'];
				$attachment_id = $this->sideload_image( $image_url, $job['created_post_id'], $recipe['name'] );

				if ( is_wp_error( $attachment_id ) ) {
					$job['errors'][] = sprintf(
						__( 'Erreur téléchargement image pour "%s"', 'ai-recipe-generator-pro' ),
						$recipe['name']
					);
					$attachment_id = null;
				}

				$job['replicate_results'][ $recipe_index ] = array(
					'status'        => 'succeeded',
					'attachment_id' => $attachment_id,
				);

				$this->append_recipe_to_post( $job['created_post_id'], $recipe, $attachment_id );
				$job['step']++;
				$progress = 30 + ( ( $recipe_index + 1 ) / $total_recipes ) * 60;
				
				ARGP_Settings::log( "Image générée avec succès pour {$recipe['name']}", 'info' );
				
				return array(
					'done'     => false,
					'progress' => min( 90, $progress ),
					'message'  => sprintf(
						__( 'Recette %1$d/%2$d (%3$s) ajoutée avec image.', 'ai-recipe-generator-pro' ),
						$recipe_index + 1,
						$total_recipes,
						$recipe['name']
					),
				);
			}
		}

		// BUGFIX: Vérifier délai avant nouvel appel Replicate
		if ( $last_call > 0 && $time_since_last < $min_delay ) {
			$wait_remaining = $min_delay - $time_since_last;
			
			return array(
				'done'     => false,
				'progress' => 30 + ( $recipe_index / $total_recipes ) * 60,
				'message'  => sprintf(
					__( '⏳ Séquençage API images (%ds)... Image %d/%d à venir', 'ai-recipe-generator-pro' ),
					$wait_remaining,
					$recipe_index + 1,
					$total_recipes
				),
			);
		}

		// Démarrer une nouvelle prédiction Replicate
		$prediction_result = $this->replicate_start_prediction( $recipe['image_prompt'] );

		// BUGFIX: Mettre à jour timestamp dernier appel
		$job['last_replicate_call'] = time();

		if ( is_wp_error( $prediction_result ) ) {
			// BUGFIX: Gestion spécifique throttling
			if ( $prediction_result->get_error_code() === 'replicate_throttled' ) {
				$retry_after = $prediction_result->get_error_data( 'replicate_throttled' );
				$retry_after = is_numeric( $retry_after ) ? $retry_after : 15;
				
				ARGP_Settings::log( "Replicate throttled au start - retry dans {$retry_after}s", 'warning' );
				
				return array(
					'done'     => false,
					'progress' => 30 + ( $recipe_index / $total_recipes ) * 60,
					'message'  => sprintf(
						__( '⏳ L\'API d\'images est momentanément ralentie. Reprise automatique dans %ds...', 'ai-recipe-generator-pro' ),
						$retry_after
					),
				);
			}

			// Autre erreur : on continue sans image
			$error_msg = $this->get_user_friendly_error_message( $prediction_result );
			$job['errors'][] = sprintf(
				__( 'Image non générée pour "%s": %s', 'ai-recipe-generator-pro' ),
				$recipe['name'],
				$error_msg
			);
			$job['replicate_results'][ $recipe_index ] = array( 'status' => 'failed' );
			$this->append_recipe_to_post( $job['created_post_id'], $recipe, null );
			$job['step']++;
			
			ARGP_Settings::log( "Erreur Replicate start pour {$recipe['name']}: " . $prediction_result->get_error_message(), 'error' );
			
			$progress = 30 + ( ( $recipe_index + 1 ) / $total_recipes ) * 60;
			return array(
				'done'     => false,
				'progress' => min( 90, $progress ),
				'message'  => sprintf(
					__( 'Recette %1$d/%2$d (%3$s) ajoutée (sans image).', 'ai-recipe-generator-pro' ),
					$recipe_index + 1,
					$total_recipes,
					$recipe['name']
				),
			);
		}

		// Sauvegarder l'ID de prédiction
		$job['replicate_results'][ $recipe_index ] = array(
			'prediction_id' => $prediction_result['id'],
			'status'        => 'pending',
			'retry_count'   => 0,
		);

		ARGP_Settings::log( "Prédiction Replicate démarrée pour {$recipe['name']}: {$prediction_result['id']}", 'info' );

		return array(
			'done'     => false,
			'progress' => 30 + ( $recipe_index / $total_recipes ) * 60,
			'message'  => sprintf(
				__( 'Génération de l\'image %1$d/%2$d (%3$s) démarrée...', 'ai-recipe-generator-pro' ),
				$recipe_index + 1,
				$total_recipes,
				$recipe['name']
			),
		);
	}

	/**
	 * BUGFIX: Convertit les erreurs techniques en messages utilisateur
	 *
	 * @param WP_Error $error Erreur technique.
	 * @return string Message utilisateur friendly.
	 */
	private function get_user_friendly_error_message( $error ) {
		$code = $error->get_error_code();
		$message = $error->get_error_message();

		// Messages utilisateur clairs
		$friendly_messages = array(
			'replicate_throttled' => __( 'API momentanément ralentie', 'ai-recipe-generator-pro' ),
			'replicate_error'     => __( 'Service temporairement indisponible', 'ai-recipe-generator-pro' ),
			'invalid_url'         => __( 'Image non accessible', 'ai-recipe-generator-pro' ),
			'openai_error'        => __( 'Service texte temporairement indisponible', 'ai-recipe-generator-pro' ),
		);

		// Si message friendly existe, l'utiliser
		if ( isset( $friendly_messages[ $code ] ) ) {
			return $friendly_messages[ $code ];
		}

		// Sinon, message générique (pas de détails techniques)
		return __( 'Erreur temporaire', 'ai-recipe-generator-pro' );
	}

	/**
	 * STEP final: Finaliser le job (MODE GLOBAL uniquement)
	 *
	 * @param array  &$job    Données du job.
	 * @param string $job_id  ID du job.
	 * @return array Résultat final.
	 */
	private function job_step_finalize( &$job, $job_id = '' ) {
		$post_id = $job['created_post_id'];
		$edit_link = get_edit_post_link( $post_id, 'raw' );

		// PHASE 5: Unregister job terminé
		if ( ! empty( $job_id ) ) {
			$this->unregister_job( $job_id );
			ARGP_Settings::log( "Job {$job_id} terminé - Post ID: {$post_id}", 'info' );
		}

		// Générer nonce pour exports
		$export_nonce = wp_create_nonce( 'argp_export_' . $post_id );

		return array(
			'done'         => true,
			'progress'     => 100,
			'message'      => __( 'Génération terminée avec succès !', 'ai-recipe-generator-pro' ),
			'post_id'      => $post_id,
			'edit_link'    => $edit_link,
			'export_nonce' => $export_nonce,
			'errors'       => $job['errors'],
		);
	}

	/* ========================================
	   OPENAI - GÉNÉRATION DE RECETTES
	   ======================================== */

	/**
	 * Génère des recettes complètes avec OpenAI
	 * UTILISE LE PROMPT PERSONNALISÉ depuis Réglages
	 *
	 * @param string $subject Sujet/Thème.
	 * @param int    $count   Nombre de recettes.
	 * @return array|WP_Error JSON structuré ou erreur.
	 */
	private function openai_generate_recipes( $subject, $count ) {
		// PHASE 5: Utiliser clé déchiffrée
		$api_key = ARGP_Settings::get_decrypted_key( 'openai_api_key' );

		// Récupérer le prompt personnalisé depuis Réglages
		$custom_prompt = ARGP_Settings::get_option( 'prompt_text', '' );

		// Construire le user prompt
		if ( ! empty( $custom_prompt ) ) {
			// Utiliser le prompt personnalisé
			$user_prompt = $custom_prompt;
			
			// Remplacer les variables
			$user_prompt = str_replace( '{titre}', $subject, $user_prompt );
			$user_prompt = str_replace( '{count}', $count, $user_prompt );
			$user_prompt = str_replace( '{nombre}', $count, $user_prompt );
			$user_prompt = str_replace( '{theme}', $subject, $user_prompt );
			$user_prompt = str_replace( '{item}', $subject, $user_prompt );
			
			// Insister sur le nombre ET le thème
			$user_prompt = "\n⚠️ CONTRAINTES STRICTES :\n";
			$user_prompt .= "- Génère EXACTEMENT {$count} recette(s), pas plus, pas moins.\n";
			$user_prompt .= "- TOUTES les recettes doivent être sur le thème : \"{$subject}\"\n";
			$user_prompt .= "- PAS de recettes hors sujet. Reste STRICTEMENT sur ce thème.\n";
			$user_prompt .= "- Si le thème est \"wraps\", UNIQUEMENT des wraps. Si \"gratins\", UNIQUEMENT des gratins.\n\n";
			$user_prompt .= $user_prompt;
			
			// Ajouter contrainte JSON APRÈS le prompt personnalisé
			$user_prompt .= "\n\n--- FORMAT DE SORTIE OBLIGATOIRE ---\n";
			$user_prompt .= "Réponds UNIQUEMENT en JSON avec cette structure exacte :\n";
			$user_prompt .= "{\n";
			$user_prompt .= "  \"intro\": \"texte introduction (2-3 phrases)\",\n";
			$user_prompt .= "  \"recipes\": [\n";
			$user_prompt .= "    {\n";
			$user_prompt .= "      \"name\": \"nom de la recette\",\n";
			$user_prompt .= "      \"ingredients\": [\"ingrédient 1\", \"ingrédient 2\", ...],\n";
			$user_prompt .= "      \"instructions\": [\"étape 1\", \"étape 2\", ...],\n";
			$user_prompt .= "      \"tips\": \"texte avec astuces, ingrédient à échanger, astuce cuisson (si demandé dans prompt)\",\n";
			$user_prompt .= "      \"image_prompt\": \"prompt image en anglais\"\n";
			$user_prompt .= "    }\n";
			$user_prompt .= "    // RÉPÉTER POUR LES {$count} RECETTES\n";
			$user_prompt .= "  ]\n";
			$user_prompt .= "}\n";
			$user_prompt .= "Le champ 'tips' doit contenir TOUT le contenu additionnel demandé dans le prompt (astuces, substitutions, etc.).\n";
			$user_prompt .= "PAS de texte avant ou après le JSON. UNIQUEMENT le JSON valide.";
			
			ARGP_Settings::log( "Utilisation prompt personnalisé pour {$count} recette(s)", 'info' );
		} else {
			// Prompt par défaut si aucun personnalisé
			$user_prompt = "⚠️ CONTRAINTES STRICTES :\n";
			$user_prompt .= "- Génère EXACTEMENT {$count} recette(s), pas plus, pas moins.\n";
			$user_prompt .= "- TOUTES les recettes doivent être UNIQUEMENT sur le thème : \"{$subject}\"\n";
			$user_prompt .= "- PAS de recettes hors sujet. STRICTEMENT ce thème.\n\n";
			
			$user_prompt .= "Génère un article de blog complet sur le thème : \"{$subject}\".\n\n";
			$user_prompt .= "L'article doit contenir :\n";
			$user_prompt .= "- Une introduction engageante (2-3 phrases)\n";
			$user_prompt .= "- Exactement {$count} recette(s) détaillée(s) sur le thème \"{$subject}\"\n\n";
			$user_prompt .= "Pour chaque recette, fournis :\n";
			$user_prompt .= "- name : nom de la recette (court et accrocheur, DOIT mentionner \"{$subject}\")\n";
			$user_prompt .= "- ingredients : liste des ingrédients (array de strings)\n";
			$user_prompt .= "- instructions : étapes de préparation (array de strings, numérotées)\n";
			$user_prompt .= "- image_prompt : prompt pour générer une photo réaliste de la recette (en anglais, style 'professional food photography of {$subject}...')\n\n";
			$user_prompt .= "Format JSON attendu :\n";
			$user_prompt .= "{\n";
			$user_prompt .= "  \"intro\": \"Texte d'introduction...\",\n";
			$user_prompt .= "  \"recipes\": [\n";
			$user_prompt .= "    {\n";
			$user_prompt .= "      \"name\": \"Nom de la recette\",\n";
			$user_prompt .= "      \"ingredients\": [\"Ingrédient 1\", \"Ingrédient 2\"],\n";
			$user_prompt .= "      \"instructions\": [\"Étape 1\", \"Étape 2\"],\n";
			$user_prompt .= "      \"image_prompt\": \"professional food photography of {$subject}...\"\n";
			$user_prompt .= "    }\n";
			$user_prompt .= "  ]\n";
			$user_prompt .= "}\n\n";
			$user_prompt .= "RAPPEL : Les {$count} recettes doivent TOUTES être des \"{$subject}\", rien d'autre.\n";
			$user_prompt .= "IMPORTANT : Réponds UNIQUEMENT avec le JSON, sans aucun texte avant ou après.";
		}

		$system_prompt = "Tu es un chef cuisinier et rédacteur culinaire professionnel. " .
			"Tu génères du contenu pour un blog de recettes grand public en français. " .
			"Tes recettes sont claires, gourmandes, réalisables, et optimisées SEO. " .
			"IMPORTANT : Toutes les recettes doivent être STRICTEMENT en rapport avec le thème demandé. " .
			"Si le thème est \"wraps\", tu ne proposes QUE des wraps. Si c'est \"gratins\", QUE des gratins. " .
			"Pas de recettes hors sujet. Reste TOUJOURS sur le thème principal. " .
			"Tu ne donnes jamais de conseils médicaux ou d'allégations santé non prouvées. " .
			"Tu réponds UNIQUEMENT en JSON valide sans markdown.";

		// Récupérer le modèle depuis réglages
		$model = ARGP_Settings::get_option( 'openai_model', 'gpt-4o' );

		// Calculer max_tokens dynamiquement selon nombre de recettes
		// ~400 tokens par recette (ingrédients + instructions + astuces)
		$estimated_tokens = 500 + ( $count * 400 );
		$max_tokens = min( 16000, max( 3000, $estimated_tokens ) );

		// Calculer timeout dynamiquement selon nombre de recettes
		// ~4-5s par recette pour générer (conservateur)
		$timeout = min( 240, max( 90, 40 + ( $count * 5 ) ) );

		ARGP_Settings::log( "Génération {$count} recettes - max_tokens: {$max_tokens}, timeout: {$timeout}s, modèle: {$model}", 'info' );

		$body = array(
			'model'       => $model,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
				),
				array(
					'role'    => 'user',
					'content' => $user_prompt,
				),
			),
			'temperature' => 0.7,
			'max_tokens'  => $max_tokens,
			'response_format' => array(
				'type' => 'json_object',
			),
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => $timeout,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'openai_error', $response->get_error_message() );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $http_code ) {
			$body_data = json_decode( wp_remote_retrieve_body( $response ), true );
			$error_msg = isset( $body_data['error']['message'] ) ? $body_data['error']['message'] : 'Erreur API OpenAI';
			return new WP_Error( 'openai_error', $error_msg );
		}

		$body_data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! isset( $body_data['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'openai_error', 'Réponse invalide' );
		}

		$content = $body_data['choices'][0]['message']['content'];
		$json_data = json_decode( $content, true );

		if ( null === $json_data || ! isset( $json_data['intro'] ) || ! isset( $json_data['recipes'] ) ) {
			ARGP_Settings::log( 'JSON invalide ou structure manquante', 'error' );
			return new WP_Error( 'openai_error', 'JSON invalide' );
		}

		$received_count = count( $json_data['recipes'] );
		
		// Log du nombre de recettes reçues
		ARGP_Settings::log( "OpenAI a généré {$received_count} recette(s), demandé : {$count}", 'info' );

		// Validation assouplie : accepter au moins 1 recette
		if ( $received_count < 1 ) {
			return new WP_Error( 'openai_error', 'Aucune recette générée' );
		}

		// Si moins de recettes que demandé, logger un warning mais continuer
		if ( $received_count < $count ) {
			ARGP_Settings::log( "Attention : seulement {$received_count}/{$count} recette(s) générée(s)", 'warning' );
		}

		return $json_data;
	}

	/* ========================================
	   REPLICATE - GÉNÉRATION D'IMAGES
	   ======================================== */

	/**
	 * Démarre une prédiction Replicate
	 *
	 * @param string $prompt Prompt pour l'image.
	 * @return array|WP_Error Données de prédiction ou erreur.
	 */
	private function replicate_start_prediction( $prompt ) {
		// PHASE 5: Utiliser clé déchiffrée
		$api_key = ARGP_Settings::get_decrypted_key( 'replicate_api_key' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'replicate_error', 'Clé API Replicate manquante' );
		}

		$body = array(
			'version' => self::REPLICATE_MODEL,
			'input'   => array(
				'prompt' => $prompt,
			),
		);

		$response = wp_remote_post(
			'https://api.replicate.com/v1/predictions',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Token ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		
		// BUGFIX: Gestion spécifique du throttling (429)
		if ( 429 === $http_code ) {
			$retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
			$retry_after = is_numeric( $retry_after ) ? (int) $retry_after : 15;
			
			ARGP_Settings::log( "Replicate throttled (429) - retry after: {$retry_after}s", 'warning' );
			
			return new WP_Error(
				'replicate_throttled',
				__( 'API ralentie (trop de requêtes)', 'ai-recipe-generator-pro' ),
				$retry_after
			);
		}
		
		if ( 201 !== $http_code && 200 !== $http_code ) {
			$body_data = json_decode( wp_remote_retrieve_body( $response ), true );
			$error_msg = isset( $body_data['detail'] ) ? $body_data['detail'] : 'Erreur Replicate';
			
			ARGP_Settings::log( "Erreur Replicate start (code {$http_code}): {$error_msg}", 'error' );
			
			return new WP_Error( 'replicate_error', $error_msg );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return $data;
	}

	/**
	 * Vérifie l'état d'une prédiction Replicate
	 *
	 * @param string $prediction_id ID de la prédiction.
	 * @return array|WP_Error État de la prédiction ou erreur.
	 */
	private function replicate_check_prediction( $prediction_id ) {
		// PHASE 5: Utiliser clé déchiffrée
		$api_key = ARGP_Settings::get_decrypted_key( 'replicate_api_key' );

		$response = wp_remote_get(
			'https://api.replicate.com/v1/predictions/' . $prediction_id,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Token ' . $api_key,
					'Content-Type'  => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		
		// BUGFIX: Gestion throttling sur check aussi
		if ( 429 === $http_code ) {
			$retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
			$retry_after = is_numeric( $retry_after ) ? (int) $retry_after : 15;
			
			ARGP_Settings::log( "Replicate throttled (429) sur check - retry after: {$retry_after}s", 'warning' );
			
			return new WP_Error(
				'replicate_throttled',
				__( 'API ralentie (trop de requêtes)', 'ai-recipe-generator-pro' ),
				$retry_after
			);
		}
		
		if ( 200 !== $http_code ) {
			ARGP_Settings::log( "Erreur Replicate check (code {$http_code})", 'error' );
			return new WP_Error( 'replicate_error', 'Erreur lors de la vérification' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		
		// BUGFIX: Gérer status "failed" de Replicate
		if ( isset( $data['status'] ) && 'failed' === $data['status'] ) {
			$error_detail = isset( $data['error'] ) ? $data['error'] : 'Génération échouée';
			ARGP_Settings::log( "Replicate prediction failed: {$error_detail}", 'error' );
			return new WP_Error( 'replicate_generation_failed', __( 'Génération d\'image échouée', 'ai-recipe-generator-pro' ) );
		}
		
		return $data;
	}

	/* ========================================
	   HELPERS - MEDIA LIBRARY
	   ======================================== */

	/**
	 * Télécharge une image et l'ajoute à la bibliothèque média
	 *
	 * @param string $image_url    URL de l'image.
	 * @param int    $post_id      ID du post parent.
	 * @param string $description  Description de l'image.
	 * @return int|WP_Error ID de l'attachment ou erreur.
	 */
	private function sideload_image( $image_url, $post_id, $description = '' ) {
		// PHASE 5: Validation SSRF
		if ( ! $this->validate_image_url( $image_url ) ) {
			return new WP_Error(
				'invalid_url',
				__( 'URL d\'image non autorisée pour des raisons de sécurité.', 'ai-recipe-generator-pro' )
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $image_url );

		if ( is_wp_error( $tmp ) ) {
			// PHASE 5: Log
			ARGP_Settings::log( "Erreur download_url: " . $tmp->get_error_message(), 'error' );
			return $tmp;
		}

		$file_array = array(
			'name'     => 'recipe-' . sanitize_title( $description ) . '-' . time() . '.jpg',
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, $post_id, $description );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $file_array['tmp_name'] );
			// PHASE 5: Log
			ARGP_Settings::log( "Erreur sideload: " . $attachment_id->get_error_message(), 'error' );
			return $attachment_id;
		}

		// PHASE 5: Log succès
		ARGP_Settings::log( "Image {$attachment_id} téléchargée avec succès pour post {$post_id}", 'info' );

		return $attachment_id;
	}

	/**
	 * Ajoute une recette au contenu du post (MODE GLOBAL uniquement)
	 *
	 * @param int        $post_id       ID du post.
	 * @param array      $recipe        Données de la recette.
	 * @param int|null   $attachment_id ID de l'image (optionnel).
	 */
	private function append_recipe_to_post( $post_id, $recipe, $attachment_id = null ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$content = $post->post_content;

		// Titre de la recette
		$content .= "\n\n" . '<h2>' . esc_html( $recipe['name'] ) . '</h2>';

		// Image si disponible
		if ( $attachment_id ) {
			$content .= "\n" . wp_get_attachment_image( $attachment_id, 'large', false, array( 'class' => 'recipe-image' ) );
		}

		// Ingrédients
		$content .= "\n\n" . '<h3>' . __( 'Ingrédients', 'ai-recipe-generator-pro' ) . '</h3>';
		$content .= "\n" . '<ul class="recipe-ingredients">';
		if ( isset( $recipe['ingredients'] ) && is_array( $recipe['ingredients'] ) ) {
			foreach ( $recipe['ingredients'] as $ingredient ) {
				$content .= "\n  " . '<li>' . esc_html( $ingredient ) . '</li>';
			}
		}
		$content .= "\n" . '</ul>';

		// Instructions
		$content .= "\n\n" . '<h3>' . __( 'Instructions', 'ai-recipe-generator-pro' ) . '</h3>';
		$content .= "\n" . '<ol class="recipe-instructions">';
		if ( isset( $recipe['instructions'] ) && is_array( $recipe['instructions'] ) ) {
			foreach ( $recipe['instructions'] as $instruction ) {
				$content .= "\n  " . '<li>' . esc_html( $instruction ) . '</li>';
			}
		}
		$content .= "\n" . '</ol>';

		// Contenu additionnel (astuces, etc.) si présent
		if ( isset( $recipe['tips'] ) && ! empty( $recipe['tips'] ) ) {
			$content .= "\n\n" . '<div class="recipe-tips" style="margin-top: 20px; padding: 16px; background: rgba(102, 126, 234, 0.1); border-left: 3px solid #667eea; border-radius: 8px;">';
			$content .= wpautop( wp_kses_post( $recipe['tips'] ) );
			$content .= '</div>';
		}

		// Mettre à jour le post
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $content,
			)
		);
	}

	/* ========================================
	   PHASE 2 - SUGGESTIONS DE TITRES
	   ======================================== */

	/**
	 * Récupère les N derniers titres d'articles publiés
	 *
	 * @param int $limit Nombre de titres à récupérer (défaut : 15).
	 * @return array Liste des titres.
	 */
	private function get_recent_post_titles( $limit = 15 ) {
		$recent_posts = get_posts(
			array(
				'numberposts' => $limit,
				'post_status' => 'publish',
				'post_type'   => 'post',
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		$titles = array();
		foreach ( $recent_posts as $post ) {
			$titles[] = $post->post_title;
		}

		return $titles;
	}

	/**
	 * Appelle OpenAI pour générer des suggestions de titres
	 *
	 * @param string $subject        Sujet/Thème fourni par l'utilisateur.
	 * @param array  $recent_titles  Liste des titres récents du blog.
	 * @param array  $manual_titles  Liste des titres manuels préférés.
	 * @return array|WP_Error Liste de 3 titres ou WP_Error en cas d'erreur.
	 */
	private function openai_suggest_titles( $subject, $recent_titles, $manual_titles ) {
		// PHASE 5: Utiliser clé déchiffrée
		$api_key = ARGP_Settings::get_decrypted_key( 'openai_api_key' );

		$system_prompt = "Tu es un rédacteur SEO spécialisé dans le domaine culinaire et les blogs food. " .
			"Tu génères des titres d'articles de blog attractifs, clairs et optimisés pour le référencement. " .
			"Tes titres sont courts (50-75 caractères maximum), accrocheurs mais honnêtes (pas de clickbait mensonger). " .
			"Tu respectes le style et le ton des articles existants du blog.";

		$user_prompt = "Je souhaite créer un article de blog sur le thème suivant : \"{$subject}\".\n\n";

		if ( ! empty( $recent_titles ) ) {
			$user_prompt .= "Voici les 15 derniers titres publiés sur mon blog (pour référence de style et éviter les doublons) :\n";
			foreach ( $recent_titles as $index => $title ) {
				$user_prompt .= ( $index + 1 ) . ". {$title}\n";
			}
			$user_prompt .= "\n";
		}

		if ( ! empty( $manual_titles ) ) {
			$user_prompt .= "Voici des titres que j'aime particulièrement (respecte ce style) :\n";
			foreach ( $manual_titles as $index => $title ) {
				$user_prompt .= "- {$title}\n";
			}
			$user_prompt .= "\n";
		}

		$user_prompt .= "Consignes :\n" .
			"- Propose exactement 3 titres différents et originaux\n" .
			"- Chaque titre doit faire entre 50 et 75 caractères maximum\n" .
			"- Les titres doivent être en français\n" .
			"- Évite de réutiliser ou de copier les titres existants\n" .
			"- Les titres doivent être pertinents pour le thème : \"{$subject}\"\n" .
			"- Réponds UNIQUEMENT avec un objet JSON contenant une clé 'titles' avec un tableau de 3 strings\n\n" .
			"Format attendu : {\"titles\": [\"Titre 1\", \"Titre 2\", \"Titre 3\"]}";

		$body = array(
			'model'       => 'gpt-4o',
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
				),
				array(
					'role'    => 'user',
					'content' => $user_prompt,
				),
			),
			'temperature' => 0.8,
			'max_tokens'  => 500,
			'response_format' => array(
				'type' => 'json_object',
			),
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'openai_network_error',
				sprintf(
					/* translators: %s: Message d'erreur */
					__( 'Erreur de connexion à OpenAI : %s', 'ai-recipe-generator-pro' ),
					$response->get_error_message()
				)
			);
		}

		$http_code = wp_remote_retrieve_response_code( $response );

		if ( $http_code !== 200 ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			
			$error_message = __( 'Erreur inconnue de l\'API OpenAI.', 'ai-recipe-generator-pro' );
			
			if ( isset( $data['error']['message'] ) ) {
				$error_message = $data['error']['message'];
			}

			if ( $http_code === 401 ) {
				$error_message = __( 'Clé API OpenAI invalide. Vérifiez votre configuration dans les Réglages.', 'ai-recipe-generator-pro' );
			} elseif ( $http_code === 429 ) {
				$error_message = __( 'Quota OpenAI dépassé. Vérifiez votre compte OpenAI ou réessayez plus tard.', 'ai-recipe-generator-pro' );
			} elseif ( $http_code === 500 || $http_code === 503 ) {
				$error_message = __( 'Les serveurs OpenAI sont temporairement indisponibles. Réessayez dans quelques instants.', 'ai-recipe-generator-pro' );
			}

			return new WP_Error(
				'openai_api_error',
				sprintf(
					/* translators: 1: Code HTTP, 2: Message d'erreur */
					__( 'Erreur API OpenAI (code %1$d) : %2$s', 'ai-recipe-generator-pro' ),
					$http_code,
					$error_message
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error(
				'openai_invalid_response',
				__( 'Réponse invalide de l\'API OpenAI : structure inattendue.', 'ai-recipe-generator-pro' )
			);
		}

		$content = $data['choices'][0]['message']['content'];
		$titles_data = json_decode( $content, true );

		if ( ! isset( $titles_data['titles'] ) || ! is_array( $titles_data['titles'] ) ) {
			$titles = $this->extract_titles_fallback( $content );
			
			if ( empty( $titles ) ) {
				return new WP_Error(
					'openai_parse_error',
					__( 'Impossible d\'extraire les titres de la réponse OpenAI.', 'ai-recipe-generator-pro' )
				);
			}
			
			return $titles;
		}

		$titles = array_slice( $titles_data['titles'], 0, 3 );

		if ( count( $titles ) < 3 ) {
			return new WP_Error(
				'openai_insufficient_titles',
				__( 'OpenAI n\'a pas retourné assez de suggestions (attendu : 3).', 'ai-recipe-generator-pro' )
			);
		}

		$titles = array_map( 'trim', $titles );
		$titles = array_map( array( $this, 'clean_title' ), $titles );

		return $titles;
	}

	/**
	 * Fallback : extrait les titres d'un texte si le JSON est invalide
	 *
	 * @param string $text Texte contenant potentiellement des titres.
	 * @return array Liste de titres extraits (max 3).
	 */
	private function extract_titles_fallback( $text ) {
		$titles = array();
		
		$lines = explode( "\n", $text );
		
		foreach ( $lines as $line ) {
			$line = trim( $line );
			
			if ( empty( $line ) || strlen( $line ) < 10 ) {
				continue;
			}
			
			$line = preg_replace( '/^[\d\-\.\)\]\*\s]+/', '', $line );
			$line = trim( $line, ' "\'' );
			
			if ( ! empty( $line ) && strlen( $line ) >= 10 ) {
				$titles[] = $line;
			}
			
			if ( count( $titles ) >= 3 ) {
				break;
			}
		}
		
		return $titles;
	}

	/**
	 * Nettoie un titre (supprime guillemets, espaces superflus)
	 *
	 * @param string $title Titre à nettoyer.
	 * @return string Titre nettoyé.
	 */
	private function clean_title( $title ) {
		$title = trim( $title, ' "\'' );
		$title = preg_replace( '/\s+/', ' ', $title );
		
		return $title;
	}

	/* ========================================
	   PHASE 5: RATE LIMITING
	   ======================================== */

	/**
	 * Vérifie le rate limit de l'utilisateur
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
						/* translators: %d: Nombre de secondes */
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
		$active_jobs = array_filter(
			$active_jobs,
			function( $job_id ) {
				return false !== get_transient( $job_id );
			}
		);

		if ( count( $active_jobs ) >= 2 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Vous avez déjà 2 générations en cours. Veuillez attendre qu\'elles se terminent ou les annuler.', 'ai-recipe-generator-pro' ),
				)
			);
		}

		return true;
	}

	/**
	 * Enregistre le démarrage d'un job
	 *
	 * @param string $job_id ID du job.
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
	 *
	 * @param string $job_id ID du job.
	 */
	private function unregister_job( $job_id ) {
		$user_id = get_current_user_id();

		$active_jobs = get_transient( 'argp_user_' . $user_id . '_jobs' );

		if ( false !== $active_jobs ) {
			$active_jobs = array_values(
				array_filter(
					$active_jobs,
					function( $id ) use ( $job_id ) {
						return $id !== $job_id;
					}
				)
			);

			set_transient( 'argp_user_' . $user_id . '_jobs', $active_jobs, HOUR_IN_SECONDS );
		}
	}

	/* ========================================
	   PHASE 5: REPRISE DE JOB
	   ======================================== */

	/**
	 * Handler AJAX : Récupère le job en cours de l'utilisateur
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
						'has_job' => true,
						'job_id'  => $job_id,
						'step'    => $job['step'],
						'subject' => $job['subject'],
						'count'   => $job['count'],
					)
				);
			}
		}

		wp_send_json_success( array( 'has_job' => false ) );
	}

	/* ========================================
	   PHASE 5: PROTECTION SSRF
	   ======================================== */

	/**
	 * Valide une URL d'image Replicate (protection SSRF)
	 *
	 * @param string $url URL à valider.
	 * @return bool True si URL valide et sûre.
	 */
	private function validate_image_url( $url ) {
		// Vérifier que c'est une URL valide
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		// Parser l'URL
		$parsed = wp_parse_url( $url );

		// Vérifier protocole HTTPS obligatoire
		if ( ! isset( $parsed['scheme'] ) || 'https' !== $parsed['scheme'] ) {
			return false;
		}

		// Whitelist des domaines Replicate autorisés
		$allowed_hosts = array(
			'replicate.delivery',
			'replicate.com',
			'pbxt.replicate.delivery',
			'cdn.replicate.com',
		);

		$host = isset( $parsed['host'] ) ? strtolower( $parsed['host'] ) : '';

		if ( empty( $host ) ) {
			return false;
		}

		// Vérifier si le host est dans la whitelist ou sous-domaine
		$allowed = false;
		foreach ( $allowed_hosts as $allowed_host ) {
			if ( $host === $allowed_host || str_ends_with( $host, '.' . $allowed_host ) ) {
				$allowed = true;
				break;
			}
		}

		if ( ! $allowed ) {
			ARGP_Settings::log( "URL refusée (domaine non autorisé): {$url}", 'warning' );
			return false;
		}

		// Vérifier que ce n'est pas une IP locale/privée
		$ip = gethostbyname( $host );

		// Si l'IP est identique au host, c'est déjà une IP
		if ( $ip !== $host && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
				ARGP_Settings::log( "URL refusée (IP privée/réservée): {$url}", 'warning' );
				return false;
			}
		}

		return true;
	}

	/* ========================================
	   UX PREMIUM: TEST API & CRÉDITS
	   ======================================== */

	/**
	 * Handler AJAX : Tester une API
	 */
	public function handle_test_api() {
		$this->check_ajax_security();

		$api_name = isset( $_POST['api'] ) ? sanitize_text_field( wp_unslash( $_POST['api'] ) ) : '';

		if ( 'openai' === $api_name ) {
			$result = $this->test_openai_api();
		} elseif ( 'replicate' === $api_name ) {
			$result = $this->test_replicate_api();
		} else {
			wp_send_json_error( array( 'message' => 'API non reconnue' ) );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Teste l'API OpenAI
	 */
	private function test_openai_api() {
		$api_key = ARGP_Settings::get_decrypted_key( 'openai_api_key' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_key', __( 'Aucune clé API configurée', 'ai-recipe-generator-pro' ) );
		}

		$response = wp_remote_get(
			'https://api.openai.com/v1/models',
			array(
				'timeout' => 10,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $code ) {
			return array(
				'status'  => 'success',
				'message' => __( '✅ API fonctionnelle', 'ai-recipe-generator-pro' ),
			);
		} elseif ( 401 === $code ) {
			return new WP_Error( 'invalid_key', __( '❌ Clé API invalide', 'ai-recipe-generator-pro' ) );
		} else {
			return new WP_Error( 'api_error', __( '⚠️ API inaccessible ou timeout', 'ai-recipe-generator-pro' ) );
		}
	}

	/**
	 * Teste l'API Replicate
	 */
	private function test_replicate_api() {
		$api_key = ARGP_Settings::get_decrypted_key( 'replicate_api_key' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_key', __( 'Aucune clé API configurée', 'ai-recipe-generator-pro' ) );
		}

		$response = wp_remote_get(
			'https://api.replicate.com/v1/predictions',
			array(
				'timeout' => 10,
				'headers' => array(
					'Authorization' => 'Token ' . $api_key,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $code ) {
			return array(
				'status'  => 'success',
				'message' => __( '✅ API fonctionnelle', 'ai-recipe-generator-pro' ),
			);
		} elseif ( 401 === $code ) {
			return new WP_Error( 'invalid_key', __( '❌ Clé API invalide', 'ai-recipe-generator-pro' ) );
		} else {
			return new WP_Error( 'api_error', __( '⚠️ API inaccessible ou timeout', 'ai-recipe-generator-pro' ) );
		}
	}

	/**
	 * Handler AJAX : Récupérer les crédits API
	 */
	public function handle_get_api_credits() {
		$this->check_ajax_security();

		$api_name = isset( $_POST['api'] ) ? sanitize_text_field( wp_unslash( $_POST['api'] ) ) : '';

		// Note: Les APIs ne fournissent pas toujours les crédits via API
		// C'est un placeholder pour future implémentation
		wp_send_json_success(
			array(
				'available' => false,
				'message'   => __( 'Vérification des crédits non disponible via API. Consultez votre tableau de bord OpenAI/Replicate.', 'ai-recipe-generator-pro' ),
			)
		);
	}

	/**
	 * Handler AJAX : Suggérer un nouveau thème inédit
	 */
	public function handle_new_theme_suggest() {
		$this->check_ajax_security();

		// Vérifier la clé API
		$openai_key = ARGP_Settings::get_decrypted_key( 'openai_api_key' );
		if ( empty( $openai_key ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Clé API OpenAI non configurée.', 'ai-recipe-generator-pro' ),
				)
			);
		}

		// Appeler OpenAI pour des thèmes inédits
		$result = $this->openai_new_theme_suggest();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
		}

		wp_send_json_success(
			array(
				'themes'  => $result,
				'message' => __( 'Nouveaux thèmes générés avec succès.', 'ai-recipe-generator-pro' ),
			)
		);
	}

	/**
	 * Génère des thèmes inédits avec OpenAI
	 */
	private function openai_new_theme_suggest() {
		$api_key = ARGP_Settings::get_decrypted_key( 'openai_api_key' );

		$system_prompt = "Tu es un expert en tendances culinaires et en contenu viral. " .
			"Tu proposes des thèmes d'articles de recettes innovants, originaux et tendance. " .
			"Tu ne te bases sur AUCUN historique, tu explores des niches peu exploitées, " .
			"des tendances émergentes, la saisonnalité, ou des angles uniques.";

		$user_prompt = "Propose 3 titres d'articles de recettes complètement inédits et tendance.\n\n" .
			"Critères :\n" .
			"- Titres originaux (pas de classiques vus partout)\n" .
			"- Tendances actuelles ou saisonnalité\n" .
			"- Niches peu exploitées\n" .
			"- Format : inclure le nombre de recettes dans le titre (ex: \"7 recettes...\")\n" .
			"- Entre 50 et 75 caractères\n" .
			"- En français\n\n" .
			"Réponds UNIQUEMENT avec un JSON : {\"themes\": [\"Titre 1\", \"Titre 2\", \"Titre 3\"]}";

		$body = array(
			'model'       => 'gpt-4o',
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
				),
				array(
					'role'    => 'user',
					'content' => $user_prompt,
				),
			),
			'temperature' => 0.9,
			'max_tokens'  => 500,
			'response_format' => array(
				'type' => 'json_object',
			),
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $http_code ) {
			return new WP_Error( 'openai_error', 'Erreur API OpenAI' );
		}

		$body_data = json_decode( wp_remote_retrieve_body( $response ), true );
		$content = $body_data['choices'][0]['message']['content'] ?? '';
		$themes_data = json_decode( $content, true );

		if ( isset( $themes_data['themes'] ) && is_array( $themes_data['themes'] ) ) {
			return array_slice( $themes_data['themes'], 0, 3 );
		}

		return new WP_Error( 'parse_error', 'Impossible de parser la réponse' );
	}

	/**
	 * Handler AJAX : Suggestion automatique au chargement
	 */
	public function handle_auto_suggest_title() {
		$this->check_ajax_security();

		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';

		if ( empty( $subject ) ) {
			$subject = 'recettes'; // Défaut si vide
		}

		// Récupérer 1 seule suggestion
		$manual_titles = array();
		$recent_titles = $this->get_recent_post_titles( 5 );

		$result = $this->openai_suggest_single_title( $subject, $recent_titles, $manual_titles );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'title' => $result ) );
	}

	/**
	 * Génère UNE suggestion de titre
	 */
	private function openai_suggest_single_title( $subject, $recent_titles, $manual_titles ) {
		$api_key = ARGP_Settings::get_decrypted_key( 'openai_api_key' );

		$system_prompt = "Tu es un rédacteur SEO food. Génère UN seul titre d'article accrocheur, " .
			"50-75 caractères, incluant un nombre de recettes (ex: '10 recettes...'). En français.";

		$user_prompt = "Thème : {$subject}\n\nGénère UN titre accrocheur avec nombre de recettes inclus.\n" .
			"Format JSON : {\"title\": \"Titre ici\"}";

		$body = array(
			'model'    => 'gpt-4o',
			'messages' => array(
				array( 'role' => 'system', 'content' => $system_prompt ),
				array( 'role' => 'user', 'content' => $user_prompt ),
			),
			'temperature' => 0.8,
			'max_tokens'  => 150,
			'response_format' => array( 'type' => 'json_object' ),
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body' => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return '5 recettes ' . $subject; // Fallback
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$content = $data['choices'][0]['message']['content'] ?? '';
		$json = json_decode( $content, true );

		return $json['title'] ?? '5 recettes ' . $subject;
	}
}
