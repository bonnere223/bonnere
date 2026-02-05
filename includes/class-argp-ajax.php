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
		// Récupérer la clé API
		$api_key = ARGP_Settings::get_option( 'openai_api_key', '' );

		// Construire le prompt système
		$system_prompt = "Tu es un rédacteur SEO spécialisé dans le domaine culinaire et les blogs food. " .
			"Tu génères des titres d'articles de blog attractifs, clairs et optimisés pour le référencement. " .
			"Tes titres sont courts (50-75 caractères maximum), accrocheurs mais honnêtes (pas de clickbait mensonger). " .
			"Tu respectes le style et le ton des articles existants du blog.";

		// Construire le contexte utilisateur
		$user_prompt = "Je souhaite créer un article de blog sur le thème suivant : \"{$subject}\".\n\n";

		// Ajouter les titres récents pour le contexte
		if ( ! empty( $recent_titles ) ) {
			$user_prompt .= "Voici les 15 derniers titres publiés sur mon blog (pour référence de style et éviter les doublons) :\n";
			foreach ( $recent_titles as $index => $title ) {
				$user_prompt .= ( $index + 1 ) . ". {$title}\n";
			}
			$user_prompt .= "\n";
		}

		// Ajouter les titres manuels préférés
		if ( ! empty( $manual_titles ) ) {
			$user_prompt .= "Voici des titres que j'aime particulièrement (respecte ce style) :\n";
			foreach ( $manual_titles as $index => $title ) {
				$user_prompt .= "- {$title}\n";
			}
			$user_prompt .= "\n";
		}

		// Consignes finales
		$user_prompt .= "Consignes :\n" .
			"- Propose exactement 3 titres différents et originaux\n" .
			"- Chaque titre doit faire entre 50 et 75 caractères maximum\n" .
			"- Les titres doivent être en français\n" .
			"- Évite de réutiliser ou de copier les titres existants\n" .
			"- Les titres doivent être pertinents pour le thème : \"{$subject}\"\n" .
			"- Réponds UNIQUEMENT avec un objet JSON contenant une clé 'titles' avec un tableau de 3 strings\n\n" .
			"Format attendu : {\"titles\": [\"Titre 1\", \"Titre 2\", \"Titre 3\"]}";

		// Préparer la requête pour l'API OpenAI
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

		// Appel à l'API OpenAI
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

		// Vérifier les erreurs réseau
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

		// Récupérer le code HTTP
		$http_code = wp_remote_retrieve_response_code( $response );

		// Vérifier le code de réponse
		if ( $http_code !== 200 ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			
			$error_message = __( 'Erreur inconnue de l\'API OpenAI.', 'ai-recipe-generator-pro' );
			
			if ( isset( $data['error']['message'] ) ) {
				$error_message = $data['error']['message'];
			}

			// Messages d'erreur spécifiques
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

		// Décoder la réponse JSON
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		// Vérifier la structure de la réponse
		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error(
				'openai_invalid_response',
				__( 'Réponse invalide de l\'API OpenAI : structure inattendue.', 'ai-recipe-generator-pro' )
			);
		}

		// Récupérer le contenu JSON de la réponse
		$content = $data['choices'][0]['message']['content'];
		$titles_data = json_decode( $content, true );

		// Vérifier que le JSON est valide et contient les titres
		if ( ! isset( $titles_data['titles'] ) || ! is_array( $titles_data['titles'] ) ) {
			// Fallback : tenter d'extraire des lignes du texte
			$titles = $this->extract_titles_fallback( $content );
			
			if ( empty( $titles ) ) {
				return new WP_Error(
					'openai_parse_error',
					__( 'Impossible d\'extraire les titres de la réponse OpenAI.', 'ai-recipe-generator-pro' )
				);
			}
			
			return $titles;
		}

		// Récupérer les 3 premiers titres
		$titles = array_slice( $titles_data['titles'], 0, 3 );

		// Vérifier qu'on a bien 3 titres
		if ( count( $titles ) < 3 ) {
			return new WP_Error(
				'openai_insufficient_titles',
				__( 'OpenAI n\'a pas retourné assez de suggestions (attendu : 3).', 'ai-recipe-generator-pro' )
			);
		}

		// Nettoyer les titres (supprimer guillemets, espaces superflus)
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
		
		// Tenter de découper par lignes
		$lines = explode( "\n", $text );
		
		foreach ( $lines as $line ) {
			$line = trim( $line );
			
			// Ignorer les lignes vides ou trop courtes
			if ( empty( $line ) || strlen( $line ) < 10 ) {
				continue;
			}
			
			// Nettoyer la ligne (supprimer numéros, tirets, guillemets)
			$line = preg_replace( '/^[\d\-\.\)\]\*\s]+/', '', $line );
			$line = trim( $line, ' "\'' );
			
			if ( ! empty( $line ) && strlen( $line ) >= 10 ) {
				$titles[] = $line;
			}
			
			// S'arrêter à 3 titres
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
		// Supprimer les guillemets doubles et simples
		$title = trim( $title, ' "\'' );
		
		// Supprimer les espaces multiples
		$title = preg_replace( '/\s+/', ' ', $title );
		
		return $title;
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
