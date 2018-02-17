<?php

abstract class Incsub_Subscribe_By_Email_Admin_Page {

	private $menu_slug;
	protected $page_id;

	private $page_title;
	private $menu_title;
	protected $capability;
	private $network = false;
	private $parent = false;

	public function __construct( $args ) {
		extract( $args );
		$this->menu_slug = $slug;

		$this->page_title = $page_title;
		$this->menu_title = $menu_title;
		$this->capability = $capability;
		$this->menu_icon = ! empty( $menu_icon ) ? $menu_icon : 'div';

		if ( ! empty( $network ) )
			$this->network = true;

		if ( ! empty( $parent ) )
			$this->parent = $parent;

		if ( $this->network ) {
			add_action( 'network_admin_menu', array( &$this, 'add_menu' ) );
		}
		else {
			add_action( 'admin_menu', array( &$this, 'add_menu' ) );
		}

	}

	/**
	 * Add the menu to the menu
	 */
	public function add_menu() {

		if ( ! empty( $this->parent ) ) {

			$this->page_id = add_submenu_page(
				$this->parent,
				$this->page_title,
				$this->menu_title,
				$this->capability,
				$this->menu_slug,
				array( &$this, 'render_page' )
			);
		}
		else {

			$this->page_id = add_menu_page(
				$this->page_title,
				$this->menu_title,
				$this->capability,
				$this->menu_slug,
				array( &$this, 'render_page' ),
				$this->menu_icon
			);

			// Yes, SBE main menu is a bit different
			add_submenu_page(
				$this->menu_slug,
				__( 'Subscribers', INCSUB_SBE_LANG_DOMAIN ),
				__( 'Subscribers', INCSUB_SBE_LANG_DOMAIN ),
				$this->capability,
				$this->menu_slug,
				array( &$this, 'render_page' )
			);
		}

	}

	public function render_page() {

		if ( ! current_user_can( $this->capability ) )
			wp_die( __( 'You do not have enough permissions to access to this page', INCSUB_SBE_LANG_DOMAIN ) );

		?>
			<div class="wrap">

				<h2><?php echo $this->page_title; ?></h2>

				<?php $this->render_content(); ?>

			</div>

		<?php

	}

	public abstract function render_content();

	public function get_menu_slug() {
		return $this->menu_slug;
	}

	public function get_page_id() {
		return $this->page_id;
	}

	public function get_permalink() {
		return add_query_arg(
			'page',
			$this->get_menu_slug(),
			$this->network ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' )
		);
	}

	public function get_capability() {
		return $this->capability;
	}

	/**
	 * Want to render a WP native page? You can use this function
	 * Remember to set a table.form-table HTML tag before and after
	 *
	 * This function is useful when not using the WP Settings API.
	 * For example, Network Pages does not accept that API so you
	 * need to add fields manually. This function will save
	 * a loot of code.
	 *
	 * @param String $title Title of the row
	 * @param string/Array $callback Method that will render the markup
	 */
	protected function render_row( $title, $callback, $args = array() ) {
		?>
			<tr valign="top">
				<th scope="row"><label><?php echo $title; ?></label></th>
				<td>
					<?php
						if ( is_array( $callback ) ) {
							if ( ! is_object( $callback[0] ) || ( is_object( $callback[0] ) && ! method_exists( $callback[0], $callback[1] ) ) ) {
								echo '';
							}
							else {
								call_user_func( $callback, $args );
							}
						}
						else {
							call_user_func( $callback, $args );
						}
					?>
				</td>
			</tr>
		<?php
	}

}