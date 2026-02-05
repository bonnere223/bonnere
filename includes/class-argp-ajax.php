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
		$openai_key = ARGP_Settings::get_option( 'openai_api_key', '' );
		$replicate_key = ARGP_Settings::get_option( 'replicate_api_key', '' );

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

		// Récupérer et valider le sujet
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';

		// Vérifier qu'un sujet est fourni
		if ( empty( $subject ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Le champ Sujet/Thème est requis pour générer des suggestions.', 'ai-recipe-generator-pro' ),
				)
			);
		}

		// Récupérer la clé API OpenAI
		$openai_key = ARGP_Settings::get_option( 'openai_api_key', '' );
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

		// Récupérer et valider les inputs
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$count   = isset( $_POST['count'] ) ? absint( $_POST['count'] ) : 1;
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$status  = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'draft';

		// Validation
		if ( empty( $subject ) ) {
			wp_send_json_error( array( 'message' => __( 'Le sujet est requis.', 'ai-recipe-generator-pro' ) ) );
		}

		if ( $count < 1 || $count > 10 ) {
			wp_send_json_error( array( 'message' => __( 'Le nombre de recettes doit être entre 1 et 10.', 'ai-recipe-generator-pro' ) ) );
		}

		if ( ! in_array( $status, array( 'draft', 'publish' ), true ) ) {
			$status = 'draft';
		}

		// Vérifier les clés API
		$openai_key = ARGP_Settings::get_option( 'openai_api_key', '' );
		if ( empty( $openai_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Clé API OpenAI manquante.', 'ai-recipe-generator-pro' ) ) );
		}

		// Créer le job
		$job_id = 'argp_job_' . get_current_user_id() . '_' . wp_generate_password( 12, false );
		$job_data = array(
			'step'              => 0,
			'subject'           => $subject,
			'count'             => $count,
			'title'             => $title,
			'status'            => $status,
			'openai_json'       => null,
			'created_post_id'   => null,
			'replicate_results' => array(),
			'errors'            => array(),
			'started_at'        => time(),
		);

		// Sauvegarder le job dans un transient (expire après 1 heure)
		set_transient( $job_id, $job_data, HOUR_IN_SECONDS );

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
			wp_send_json_error( array( 'message' => __( 'Job non trouvé ou expiré.', 'ai-recipe-generator-pro' ) ) );
		}

		// Exécuter l'étape actuelle
		$result = $this->execute_job_step( $job, $job_id );

		// Mettre à jour le job
		set_transient( $job_id, $job, HOUR_IN_SECONDS );

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
			delete_transient( $job_id );
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
		return $this->job_step_finalize( $job );
	}

	/**
	 * STEP 0: Appeler OpenAI pour générer le JSON des recettes
	 *
	 * @param array &$job Données du job.
	 * @return array Résultat de l'étape.
	 */
	private function job_step_generate_openai( &$job ) {
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

		return array(
			'done'     => false,
			'progress' => 20,
			'message'  => __( 'Contenu généré avec succès. Création de l\'article...', 'ai-recipe-generator-pro' ),
		);
	}

	/**
	 * STEP 1: Créer le post WordPress
	 *
	 * @param array &$job Données du job.
	 * @return array Résultat de l'étape.
	 */
	private function job_step_create_post( &$job ) {
		$openai_data = $job['openai_json'];
		$title = ! empty( $job['title'] ) ? $job['title'] : $job['subject'];

		// Construire le contenu initial avec l'intro
		$content = '<p>' . wp_kses_post( $openai_data['intro'] ) . '</p>';

		// Créer le post
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

		return array(
			'done'     => false,
			'progress' => 30,
			'message'  => sprintf(
				/* translators: %d: ID de l'article */
				__( 'Article créé (ID: %d). Génération des images...', 'ai-recipe-generator-pro' ),
				$post_id
			),
			'post_id'  => $post_id,
		);
	}

	/**
	 * STEP 2+: Générer l'image pour une recette
	 *
	 * @param array &$job          Données du job.
	 * @param int   $recipe_index  Index de la recette.
	 * @return array Résultat de l'étape.
	 */
	private function job_step_generate_image( &$job, $recipe_index ) {
		$recipe = $job['openai_json']['recipes'][ $recipe_index ];
		$total_recipes = count( $job['openai_json']['recipes'] );

		// Vérifier si on a déjà une prédiction en cours
		if ( isset( $job['replicate_results'][ $recipe_index ]['prediction_id'] ) ) {
			$prediction_id = $job['replicate_results'][ $recipe_index ]['prediction_id'];
			$result = $this->replicate_check_prediction( $prediction_id );

			if ( is_wp_error( $result ) ) {
				// Erreur : on continue sans image
				$job['errors'][] = sprintf(
					/* translators: 1: Nom de la recette, 2: Message d'erreur */
					__( 'Erreur image pour "%1$s": %2$s', 'ai-recipe-generator-pro' ),
					$recipe['name'],
					$result->get_error_message()
				);
				$job['replicate_results'][ $recipe_index ] = array( 'status' => 'failed' );
				$this->append_recipe_to_post( $job['created_post_id'], $recipe, null );
				$job['step']++;
				$progress = 30 + ( ( $recipe_index + 1 ) / $total_recipes ) * 60;
				return array(
					'done'     => false,
					'progress' => min( 90, $progress ),
					'message'  => sprintf(
						/* translators: 1: Index recette, 2: Total, 3: Nom recette */
						__( 'Recette %1$d/%2$d (%3$s) ajoutée (sans image).', 'ai-recipe-generator-pro' ),
						$recipe_index + 1,
						$total_recipes,
						$recipe['name']
					),
				);
			}

			if ( 'pending' === $result['status'] ) {
				// Toujours en cours
				return array(
					'done'     => false,
					'progress' => 30 + ( $recipe_index / $total_recipes ) * 60,
					'message'  => sprintf(
						/* translators: 1: Index recette, 2: Total, 3: Nom recette */
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
						/* translators: 1: Nom de la recette, 2: Message d'erreur */
						__( 'Erreur téléchargement image pour "%1$s": %2$s', 'ai-recipe-generator-pro' ),
						$recipe['name'],
						$attachment_id->get_error_message()
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
				return array(
					'done'     => false,
					'progress' => min( 90, $progress ),
					'message'  => sprintf(
						/* translators: 1: Index recette, 2: Total, 3: Nom recette */
						__( 'Recette %1$d/%2$d (%3$s) ajoutée avec image.', 'ai-recipe-generator-pro' ),
						$recipe_index + 1,
						$total_recipes,
						$recipe['name']
					),
				);
			}
		}

		// Démarrer une nouvelle prédiction Replicate
		$prediction_result = $this->replicate_start_prediction( $recipe['image_prompt'] );

		if ( is_wp_error( $prediction_result ) ) {
			// Erreur : on continue sans image
			$job['errors'][] = sprintf(
				/* translators: 1: Nom de la recette, 2: Message d'erreur */
				__( 'Erreur Replicate pour "%1$s": %2$s', 'ai-recipe-generator-pro' ),
				$recipe['name'],
				$prediction_result->get_error_message()
			);
			$job['replicate_results'][ $recipe_index ] = array( 'status' => 'failed' );
			$this->append_recipe_to_post( $job['created_post_id'], $recipe, null );
			$job['step']++;
			$progress = 30 + ( ( $recipe_index + 1 ) / $total_recipes ) * 60;
			return array(
				'done'     => false,
				'progress' => min( 90, $progress ),
				'message'  => sprintf(
					/* translators: 1: Index recette, 2: Total, 3: Nom recette */
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
		);

		return array(
			'done'     => false,
			'progress' => 30 + ( $recipe_index / $total_recipes ) * 60,
			'message'  => sprintf(
				/* translators: 1: Index recette, 2: Total, 3: Nom recette */
				__( 'Génération de l\'image %1$d/%2$d (%3$s) démarrée...', 'ai-recipe-generator-pro' ),
				$recipe_index + 1,
				$total_recipes,
				$recipe['name']
			),
		);
	}

	/**
	 * STEP final: Finaliser le job
	 *
	 * @param array &$job Données du job.
	 * @return array Résultat final.
	 */
	private function job_step_finalize( &$job ) {
		$post_id = $job['created_post_id'];
		$edit_link = get_edit_post_link( $post_id, 'raw' );

		return array(
			'done'      => true,
			'progress'  => 100,
			'message'   => __( 'Génération terminée avec succès !', 'ai-recipe-generator-pro' ),
			'post_id'   => $post_id,
			'edit_link' => $edit_link,
			'errors'    => $job['errors'],
		);
	}

	/* ========================================
	   OPENAI - GÉNÉRATION DE RECETTES
	   ======================================== */

	/**
	 * Génère des recettes complètes avec OpenAI
	 *
	 * @param string $subject Sujet/Thème.
	 * @param int    $count   Nombre de recettes.
	 * @return array|WP_Error JSON structuré ou erreur.
	 */
	private function openai_generate_recipes( $subject, $count ) {
		$api_key = ARGP_Settings::get_option( 'openai_api_key', '' );

		$system_prompt = "Tu es un chef cuisinier et rédacteur culinaire professionnel. " .
			"Tu génères du contenu pour un blog de recettes grand public en français. " .
			"Tes recettes sont claires, gourmandes, réalisables, et optimisées SEO. " .
			"Tu ne donnes jamais de conseils médicaux ou d'allégations santé non prouvées. " .
			"Tu réponds UNIQUEMENT en JSON valide sans markdown.";

		$user_prompt = "Génère un article de blog complet sur le thème : \"{$subject}\".\n\n";
		$user_prompt .= "L'article doit contenir :\n";
		$user_prompt .= "- Une introduction engageante (2-3 phrases)\n";
		$user_prompt .= "- Exactement {$count} recette(s) détaillée(s)\n\n";
		$user_prompt .= "Pour chaque recette, fournis :\n";
		$user_prompt .= "- name : nom de la recette (court et accrocheur)\n";
		$user_prompt .= "- ingredients : liste des ingrédients (array de strings)\n";
		$user_prompt .= "- instructions : étapes de préparation (array de strings, numérotées)\n";
		$user_prompt .= "- image_prompt : prompt pour générer une photo réaliste de la recette (en anglais, style 'professional food photography of...')\n\n";
		$user_prompt .= "Format JSON attendu :\n";
		$user_prompt .= "{\n";
		$user_prompt .= "  \"intro\": \"Texte d'introduction...\",\n";
		$user_prompt .= "  \"recipes\": [\n";
		$user_prompt .= "    {\n";
		$user_prompt .= "      \"name\": \"Nom de la recette\",\n";
		$user_prompt .= "      \"ingredients\": [\"Ingrédient 1\", \"Ingrédient 2\"],\n";
		$user_prompt .= "      \"instructions\": [\"Étape 1\", \"Étape 2\"],\n";
		$user_prompt .= "      \"image_prompt\": \"professional food photography of...\"\n";
		$user_prompt .= "    }\n";
		$user_prompt .= "  ]\n";
		$user_prompt .= "}\n\n";
		$user_prompt .= "IMPORTANT : Réponds UNIQUEMENT avec le JSON, sans aucun texte avant ou après.";

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
			'temperature' => 0.7,
			'max_tokens'  => 3000,
			'response_format' => array(
				'type' => 'json_object',
			),
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 60,
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
			return new WP_Error( 'openai_error', 'JSON invalide' );
		}

		if ( count( $json_data['recipes'] ) !== $count ) {
			return new WP_Error( 'openai_error', 'Nombre de recettes incorrect' );
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
		$api_key = ARGP_Settings::get_option( 'replicate_api_key', '' );

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
		if ( 201 !== $http_code && 200 !== $http_code ) {
			$body_data = json_decode( wp_remote_retrieve_body( $response ), true );
			$error_msg = isset( $body_data['detail'] ) ? $body_data['detail'] : 'Erreur Replicate';
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
		$api_key = ARGP_Settings::get_option( 'replicate_api_key', '' );

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
		if ( 200 !== $http_code ) {
			return new WP_Error( 'replicate_error', 'Erreur lors de la vérification' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
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
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $image_url );

		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$file_array = array(
			'name'     => 'recipe-' . sanitize_title( $description ) . '-' . time() . '.jpg',
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, $post_id, $description );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $file_array['tmp_name'] );
			return $attachment_id;
		}

		return $attachment_id;
	}

	/**
	 * Ajoute une recette au contenu du post
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
		foreach ( $recipe['ingredients'] as $ingredient ) {
			$content .= "\n  " . '<li>' . esc_html( $ingredient ) . '</li>';
		}
		$content .= "\n" . '</ul>';

		// Instructions
		$content .= "\n\n" . '<h3>' . __( 'Instructions', 'ai-recipe-generator-pro' ) . '</h3>';
		$content .= "\n" . '<ol class="recipe-instructions">';
		foreach ( $recipe['instructions'] as $instruction ) {
			$content .= "\n  " . '<li>' . esc_html( $instruction ) . '</li>';
		}
		$content .= "\n" . '</ol>';

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
		$api_key = ARGP_Settings::get_option( 'openai_api_key', '' );

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
}
