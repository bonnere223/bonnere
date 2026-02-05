<?php
/**
 * Plugin Name: AI Recipe Generator Pro
 * Plugin URI: https://example.com/ai-recipe-generator-pro
 * Description: Génère des recettes intelligentes avec OpenAI et Replicate, puis les publie automatiquement dans WordPress.
 * Version: 2.1.1
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Votre Nom
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-recipe-generator-pro
 * Domain Path: /languages
 *
 * @package AI_Recipe_Generator_Pro
 */

// Si ce fichier est appelé directement, quitter.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Constantes globales du plugin
 */
define( 'ARGP_VERSION', '2.1.1' );
define( 'ARGP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ARGP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ARGP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Classe principale du plugin AI Recipe Generator Pro
 */
class AI_Recipe_Generator_Pro {

	/**
	 * Instance unique de la classe (singleton)
	 *
	 * @var AI_Recipe_Generator_Pro
	 */
	private static $instance = null;

	/**
	 * Récupère l'instance unique
	 *
	 * @return AI_Recipe_Generator_Pro
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructeur privé (pattern Singleton)
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Charge les dépendances (classes)
	 */
	private function load_dependencies() {
		require_once ARGP_PLUGIN_DIR . 'includes/class-argp-admin.php';
		require_once ARGP_PLUGIN_DIR . 'includes/class-argp-settings.php';
		require_once ARGP_PLUGIN_DIR . 'includes/class-argp-ajax.php';
		require_once ARGP_PLUGIN_DIR . 'includes/class-argp-export.php';
		require_once ARGP_PLUGIN_DIR . 'includes/class-argp-updater.php';
	}

	/**
	 * Initialise les hooks WordPress
	 */
	private function init_hooks() {
		// Hook d'activation
		register_activation_hook( __FILE__, array( $this, 'activate' ) );

		// Hook de désactivation
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Chargement de la textdomain pour l'internationalisation
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// PHASE 5: Hook pour le cron de nettoyage quotidien
		add_action( 'argp_daily_cleanup', array( $this, 'daily_cleanup' ) );

		// Initialiser le système de mise à jour automatique
		ARGP_Updater::get_instance();

		// Initialiser les composants admin
		if ( is_admin() ) {
			ARGP_Admin::get_instance();
			ARGP_Settings::get_instance();
			ARGP_Ajax::get_instance();
			ARGP_Export::get_instance();
		}
	}

	/**
	 * Activation du plugin
	 */
	public function activate() {
		// Options par défaut
		$default_options = array(
			'openai_api_key'      => '',
			'replicate_api_key'   => '',
			'manual_titles'       => '',
			'generation_order'    => 'image_first',
			'stop_on_error'       => false,
			'prompt_text'         => "Écris une recette à partir de : {titre}\n\nFormat:\n- Titre court\n- Personnes et temps\n- Ingrédients avec émojis et grammage\n- Étapes numérotées 1️⃣, 2️⃣ avec émojis\n- Astuce pour faciliter\n- Ingrédient à échanger\n- Astuce de cuisson",
			'prompt_image'        => "Tu es expert en direction artistique culinaire. Crée un prompt d'image détaillé et appétissant.\n\nConsignes:\n- Décris le rendu visuel final du plat\n- Type de plat, portions visibles\n- Ingrédients reconnaissables\n- Textures (fondant, croustillant, gratiné)\n- Couleurs dominantes\n- Type et couleur assiette\n- Disposition éléments\n- Ambiance : surface, style, éclairage naturel\n- Interdiction : personnages, mains\n- Style : photographie culinaire professionnelle, ultra réaliste, magazine",
			'enable_debug'        => false, // PHASE 5
		);
		
		// Ajouter les options si elles n'existent pas
		if ( false === get_option( 'argp_settings' ) ) {
			add_option( 'argp_settings', $default_options );
		}

		// PHASE 5: Programmer le cron de nettoyage quotidien
		if ( ! wp_next_scheduled( 'argp_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'argp_daily_cleanup' );
		}

		// Flush les rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Désactivation du plugin
	 */
	public function deactivate() {
		// PHASE 5: Supprimer le cron programmé
		$timestamp = wp_next_scheduled( 'argp_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'argp_daily_cleanup' );
		}

		// Flush les rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Charge la textdomain pour l'internationalisation
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'ai-recipe-generator-pro',
			false,
			dirname( ARGP_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * PHASE 5: Nettoyage quotidien automatique
	 * Nettoie les transients expirés et fichiers temporaires
	 */
	public function daily_cleanup() {
		global $wpdb;

		// Nettoyer les transients jobs expirés (argp_job_*)
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE %s 
				OR option_name LIKE %s",
				'%_transient_argp_job_%',
				'%_transient_timeout_argp_job_%'
			)
		);

		// Nettoyer les transients utilisateurs expirés (argp_user_*)
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE %s 
				OR option_name LIKE %s",
				'%_transient_argp_user_%',
				'%_transient_timeout_argp_user_%'
			)
		);

		// Nettoyer les fichiers temporaires
		$temp_dir = get_temp_dir();
		$patterns = array( 'argp-images-*', 'argp-recettes-*' );

		foreach ( $patterns as $pattern ) {
			$files = glob( $temp_dir . $pattern );

			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					// Supprimer si > 24h
					if ( is_file( $file ) && ( time() - filemtime( $file ) ) > DAY_IN_SECONDS ) {
						@unlink( $file );
					}
				}
			}
		}

		// Log si debug activé
		if ( class_exists( 'ARGP_Settings' ) && ARGP_Settings::is_debug_enabled() ) {
			error_log( '[AI Recipe Generator Pro] Nettoyage quotidien effectué - ' . $deleted . ' transients supprimés' );
		}
	}
}

/**
 * Lance le plugin
 */
function argp_init() {
	return AI_Recipe_Generator_Pro::get_instance();
}

// Démarrage du plugin
argp_init();
