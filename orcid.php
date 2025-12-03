<?php
/**
 * Plugin Name: ORCID
 * Plugin URI: https://github.com/latinvm/wordpress-orcid
 * Description: This plugin adds a field for ORCID to users posts and comments
 * Version: 1.0.0
 * Author: Roy Boverhof
 * Author URI: https://www.elsevier.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: orcid
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package ORCID
 */

/*
 * Copyright 2014-2025 Roy Boverhof (email: r.boverhof@elsevier.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'ORCID_VERSION', '1.0.0' );
define( 'ORCID_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ORCID_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ORCID_ASSETS_URL', ORCID_PLUGIN_URL . 'assets/' );

/**
 * Main ORCID Plugin Class.
 *
 * @since 1.0.0
 */
class WP_ORCID {

	/**
	 * Plugin instance.
	 *
	 * @var WP_ORCID|null
	 */
	private static ?WP_ORCID $instance = null;

	/**
	 * Get plugin instance (singleton).
	 *
	 * @return WP_ORCID
	 */
	public static function get_instance(): WP_ORCID {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Load text domain for translations.
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Enqueue scripts and styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Comment functionality.
		if ( 'on' === get_option( 'add-orcid-to-comments', 'on' ) ) {
			add_filter( 'comment_form_default_fields', array( $this, 'add_comment_orcid_field' ) );
			add_action( 'comment_post', array( $this, 'save_comment_orcid' ) );
			add_filter( 'comment_text', array( $this, 'display_comment_orcid' ) );
		}

		// User profile hooks.
		add_action( 'user_new_form', array( $this, 'show_user_orcid_field' ) );
		add_action( 'edit_user_profile', array( $this, 'show_user_orcid_field' ) );
		add_action( 'show_user_profile', array( $this, 'show_user_orcid_field' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_orcid' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_admin_user_orcid' ) );
		add_action( 'user_register', array( $this, 'save_admin_user_orcid' ) );

		// Admin settings.
		add_action( 'admin_menu', array( $this, 'add_settings_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Content output.
		add_filter( 'the_content', array( $this, 'add_orcid_to_content' ) );

		// Shortcode support.
		if ( get_option( 'use-orcid-shortcode' ) ) {
			$shortcode_name = get_option( 'orcid-shortcode', 'ORCID' );
			add_shortcode( sanitize_key( $shortcode_name ), array( $this, 'render_shortcode' ) );
		}

		// Enable shortcodes in widgets.
		add_filter( 'widget_text', 'do_shortcode' );

		// AJAX handlers for ORCID validation.
		add_action( 'wp_ajax_validate_orcid', array( $this, 'ajax_validate_orcid' ) );
		add_action( 'wp_ajax_nopriv_validate_orcid', array( $this, 'ajax_validate_orcid' ) );
	}

	/**
	 * Load plugin text domain for translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'orcid', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Enqueue plugin assets.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style(
			'orcid-style',
			ORCID_ASSETS_URL . 'orcid.css',
			array(),
			ORCID_VERSION
		);

		wp_enqueue_script(
			'orcid-script',
			ORCID_ASSETS_URL . 'orcid.js',
			array( 'jquery' ),
			ORCID_VERSION,
			true
		);

		// Pass data to JavaScript.
		wp_localize_script(
			'orcid-script',
			'orcidData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'orcid_validation_nonce' ),
				'i18n'    => array(
					'example'       => esc_html__( 'e.g. 0000-0002-7299-680X', 'orcid' ),
					'youAre'        => esc_html__( 'You are: %s', 'orcid' ),
					'notFound'      => esc_html__( 'Your ORCID profile could not be found', 'orcid' ),
					'invalidFormat' => esc_html__( 'Invalid ORCID format', 'orcid' ),
				),
			)
		);
	}

	/**
	 * Add ORCID settings menu.
	 *
	 * @return void
	 */
	public function add_settings_menu(): void {
		add_options_page(
			__( 'ORCID for WordPress', 'orcid' ),
			__( 'ORCID for WordPress', 'orcid' ),
			'manage_options',
			'orcid-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		$settings = array(
			'add-orcid-to-posts',
			'add-orcid-to-pages',
			'add-orcid-to-comments',
			'use-orcid-shortcode',
			'orcid-shortcode',
			'orcid-display',
			'orcid-approve-comments',
			'orcid-html-position',
			'orcid-html-comments-position',
		);

		foreach ( $settings as $setting ) {
			register_setting(
				'orcid_settings_group',
				$setting,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
		}
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php" id="orcid-settings">
				<?php
				settings_fields( 'orcid_settings_group' );
				wp_nonce_field( 'orcid_settings_action', 'orcid_settings_nonce' );
				?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Automatically add ORCID to', 'orcid' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" name="add-orcid-to-posts" <?php checked( get_option( 'add-orcid-to-posts', 'on' ), 'on' ); ?> value="on">
									<?php esc_html_e( 'Posts', 'orcid' ); ?>
								</label><br>
								<label>
									<input type="checkbox" name="add-orcid-to-pages" <?php checked( get_option( 'add-orcid-to-pages' ), 'on' ); ?> value="on">
									<?php esc_html_e( 'Pages', 'orcid' ); ?>
								</label><br>
								<label>
									<input type="checkbox" name="add-orcid-to-comments" <?php checked( get_option( 'add-orcid-to-comments', 'on' ), 'on' ); ?> value="on">
									<?php esc_html_e( 'Comments', 'orcid' ); ?>
								</label><br>
								<label>
									<input type="checkbox" name="use-orcid-shortcode" <?php checked( get_option( 'use-orcid-shortcode' ), 'on' ); ?> value="on">
									<?php esc_html_e( 'Shortcode', 'orcid' ); ?>
								</label>
								[<input type="text" name="orcid-shortcode" value="<?php echo esc_attr( get_option( 'orcid-shortcode', 'ORCID' ) ); ?>" class="regular-text" style="width: 100px;">]
								<p class="description">
									<?php
									printf(
										/* translators: %1$s and %2$s are function names */
										esc_html__( 'You can insert ORCIDs into templates directly using %1$s and %2$s', 'orcid' ),
										'<code>the_orcid_author()</code>',
										'<code>the_orcid_comment_author()</code>'
									);
									?>
								</p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'ORCID position', 'orcid' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'ORCID position in posts', 'orcid' ); ?></legend>
								<label>
									<input type="radio" name="orcid-html-position" value="top" <?php checked( get_option( 'orcid-html-position', 'top' ), 'top' ); ?>>
									<?php esc_html_e( 'Top of posts', 'orcid' ); ?>
								</label><br>
								<label>
									<input type="radio" name="orcid-html-position" value="bottom" <?php checked( get_option( 'orcid-html-position', 'top' ), 'bottom' ); ?>>
									<?php esc_html_e( 'Bottom of posts', 'orcid' ); ?>
								</label>
							</fieldset>
							<br>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'ORCID position in comments', 'orcid' ); ?></legend>
								<label>
									<input type="radio" name="orcid-html-comments-position" value="top" <?php checked( get_option( 'orcid-html-comments-position', 'top' ), 'top' ); ?>>
									<?php esc_html_e( 'Top of comments', 'orcid' ); ?>
								</label><br>
								<label>
									<input type="radio" name="orcid-html-comments-position" value="bottom" <?php checked( get_option( 'orcid-html-comments-position', 'top' ), 'bottom' ); ?>>
									<?php esc_html_e( 'Bottom of comments', 'orcid' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Display text', 'orcid' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Display text options', 'orcid' ); ?></legend>
								<label>
									<input type="radio" name="orcid-display" value="numbers" <?php checked( get_option( 'orcid-display', 'numbers' ), 'numbers' ); ?>>
									<?php esc_html_e( 'Show ORCID numbers', 'orcid' ); ?>
								</label><br>
								<label>
									<input type="radio" name="orcid-display" value="names" <?php checked( get_option( 'orcid-display', 'numbers' ), 'names' ); ?>>
									<?php esc_html_e( "Show author's name", 'orcid' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Comment validation', 'orcid' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="orcid-approve-comments" <?php checked( get_option( 'orcid-approve-comments' ), 'on' ); ?> value="on">
								<?php esc_html_e( 'Automatically approve comments with valid ORCIDs', 'orcid' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save Changes', 'orcid' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Add ORCID field to comment form.
	 *
	 * @param array $fields Default comment form fields.
	 * @return array Modified fields.
	 */
	public function add_comment_orcid_field( array $fields ): array {
		$commenter = wp_get_current_commenter();
		$req       = get_option( 'require_name_email' );
		$aria_req  = $req ? ' aria-required="true" required' : '';
		$required  = $req ? '<span class="required" aria-hidden="true">*</span>' : '';

		$fields['author'] = sprintf(
			'<p class="comment-form-author"><label for="author">%s%s</label><input id="author" name="author" type="text" value="%s" size="30"%s></p>',
			esc_html__( 'Name', 'orcid' ),
			$required,
			esc_attr( $commenter['comment_author'] ),
			$aria_req
		);

		$fields['email'] = sprintf(
			'<p class="comment-form-email"><label for="email">%s%s</label><input id="email" name="email" type="email" value="%s" size="30"%s></p>',
			esc_html__( 'Email', 'orcid' ),
			$required,
			esc_attr( $commenter['comment_author_email'] ),
			$aria_req
		);

		$fields['url'] = sprintf(
			'<p class="comment-form-url"><label for="url">%s</label><input id="url" name="url" type="url" value="%s" size="30"></p>',
			esc_html__( 'Website', 'orcid' ),
			esc_attr( $commenter['comment_author_url'] )
		);

		$fields['orcid'] = sprintf(
			'<p class="comment-form-orcid">
				<label for="orcid">%s
					<img src="%s" id="orcid-success" class="orcid-icon" alt="%s">
					<img src="%s" id="orcid-failure" class="orcid-icon" alt="%s">
					<img src="%s" id="orcid-waiting" class="orcid-icon" alt="%s">
				</label>
				<input id="orcid" name="orcid" type="text" autocomplete="off">
				<span id="orcid-instructions" class="description">%s</span>
			</p>',
			esc_html__( 'ORCID', 'orcid' ),
			esc_url( ORCID_ASSETS_URL . 'orcid.png' ),
			esc_attr__( 'Valid ORCID', 'orcid' ),
			esc_url( ORCID_ASSETS_URL . 'close-icon.png' ),
			esc_attr__( 'Invalid ORCID', 'orcid' ),
			esc_url( ORCID_ASSETS_URL . 'orcid-waiting.gif' ),
			esc_attr__( 'Validating ORCID', 'orcid' ),
			esc_html__( 'Add your ORCID here. (e.g. 0000-0002-7299-680X)', 'orcid' )
		);

		return $fields;
	}

	/**
	 * Save ORCID metadata for a comment.
	 *
	 * @param int $comment_id The comment ID.
	 * @return void
	 */
	public function save_comment_orcid( int $comment_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Comment form nonce handled by WordPress core.
		if ( ! isset( $_POST['orcid'] ) || '' === $_POST['orcid'] ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$orcid = sanitize_text_field( wp_unslash( $_POST['orcid'] ) );
		$orcid = $this->extract_orcid_id( $orcid );

		if ( ! $this->is_valid_orcid_format( $orcid ) ) {
			return;
		}

		add_comment_meta( $comment_id, 'orcid', $orcid );

		$api = new ORCID_API( $orcid );
		if ( $api->is_connected() ) {
			add_comment_meta( $comment_id, 'orcid-name', sanitize_text_field( $api->get_name() ) );

			if ( get_option( 'orcid-approve-comments' ) ) {
				wp_set_comment_status( $comment_id, 'approve' );
			}
		} else {
			add_comment_meta( $comment_id, 'orcid-name', '' );
		}
	}

	/**
	 * Extract ORCID ID from a URL or string.
	 *
	 * @param string $input User input.
	 * @return string The ORCID ID.
	 */
	private function extract_orcid_id( string $input ): string {
		$pos = strpos( $input, 'orcid.org/' );
		if ( false !== $pos ) {
			return substr( $input, $pos + 10 );
		}
		return $input;
	}

	/**
	 * Validate ORCID format.
	 *
	 * @param string $orcid The ORCID to validate.
	 * @return bool True if valid format.
	 */
	private function is_valid_orcid_format( string $orcid ): bool {
		// ORCID format: 0000-0000-0000-000X (where X can be 0-9 or X).
		return (bool) preg_match( '/^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{3}[0-9X]$/', $orcid );
	}

	/**
	 * Show ORCID field on user profile.
	 *
	 * @param WP_User|string $user User object or string for new user form.
	 * @return void
	 */
	public function show_user_orcid_field( $user ): void {
		$orcid = '';
		if ( $user instanceof WP_User ) {
			$orcid = get_user_meta( $user->ID, 'orcid', true );
		}

		wp_nonce_field( 'orcid_user_profile_action', 'orcid_user_nonce' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="orcid"><?php esc_html_e( 'ORCID', 'orcid' ); ?></label></th>
				<td>
					<input type="text" id="orcid" name="orcid" class="regular-text" value="<?php echo esc_attr( $orcid ); ?>">
					<img src="<?php echo esc_url( ORCID_ASSETS_URL . 'orcid.png' ); ?>" id="orcid-success" class="orcid-icon" alt="<?php esc_attr_e( 'Valid ORCID', 'orcid' ); ?>">
					<img src="<?php echo esc_url( ORCID_ASSETS_URL . 'close-icon.png' ); ?>" id="orcid-failure" class="orcid-icon" alt="<?php esc_attr_e( 'Invalid ORCID', 'orcid' ); ?>">
					<img src="<?php echo esc_url( ORCID_ASSETS_URL . 'orcid-waiting.gif' ); ?>" id="orcid-waiting" class="orcid-icon" alt="<?php esc_attr_e( 'Validating ORCID', 'orcid' ); ?>">
					<p class="description" id="orcid-instructions"><?php esc_html_e( 'Add your ORCID here. (e.g. 0000-0002-7299-680X)', 'orcid' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save user's own ORCID.
	 *
	 * @param int $user_id The user ID.
	 * @return void
	 */
	public function save_user_orcid( int $user_id ): void {
		if ( ! isset( $_POST['orcid_user_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['orcid_user_nonce'] ) ), 'orcid_user_profile_action' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$this->update_user_orcid( $user_id );
	}

	/**
	 * Save user ORCID from admin pages.
	 *
	 * @param int $user_id The user ID.
	 * @return void
	 */
	public function save_admin_user_orcid( int $user_id ): void {
		if ( ! isset( $_POST['orcid_user_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['orcid_user_nonce'] ) ), 'orcid_user_profile_action' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		$this->update_user_orcid( $user_id );
	}

	/**
	 * Update user ORCID metadata.
	 *
	 * @param int $user_id The user ID.
	 * @return void
	 */
	private function update_user_orcid( int $user_id ): void {
		if ( ! isset( $_POST['orcid'] ) ) {
			return;
		}

		$orcid = sanitize_text_field( wp_unslash( $_POST['orcid'] ) );
		$orcid = $this->extract_orcid_id( $orcid );

		update_user_meta( $user_id, 'orcid', $orcid );

		if ( $orcid && $this->is_valid_orcid_format( $orcid ) ) {
			$api = new ORCID_API( $orcid );
			if ( $api->is_connected() ) {
				update_user_meta( $user_id, 'orcid-name', sanitize_text_field( $api->get_name() ) );
			}
		}
	}

	/**
	 * Display ORCID in comment text.
	 *
	 * @param string $text The comment text.
	 * @return string Modified comment text.
	 */
	public function display_comment_orcid( string $text ): string {
		$field = new ORCID_Field( 'comment' );

		if ( ! $field->get_orcid() ) {
			return $text;
		}

		$position = get_option( 'orcid-html-comments-position', 'top' );
		if ( 'bottom' === $position ) {
			return $text . $field->get_html();
		}

		return $field->get_html() . $text;
	}

	/**
	 * Add ORCID to post/page content.
	 *
	 * @param string $content The post content.
	 * @return string Modified content.
	 */
	public function add_orcid_to_content( string $content ): string {
		// Only run on frontend, not in feeds, admin, or REST API contexts.
		if ( is_feed() || is_admin() || wp_is_serving_rest_request() ) {
			return $content;
		}

		// Only run in the main query to avoid duplication in widgets/sidebars.
		if ( ! is_main_query() || ! in_the_loop() ) {
			return $content;
		}

		// Prevent duplicate insertion (e.g., from FeedWordPress or caching plugins).
		if ( strpos( $content, 'class="wp_orcid_field"' ) !== false ) {
			return $content;
		}

		$post_type = get_post_type();

		if ( 'post' === $post_type && ! get_option( 'add-orcid-to-posts', 'on' ) ) {
			return $content;
		}

		if ( 'page' === $post_type && ! get_option( 'add-orcid-to-pages' ) ) {
			return $content;
		}

		if ( ! in_array( $post_type, array( 'post', 'page' ), true ) ) {
			return $content;
		}

		$field = new ORCID_Field( 'author' );

		if ( ! $field->get_orcid() ) {
			return $content;
		}

		$position = get_option( 'orcid-html-position', 'top' );
		if ( 'bottom' === $position ) {
			return $content . $field->get_html();
		}

		return $field->get_html() . $content;
	}

	/**
	 * Render ORCID shortcode.
	 *
	 * @return string The shortcode output.
	 */
	public function render_shortcode(): string {
		$field = new ORCID_Field( 'author' );
		return $field->get_html();
	}

	/**
	 * AJAX handler for ORCID validation.
	 *
	 * @return void
	 */
	public function ajax_validate_orcid(): void {
		check_ajax_referer( 'orcid_validation_nonce', 'nonce' );

		if ( ! isset( $_POST['orcid'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No ORCID provided', 'orcid' ) ) );
		}

		$orcid = sanitize_text_field( wp_unslash( $_POST['orcid'] ) );
		$orcid = $this->extract_orcid_id( $orcid );

		if ( ! $this->is_valid_orcid_format( $orcid ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid ORCID format', 'orcid' ) ) );
		}

		$api = new ORCID_API( $orcid );

		if ( $api->is_connected() ) {
			wp_send_json_success(
				array(
					'name'  => sanitize_text_field( $api->get_name() ),
					'orcid' => $orcid,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'ORCID profile not found', 'orcid' ) ) );
		}
	}
}

/**
 * ORCID API Handler Class.
 *
 * @since 1.0.0
 */
class ORCID_API {

	/**
	 * Whether the API connection was successful.
	 *
	 * @var bool
	 */
	private bool $connected = false;

	/**
	 * The name from the ORCID profile.
	 *
	 * @var string
	 */
	private string $name = '';

	/**
	 * Constructor.
	 *
	 * @param string $orcid The ORCID to look up.
	 */
	public function __construct( string $orcid ) {
		$this->fetch_profile( $orcid );
	}

	/**
	 * Fetch ORCID profile from API.
	 *
	 * @param string $orcid The ORCID ID.
	 * @return void
	 */
	private function fetch_profile( string $orcid ): void {
		// Use the modern ORCID public API with HTTPS.
		$url = 'https://pub.orcid.org/v3.0/' . $orcid . '/record';

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Accept' => 'application/json',
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			return;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return;
		}

		$this->connected = true;
		$this->name      = $this->extract_name( $data );
	}

	/**
	 * Extract name from ORCID API response.
	 *
	 * @param array $data The API response data.
	 * @return string The extracted name.
	 */
	private function extract_name( array $data ): string {
		// Try credit-name first.
		if ( ! empty( $data['person']['name']['credit-name']['value'] ) ) {
			return $data['person']['name']['credit-name']['value'];
		}

		// Fall back to given + family name.
		$given  = $data['person']['name']['given-names']['value'] ?? '';
		$family = $data['person']['name']['family-name']['value'] ?? '';

		if ( $given || $family ) {
			return trim( $given . ' ' . $family );
		}

		return '';
	}

	/**
	 * Check if API connection was successful.
	 *
	 * @return bool
	 */
	public function is_connected(): bool {
		return $this->connected;
	}

	/**
	 * Get the name from the ORCID profile.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}
}

/**
 * ORCID Field Output Class.
 *
 * @since 1.0.0
 */
class ORCID_Field {

	/**
	 * The field type (comment or author).
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * The ORCID ID.
	 *
	 * @var string
	 */
	private string $orcid = '';

	/**
	 * The ORCID name.
	 *
	 * @var string
	 */
	private string $orcid_name = '';

	/**
	 * Constructor.
	 *
	 * @param string $type The field type (comment or author).
	 * @throws InvalidArgumentException If invalid type provided.
	 */
	public function __construct( string $type ) {
		if ( ! in_array( $type, array( 'comment', 'author' ), true ) ) {
			throw new InvalidArgumentException( 'Invalid ORCID_Field type. Must be "comment" or "author".' );
		}

		$this->type = $type;
		$this->load_data();
	}

	/**
	 * Load ORCID data based on type.
	 *
	 * @return void
	 */
	private function load_data(): void {
		if ( 'comment' === $this->type ) {
			$this->load_comment_data();
		} else {
			$this->load_author_data();
		}
	}

	/**
	 * Load ORCID data for a comment.
	 *
	 * @return void
	 */
	private function load_comment_data(): void {
		$comment_id = get_comment_ID();

		$this->orcid      = get_comment_meta( $comment_id, 'orcid', true );
		$this->orcid_name = get_comment_meta( $comment_id, 'orcid-name', true );

		if ( ! $this->orcid ) {
			// Try to get from user metadata if commenter is a registered user.
			$comment = get_comment( $comment_id );
			if ( $comment && $comment->user_id ) {
				$this->orcid      = get_user_meta( $comment->user_id, 'orcid', true );
				$this->orcid_name = get_user_meta( $comment->user_id, 'orcid-name', true );
			}
		}
	}

	/**
	 * Load ORCID data for a post author.
	 *
	 * @return void
	 */
	private function load_author_data(): void {
		$this->orcid      = get_the_author_meta( 'orcid' );
		$this->orcid_name = get_the_author_meta( 'orcid-name' );
	}

	/**
	 * Get the ORCID ID.
	 *
	 * @return string
	 */
	public function get_orcid(): string {
		return $this->orcid;
	}

	/**
	 * Get the ORCID name.
	 *
	 * @return string
	 */
	public function get_orcid_name(): string {
		return $this->orcid_name;
	}

	/**
	 * Get the HTML output for the ORCID field.
	 *
	 * @return string
	 */
	public function get_html(): string {
		if ( ! $this->orcid ) {
			return '';
		}

		$display_text = $this->orcid;

		if ( 'names' === get_option( 'orcid-display' ) && $this->orcid_name ) {
			$display_text = $this->orcid_name;
		}

		$html = sprintf(
			'<div class="wp_orcid_field"><a href="%s" target="_blank" rel="author noopener">%s</a></div>',
			esc_url( 'https://orcid.org/' . $this->orcid ),
			esc_html( $display_text )
		);

		/**
		 * Filter the ORCID field HTML output.
		 *
		 * @param string $html         The HTML output.
		 * @param string $orcid        The ORCID ID.
		 * @param string $display_text The display text.
		 * @param string $type         The field type (comment or author).
		 */
		return apply_filters( 'orcid_field_html', $html, $this->orcid, $display_text, $this->type );
	}
}

// Initialize the plugin.
WP_ORCID::get_instance();

/**
 * Template function to display comment author's ORCID.
 *
 * @return void
 */
function the_orcid_comment_author(): void {
	$field = new ORCID_Field( 'comment' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped in get_html().
	echo $field->get_html();
}

/**
 * Template function to display post author's ORCID.
 *
 * @return void
 */
function the_orcid_author(): void {
	$field = new ORCID_Field( 'author' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped in get_html().
	echo $field->get_html();
}
