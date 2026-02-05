<?php
/**
 * Gestion des réglages via Settings API
 *
 * @package AI_Recipe_Generator_Pro
 */

// Si ce fichier est appelé directement, quitter.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe ARGP_Settings
 * Gère l'enregistrement et l'affichage des paramètres via Settings API
 */
class ARGP_Settings {

	/**
	 * Instance unique (singleton)
	 *
	 * @var ARGP_Settings
	 */
	private static $instance = null;

	/**
	 * Nom de l'option dans la base de données
	 *
	 * @var string
	 */
	private $option_name = 'argp_settings';

	/**
	 * Récupère l'instance unique
	 *
	 * @return ARGP_Settings
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
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Enregistre les réglages via Settings API
	 */
	public function register_settings() {
		// Enregistrer le groupe d'options
		register_setting(
			'argp_settings_group',
			$this->option_name,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		// Section : Clés API
		add_settings_section(
			'argp_api_keys_section',
			__( 'Clés API', 'ai-recipe-generator-pro' ),
			array( $this, 'render_api_keys_section_callback' ),
			'argp-settings'
		);

		// Champ : OpenAI API Key
		add_settings_field(
			'openai_api_key',
			__( 'OpenAI API Key', 'ai-recipe-generator-pro' ),
			array( $this, 'render_openai_api_key_field' ),
			'argp-settings',
			'argp_api_keys_section'
		);

		// Champ : Replicate API Key
		add_settings_field(
			'replicate_api_key',
			__( 'Replicate API Key', 'ai-recipe-generator-pro' ),
			array( $this, 'render_replicate_api_key_field' ),
			'argp-settings',
			'argp_api_keys_section'
		);

		// Section : Préférences
		add_settings_section(
			'argp_preferences_section',
			__( 'Préférences', 'ai-recipe-generator-pro' ),
			array( $this, 'render_preferences_section_callback' ),
			'argp-settings'
		);

		// Champ : Titres manuels préférés
		add_settings_field(
			'manual_titles',
			__( 'Titres manuels préférés', 'ai-recipe-generator-pro' ),
			array( $this, 'render_manual_titles_field' ),
			'argp-settings',
			'argp_preferences_section'
		);

		// PHASE 5: Section Debug
		add_settings_section(
			'argp_debug_section',
			__( 'Options avancées', 'ai-recipe-generator-pro' ),
			array( $this, 'render_debug_section_callback' ),
			'argp-settings'
		);

		// PHASE 5: Champ Debug
		add_settings_field(
			'enable_debug',
			__( 'Activer les logs', 'ai-recipe-generator-pro' ),
			array( $this, 'render_debug_field' ),
			'argp-settings',
			'argp_debug_section'
		);
	}

	/**
	 * Callback de la section API Keys
	 */
	public function render_api_keys_section_callback() {
		echo '<p class="description">';
		esc_html_e( 'Configurez vos clés API pour OpenAI et Replicate. Ces clés sont nécessaires pour générer du contenu avec l\'IA.', 'ai-recipe-generator-pro' );
		echo '</p>';

		// PHASE 5: Warning si chiffrement indisponible
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			echo '<div class="notice notice-warning inline">';
			echo '<p><strong>' . esc_html__( '⚠️ Attention :', 'ai-recipe-generator-pro' ) . '</strong> ';
			esc_html_e( 'L\'extension OpenSSL n\'est pas disponible sur votre serveur. Les clés API seront stockées en clair dans la base de données. Pour plus de sécurité, contactez votre hébergeur pour activer OpenSSL.', 'ai-recipe-generator-pro' );
			echo '</p></div>';
		} else {
			echo '<div class="notice notice-success inline">';
			echo '<p><strong>' . esc_html__( '✓ Sécurité :', 'ai-recipe-generator-pro' ) . '</strong> ';
			esc_html_e( 'Les clés API sont chiffrées avant stockage avec OpenSSL (AES-256-CBC).', 'ai-recipe-generator-pro' );
			echo '</p></div>';
		}
	}

	/**
	 * Callback de la section Préférences
	 */
	public function render_preferences_section_callback() {
		echo '<p class="description">';
		esc_html_e( 'Personnalisez le comportement du générateur de recettes.', 'ai-recipe-generator-pro' );
		echo '</p>';
	}

	/**
	 * PHASE 5: Callback de la section Debug
	 */
	public function render_debug_section_callback() {
		echo '<p class="description">';
		esc_html_e( 'Options de débogage et maintenance. Activer uniquement si vous rencontrez des problèmes.', 'ai-recipe-generator-pro' );
		echo '</p>';
	}

