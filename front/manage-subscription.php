<?php

class Incsub_Subscribe_By_Email_Manage_Subscription {

	private $settings;

	public function __construct() {
		$this->settings = incsub_sbe_get_settings();

		add_filter( 'the_content', array( &$this, 'set_the_content' ), 80 );

		add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_scripts' ) );
	}

	public function enqueue_scripts() {
		$post_id = get_the_ID();
		if ( ! empty( $this->settings['manage_subs_page'] ) && $this->settings['manage_subs_page'] == $post_id ) {
			wp_enqueue_style( 'manage-subscriptions-css', INCSUB_SBE_ASSETS_URL . 'css/manage-subscriptions.css' );
		}
	}

	public function set_the_content( $content ) {
		$new_content = $content;

		$post_id = get_the_ID();

		if ( ! empty( $this->settings['manage_subs_page'] ) && $this->settings['manage_subs_page'] == $post_id ) {
			$model = Incsub_Subscribe_By_Email_Model::get_instance();
			$settings_handler = Incsub_Subscribe_By_Email_Settings_Handler::get_instance();

			if ( ! isset( $_REQUEST['sub_key'] ) ) {
				ob_start();
				?>
					<div id="manage_subscription_wrap">
						<h4><?php _e( 'This page will show details of your email subscriptions. To see the options available to you, click the link in any newsletter email you have received from us', INCSUB_SBE_LANG_DOMAIN ); ?></h4>
					</div>
				<?php
				return $new_content . ob_get_clean();
			}

			$key = $_REQUEST['sub_key'];
			$subscriber = incsub_sbe_get_subscriber_by_key ( $key );
			if ( ! $subscriber )
				return $new_content;

			$updated = false;

			if ( ! empty( $_POST['sub_submit'] ) ) {
				$submitted = empty( $_POST['sub_post_types'] ) ? array() : $_POST['sub_post_types'];
				if ( ! is_array( $submitted ) )
					$submitted = false;

				$subscriber->set_post_types( $submitted );
				$updated = true;
			}

			$user_post_types = $subscriber->subscription_post_types;
			if ( false === $user_post_types ) {
				// If is false, the subscriber is subscribed to everything
				$user_post_types = incsub_sbe_get_subscriptions_post_types();
			}

			$post_types = incsub_sbe_get_subscriptions_post_types();

			//TEST

			ob_start();
			?>
				<div id="manage_subscription_wrap">

					<?php if ( $updated ): ?>
						<p class="sbe-saved"><?php _e( 'Settings saved', INCSUB_SBE_LANG_DOMAIN ); ?></p>
					<?php endif; ?>

					<form action="" method="POST">

						<input type="hidden" name="sub_key" value="<?php echo $key; ?>">

						<?php if ( ! empty( $post_types ) ): ?>

							<h3><?php _e( 'Please select which post types you wish to be notified about.', INCSUB_SBE_LANG_DOMAIN ); ?></h3>

							<?php foreach ( $post_types as $post_type ): ?>
								<?php
									$_post_type = get_post_type_object( $post_type );
									if ( ! $_post_type )
										continue;
								?>

								<div class="post-type-box">
									<label class="sub_post_type_label" for="sub_post_type-<?php echo $post_type; ?>">
										<input type="checkbox" class="sub_post_types" <?php checked( in_array( $post_type, $user_post_types ) ); ?> id="sub_post_type-<?php echo $post_type; ?>" name="sub_post_types[]" value="<?php echo $post_type; ?>">
										<?php echo $_post_type->label; ?>
									</label><br/>
								</div>
							<?php endforeach; ?>

						<?php endif; ?>

						<p><input type="submit" name="sub_submit" value="<?php _e( 'Submit settings', INCSUB_SBE_LANG_DOMAIN ); ?>"></p>

					</form>
				</div>
			<?php
			$new_content .= ob_get_clean();
		}
		return $new_content;
	}

	public static function get_sbe_post_types() {
		$settings = incsub_sbe_get_settings();
		$post_types = $settings['post_types'];

		$result = array();
		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type ) {
				$object = get_post_type_object( $post_type );
				if ( ! empty( $object->labels->name ) )
					$result[] = array( 'slug' => $post_type, 'name' => $object->labels->name );
			}
		}

		return $result;
	}



}
