<?php
/**
 * Plugin Name: AI Recipe Generator Pro
 * Plugin URI: https://example.com/ai-recipe-generator-pro
 * Description: Génère des recettes intelligentes avec OpenAI et Replicate, puis les publie automatiquement dans WordPress.
 * Version: 1.0.0
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
define( 'ARGP_VERSION', '1.0.0' );
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
		// TODO: Créer les tables custom si nécessaire pour les phases futures
		// TODO: Définir les options par défaut
		
		// Options par défaut
		$default_options = array(
			'openai_api_key'      => '',
			'replicate_api_key'   => '',
			'manual_titles'       => '',
		);
		
		// Ajouter les options si elles n'existent pas
		if ( false === get_option( 'argp_settings' ) ) {
			add_option( 'argp_settings', $default_options );
		}

		// Flush les rewrite rules si on ajoute des CPT plus tard
		flush_rewrite_rules();
	}

	/**
	 * Désactivation du plugin
	 */
	public function deactivate() {
		// Flush les rewrite rules
		flush_rewrite_rules();
		
		// TODO: Nettoyage si nécessaire (ne pas supprimer les données par défaut)
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
}

/**
 * Lance le plugin
 */
function argp_init() {
	return AI_Recipe_Generator_Pro::get_instance();
}

// Démarrage du plugin
argp_init();
