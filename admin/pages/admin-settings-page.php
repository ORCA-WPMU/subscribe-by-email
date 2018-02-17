<?php

class Incsub_Subscribe_By_Email_Admin_Settings_Page extends Incsub_Subscribe_By_Email_Admin_Page {

	// Needed for registering settings
	private $settings_group;
	private $settings_name;

	// The settings
	private $settings;

	// Tabs
	private $tabs;

	public function __construct() {

		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/helpers/settings-page-helpers.php' );

		$subscribers_page = Incsub_Subscribe_By_Email::$admin_subscribers_page;

		$this->tabs = array(
			'general' => __( 'General Settings', INCSUB_SBE_LANG_DOMAIN ),
			'content' => __( 'Contents', INCSUB_SBE_LANG_DOMAIN ),
			'template' => __( 'Mail template', INCSUB_SBE_LANG_DOMAIN ),
			'extra-fields' => __( 'Custom Fields', INCSUB_SBE_LANG_DOMAIN )
		);

		$args = array(
			'slug' => 'sbe-settings',
			'page_title' => __( 'Settings', INCSUB_SBE_LANG_DOMAIN ),
			'menu_title' => __( 'Settings', INCSUB_SBE_LANG_DOMAIN ),
			'capability' => 'manage_subscribe_by_email',
			'parent' => $subscribers_page->get_menu_slug()
		);
		parent::__construct( $args );

		$this->settings_name = incsub_sbe_get_settings_slug();
		$this->settings_group = incsub_sbe_get_settings_slug();
		$this->settings = incsub_sbe_get_settings();

		add_action( 'admin_init', array( &$this, 'register_settings' ) );

		add_action( 'admin_init', array( &$this, 'restore_default_template' ) );

		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_styles' ) );

		add_action( 'admin_init', array( &$this, 'maybe_remove_extra_field' ) );

		add_filter( 'plugin_action_links_' . INCSUB_SBE_PLUGIN_FILE, array( &$this, 'add_plugin_list_link' ), 10 , 2 );

		add_action( 'wp_ajax_incsub_sbe_sort_extra_fields', array( &$this, 'sort_extra_fields' ) );
		add_action( 'wp_ajax_sbe_reload_preview_template', array( &$this, 'reload_preview_template' ) );

        add_filter( 'option_page_capability_incsub_sbe_settings', array( $this, 'set_options_capability' ) );

	}

    public function set_options_capability( $cap ) {
        return 'manage_subscribe_by_email';
    }

	public function add_plugin_list_link( $actions, $file ) {
		$new_actions = $actions;
		$new_actions['settings'] = '<a href="' . $this->get_permalink() . '" class="edit" title="' . __( 'Subscribe by Email Settings Page', INCSUB_SBE_LANG_DOMAIN ) . '">' . __( 'Settings', INCSUB_SBE_LANG_DOMAIN ) . '</a>';
		return $new_actions;
	}


	/**
	 * Enqueue needed scripts
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();
		if ( $screen->id == $this->get_page_id() ) {

			if ( 'general' == $this->get_current_tab() ) {
				wp_enqueue_script( 'sbe-settings-scripts', INCSUB_SBE_ASSETS_URL . 'js/settings-general.js', array( 'jquery' ), '20130721' );
			}
			elseif ( 'content' == $this->get_current_tab() ) {
				wp_enqueue_script( 'sbe-settings-scripts', INCSUB_SBE_ASSETS_URL . 'js/settings-content.js', array( 'jquery' ), '20130721' );
			}
			elseif ( 'template' == $this->get_current_tab() ) {
				wp_enqueue_media();
			    wp_enqueue_script( 'jquery-ui-slider' );
			    wp_enqueue_script( 'wp-color-picker' );
				wp_enqueue_script( 'sbe-settings-scripts', INCSUB_SBE_ASSETS_URL . 'js/settings-template.js', array( 'thickbox', 'media-upload' ), '20130721' );
			}
			elseif ( 'extra-fields' == $this->get_current_tab() ) {
				wp_enqueue_script( 'jquery-ui-sortable' );
			}


			$l10n = array(
				'title_text' => __( 'Upload a logo', INCSUB_SBE_LANG_DOMAIN ),
				'button_text' => __( 'Upload logo', INCSUB_SBE_LANG_DOMAIN )
			);
			wp_localize_script( 'sbe-settings-scripts', 'sbe_captions', $l10n );

		}
	}



	/**
	 * Enqueue needed styles
	 */
	public function enqueue_styles() {
		$screen = get_current_screen();
		if ( $screen->id == $this->get_page_id() ) {
			wp_enqueue_style( 'thickbox' );
			wp_enqueue_style( 'farbtastic' );

			if ( 'template' == $this->get_current_tab() ) {
				wp_enqueue_style( 'jquery-ui-css', INCSUB_SBE_ASSETS_URL .'css/jquery-ui/jquery-ui-1.10.3.custom.min.css' );
				wp_enqueue_style( 'wp-color-picker' );
			}

			if ( 'extra-fields' == $this->get_current_tab() )
				wp_enqueue_style( 'sbe-settings', INCSUB_SBE_ASSETS_URL .'css/settings.css' );
		}
	}

	public function maybe_remove_extra_field() {
		$screen = get_current_screen();
		if ( isset( $_GET['page'] ) && $this->get_menu_slug() == $_GET['page'] && $this->get_current_tab() == 'extra-fields' && isset( $_GET['remove'] ) ) {
			if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'remove_extra_field' ) )
				return false;

