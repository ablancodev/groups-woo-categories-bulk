<?php
/**
 *
 * Copyright (c) 2011,2017 Antonio Blanco http://www.ablancodev.com
 *
 * This code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt.
 *
 * This code is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This header and all notices must be kept intact.
 *
 * @author Antonio Blanco
 * @package groups-woo-categories-bulk
 * @since groups-woo-categories-bulk 1.0.0
 *
 * Plugin Name: Groups Woocommerce Categories Bulk
 * Plugin URI: http://www.eggemplo.com
 * Description: Groups Woocommerce Categories Bulk
 * Version: 1.0.0
 * Author: eggemplo
 * Author URI: http://www.ablancodev.com
 * License: GPLv3
 */
if (! defined ( 'GWC_BULK_CORE_DIR' )) {
	define ( 'GWC_BULK_CORE_DIR', WP_PLUGIN_DIR . '/groups-woo-categories-bulk' );
}
define ( 'GWC_BULK_FILE', __FILE__ );

define ( 'GWC_BULK_PLUGIN_URL', plugin_dir_url ( GWC_BULK_FILE ) );

class GWCBulk_Plugin {

	public static $notices = array ();


	public static function init() {
		add_action ( 'init', array (
				__CLASS__,
				'wp_init' 
		) );
		add_action ( 'admin_notices', array (
				__CLASS__,
				'admin_notices' 
		) );

		add_action('admin_init', array ( __CLASS__, 'admin_init' ) );

		register_activation_hook( GWC_BULK_FILE, array( __CLASS__, 'activate' ) );

	}
	public static function wp_init() {

		add_action ( 'admin_menu', array (
				__CLASS__,
				'admin_menu' 
		), 40 );

		// styles & javascript
		add_action ( 'admin_enqueue_scripts', array (
				__CLASS__,
				'admin_enqueue_scripts' 
		) );
	}

	public static function admin_init() {

	}


	public static function admin_enqueue_scripts($page) {
		// css
		wp_register_style ( 'groups-woo-categories-bulk-admin-style', GWC_BULK_PLUGIN_URL . '/css/admin-style.css', array (), '1.0.0' );
		wp_enqueue_style ( 'gp-admin-style' );

		// Our javascript
		wp_register_script ( 'groups-woo-categories-bulk-admin-scripts', GWC_BULK_PLUGIN_URL . '/js/admin-scripts.js', array ( 'jquery' ), '1.0.0', true );
		wp_enqueue_script ( 'groups-woo-categories-bulk-admin-scripts' );

		// Groups selectize
		Groups_UIE::enqueue( 'select' );
	}

	public static function admin_notices() {
		if (! empty ( self::$notices )) {
			foreach ( self::$notices as $notice ) {
				echo $notice;
			}
		}
	}

	/**
	 * Adds the admin section.
	 */
	public static function admin_menu() {
		$admin_page = add_menu_page (
				__ ( 'Groups Woo Bulk', 'groups-woo-categories-bulk' ),
				__ ( 'Groups Woo Bulk', 'groups-woo-categories-bulk' ),
				'manage_options', 'groups-woo-categories-bulk',
				array (
					__CLASS__,
					'gwc_bulk_menu_settings' 
				),
				GWC_BULK_PLUGIN_URL . '/images/settings.png' );
	}

