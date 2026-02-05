<?php
/**
 * Système de mise à jour automatique depuis GitHub
 *
 * @package AI_Recipe_Generator_Pro
 */

// Si ce fichier est appelé directement, quitter.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe ARGP_Updater
 * Gère les mises à jour automatiques depuis GitHub
 */
class ARGP_Updater {

	/**
	 * Instance unique (singleton)
	 *
	 * @var ARGP_Updater
	 */
	private static $instance = null;

	/**
	 * Slug du plugin
	 *
	 * @var string
	 */
	private $plugin_slug = 'ai-recipe-generator-pro';

	/**
	 * Basename du plugin
	 *
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * Version actuelle du plugin
	 *
	 * @var string
	 */
	private $plugin_version;

	/**
	 * Configuration GitHub
	 *
	 * @var array
	 */
	private $github_config = array(
		'owner'      => 'bonnere223',           // Owner du repo GitHub
		'repo'       => 'bonnere',              // Nom du repo
		'branch'     => 'main',                 // Branche par défaut
		'update_url' => '',                     // URL du fichier update.json (optionnel)
		'use_tags'   => true,                   // Utiliser les tags GitHub (recommandé)
		'token'      => '',                     // Token GitHub pour repo privé (optionnel)
	);

	/**
	 * Récupère l'instance unique
	 *
	 * @return ARGP_Updater
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
		$this->plugin_basename = ARGP_PLUGIN_BASENAME;
		$this->plugin_version  = ARGP_VERSION;

		// Hook pour détecter les mises à jour
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );

		// Hook pour les détails du plugin (popup "Voir les détails")
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );

		// Hook après mise à jour (nettoyage cache)
		add_action( 'upgrader_process_complete', array( $this, 'after_update' ), 10, 2 );
	}

	/**
	 * Vérifie si une mise à jour est disponible
	 *
	 * @param object $transient Transient update_plugins.
	 * @return object Transient modifié.
	 */
	public function check_for_update( $transient ) {
		// Si pas de transient ou pas de checked, ne rien faire
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		// Récupérer les infos de mise à jour (avec cache)
		$remote_info = $this->get_remote_info();

		// Si erreur ou pas de version distante, retourner tel quel
		if ( ! $remote_info || ! isset( $remote_info['version'] ) ) {
			return $transient;
		}

		// Comparer les versions
		if ( version_compare( $this->plugin_version, $remote_info['version'], '<' ) ) {
			// Une mise à jour est disponible !
			$plugin_data = array(
				'slug'        => $this->plugin_slug,
				'plugin'      => $this->plugin_basename,
				'new_version' => $remote_info['version'],
				'url'         => $remote_info['homepage'] ?? '',
				'package'     => $remote_info['download_url'] ?? '',
				'icons'       => $remote_info['icons'] ?? array(),
				'banners'     => $remote_info['banners'] ?? array(),
				'tested'      => $remote_info['tested_up_to'] ?? '',
				'requires'    => $remote_info['requires_wp'] ?? '',
				'requires_php' => $remote_info['requires_php'] ?? '',
			);

			$transient->response[ $this->plugin_basename ] = (object) $plugin_data;
		}

		return $transient;
	}