	/**
	 * Affiche le champ OpenAI API Key
	 */
	public function render_openai_api_key_field() {
		$options = get_option( $this->option_name, array() );
		$value   = isset( $options['openai_api_key'] ) ? $this->decrypt_api_key( $options['openai_api_key'] ) : '';
		$masked  = ! empty( $value ) ? str_repeat( '•', 20 ) . substr( $value, -4 ) : '';

		?>
		<input 
			type="password" 
			id="argp_openai_api_key" 
			name="<?php echo esc_attr( $this->option_name ); ?>[openai_api_key]" 
			value="<?php echo esc_attr( $value ); ?>" 
			class="regular-text argp-api-key-field"
			placeholder="sk-..."
			maxlength="200"
		/>
		<button type="button" class="button button-small argp-toggle-visibility" data-target="argp_openai_api_key">
			<?php esc_html_e( 'Afficher', 'ai-recipe-generator-pro' ); ?>
		</button>
		<?php if ( ! empty( $value ) ) : ?>
			<span class="argp-key-preview"><?php echo esc_html( $masked ); ?></span>
		<?php endif; ?>
		<p class="description">
			<?php
			printf(
				/* translators: %s: URL vers OpenAI */
				esc_html__( 'Obtenez votre clé API sur %s', 'ai-recipe-generator-pro' ),
				'<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Affiche le champ Replicate API Key
	 */
	public function render_replicate_api_key_field() {
		$options = get_option( $this->option_name, array() );
		$value   = isset( $options['replicate_api_key'] ) ? $this->decrypt_api_key( $options['replicate_api_key'] ) : '';
		$masked  = ! empty( $value ) ? str_repeat( '•', 20 ) . substr( $value, -4 ) : '';

		?>
		<input 
			type="password" 
			id="argp_replicate_api_key" 
			name="<?php echo esc_attr( $this->option_name ); ?>[replicate_api_key]" 
			value="<?php echo esc_attr( $value ); ?>" 
			class="regular-text argp-api-key-field"
			placeholder="r8_..."
			maxlength="200"
		/>
		<button type="button" class="button button-small argp-toggle-visibility" data-target="argp_replicate_api_key">
			<?php esc_html_e( 'Afficher', 'ai-recipe-generator-pro' ); ?>
		</button>
		<?php if ( ! empty( $value ) ) : ?>
			<span class="argp-key-preview"><?php echo esc_html( $masked ); ?></span>
		<?php endif; ?>
		<p class="description">
			<?php
			printf(
				/* translators: %s: URL vers Replicate */
				esc_html__( 'Obtenez votre clé API sur %s', 'ai-recipe-generator-pro' ),
				'<a href="https://replicate.com/account/api-tokens" target="_blank">Replicate</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Affiche le champ Titres manuels préférés
	 */
	public function render_manual_titles_field() {
		$options = get_option( $this->option_name, array() );
		$value   = isset( $options['manual_titles'] ) ? $options['manual_titles'] : '';

		?>
		<textarea 
			id="argp_manual_titles" 
			name="<?php echo esc_attr( $this->option_name ); ?>[manual_titles]" 
			rows="8" 
			class="large-text code"
			placeholder="<?php esc_attr_e( 'Un titre par ligne...', 'ai-recipe-generator-pro' ); ?>"
		><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Liste de titres d\'articles à utiliser comme référence pour les suggestions. Entrez un titre par ligne.', 'ai-recipe-generator-pro' ); ?>
			<br>
			<?php esc_html_e( 'Ces titres seront combinés avec les 15 derniers articles de votre blog pour générer des suggestions pertinentes.', 'ai-recipe-generator-pro' ); ?>
		</p>
		<?php
	}

	/**
	 * PHASE 5: Affiche le champ Debug
	 */
	public function render_debug_field() {
		$options = get_option( $this->option_name, array() );
		$value   = isset( $options['enable_debug'] ) ? (bool) $options['enable_debug'] : false;

		?>
		<label>
			<input 
				type="checkbox" 
				id="argp_enable_debug" 
				name="<?php echo esc_attr( $this->option_name ); ?>[enable_debug]" 
				value="1"
				<?php checked( $value, true ); ?>
			/>
			<?php esc_html_e( 'Activer les logs de débogage', 'ai-recipe-generator-pro' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Les événements seront enregistrés dans le fichier de log WordPress (wp-content/debug.log). Activez uniquement en cas de problème pour diagnostiquer les erreurs.', 'ai-recipe-generator-pro' ); ?>
		</p>
		<?php
	}

	/**
	 * Sanitize les réglages avant sauvegarde
	 *
	 * @param array $input Données du formulaire.
	 * @return array Données nettoyées.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		// PHASE 5: Sanitize et chiffrer OpenAI API Key
		if ( isset( $input['openai_api_key'] ) ) {
			$key = sanitize_text_field( $input['openai_api_key'] );
			$key = substr( $key, 0, 200 ); // Limite 200 caractères
			$sanitized['openai_api_key'] = $this->encrypt_api_key( $key );
		}

		// PHASE 5: Sanitize et chiffrer Replicate API Key
		if ( isset( $input['replicate_api_key'] ) ) {
			$key = sanitize_text_field( $input['replicate_api_key'] );
			$key = substr( $key, 0, 200 ); // Limite 200 caractères
			$sanitized['replicate_api_key'] = $this->encrypt_api_key( $key );
		}

		// Sanitize Manual Titles (textarea)
		if ( isset( $input['manual_titles'] ) ) {
			$sanitized['manual_titles'] = sanitize_textarea_field( $input['manual_titles'] );
		}

		// PHASE 5: Sanitize Debug option
		$sanitized['enable_debug'] = isset( $input['enable_debug'] ) && '1' === $input['enable_debug'];

		return $sanitized;
	}

	/* ========================================
	   PHASE 5: CHIFFREMENT DES CLÉS API
	   ======================================== */

	/**
	 * Chiffre une clé API avec OpenSSL (AES-256-CBC)
	 *
	 * @param string $key Clé en clair.
	 * @return string Clé chiffrée (base64) ou clé en clair si openssl indisponible.
	 */
	private function encrypt_api_key( $key ) {
		if ( empty( $key ) ) {
			return '';
		}

		// Si openssl n'est pas disponible, stocker en clair
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return $key;
		}

		// Si la clé est déjà chiffrée (base64 sans préfixe sk-/r8_), ne pas rechiffrer
		if ( ! preg_match( '/^(sk-|r8_)/', $key ) && base64_decode( $key, true ) !== false ) {
			return $key;
		}

		$method     = 'AES-256-CBC';
		$secret_key = substr( AUTH_KEY . SECURE_AUTH_KEY, 0, 32 );
		$iv         = substr( NONCE_KEY, 0, 16 );

		$encrypted = openssl_encrypt( $key, $method, $secret_key, 0, $iv );

		if ( false === $encrypted ) {
			// Fallback : stocker en clair
			return $key;
		}

		return base64_encode( $encrypted );
	}

	/**
	 * Déchiffre une clé API
	 *
	 * @param string $encrypted Clé chiffrée (base64).
	 * @return string Clé en clair.
	 */
	private function decrypt_api_key( $encrypted ) {
		if ( empty( $encrypted ) ) {
			return '';
		}

		// Si la clé commence par sk- ou r8_, elle est déjà en clair
		if ( preg_match( '/^(sk-|r8_)/', $encrypted ) ) {
			return $encrypted;
		}

		// Si openssl n'est pas disponible, assume clair
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return $encrypted;
		}

		$method     = 'AES-256-CBC';
		$secret_key = substr( AUTH_KEY . SECURE_AUTH_KEY, 0, 32 );
		$iv         = substr( NONCE_KEY, 0, 16 );

		$decrypted = openssl_decrypt( base64_decode( $encrypted ), $method, $secret_key, 0, $iv );

		if ( false === $decrypted ) {
			// Fallback : retourner tel quel
			return $encrypted;
		}

		return $decrypted;
	}

	/**
	 * PHASE 5: Récupère une clé déchiffrée (méthode statique publique)
	 *
	 * @param string $key_name Nom de la clé (openai_api_key ou replicate_api_key).
	 * @return string Clé déchiffrée.
	 */
	public static function get_decrypted_key( $key_name ) {
		$options = get_option( 'argp_settings', array() );

		if ( ! isset( $options[ $key_name ] ) ) {
			return '';
		}

		$instance = self::get_instance();
		return $instance->decrypt_api_key( $options[ $key_name ] );
	}

	/**
	 * Récupère une option spécifique
	 *
	 * @param string $key     Clé de l'option.
	 * @param mixed  $default Valeur par défaut.
	 * @return mixed Valeur de l'option.
	 */
	public static function get_option( $key, $default = '' ) {
		$options = get_option( 'argp_settings', array() );
		return isset( $options[ $key ] ) ? $options[ $key ] : $default;
	}

	/**
	 * PHASE 5: Vérifie si le mode debug est activé
	 *
	 * @return bool True si debug activé.
	 */
	public static function is_debug_enabled() {
		return (bool) self::get_option( 'enable_debug', false );
	}

	/**
	 * PHASE 5: Log un message si debug activé
	 *
	 * @param string $message Message à logger.
	 * @param string $level   Niveau (info, warning, error).
	 */
	public static function log( $message, $level = 'info' ) {
		if ( ! self::is_debug_enabled() ) {
			return;
		}

		$formatted = sprintf(
			'[AI Recipe Generator Pro] [%s] %s',
			strtoupper( $level ),
			$message
		);

		error_log( $formatted );
	}
}