	public static function gwc_bulk_menu_settings() {
		// if submit
		if ( isset( $_POST ["gwc_bulk_settings"] ) && wp_verify_nonce ( $_POST ["gwc_bulk_settings"], "gwc_bulk_settings" ) ) {
			if ( isset( $_REQUEST['groups-bulk-read'] ) && isset( $_REQUEST['category'] ) ) {
				$categories = $_REQUEST['category'];
				foreach ( $categories as $category_id ) {
					if ( Groups_Access_Meta_Boxes::user_can_restrict() ) {
						$include = Groups_Access_Meta_Boxes::get_user_can_restrict_group_ids();
						$groups  = Groups_Group::get_groups( array( 'order_by' => 'name', 'order' => 'ASC', 'include' => $include ) );
						$group_ids = array();
						foreach( $groups as $group ) {
							$group_ids[] = $group->group_id;
						}
						$validated_group_ids = array();
						foreach( $_REQUEST['groups-bulk-read'] as $group_id ) {
							if ( $group = Groups_Group::read( $group_id ) ) {
								if ( in_array( $group->group_id, $group_ids ) ) {
									$validated_group_ids[] = $group->group_id;
								}
							}
						}
						if( isset( $_REQUEST['add-group'] ) && $_REQUEST['add-group'] ) {
							$old_groups = Groups_Restrict_Categories::get_term_read_groups( $category_id );
							$validated_group_ids = array_merge( $validated_group_ids, $old_groups );
							Groups_Restrict_Categories::set_term_read_groups( $category_id, $validated_group_ids );
						}
						if( isset( $_REQUEST['remove-group'] ) && $_REQUEST['remove-group'] ) {
							Groups_Restrict_Categories::delete_term_read_groups( $category_id, $validated_group_ids );
						}
					}
				}
			}
		}
		?>
		<h2><?php echo __( 'Groups Woocommerce Categories Bulk', 'groups-woo-categories-bulk' ); ?></h2>

		<h3><?php echo __( 'Products Categories', 'groups-woo-categories-bulk' ); ?></h3>

		<form method="post" action="" enctype="multipart/form-data" >
			<div class="">

				<?php
				$orderby = 'name';
				$order = 'asc';
				$hide_empty = false ;
				$cat_args = array(
						'orderby'    => $orderby,
						'order'      => $order,
						'hide_empty' => $hide_empty,
				);

				$product_categories = get_terms( 'product_cat', $cat_args );
				if( !empty($product_categories) ){
					foreach ( $product_categories as $cat ) {
						if ( !$cat->parent ) {
							echo '<p>';
							echo '<input type="checkbox" name="category[]" value="' . $cat->term_id . '">';
							echo $cat->name;
							echo '</p>';
							self::print_subcat( $cat->term_id, 1 );
						}
					}
				}

				$user    = new Groups_User( get_current_user_id() );
				$include = Groups_Access_Meta_Boxes::get_user_can_restrict_group_ids( get_current_user_id() );
				$groups  = Groups_Group::get_groups( array( 'order_by' => 'name', 'order' => 'ASC', 'include' => $include ) );

				?>
				<h3><?php echo __( 'Groups', 'groups-woo-categories-bulk' ); ?></h3>
				<?php

				$output = '<div class="groups-groups-container">';
				$output .= sprintf(
					'<select class="select bulk-group" name="%s[]" multiple="multiple" placeholder="%s" data-placeholder="%s">',
					esc_attr( Groups_Post_Access::POSTMETA_PREFIX . 'bulk-' . Groups_Post_Access::READ ),
					esc_attr( __( 'Choose access restriction groups &hellip;', 'groups' ) ) ,
					esc_attr( __( 'Choose access restriction groups &hellip;', 'groups' ) )
				);

				foreach( $groups as $group ) {
					$output .= sprintf( '<option value="%s" >%s</option>', esc_attr( $group->group_id ), wp_filter_nohtml_kses( $group->name ) );
				}
				$output .= '</select>';
				$output .= '</div>'; // .groups-groups-container
				$output .= Groups_UIE::render_select( '.select.bulk-group' );

				echo $output;

				wp_nonce_field ( 'gwc_bulk_settings', 'gwc_bulk_settings' )?>
				<input type="submit"
				name="add-group"
				value="<?php echo __( "Add to groups", 'groups-woo-categories-bulk' );?>"
				class="button button-primary button-large" />
				<input type="submit"
				name="remove-group"
				value="<?php echo __( "Remove from groups", 'groups-woo-categories-bulk' );?>"
				class="button button-primary button-large" />
			</div>
		</form>
		<?php 
	}

	/**
	 * Plugin activation work.
	 *
	 */
	public static function activate() {
	}

	public static function print_subcat( $parent_id, $level = 0 ) {
		$subcats = get_categories( array( 'hide_empty' => 0, 'parent' => $parent_id, 'taxonomy' => 'product_cat' ) );
		if ( sizeof( $subcats ) > 0 ) {
			foreach ( $subcats as $sub ) {
				echo '<p style="margin-left:' . $level * 30 . 'px;">';
				echo '<input type="checkbox" name="category[]" value="' . $sub->term_id . '">';
				echo $sub->name;
				echo '</p>';
				self::print_subcat( $sub->term_id, $level + 1 );
			}
		}
	}

}
GWCBulk_Plugin::init();