	/**
	 * Fournit les détails du plugin pour la popup "Voir les détails"
	 *
	 * @param false|object|array $result Résultat.
	 * @param string             $action Action.
	 * @param object             $args   Arguments.
	 * @return false|object Résultat modifié.
	 */
	public function plugin_info( $result, $action, $args ) {
		// Vérifier si c'est une requête pour notre plugin
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( $this->plugin_slug !== $args->slug ) {
			return $result;
		}

		// Récupérer les infos distantes
		$remote_info = $this->get_remote_info();

		if ( ! $remote_info ) {
			return $result;
		}

		// Construire l'objet de réponse
		$plugin_info = new stdClass();
		$plugin_info->name          = $remote_info['name'] ?? 'AI Recipe Generator Pro';
		$plugin_info->slug          = $this->plugin_slug;
		$plugin_info->version       = $remote_info['version'] ?? $this->plugin_version;
		$plugin_info->author        = $remote_info['author'] ?? 'Votre Nom';
		$plugin_info->homepage      = $remote_info['homepage'] ?? '';
		$plugin_info->requires      = $remote_info['requires_wp'] ?? '5.8';
		$plugin_info->tested        = $remote_info['tested_up_to'] ?? get_bloginfo( 'version' );
		$plugin_info->downloaded    = 0;
		$plugin_info->last_updated  = $remote_info['last_updated'] ?? date( 'Y-m-d' );
		$plugin_info->sections      = array(
			'description' => $remote_info['description'] ?? 'Génère des recettes intelligentes avec l\'IA.',
			'changelog'   => $remote_info['changelog'] ?? '<h4>Version ' . $remote_info['version'] . '</h4><ul><li>Mise à jour disponible</li></ul>',
		);
		$plugin_info->download_link = $remote_info['download_url'] ?? '';

		return $plugin_info;
	}

	/**
	 * Action après mise à jour (nettoyage cache)
	 *
	 * @param WP_Upgrader $upgrader Instance upgrader.
	 * @param array       $options  Options de mise à jour.
	 */
	public function after_update( $upgrader, $options ) {
		// Vérifier si c'est notre plugin qui a été mis à jour
		if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {
			if ( isset( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
				foreach ( $options['plugins'] as $plugin ) {
					if ( $plugin === $this->plugin_basename ) {
						// Vider le cache de l'updater
						delete_transient( 'argp_update_info' );
						
						// Log
						ARGP_Settings::log( 'Plugin mis à jour vers ' . $this->plugin_version, 'info' );
					}
				}
			}
		}
	}

	/**
	 * Récupère les informations de mise à jour depuis GitHub
	 * Utilise un cache de 12 heures
	 *
	 * @return array|false Informations de mise à jour ou false.
	 */
	private function get_remote_info() {
		// Vérifier le cache (12 heures)
		$cache_key = 'argp_update_info';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Récupérer les infos depuis GitHub
		$remote_info = $this->fetch_github_info();

		// Si succès, mettre en cache pour 12 heures
		if ( $remote_info && is_array( $remote_info ) ) {
			set_transient( $cache_key, $remote_info, 12 * HOUR_IN_SECONDS );
		}

		return $remote_info;
	}

	/**
	 * Récupère les infos depuis GitHub
	 *
	 * @return array|false Informations ou false.
	 */
	private function fetch_github_info() {
		// Option 1 : Utiliser un fichier update.json hébergé
		if ( ! empty( $this->github_config['update_url'] ) ) {
			return $this->fetch_from_json_url();
		}

		// Option 2 : Utiliser l'API GitHub (tags ou releases)
		if ( $this->github_config['use_tags'] ) {
			return $this->fetch_from_github_api();
		}

		return false;
	}

	/**
	 * Récupère les infos depuis un fichier JSON
	 *
	 * @return array|false Informations ou false.
	 */
	private function fetch_from_json_url() {
		$url = $this->github_config['update_url'];

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return is_array( $data ) ? $data : false;
	}

	/**
	 * Récupère les infos depuis l'API GitHub (dernière release ou tag)
	 *
	 * @return array|false Informations ou false.
	 */
	private function fetch_from_github_api() {
		$owner = $this->github_config['owner'];
		$repo  = $this->github_config['repo'];
		$token = $this->github_config['token'];

		// Essayer d'abord l'endpoint releases
		$url = "https://api.github.com/repos/{$owner}/{$repo}/releases/latest";

		$args = array(
			'timeout' => 15,
			'headers' => array(
				'Accept' => 'application/vnd.github.v3+json',
			),
		);

		// Ajouter token si repo privé
		if ( ! empty( $token ) ) {
			$args['headers']['Authorization'] = 'token ' . $token;
		}

		$response = wp_remote_get( $url, $args );

		// Si pas de release, essayer les tags
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $this->fetch_from_github_tags();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data || ! isset( $data['tag_name'] ) ) {
			return false;
		}

		// Construire les infos de mise à jour
		$version = ltrim( $data['tag_name'], 'v' ); // Supprimer le 'v' si présent (v2.0.1 → 2.0.1)

		// URL de téléchargement : zipball de la release
		$download_url = isset( $data['zipball_url'] ) 
			? $data['zipball_url'] 
			: "https://github.com/{$owner}/{$repo}/archive/refs/tags/{$data['tag_name']}.zip";

		return array(
			'version'       => $version,
			'download_url'  => $download_url,
			'name'          => $data['name'] ?? 'AI Recipe Generator Pro',
			'description'   => $data['body'] ?? '',
			'changelog'     => $this->format_changelog( $data['body'] ?? '' ),
			'author'        => $owner,
			'homepage'      => "https://github.com/{$owner}/{$repo}",
			'requires_wp'   => '5.8',
			'tested_up_to'  => get_bloginfo( 'version' ),
			'requires_php'  => '7.4',
			'last_updated'  => isset( $data['published_at'] ) ? date( 'Y-m-d', strtotime( $data['published_at'] ) ) : date( 'Y-m-d' ),
		);
	}

