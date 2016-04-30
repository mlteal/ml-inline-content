<?php

/**
 * ML Inline Content class
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
class ML_Inline_Content_Admin {

	function __construct() {

		// add widget form and form callback - priority #'s and # of accepted arguments are necessary here
		add_action( 'in_widget_form', array( $this, 'widget_options' ), 5, 3 );
		add_filter( 'widget_update_callback', array( $this, 'widget_options_update' ), 5, 3 );

		// add scripts used for select2
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_print_footer_scripts', array( $this, 'admin_print_scripts' ), 999 );
	}
	
	/**
	 * Display a single widget from a specific widget instance ID
	 *
	 * A pared down version of the shortcode_sidebar function from the
	 * AMR Shortcode Any Widget plugin by Anmari
	 * (https://github.com/wp-plugins/amr-shortcode-any-widget)
	 *
	 * @param        $widget_id
	 * @param string $sidebar_id
	 *
	 * @return bool
	 */
	function do_widget( $widget_id, $sidebar_id = 'ml-inline-content' ) {
		global $wp_registered_sidebars, $wp_registered_widgets;

		$sidebar = $wp_registered_sidebars[ $sidebar_id ];  // has the params we need to display the sidebar
		$params  = array_merge(
			array(
				array_merge( $sidebar,
					array(
						'widget_id'   => $widget_id,
						'widget_name' => $wp_registered_widgets[ $widget_id ]['name']
					)
				)
			),
			(array) $wp_registered_widgets[ $widget_id ]['params']
		);

		$callback = $wp_registered_widgets[ $widget_id ]['callback'];

		if ( is_callable( $callback ) ) {
			ob_start();
			call_user_func_array( $callback, $params );

			return ob_get_clean();
		}

	} // END function do_widget

	/**
	 * Add form items to the bottom of the widget
	 *
	 * @param $widget
	 * @param $return
	 * @param $instance
	 *
	 * @return array($widget, $return, $instance)
	 */
	function widget_options( $widget, $return, $instance ) {
		if ( ! isset( $instance['position'] ) ) {
			$instance['position'] = null;
		}
		if ( ! isset( $instance['display_at_end'] ) ) {
			$instance['display_at_end'] = null;
		}
		if ( ! isset( $instance['device_display'] ) ) {
			$instance['device_display'] = array();
		}

		// build the form
		echo '<div class="ml-content-position" style="display: none;">';

		// Position of widget after paragraph #
		printf( '<label for="%s">Display after paragraph</label>', $widget->get_field_id( 'position' ) );
		printf( '<input type="number" name="%1$s" min="0" id="%2$s" value="%3$s" style="width: 40px;" />', $widget->get_field_name( 'position' ), $widget->get_field_id( 'position' ), $instance['position'] );

		echo '<br>';

		// Checkbox for displaying at end of article
		printf( '<label for="%s">Or check to display at the end of the article</label> ', $widget->get_field_id( 'display_at_end' ) );
		printf( '<input type="checkbox" %1$s name="%2$s" id="%3$s" value="true" />', checked( $instance['display_at_end'], 'true', false ), $widget->get_field_name( 'display_at_end' ), $widget->get_field_id( 'display_at_end' ) );

		echo '<br><br>';

		// Multiselect for choosing what device_display to display on
		printf( '<label for="%s">Where should this content display?</label><br>', $widget->get_field_id( 'device_display' ) );

		printf( '<select name="%1$s[]" multiple="multiple" id="%2$s" class="widefat multiselect" data-placeholder="Choose one or more devices">', $widget->get_field_name( 'device_display' ), $widget->get_field_id( 'device_display' ) );
		?>
		<option value="desktop" <?php if ( in_array( 'desktop', $instance['device_display'] ) ) {
			echo 'selected';
		} ?>>
			Desktop
		</option>
		<option value="tablet" <?php if ( in_array( 'tablet', $instance['device_display'] ) ) {
			echo 'selected';
		} ?>>
			Tablet
		</option>
		<option value="mobile" <?php if ( in_array( 'mobile', $instance['device_display'] ) ) {
			echo 'selected';
		} ?>>
			Mobile
		</option>

		<?php
		echo '</select>';

		echo '</div>';

	} // END function widget_options

	/**
	 * Update the widget options
	 *
	 * @param $instance
	 * @param $new_instance
	 * @param $old_instance
	 *
	 * @return mixed
	 */
	function widget_options_update( $instance, $new_instance, $old_instance ) {
		if ( ! isset( $new_instance['display_at_end'] ) ) {
			$new_instance['display_at_end'] = null;
		}
		if ( ! isset( $new_instance['device_display'] ) ) {
			$new_instance['device_display'] = array();
		}

		$instance['position']       = $new_instance['position'];
		$instance['display_at_end'] = $new_instance['display_at_end'];
		$instance['device_display'] = $new_instance['device_display'];

		return $instance;
	} // END function widget_options_update

	/**
	 * Enqueue scripts used on widgets.php
	 *
	 * @param $hook
	 */
	function admin_enqueue_scripts( $hook ) {

		if ( 'widgets.php' == $hook ) {
			wp_register_script( 'select2_js', MLIC_PLUGIN_URL . 'assets/select2.min.js', 'jquery', '3.5.2', false );
			wp_register_style( 'select2_css', MLIC_PLUGIN_URL . 'assets/select2.css', '', '3.5.2' );
			wp_enqueue_script( 'select2_js' );
			wp_enqueue_style( 'select2_css' );
		}
	} // END function admin_enqueue_scripts

	/**
	 * Print the Select2 JS only on the admin pages that need it
	 */
	function admin_print_scripts() {
		$current_screen = get_current_screen();

		if ( 'widgets' == $current_screen->id ) {
			// force the CSS for now to display inline content widget form
			?>
			<style type="text/css">
				#ml-inline-content form div.ml-content-position {
					display: block !important;
					margin-bottom: 12px;
				}
			</style>
			<script type='text/javascript'>
				jQuery( function( $ ) {
					$( document ).on( 'ready widget-added widget-updated', function() {
						$( '.widgets-sortables select.multiselect' ).select2().removeClass( 'multiselect' ).addClass( 'fancy-select' );
					} );
				} );
			</script>
			<?php
		}

	} // END function admin_print_scripts
}