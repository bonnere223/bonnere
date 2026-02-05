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
	}

	/**
	 * Callback de la section API Keys
	 */
	public function render_api_keys_section_callback() {
		echo '<p class="description">';
		esc_html_e( 'Configurez vos clés API pour OpenAI et Replicate. Ces clés sont nécessaires pour générer du contenu avec l\'IA.', 'ai-recipe-generator-pro' );
		echo '</p>';
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
	 * Affiche le champ OpenAI API Key
	 */
	public function render_openai_api_key_field() {
		$options = get_option( $this->option_name, array() );
		$value   = isset( $options['openai_api_key'] ) ? $options['openai_api_key'] : '';
		$masked  = ! empty( $value ) ? str_repeat( '•', 20 ) . substr( $value, -4 ) : '';

		?>
		<input 
			type="password" 
			id="argp_openai_api_key" 
			name="<?php echo esc_attr( $this->option_name ); ?>[openai_api_key]" 
			value="<?php echo esc_attr( $value ); ?>" 
			class="regular-text argp-api-key-field"
			placeholder="sk-..."
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
		$value   = isset( $options['replicate_api_key'] ) ? $options['replicate_api_key'] : '';
		$masked  = ! empty( $value ) ? str_repeat( '•', 20 ) . substr( $value, -4 ) : '';

		?>
		<input 
			type="password" 
			id="argp_replicate_api_key" 
			name="<?php echo esc_attr( $this->option_name ); ?>[replicate_api_key]" 
			value="<?php echo esc_attr( $value ); ?>" 
			class="regular-text argp-api-key-field"
			placeholder="r8_..."
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
	 * Sanitize les réglages avant sauvegarde
	 *
	 * @param array $input Données du formulaire.
	 * @return array Données nettoyées.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		// Sanitize OpenAI API Key
		if ( isset( $input['openai_api_key'] ) ) {
			$sanitized['openai_api_key'] = sanitize_text_field( $input['openai_api_key'] );
		}

		// Sanitize Replicate API Key
		if ( isset( $input['replicate_api_key'] ) ) {
			$sanitized['replicate_api_key'] = sanitize_text_field( $input['replicate_api_key'] );
		}

		// Sanitize Manual Titles (textarea)
		if ( isset( $input['manual_titles'] ) ) {
			$sanitized['manual_titles'] = sanitize_textarea_field( $input['manual_titles'] );
		}

		return $sanitized;
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
}
