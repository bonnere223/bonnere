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

		// TODO Phase 3: Hook pour générer les recettes avec OpenAI
		// add_action( 'wp_ajax_argp_generate_recipes', array( $this, 'handle_generate_recipes' ) );

		// TODO Phase 4: Hook pour générer les images avec Replicate
		// add_action( 'wp_ajax_argp_generate_images', array( $this, 'handle_generate_images' ) );

		// TODO Phase 5: Hook pour publier les articles
		// add_action( 'wp_ajax_argp_publish_recipes', array( $this, 'handle_publish_recipes' ) );
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

		// Récupérer le sujet (optionnel pour le contexte)
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';

		// TODO Phase 2 complète : Utiliser les 15 derniers titres du blog + titres manuels
		// Pour l'instant, on retourne des suggestions factices

		// Récupérer les titres manuels configurés
		$manual_titles_raw = ARGP_Settings::get_option( 'manual_titles', '' );
		$manual_titles = array_filter( array_map( 'trim', explode( "\n", $manual_titles_raw ) ) );

		// Récupérer les 15 derniers articles du blog
		$recent_posts = get_posts(
			array(
				'numberposts' => 15,
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		$recent_titles = array();
		foreach ( $recent_posts as $post ) {
			$recent_titles[] = $post->post_title;
		}

		// Combiner les titres
		$all_titles = array_merge( $manual_titles, $recent_titles );

		// Pour l'instant, générer 3 suggestions basées sur le sujet et les titres existants
		// TODO Phase 3 : Utiliser OpenAI pour générer de vraies suggestions intelligentes
		$suggestions = $this->generate_mock_suggestions( $subject, $all_titles );

		wp_send_json_success(
			array(
				'suggestions' => $suggestions,
				'context'     => array(
					'manual_count' => count( $manual_titles ),
					'recent_count' => count( $recent_titles ),
					'subject'      => $subject,
				),
				'message'     => __( 'Suggestions générées avec succès.', 'ai-recipe-generator-pro' ),
			)
		);
	}

	/**
	 * Génère des suggestions factices (mock) pour la phase MVP
	 *
	 * @param string $subject     Sujet fourni par l'utilisateur.
	 * @param array  $all_titles  Tous les titres disponibles.
	 * @return array Liste de 3 suggestions.
	 */
	private function generate_mock_suggestions( $subject, $all_titles ) {
		$suggestions = array();

		// Si un sujet est fourni, créer des suggestions basées dessus
		if ( ! empty( $subject ) ) {
			$suggestions[] = sprintf(
				/* translators: %s: Sujet */
				__( 'Guide ultime : %s pour débutants', 'ai-recipe-generator-pro' ),
				$subject
			);
			$suggestions[] = sprintf(
				/* translators: %s: Sujet */
				__( '10 astuces pour réussir %s', 'ai-recipe-generator-pro' ),
				$subject
			);
			$suggestions[] = sprintf(
				/* translators: %s: Sujet */
				__( '%s : tout ce que vous devez savoir', 'ai-recipe-generator-pro' ),
				ucfirst( $subject )
			);
		} else {
			// Suggestions génériques si pas de sujet
			$suggestions[] = __( 'Recette facile et rapide pour tous les jours', 'ai-recipe-generator-pro' );
			$suggestions[] = __( '5 idées de repas sains et délicieux', 'ai-recipe-generator-pro' );
			$suggestions[] = __( 'Le secret des chefs pour des plats parfaits', 'ai-recipe-generator-pro' );
		}

		// TODO Phase 3: Remplacer par un vrai appel à OpenAI
		// Exemple : Utiliser les titres existants comme contexte pour générer des suggestions cohérentes

		return $suggestions;
	}

	/**
	 * TODO Phase 3 : Handler pour générer les recettes avec OpenAI
	 *
	 * Cette méthode devra :
	 * - Recevoir le sujet, nombre de recettes, titre
	 * - Appeler l'API OpenAI pour générer le contenu
	 * - Retourner les recettes générées en JSON
	 */
	// public function handle_generate_recipes() { }

	/**
	 * TODO Phase 4 : Handler pour générer les images avec Replicate
	 *
	 * Cette méthode devra :
	 * - Recevoir les prompts pour les images
	 * - Appeler l'API Replicate
	 * - Télécharger et sauvegarder les images dans la bibliothèque WP
	 * - Retourner les IDs des attachments
	 */
	// public function handle_generate_images() { }

	/**
	 * TODO Phase 5 : Handler pour publier les articles
	 *
	 * Cette méthode devra :
	 * - Créer les articles WordPress avec wp_insert_post
	 * - Associer les images featured
	 * - Gérer les taxonomies (catégories, tags)
	 * - Retourner les URLs des articles publiés
	 */
	// public function handle_publish_recipes() { }
}