			$settings = incsub_sbe_get_settings();
			if ( isset( $settings['extra_fields'][ $_GET['remove'] ] ) ) {
				$meta_slug = $settings['extra_fields'][ $_GET['remove'] ]['slug'];
				unset( $settings['extra_fields'][ $_GET['remove'] ] );
				remove_filter( 'sanitize_option_' . $this->settings_name, array( &$this, 'sanitize_settings' ) );
				incsub_sbe_update_settings( $settings );
				add_filter( 'sanitize_option_' . $this->settings_name, array( &$this, 'sanitize_settings' ) );

				sbe_delete_all_subscribers_meta( $meta_slug );

				wp_redirect(
					add_query_arg(
						array(
							'tab' => 'extra-fields',
							'updated' => 'true'
						),
						$this->get_permalink()
					)
				);
			}
		}
	}

	/**
	 * Register the settings, sections and fields
	 */
	public function register_settings() {
		register_setting( $this->settings_group, $this->settings_name, array( &$this, 'sanitize_settings' ) );

		if ( $this->get_current_tab() == 'general' ) {

			add_settings_section( 'general-settings', __( 'General Settings', INCSUB_SBE_LANG_DOMAIN ), null, $this->get_menu_slug() );
			add_settings_field( 'from-sender', __( 'Notification From Sender', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_from_sender_field' ), $this->get_menu_slug(), 'general-settings' );

			if ( ! is_multisite() )
				add_settings_field( 'from-email', __( 'Notification From Email', INCSUB_SBE_LANG_DOMAIN ), 'incsub_sbe_render_from_email_field', $this->get_menu_slug(), 'general-settings' );

			add_settings_field( 'subject', __( 'Mail subject', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_subject_field' ), $this->get_menu_slug(), 'general-settings' );
			add_settings_field( 'frequency', __( 'Email Frequency', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_frequency_field' ), $this->get_menu_slug(), 'general-settings' );

			if ( ! is_multisite() )
				add_settings_field( 'mail_batch', __( 'Mail batches', INCSUB_SBE_LANG_DOMAIN ), 'incsub_sbe_render_mail_batches_field', $this->get_menu_slug(), 'general-settings' );

			add_settings_field( 'get-nofitications', __( 'Get notifications', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_get_notifications_field' ), $this->get_menu_slug(), 'general-settings' );

			add_settings_section( 'user-subs-page-settings', __( 'Subscription page', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_subscription_page_section' ), $this->get_menu_slug() );
			add_settings_field( 'user-subs-page', __( 'Subscribers Management Page', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_subscription_page_field' ), $this->get_menu_slug(), 'user-subs-page-settings' );

			add_settings_section( 'follow-button', __( 'Follow button', INCSUB_SBE_LANG_DOMAIN ), null, $this->get_menu_slug() );
			add_settings_field( 'follow-button-field', __( 'Display a follow button?', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_follow_button_field' ), $this->get_menu_slug(), 'follow-button' );
			add_settings_field( 'follow-button-position-field', __( 'Position', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_follow_button_position_field' ), $this->get_menu_slug(), 'follow-button' );
			add_settings_field( 'follow-button-schema-field', __( 'Schema', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_follow_button_schema_field' ), $this->get_menu_slug(), 'follow-button' );


			if ( ! is_multisite() ) {
				add_settings_section( 'logs-settings', __( 'Logs', INCSUB_SBE_LANG_DOMAIN ), null, $this->get_menu_slug() );
				add_settings_field( 'keep-logs-for', __( 'Keep logs files during', INCSUB_SBE_LANG_DOMAIN ), 'incsub_sbe_render_keep_logs_for_field', $this->get_menu_slug(), 'logs-settings' );
			}
		}
		elseif ( $this->get_current_tab() == 'content' ) {
			$settings_handler = Incsub_Subscribe_By_Email_Settings_Handler::get_instance();

			$post_types = $settings_handler->get_post_types();

				foreach ( $post_types as $post_type_slug => $post_type ) {
					add_settings_section( 'post-type-' . $post_type_slug . '-settings', $post_type->labels->name, null, $this->get_menu_slug() );
					add_settings_field( 'post-types' . $post_type_slug . '-send-content-field', __( 'Send this post type', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_send_content_field' ), $this->get_menu_slug(), 'post-type-' . $post_type_slug . '-settings', array( 'post_type_slug' => $post_type_slug, 'post_type_name' => $post_type->labels->name ) );

					$taxonomies = $settings_handler->get_taxonomies_by_post_type( $post_type_slug );
					foreach ( $taxonomies as $tax_slug => $taxonomy ) {
						add_settings_field( 'post-types' . $post_type_slug . '-tax-' . $tax_slug, $taxonomy->labels->name, array( &$this, 'render_send_content_taxonomy_field' ), $this->get_menu_slug(), 'post-type-' . $post_type_slug . '-settings', array( 'taxonomy_slug' => $tax_slug, 'taxonomy' => $taxonomy, 'post_type_slug' => $post_type_slug ) );
					}

				}
		}
		elseif ( $this->get_current_tab() == 'template' ) {
			add_settings_section( 'preview-settings', '', array( $this, 'render_preview_section' ), $this->get_menu_slug() );

			add_settings_section( 'subscribe-email-settings', __( 'Subscribe Email', INCSUB_SBE_LANG_DOMAIN ), array( $this, 'render_subscribe_email_section' ), $this->get_menu_slug() );
			add_settings_field( 'subscribe-email-content', __( 'Subscribe Email Content', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_subscribe_email_content' ), $this->get_menu_slug(), 'subscribe-email-settings' );

		}
		elseif ( $this->get_current_tab() == 'extra-fields' ) {
			add_settings_section( 'custom-fields', __( 'Custom Fields', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_extra_fields_section' ), $this->get_menu_slug() );
			add_settings_field( 'custom-fields-meta', __( 'Subscribers custom fields', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_subscribers_extra_fields_field' ), $this->get_menu_slug(), 'custom-fields' );
		}

		do_action( 'sbe_register_settings', $this->get_current_tab(), $this->settings_group, $this->settings_name, $this->get_menu_slug() );

	}

	private function get_current_tab() {
		if ( isset( $_GET['tab'] ) && array_key_exists( $_GET['tab'], $this->tabs ) ) {
			return $_GET['tab'];
		}
		else {
			return 'general';
		}
	}

	private function the_tabs() {
		$current_tab = $this->get_current_tab();

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $this->tabs as $key => $name ): ?>
			<a href="?page=<?php echo $this->get_menu_slug(); ?>&tab=<?php echo $key; ?>" class="nav-tab <?php echo $current_tab == $key ? 'nav-tab-active' : ''; ?>"><?php echo $name; ?></a>
		<?php endforeach;

		echo '</h2>';

	}

	public function render_page() {

		if ( ! current_user_can( $this->get_capability() ) )
			wp_die( __( 'You do not have enough permissions to access to this page', INCSUB_SBE_LANG_DOMAIN ) );

		?>
			<div class="wrap">

				<?php screen_icon( 'sbe' ); ?>

				<?php $this->the_tabs(); ?>

				<?php $this->render_content(); ?>

			</div>

		<?php

	}


	/**
	 * render the settings page
	 */
	public function render_content() {

		settings_errors( $this->settings_name );
		if ( isset( $_GET['settings-updated'] ) ) {
			?>
				<div class="updated"><p><?php _e( 'Settings updated', INCSUB_SBE_LANG_DOMAIN ); ?></p></div>
			<?php
		}
		?>

			<form action="options.php" method="post" id="sbe-settings-form">
				<?php settings_fields( $this->settings_group ); ?>
				<?php do_settings_sections( $this->get_menu_slug() ); ?>

				<?php if ( 'extra-fields' != $this->get_current_tab() ): ?>
					<p class="submit">
						<?php submit_button( null, 'primary', $this->settings_name . '[submit_settings_' . $this->get_current_tab() . ']', false, array( 'id' => 'submit_settings_' . $this->get_current_tab() ) ) ?>
					</p>
				<?php endif; ?>
			</form>

		<?php
	}

	/********************************/
	/* 		FIELDS RENDERINGS		*/
	/********************************/

	/**
	 * Auto Subscribe field
	 */
	public function render_auto_subscribe_field() {

		?>
			<label for="auto_subscribe_yes">
				<input id="auto_subscribe_yes" type="radio" name="<?php echo $this->settings_name; ?>[auto_subscribe]" value="yes" <?php checked( $this->settings['auto-subscribe'], true ); ?>>
				<?php _e( 'Yes', INCSUB_SBE_LANG_DOMAIN ); ?>
			</label><br/>
			<label for="auto_subscribe_no">
				<input id="auto_subscribe_no" type="radio" name="<?php echo $this->settings_name; ?>[auto_subscribe]" value="no" <?php checked( $this->settings['auto-subscribe'], false ); ?>>
				<?php _e( 'No', INCSUB_SBE_LANG_DOMAIN ); ?>
			</label><br/>
			<span class="description"><?php _e( 'Subscribe users without sending a confirmation mail.', INCSUB_SBE_LANG_DOMAIN ); ?></span>
		<?php
	}



	/**
	 * From Sender field
	 */
	public function render_from_sender_field() {
		?>
			<input type="text" name="<?php echo $this->settings_name; ?>[from_sender]" class="regular-text" value="<?php echo esc_attr( $this->settings['from_sender'] ); ?>">
		<?php
	}



	/**
	 * Subject field
	 */
	public function render_subject_field() {
		?>
			<input type="text" name="<?php echo $this->settings_name; ?>[subject]" class="regular-text" value="<?php echo esc_attr( $this->settings['subject'] ); ?>"><br/>
			<span><?php _e( 'You can use the <strong>%title%</strong> wildcard to show the latest post title/s, they will be shortened to no more than 50 charactes', INCSUB_SBE_LANG_DOMAIN ); ?></span>
		<?php
	}

	public function render_get_notifications_field() {
		?>
			<label for="get-notifications">
				<input id="get-notifications" type="checkbox" name="<?php echo $this->settings_name; ?>[get_notifications]" <?php checked( $this->settings['get_notifications'] ); ?> />
				<?php _e( "If checked, the following role will get email notifications when there's a new subscriber or when someone ends their subscription", INCSUB_SBE_LANG_DOMAIN ); ?>
			</label>

			<select name="<?php echo $this->settings_name; ?>[get_notifications_role]" id="get-notifications-role">
				<?php echo wp_dropdown_roles( $this->settings['get_notifications_role'] ); ?>
			</select>

		<?php
	}

	/**
	 * Frequency field
	 */
	public function render_frequency_field() {
		$time_format = get_option( 'time_format', 'H:i' );

		?>
			<select name="<?php echo $this->settings_name; ?>[frequency]" id="frequency-select">
				<?php foreach ( incsub_sbe_get_digest_frequency() as $key => $freq ): ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $this->settings['frequency'] ); ?>><?php echo $freq; ?></option>
				<?php endforeach; ?>
			</select>
			<br/><br/>

			<div id="time-wrap">
				<label for="time-select"><?php _e( 'What time should the digest email be sent?', INCSUB_SBE_LANG_DOMAIN ); ?>
					<select name="<?php echo $this->settings_name; ?>[daily-time]" id="time-select">
						<?php foreach ( incsub_sbe_get_digest_times() as $key => $t ): ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $this->settings['time'] ); ?>><?php echo $t; ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<span class="description"><?php printf( __( 'The time now is %s', INCSUB_SBE_LANG_DOMAIN ), date_i18n( $time_format, current_time( 'timestamp' ) ) ); ?></span>
			</div>

			<div id="day-of-week-wrap">
				<label for="day-of-week-select"><?php _e( 'What day of the week should the digest email be sent?', INCSUB_SBE_LANG_DOMAIN ); ?>
					<select name="<?php echo $this->settings_name; ?>[day_of_week]" id="day-of-week-select">
						<?php foreach ( incsub_sbe_get_digest_days_of_week() as $key => $day ): ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $this->settings['day_of_week'] ); ?>><?php echo $day; ?></option>
						<?php endforeach; ?>
					</select>
				</label><br/>
				<label for="time-select"><?php _e( 'What time should the digest email be sent?', INCSUB_SBE_LANG_DOMAIN ); ?>
					<select name="<?php echo $this->settings_name; ?>[weekly-time]" id="time-select">
						<?php foreach ( incsub_sbe_get_digest_times() as $key => $t ): ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $this->settings['time'] ); ?>><?php echo $t; ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>

			<?php $next_scheduled = Incsub_Subscribe_By_Email::get_next_scheduled_date(); ?>
			<?php if ( $next_scheduled ): ?>
				<p><?php _e( 'Next digest will be sent on:', INCSUB_SBE_LANG_DOMAIN ); ?> <code><?php echo $next_scheduled; ?></code></p>
			<?php endif; ?>


		<?php
	}

	/**
	 * Post Types Section
	 */
	public function render_posts_types_section() {
		?>
			<p><?php _e( 'Check those Post Types that you want to send to your subscribers.', INCSUB_SBE_LANG_DOMAIN ); ?></p>
		<?php
	}

	public function render_send_content_field( $args ) {
		extract( $args );
		?>
			<label>
				<input class="post-type-checkbox" data-post-slug="<?php echo $post_type_slug; ?>" type="checkbox" <?php checked( in_array( $post_type_slug, $this->settings['post_types'] ) ); ?> name="<?php echo $this->settings_name; ?>[post_types][]" value="<?php echo $post_type_slug; ?>"> <?php printf( __( '%s will be included in the digests', INCSUB_SBE_LANG_DOMAIN ), $post_type_name ); ?>
			</label>
		<?php
	}

	public function render_send_content_taxonomy_field( $args ) {
		extract( $args );

		// All categories checkbox is checked?
		$all_checked = (
			( ! isset( $this->settings['taxonomies'][ $post_type_slug ][ $taxonomy_slug ] ) )
			|| ( in_array( 'all', $this->settings['taxonomies'][ $post_type_slug ][ $taxonomy_slug ] ) )
			|| ( empty( $this->settings['taxonomies'][ $post_type_slug ][ $taxonomy_slug ] ) )
		);

		// Checkboxes are disabled?
		$disabled = ! in_array( $post_type_slug, $this->settings['post_types'] );

		$base_name = $this->settings_name . '[tax_input]';

		if ( isset( $this->settings['taxonomies'][ $post_type_slug ][ $taxonomy_slug ] ) && is_array( $this->settings['taxonomies'][ $post_type_slug ][ $taxonomy_slug ] ) && ! $all_checked ) {
			$selected_cats = $this->settings['taxonomies'][ $post_type_slug ][ $taxonomy_slug ];
		}
		else {
			$selected_cats = array();
		}

		?>
			<p><?php printf( __( 'Choose between these %s:', INCSUB_SBE_LANG_DOMAIN ), $taxonomy->labels->name ); ?></p>
			<div id="poststuff" style="width:280px;margin-left:0;padding-top:0">
        		<div id="<?php echo $taxonomy_slug; ?>-categorydiv" class="postbox ">
					<h3 class="hndle"><span><?php echo $taxonomy->labels->name; ?></span></h3>
					<div class="inside">
						<div id="taxonomy-<?php echo $taxonomy_slug; ?>" class="categorydiv">
							<div id="<?php echo $taxonomy_slug; ?>-all" class="tabs-panel">
								<ul id="<?php echo $taxonomy_slug; ?>checklist" class="<?php echo $taxonomy_slug; ?>checklist form-no-clear">
									<li id="<?php echo $taxonomy_slug; ?>-all"><label class="selectit"><input class="settings-term-checkbox <?php echo $post_type_slug; ?>-checkbox" value="all" type="checkbox" <?php checked( $all_checked ); ?> <?php disabled( $disabled ); ?> name="<?php echo $base_name; ?>[<?php echo $post_type_slug; ?>][<?php echo $taxonomy_slug; ?>][]" id="in-<?php echo $taxonomy_slug; ?>-all"> <strong><?php _e( 'All', INCSUB_SBE_LANG_DOMAIN ); ?></strong></label></li>
									<?php
										require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/walker-terms-checklist.php' );
										$walker = new Walker_SBE_Terms_Checklist;

										sbe_terms_checklist(
											0,
											array(
												'taxonomy' => $taxonomy_slug,
												'walker' => $walker,
												'disabled' => $disabled,
												'taxonomy_slug' => $taxonomy_slug,
												'post_type_slug' => $post_type_slug,
												'base_name' => $base_name,
												'selected_cats' => $selected_cats
											)
										); ?>
								</ul>
							</div>

						</div>
					</div>
				</div>
			</div>
		<?php
	}


	public function render_subscription_page_section() {
		?><p><?php _e( 'You can select a page where users will be able to subscribe/unsubscribe to any post type', INCSUB_SBE_LANG_DOMAIN ); ?></p><?php
	}


	public function render_subscription_page_field() {
		$args = array(
			'show_option_none' => __( '--Select a page--', INCSUB_SBE_LANG_DOMAIN ),
			'selected' => $this->settings['manage_subs_page'],
			'option_none_value' => 0,
			'name' => $this->settings_name . '[manage_subs_page]',
			'id' => 'manage_subs_page_selector'
		);
		wp_dropdown_pages( $args );
		?>
			 <span class="description"><?php _e( 'After a page is selected, the management form will be appended to the content of the page', INCSUB_SBE_LANG_DOMAIN ); ?></span>
			 <p><?php _e( "Users will receive a link to this page via email.", INCSUB_SBE_LANG_DOMAIN ); ?></p>
		<?php
	}

	public function render_follow_button_field() {
		?>
			<label>
				<input type="checkbox" name="<?php echo $this->settings_name; ?>[follow_button]" <?php checked( $this->settings['follow_button'] ); ?> />
				<?php _e( 'Will place a follow button permanently in the selected position of your site.', INCSUB_SBE_LANG_DOMAIN ); ?>
			</label>
		<?php
	}

	public function render_follow_button_schema_field() {
		$settings = incsub_sbe_get_settings();
		$schemas = incsub_sbe_get_follow_button_schemas();
		foreach ( $schemas as $schema ) {
			?>
				<label>
					<input type="radio" name="<?php echo $this->settings_name; ?>[follow_button_schema]" value="<?php echo esc_attr( $schema['slug'] ); ?>" <?php checked( $this->settings['follow_button_schema'] == $schema['slug'] ); ?> />
					<?php echo $schema['label']; ?>
				</label><br/>
			<?php
		}

	}

	public function render_follow_button_position_field() {
		$settings = incsub_sbe_get_settings();
		$positions = incsub_sbe_get_follow_button_positions();
		foreach ( $positions as $position ) {
			?>
				<label>
					<input type="radio" name="<?php echo $this->settings_name; ?>[follow_button_position]" value="<?php echo esc_attr( $position['slug'] ); ?>" <?php checked( $this->settings['follow_button_position'] == $position['slug'] ); ?> />
					<?php echo $position['label']; ?>
				</label><br/>
			<?php
		}

	}

	private function preview_modal() {
		$display_preview = empty( $this->settings['logo'] ) ? 'display:none' : '';
		?>
		<div id="sbe-preview-modal" class="hidden">
				<div class="media-modal wp-core-ui">
					<a class="media-modal-close" href="#">
						<span class="media-modal-icon"><span class="screen-reader-text"><?php _e( 'Close Preview Template Panel', INCSUB_SBE_LANG_DOMAIN ); ?></span></span>
					</a>

					<div class="media-modal-content">
						<div class="media-frame mode-select wp-core-ui hide-menu">
							<div class="media-frame-title">
								<h1><?php _e( 'Preview Digests Template', INCSUB_SBE_LANG_DOMAIN ); ?><span class="dashicons dashicons-arrow-down"></span></h1>
							</div>

							<div class="media-frame-content" data-columns="10">
								<div class="attachments-browser">

									<div class="sbe-modal-preview-content">
										<div id="sbe-modal-email-preview">
											<?php $this->render_email_inner_preview(); ?>
										</div>
										<div id="preview-section-backdrop-wrapper">
											<div id="preview-section-backdrop">
											</div>
										</div>
									</div>
									<div class="media-sidebar">
										<div class="sbe-modal-sidebar-section">
											<h3><?php _e( 'Logo', INCSUB_SBE_LANG_DOMAIN ); ?></h3>
											<div class="sbe-modal-sidebar-field">
												<input type="hidden" name="<?php echo $this->settings_name; ?>[logo]" id="upload-logo-value" value="<?php echo esc_url( $this->settings['logo'] ); ?>">
												<a href="#" id="upload-logo" data-frame-title="<?php echo esc_attr( 'Select a template logo', INCSUB_SBE_LANG_DOMAIN ); ?>" data-frame-update="<?php echo esc_attr( 'Select this logo', INCSUB_SBE_LANG_DOMAIN ); ?>"><?php _e( 'Upload logo', INCSUB_SBE_LANG_DOMAIN ); ?></a>
												<div class="sbe-remove-logo-wrap">
													| <a href="#" id="remove-logo" class="delete-attachment"><?php _e( 'Remove logo', INCSUB_SBE_LANG_DOMAIN ); ?></a>
												</div>
												<div class="sbe-logo-preview">
													<img style="max-width:100px;margin-top:20px;<?php echo $display_preview; ?>" id="sbe-logo-img" src="<?php echo esc_url( $this->settings['logo'] ); ?>"></img>
												</div>

											</div>
											<div class="sbe-modal-sidebar-field">
												<label class="big-label" for="logo-width"><?php _e( 'Logo max width in pixels', INCSUB_SBE_LANG_DOMAIN ); ?></label>
												<div style="max-width:100%;" id="logo-width-slider"></div><br/>
												<p id="logo-width-caption"><span id="logo-width-quantity"><?php echo $this->settings['logo_width']; ?></span> <span class="description">px</span></p>
												<input type="hidden" class="small-text" name="<?php echo $this->settings_name; ?>[logo_width]" id="logo-width" value="<?php echo $this->settings['logo_width']; ?>" />
											</div>
										</div>

										<div class="sbe-modal-sidebar-section">
											<h3><?php _e( 'Header', INCSUB_SBE_LANG_DOMAIN ); ?></h3>
											<div class="sbe-modal-sidebar-field">
												<input type="checkbox" name="<?php echo $this->settings_name; ?>[show_blog_name]" <?php checked( $this->settings['show_blog_name'] ); ?> id="show-blog-name">
												<label for="show-blog-name"><?php _e( 'Show Blog Name', INCSUB_SBE_LANG_DOMAIN ); ?></label>
											</div>

											<div class="sbe-modal-sidebar-field">
												<label class="big-label" for="header-color"><?php _e( 'Background color', INCSUB_SBE_LANG_DOMAIN ); ?></label>
												<input type="text" class="colorpicker" id="header-color" data-styles-rule="background-color" data-styles-class="header-bg-color" name="<?php echo $this->settings_name; ?>[header_color]" value="<?php echo esc_attr( $this->settings['header_color'] ); ?>" />
											</div>
											<div class="sbe-modal-sidebar-field">
												<label class="big-label" for="header-text-color"><?php _e( 'Text color', INCSUB_SBE_LANG_DOMAIN ); ?></label>
												<input type="text" class="colorpicker" id="header-text-color" data-styles-rule="color" data-styles-class="header-text-color" name="<?php echo $this->settings_name; ?>[header_text_color]" value="<?php echo esc_attr( $this->settings['header_text_color'] ); ?>" />
											</div>

											<div class="sbe-modal-sidebar-field">
												<label class="big-label" for="header-text"><?php _e( 'Subtitle Text', INCSUB_SBE_LANG_DOMAIN ); ?></label>
												<textarea class="large-text" name="<?php echo $this->settings_name; ?>[header_text]" id="header-text" rows="4"><?php echo esc_textarea( $this->settings['header_text'] ); ?></textarea>
											</div>

										</div>

										<div class="sbe-modal-sidebar-section">
											<h3><?php _e( 'Content', INCSUB_SBE_LANG_DOMAIN ); ?></h3>
											<div class="sbe-modal-sidebar-field">
												<input type="checkbox" name="<?php echo $this->settings_name; ?>[featured_image]" id="featured-image" <?php checked( $this->settings['featured_image'] ); ?>>
												<label for="featured-image"><?php _e( 'Show featured images', INCSUB_SBE_LANG_DOMAIN ); ?></label>
											</div>

											<div class="sbe-modal-sidebar-field">
												<input type="checkbox" name="<?php echo $this->settings_name; ?>[send_full_post]" id="send-full-post" <?php checked( $this->settings['send_full_post'] ); ?>>
												<label for="send-full-post"><?php _e( 'Show full posts', INCSUB_SBE_LANG_DOMAIN ); ?></label>
											</div>
										</div>

										<div class="sbe-modal-sidebar-section">
											<h3><?php _e( 'Footer', INCSUB_SBE_LANG_DOMAIN ); ?></h3>
											<div class="sbe-modal-sidebar-field">
												<label for="footer-text"><?php _e( 'Footer Text', INCSUB_SBE_LANG_DOMAIN ); ?></label>
												<textarea class="large-text" name="<?php echo $this->settings_name; ?>[footer_text]" id="footer-text" rows="4"><?php echo esc_textarea( $this->settings['footer_text'] ); ?></textarea>
											</div>
										</div>

									</div>
								</div>
							</div>

							<div class="media-frame-toolbar">
								<div class="media-toolbar">
									<div class="media-toolbar-secondary">
										<div class="spinner"></div>
									</div>
									<div class="media-toolbar-primary">
										<input type="submit" name="<?php echo $this->settings_name; ?>[submit_settings_template]" id="submit-preview" class="button button-primary media-button" value="<?php esc_attr_e( 'Save Changes' ); ?>">
									</div>
								</div>
							</div>

						</div>
					</div>
				</div>
				<div class="media-modal-backdrop"></div>
			</div>
			<?php
	}

	/**
	 * Logo field
	 */
	public function render_preview_section() {

		?>
			<h3><?php _e( 'Preview Template', INCSUB_SBE_LANG_DOMAIN ); ?></h3>

			<?php $this->preview_modal(); ?>

			<button id="preview-template" class="button button-primary button-hero"><?php _e( 'Preview', INCSUB_SBE_LANG_DOMAIN ); ?></button>

			<?php $this->override_templates_instructions(); ?>
			<style>
				#sbe-preview-modal .sbe-modal-sidebar-section {
					border-bottom:1px solid #ddd;
				}
				#sbe-preview-modal .sbe-modal-sidebar-section:last-child {
					border:none;
				}
				#sbe-preview-modal .sbe-modal-sidebar-field {
					padding-left:20px;
					padding-bottom:20px;
					margin-top:1.33em;
				}
				#sbe-preview-modal label.big-label {
					display:block;
					font-size: 1em;
  					margin: 0 0 1.33em 0;
  					font-weight: 600;
				}

				#sbe-preview-modal .sbe-modal-preview-content {
					  position: absolute;
					  top: 0;
					  left: 0;
					  right: 300px;
					  bottom: 0;
					  overflow: auto;
					  outline: none;
					  padding: 2px 8px 8px;
					  margin:0;
				}
				#preview-section-backdrop-wrapper {
					/**position:relative;**/
				}
				#preview-section-backdrop {
					  position: absolute;
					  right: 0;
					  min-height: 360px;
					  width: 100%;
					  opacity: 0.7;
					  z-index: 159900;
					  background: #FFF;
					  top: 0;
					  bottom: 0;
					  left: 0;
					  display:none;
				}
				.sbe-remove-logo-wrap {
					display: inline-block;
				}

			</style>
			<script>
				var sbe_preview_modal;
				var frame;
				jQuery(document).ready(function($) {
					sbe_preview_modal = {
						isLoading: false,
						timeout: false,
                        current_request: false,
						init:function( selector ) {
							var self = this;
							this.$modal = $( selector );

							this.$modal.find('.media-modal-close').click( function( e ) {
								e.preventDefault();
								self.hide();
							});

							this.$modal.find( "#logo-width-slider" ).slider({
								value:<?php echo $this->settings['logo_width']; ?>,
								min: 100,
								max: 700,
								step: 10,
								slide: function( event, ui ) {
									$( "#logo-width" ).val( ui.value );
									$( "#logo-width-quantity" ).text( ui.value );
									$( '.logo-width' ).css( 'max-width', ui.value );
								}
							});
					    	$( "#logo-width" ).val( $( "#logo-width-slider" ).slider( "value" ) );
					    	$( "#logo-width-quantity" ).val( $( "#logo-width-slider" ).slider( "value" ) );


					    	$('#header-color').change( function() {
					    		self.refreshStyles( $(this).data( 'styles-class' ), $(this).data( 'styles-rule' ), $(this).val() );
					    	});

					    	// Colorpickers
					    	$('.colorpicker').wpColorPicker({
					    		change: function( e, ui ) {
					    			self.refreshStyles( $(e.target).data( 'styles-class' ), $(e.target).data( 'styles-rule' ), ui.color.toString() );
					    		}
					    	});

					    	$( '#submit-preview' ).click( function(e) {
					    		e.preventDefault();
					    		self.toggleSpinner();
					    		$('#submit_settings_template').trigger( 'click' );
					    	});

					    	$('#show-blog-name, #featured-image, #send-full-post').change( function() {
					    		self.reloadTemplate.apply( self );
					    	});

					    	$( '#upload-logo' ).on( 'click', self.uploadLogo );
							if ( $( '#upload-logo-value' ).val() == '' )
								$( '.sbe-remove-logo-wrap' ).hide();

							$('#remove-logo').on( 'click', function() {
								$( '#upload-logo-value' ).val('');
								$( '.sbe-remove-logo-wrap' ).hide();
								$('.sbe-logo-preview').hide();
								self.reloadTemplate();
							});

							$('#header-text, #footer-text').keyup(function(event) {
								if ( self.timeout ) {
									clearTimeout( self.timeout );
								}

								if ( ! self.isLoading ) {
									self.timeout = setTimeout( function() {
										self.isLoading = true;
										self.reloadTemplate();
										setTimeout( function() { self.isLoading = false; }, 1000 );
									}, 1000 );
								}

							});


						},
						show: function() {
							$('body').addClass( 'modal-open' );
							this.$modal.show();
						},
						hide: function() {
							$('body').removeClass( 'modal-open' );
							this.$modal.hide();
						},
						reloadTemplate: function() {
                            if ( this.current_request )
                                this.current_request.abort();
							var preview_backdrop = $('#preview-section-backdrop');
							preview_backdrop.css( 'height', $('#sbe-modal-email-preview').outerHeight() );
							preview_backdrop.show();
							this.toggleSpinner();
							var self = this;

							var form_values = $('#sbe-settings-form').serialize();

							this.current_request = $.ajax({
								url: ajaxurl,
								type: 'POST',
								data: {
									new_settings: form_values,
									action: 'sbe_reload_preview_template'
								},
							})
							.done(function(data) {
								if ( data.success ) {
									$('#sbe-modal-email-preview').html( data.data );
								}
							})
							.always(function(data) {
								self.toggleSpinner();
								preview_backdrop.hide();
							});


						},
						refreshStyles: function( cssClass, rule, value ) {
							$( '.' + cssClass ).css( rule, value );
						},
						toggleSpinner: function() {
							if ( ! this.$spinner )
								this.$spinner = this.$modal.find( '.spinner' );

							if ( this.$spinner.css( 'visibility' ) == 'hidden' )
								this.$spinner.css( 'visibility', 'visible' )
							else
								this.$spinner.css( 'visibility', 'hidden' )

						},
						uploadLogo: function( e ) {
							e.preventDefault();

							var element = $(this);

							if ( frame ) {
						    	frame.open();
						      	return;
						    }

						    frame = wp.media.frames.sbeTemplateLogo = wp.media({
								title: element.data( 'frame-title' ),
								library: {
									type: 'image'
								},
								button: {
									text: element.data( 'frame-update' ),
									close: false
								}
							});

							frame.on( 'select', function() {
								// Grab the selected attachment.
								var attachment = frame.state().get('selection').first();
								$( '#sbe-logo-img' )
									.attr( 'src', attachment.attributes.url )
									.css( 'display', 'inline-block' );

								$( '#upload-logo-value' ).attr( 'value', attachment.attributes.url );
								if ( attachment.attributes.url != '' )
									$( '.sbe-remove-logo-wrap' ).show();

								sbe_preview_modal.reloadTemplate.apply( sbe_preview_modal );

								frame.close();
							});

							frame.open();
						},
					}

					sbe_preview_modal.init('#sbe-preview-modal');

					$('#preview-template').click( function( e ) {
						e.preventDefault();
						sbe_preview_modal.show();
					});
				});
			</script>




		<?php
	}

	public function reload_preview_template() {
		wp_parse_str( $_POST['new_settings'], $new_settings );

		if ( ! empty( $new_settings['incsub_sbe_settings'] ) ) {
			// Checkboxes
			$new_settings = $new_settings['incsub_sbe_settings'];
			$new_settings['featured_image'] = isset( $new_settings['featured_image'] ) ? true : false;
			$new_settings['send_full_post'] = isset( $new_settings['send_full_post'] ) ? true : false;
			$new_settings['show_blog_name'] = isset( $new_settings['show_blog_name'] ) ? true : false;
			$this->preview_settings = incsub_sbe_sanitize_template_settings( $new_settings );

			add_filter( 'sbe_get_settings', array( $this, 'filter_preview_settings' ) );
			$template = $this->render_email_inner_preview( false );
			remove_filter( 'sbe_get_settings', array( $this, 'filter_preview_settings' ) );

			wp_send_json_success( $template );
		}

		wp_send_json_error();
	}

	public function filter_preview_settings( $settings ) {
		return wp_parse_args( $this->preview_settings, $settings );
	}

	public function override_templates_instructions() {
		$settings = incsub_sbe_get_settings();
		$sbe_templates_dir = INCSUB_SBE_PLUGIN_DIR . 'inc/mail-templates/views/';
		$theme_templates_dir = get_stylesheet_directory() . '/subscribe-by-email/';


		?>

		<?php
				$restore_link = add_query_arg(
					'restore-template',
					'true',
					$this->get_permalink()
				);
			?>
			<p>
				<strong><?php _e( 'Want to override the template files?', INCSUB_SBE_LANG_DOMAIN ); ?></strong>
			</p>
			<p>
				<?php printf(
						__( ' Move any file in <code>%s</code> to <code>%s</code> an edit them for changes:', INCSUB_SBE_LANG_DOMAIN ),
						$sbe_templates_dir,
						$theme_templates_dir
				); ?>
			</p>
			<ol>
				<li><strong><code>header.php</code></strong> <?php _e( 'The code that handles the header styles', INCSUB_SBE_LANG_DOMAIN ); ?></li>
				<li><strong><code>footer.php</code></strong> <?php _e( 'The code that handles the footer styles', INCSUB_SBE_LANG_DOMAIN ); ?></li>
				<li><strong><code>body.php</code></strong> <?php _e( 'The code that handles the main body styles', INCSUB_SBE_LANG_DOMAIN ); ?></li>
				<li><strong><code>post.php</code></strong> <?php _e( 'The code that handles every single post included in the digests', INCSUB_SBE_LANG_DOMAIN ); ?></li>
			</ol>
			<p>
				<?php submit_button( __( 'Send a test mail to:', INCSUB_SBE_LANG_DOMAIN ), 'secondary', $this->settings_name . '[submit_test_email]', false ) ?>
				<input type="text" class="regular-text" name="<?php echo $this->settings_name; ?>[test_mail]" value="<?php echo esc_attr( get_option( 'admin_email') ); ?>"><br/>
			</p>
			<p><a href="<?php echo $restore_link; ?>"><?php _e( 'Restore template to default', INCSUB_SBE_LANG_DOMAIN ); ?></a></p>
		<?php
	}

	public function render_email_inner_preview( $echo = true ) {
		$settings = incsub_sbe_get_settings();

		if ( ! $echo )
			ob_start();

		incsub_sbe_include_templates_files();
		$content_generator = new Incsub_Subscribe_By_Email_Content_Generator( $settings['frequency'], array( 'post' ), true );
		$posts = $content_generator->get_content();
		$template = sbe_get_email_template( $posts, false );
		sbe_render_email_template( $template );

		if ( ! $echo )
			return ob_get_clean();
	}

	public function render_subscribe_email_section() {
		?>
		<p><?php _e( 'Subscribe email is sent whenever a new user is subscribed', INCSUB_SBE_LANG_DOMAIN ); ?></p>
		<?php
	}

	/**
	 * Subscribing Email Contents
	 */
	public function render_subscribe_email_content() {
		?>
			<textarea class="widefat" rows="8" name="<?php echo $this->settings_name; ?>[subscribe_email_content]"><?php echo esc_textarea( $this->settings['subscribe_email_content'] ); ?></textarea>
		<?php
	}



	public function render_extra_fields_section() {
		?>
			<p><?php _e( 'In this screen you can add new fields that subscribers can fill when they try to subscribe via widget and Follow Button', INCSUB_SBE_LANG_DOMAIN ); ?></p>
		<?php
	}


	public function render_subscribers_extra_fields_field() {
		?>
			<label><?php _e( 'Field title', INCSUB_SBE_LANG_DOMAIN ); ?>
				<input type="text" name="<?php echo $this->settings_name; ?>[extra_field_name]" />
			</label>
			<select name="<?php echo $this->settings_name; ?>[extra_field_type]" id="extra_field_type">
				<option value="">-- <?php _e( 'Field type', INCSUB_SBE_LANG_DOMAIN ); ?> --</option>
				<?php incsub_sbe_extra_field_types_dropdown(); ?>
			</select>
			<label>
				<?php _e( 'Required', INCSUB_SBE_LANG_DOMAIN ); ?>
				<input type="checkbox" name="<?php echo $this->settings_name; ?>[extra_field_required]" />

			</label>
			<?php submit_button( __( 'Add field', INCSUB_SBE_LANG_DOMAIN ), 'secondary', $this->settings_name . '[submit_new_extra_field]', false ); ?>

			<?php $allowed_types = incsub_sbe_get_extra_field_types(); ?>
			<?php $remove_link = add_query_arg( 'tab', 'extra-fields', $this->get_permalink() ); ?>
			<div id="extra-fields-list" >
				<div class="spinner"></div>
				<table>
					<tbody class="extra-fields-sortables">
				<?php foreach ( $this->settings['extra_fields'] as $field_id => $value ): ?>

						<tr class="extra-field-item" data-field-slug="<?php echo esc_attr( $value['slug'] ); ?>">
							<td class="extra-field-item-move"></td>
							<td class="extra-field-item-edit">
								<a onclick="return confirm('<?php printf( __( 'Are you sure? %s data will be deleted for all users',INCSUB_SBE_LANG_DOMAIN ), $value['title'] ); ?>');" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'remove', $field_id, $remove_link ), 'remove_extra_field' ) ); ?>">
									<span class="remove"><?php _e( 'Remove', INCSUB_SBE_LANG_DOMAIN ); ?></span>
								</a>
							</td>
							<td class="extra-field-item-title"><strong><?php echo esc_html( $value['title'] ); ?></strong></td>

							<td class="extra-field-item-type"><?php echo $allowed_types[ $value['type'] ]['name']; ?> </td>
							<td class="extra-field-item-required">
								<?php _e( 'Required', INCSUB_SBE_LANG_DOMAIN ); ?>:
								<strong><?php echo $value['required'] ? __( 'Yes' ) : __( 'No' ); ?> </strong>
							</td>
						</tr>

				<?php endforeach; ?>
					</tbody>
				</table>

			</div>
			<script>
				jQuery(document).ready(function($) {
					$('.extra-fields-sortables').sortable({
						handle: '.extra-field-item-move',
						stop: function( event, ui ) {
							$('#extra-fields-list .spinner').css({'visibility':'visible'});
							var nodes = $('.extra-field-item');
							var slugs = new Array();
							nodes.each( function ( i, element ) {
								slugs.push($(this).data('field-slug'));
							});

							$.ajax({
								url: ajaxurl,
								type: 'post',
								data: {
									slugs: slugs,
									action: 'incsub_sbe_sort_extra_fields',
									nonce: "<?php echo wp_create_nonce( 'sort_extra_fields' ); ?>"
								},
							})
							.always( function() {
								$('#extra-fields-list .spinner').css({'visibility':'hidden'});
							});
						}
					});
				});
			</script>
		<?php

	}

	public function sort_extra_fields() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'sort_extra_fields' ) )
			die();

		if ( empty( $_POST['slugs'] ) || ! is_array( $_POST['slugs'] ) )
			die();

		$slugs = $_POST['slugs'];

		$settings = incsub_sbe_get_settings();
		$extra_fields = $settings['extra_fields'];
		$new_extra_fields = array();

		foreach ( $slugs as $slug ) {
			foreach ( $extra_fields as $extra_field ) {
				if ( $slug == $extra_field['slug'] ) {
					$new_extra_fields[] = $extra_field;
					break;
				}
			}
		}

		$settings['extra_fields'] = $new_extra_fields;

		remove_filter( 'sanitize_option_' . $this->settings_name, array( &$this, 'sanitize_settings' ) );
		incsub_sbe_update_settings( $settings );
		add_filter( 'sanitize_option_' . $this->settings_name, array( &$this, 'sanitize_settings' ) );

		die();
	}


	/**
	 * Sanitizes the settings and return the values to be saved
	 *
	 * @param Array $input $_POST values
	 *
	 * @return Array New settings
	 */
	public function sanitize_settings( $input ) {

		$new_settings = $this->settings;

		if ( isset( $input['submit_settings_general'] ) ) {
			// From Sender
			if ( ! is_multisite() ) {
				$result = incsub_sbe_sanitize_from_email( $input['from_email'] );

				if ( is_wp_error( $result ) ) {
					add_settings_error( $this->settings_name, $result->get_error_code(), $result->get_error_message() );
				}
				else {
					$new_settings['from_email'] = $result;
				}

			}

			$from_sender = sanitize_text_field( $input['from_sender'] );
			if ( ! empty( $from_sender ) )
				$new_settings['from_sender'] = $from_sender;
			else
				add_settings_error( $this->settings_name, 'invalid-from-sender', __( 'Notification From Sender cannot be empty', INCSUB_SBE_LANG_DOMAIN ) );

			// Mail subject
			$subject = sanitize_text_field( $input['subject'] );
			if ( ! empty( $subject ) )
				$new_settings['subject'] = $subject;
			else
				add_settings_error( $this->settings_name, 'invalid-subject', __( 'Mail subject cannot be empty', INCSUB_SBE_LANG_DOMAIN ) );

			// Frequency
			if ( array_key_exists( $input['frequency'], incsub_sbe_get_digest_frequency() ) ) {
				$new_settings['frequency'] = $input['frequency'];
			}
			else {
				$default_settings = incsub_sbe_get_default_settings();
				$new_settings['frequency'] = $default_settings['frequency'];
			}

			// For daily frequencies
			if ( 'daily' == $new_settings['frequency'] && array_key_exists( $input['daily-time'], incsub_sbe_get_digest_times() ) ) {
				$new_settings['time'] = $input['daily-time'];
				// We have changed this setting
				Incsub_Subscribe_By_Email::set_next_day_schedule_time( $input['daily-time'] );
			}
			else {
				$default_settings = incsub_sbe_get_default_settings();
			}

			// For weekly frequencies
			if ( 'weekly' == $new_settings['frequency'] && array_key_exists( $input['day_of_week'], incsub_sbe_get_digest_days_of_week() ) && array_key_exists( $input['weekly-time'], incsub_sbe_get_digest_times() ) ) {
				$new_settings['day_of_week'] = $input['day_of_week'];
				$new_settings['time'] = $input['weekly-time'];
				Incsub_Subscribe_By_Email::set_next_week_schedule_time( $input['day_of_week'], $input['weekly-time'] );

			}
			else {
				$default_settings = incsub_sbe_get_default_settings();
				$new_settings['day_of_week'] = $default_settings['day_of_week'];
			}

			// Management Page
			if ( isset( $input['manage_subs_page'] ) ) {
				$new_settings['manage_subs_page'] = absint( $input['manage_subs_page'] );
			}

			// Batches
		    if ( ! is_multisite() && ! empty( $input['mail_batches'] ) )
				$new_settings['mails_batch_size'] = absint( $input['mail_batches'] );

			$new_settings['get_notifications'] = isset( $input['get_notifications'] );

			$new_settings['follow_button'] = isset( $input['follow_button'] );
			if ( ! empty( $input['follow_button_schema'] ) && array_key_exists( $input['follow_button_schema'], incsub_sbe_get_follow_button_schemas() ) )
				$new_settings['follow_button_schema'] = $input['follow_button_schema'];

			$new_settings['follow_button'] = isset( $input['follow_button'] );
			if ( ! empty( $input['follow_button_position'] ) && array_key_exists( $input['follow_button_position'], incsub_sbe_get_follow_button_positions() ) )
				$new_settings['follow_button_position'] = $input['follow_button_position'];

			$new_settings['get_notifications_role'] = $input['get_notifications_role'];

			if ( ! is_multisite() &&  ! empty( $input['keep_logs_for'] ) ) {
				$option = absint( $input['keep_logs_for'] );
				if ( $option > 31 ) {
					$new_settings['keep_logs_for'] = 31;
				}
				elseif ( $option < 1 ) {
					$new_settings['keep_logs_for'] = 1;
				}
				else {
					$new_settings['keep_logs_for'] = $option;
				}
			}
		}

		if ( isset( $input['submit_settings_content'] ) ) {

			// Post types
			if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
				$new_settings['post_types'] = $input['post_types'];
			}

			//Taxonomies
			if ( ! empty( $input['tax_input'] ) ) {
				$new_settings['taxonomies'] = array();

				foreach ( $input['tax_input'] as $post_type_slug => $taxonomies ) {
					foreach ( $taxonomies as $tax_slug => $taxonomy_items ) {
						if ( ! in_array( $post_type_slug, $new_settings['post_types'] ) ) {
							$new_settings['taxonomies'][ $post_type_slug ][ $tax_slug ] = array( 'all' );
							continue;
						}

						if ( in_array( 'all', $taxonomy_items ) ) {
							$new_settings['taxonomies'][ $post_type_slug ][ $tax_slug ] = array( 'all' );
						}
						else {
							$new_settings['taxonomies'][ $post_type_slug ][ $tax_slug ] = $taxonomy_items;
						}
					}

				}

			}
		}

		if ( isset( $input['submit_settings_template'] ) || isset( $input['remove-logo'] ) || isset( $input['submit_test_email'] ) || isset( $input['submit_refresh_changes'] ) ) {

			// Featured image
			$input['featured_image'] = isset( $input['featured_image'] ) ? true : false;

			// Full post/just excerpt
			$input['send_full_post'] = isset( $input['send_full_post'] ) ? true : false;

			$input['show_blog_name'] = isset( $input['show_blog_name'] ) ? true : false;

			$input['logo'] = isset( $input['remove-logo'] ) ? '' : $input['logo'];

			$new_settings = wp_parse_args( incsub_sbe_sanitize_template_settings( $input ), $new_settings );

			if ( isset( $input['submit_test_email'] ) ) {

				$mail = sanitize_email( $input['test_mail'] );

				if ( is_email( $mail ) ) {
					$_settings = incsub_sbe_get_settings();
					incsub_sbe_include_templates_files();
					$content_generator = new Incsub_Subscribe_By_Email_Content_Generator( $_settings['frequency'], array( 'post' ), true );
					$posts = $content_generator->get_content();

					$digest_sender = new SBE_Digest_Sender( true );
					$digest_sender->send_digest( $posts, $mail );

				}
			}

		}

		if ( isset( $input['submit_new_extra_field'] ) ) {
			$extra_field_error = false;

			if ( empty( $input['extra_field_name'] ) ) {
				add_settings_error( $this->settings_name, 'extra-field-name', __( 'Name cannot be empty', INCSUB_SBE_LANG_DOMAIN ) );
				$extra_field_error = true;
			}
			else {
				$name = sanitize_text_field( $input['extra_field_name'] );
			}

			if ( ! $extra_field_error ) {
				$slug = sanitize_title_with_dashes( $name );

				$settings = incsub_sbe_get_settings();
				$slug_found = false;
				foreach ( $settings['extra_fields'] as $extra_field ) {
					if ( $extra_field['slug'] == $slug )
						$slug_found = true;
				}
				if ( $slug_found ) {
					add_settings_error( $this->settings_name, 'extra-field-slug', __( 'Field already exists', INCSUB_SBE_LANG_DOMAIN ) );
					$extra_field_error = true;
				}

				$type = ! empty( $input['extra_field_type'] ) ? $input['extra_field_type'] : '';
				if ( ! $extra_field_error && array_key_exists( $type, incsub_sbe_get_extra_field_types() ) ) {
					$new_settings['extra_fields'][] = array(
						'slug' => $slug,
						'title' => $name,
						'type' => $type,
						'required' => ! empty( $input['extra_field_required'] )
					);
				}
				else {
					add_settings_error( $this->settings_name, 'extra-field-type', __( 'Select a field type', INCSUB_SBE_LANG_DOMAIN ) );
				}
			}

		}

		$new_settings = apply_filters( 'sbe_sanitize_settings', $new_settings, $input, $this->settings_name );


		return $new_settings;

	}

	public function restore_default_template() {
		if ( isset( $_GET['page'] ) && $this->get_menu_slug() == $_GET['page'] && isset( $_GET['restore-template'] ) ) {
			$default_settings = incsub_sbe_get_default_settings();

			$this->settings['logo'] = $default_settings['logo'];
			$this->settings['header_color'] = $default_settings['header_color'];
			$this->settings['header_text_color'] = $default_settings['header_text_color'];
			$this->settings['featured_image'] = $default_settings['featured_image'];
			$this->settings['send_full_post'] = $default_settings['send_full_post'];
			$this->settings['header_text'] = $default_settings['header_text'];
			$this->settings['footer_text'] = $default_settings['footer_text'];
			$this->settings['show_blog_name'] = $default_settings['show_blog_name'];
			$this->settings['logo_width'] = $default_settings['logo_width'];

			incsub_sbe_update_settings( $this->settings );

			wp_redirect( add_query_arg(
				array(
					'tab' => 'template',
					'updated' => 'true'
				),
				$this->get_permalink()
			) );
		}
	}



}