	/**
	 * Récupère les infos depuis les tags GitHub (fallback)
	 *
	 * @return array|false Informations ou false.
	 */
	private function fetch_from_github_tags() {
		$owner = $this->github_config['owner'];
		$repo  = $this->github_config['repo'];
		$token = $this->github_config['token'];

		$url = "https://api.github.com/repos/{$owner}/{$repo}/tags";

		$args = array(
			'timeout' => 15,
			'headers' => array(
				'Accept' => 'application/vnd.github.v3+json',
			),
		);

		if ( ! empty( $token ) ) {
			$args['headers']['Authorization'] = 'token ' . $token;
		}

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$tags = json_decode( $body, true );

		if ( ! $tags || empty( $tags ) || ! is_array( $tags ) ) {
			return false;
		}

		// Prendre le premier tag (le plus récent)
		$latest_tag = $tags[0];
		$version    = ltrim( $latest_tag['name'], 'v' );

		$download_url = "https://github.com/{$owner}/{$repo}/archive/refs/tags/{$latest_tag['name']}.zip";

		return array(
			'version'       => $version,
			'download_url'  => $download_url,
			'name'          => 'AI Recipe Generator Pro',
			'description'   => 'Génère des recettes intelligentes avec l\'IA.',
			'changelog'     => '<h4>Version ' . $version . '</h4><ul><li>Mise à jour disponible</li></ul>',
			'author'        => $owner,
			'homepage'      => "https://github.com/{$owner}/{$repo}",
			'requires_wp'   => '5.8',
			'tested_up_to'  => get_bloginfo( 'version' ),
			'requires_php'  => '7.4',
			'last_updated'  => date( 'Y-m-d' ),
		);
	}

	/**
	 * Formate le changelog (markdown → HTML)
	 *
	 * @param string $markdown Texte markdown.
	 * @return string HTML formaté.
	 */
	private function format_changelog( $markdown ) {
		if ( empty( $markdown ) ) {
			return '<p>Mise à jour disponible.</p>';
		}

		// Conversion simple markdown → HTML
		$html = wpautop( $markdown );
		
		// Convertir les listes
		$html = preg_replace( '/^- (.+)$/m', '<li>$1</li>', $html );
		$html = preg_replace( '/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html );
		
		// Convertir les titres
		$html = preg_replace( '/^### (.+)$/m', '<h4>$1</h4>', $html );
		$html = preg_replace( '/^## (.+)$/m', '<h3>$1</h3>', $html );

		return $html;
	}

	/**
	 * Vide le cache de l'updater (utile après modification config)
	 */
	public static function clear_cache() {
		delete_transient( 'argp_update_info' );
	}

	/**
	 * Définit la configuration GitHub
	 *
	 * @param array $config Configuration.
	 */
	public function set_github_config( $config ) {
		$this->github_config = array_merge( $this->github_config, $config );
	}
}
