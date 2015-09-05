<?php
/**
 * Plugin Name: ML Inline Content
 * Plugin URI: http://mlteal.com
 * Description: A widget area that allows you to add content dynamically to all blog posts.
 * This plugin has not been made to work with the customizer yet.
 * Version: 1.0
 * Author: mlteal
 * Author URI: http://mlteal.com
 * Text Domain: ml_ic
 *
 */
/*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

class ML_Inline_Content {
	static $installed_dir;
	static $installed_url;

	function __construct() {
		self::$installed_dir = dirname( __FILE__ );
		self::$installed_url = plugins_url( '/', __FILE__ );

		// add sidebar
		add_action( 'widgets_init', array( $this, 'register_inline_content_sidebar' ) );

		// add widget form and form callback - priority #'s and # of accepted arguments are necessary here
		add_action( 'in_widget_form', array( $this, 'widget_options' ), 5, 3 );
		add_filter( 'widget_update_callback', array( $this, 'widget_options_update' ), 5, 3 );
		add_filter( 'the_content', array( $this, 'inline_content_sidebar' ) );

		// add scripts used for select2
		add_action( 'admin_enqueue_scripts',      array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_print_footer_scripts', array( $this, 'admin_print_scripts' ), 999 );
	}

	/**
	 * Register the inline content sidebar
	 */
	public function register_inline_content_sidebar() {
		register_sidebar( array(
			'name'          => __( 'Article Inline Content', 'ml_ic' ),
			'id'            => 'ml-inline-content',
			'description'   => __( 'Widgets in this sidebar will be used for inline content within single posts.', 'ml_ic' ),
			'before_widget' => '<div class="ml-inline-content">',
			'after_widget'  => '</div>',
		) );
	}

	/**
	 * Function inline_content_sidebar()
	 *
	 * Places the widgets found in the sidebar into a post's content.
	 *
	 * @param $content
	 *
	 * @return mixed
	 */
	function inline_content_sidebar( $content ) {

		if ( is_single() && ( $widget_details = $this->get_sidebar_contents_array( 'ml-inline-content' ) ) != array() ) {

			include_once( self::$installed_dir . '/class-mobile-detect.php' );
			$detect = new Mobile_Detect;

			// sort the $widget_details array by position number so we can go through in order
			usort( $widget_details, create_function( '$a, $b', 'if ($a["position"] == $b["position"]) return 0; return ($a["position"] < $b["position"]) ? -1 : 1;' ) );

			// reverse the array of widgets so we can go from bottom up & have an accurate count
			array_reverse( $widget_details );

			// grab the paragraphs from the content and break them out into an array
			$split_after = '</p>';
			$paragraphs = explode( $split_after, $content );
			$number_of_paragraphs = count( $paragraphs );

			// create and use a counter to account for the position shifting as the array grows
			$count = 0;
			foreach ( $widget_details as $detail ) {
				$device = $detail['device_display'];

				// only continue if the position is set - display_at_end will always be set and will be NULL if unchecked
				if (
					isset( $detail['position'] ) // has a position
					&& ! empty( $detail['position'] ) // and the position isn't empty
					&& $detail['position'] < $number_of_paragraphs // don't display more widgets than there are paragraphs
					&& $detail['display_at_end'] == null // display_at_end isn't checked
				) {

					if (
						(
							empty( $device )
							|| $detect->isMobile() && in_array( 'mobile', $device )
						)
						|| ( $detect->isTablet() && in_array( 'tablet', $device ) )
						|| ( ! $detect->isMobile() && ! $detect->isTablet() && in_array( 'desktop', $device ) )
					) {
						/**
						 * When we specify the position of the splice we can't set $detail['position']
						 * as its own variable like $position = $detail['position'] because it won't work.
						 */
						array_splice( $paragraphs, $detail['position'] + $count, 0, array( 'ad' => $this->do_widget( $detail['id'] ) ) );

						$count++;
					}
				} elseif ( isset( $detail['display_at_end'] ) && $detail['display_at_end'] == 'true' ) {

					if (
						(
							empty( $device )
							|| $detect->isMobile() && in_array( 'mobile', $device )
						)
						|| ( $detect->isTablet() && in_array( 'tablet', $device ) )
						|| ( ! $detect->isMobile() && ! $detect->isTablet() && in_array( 'desktop', $device ) )
					) {
						/**
						 * If multiple widgets have display_at_end checked, you can control
						 * display order by using the "Display after paragraph" setting.
						 */
						$paragraphs[] = $this->do_widget( $detail['id'] );
					}
				}
			}
			$content = implode( '', $paragraphs );
		}

		return $content;
	} // END function inline_content_sidebar

	/**
	 * Function that returns an array of widget details for a specific sidebar
	 *
	 * This function is using a function that's private to WP Core,
	 * wp_get_sidebars_widgets(). Consider usage and find an alternative for
	 * this function if possible.
	 *
	 * See http://codex.wordpress.org/Function_Reference/wp_get_sidebars_widgets
	 *
	 * @TODO review wp_get_sidebars_widgets() for alternative - currently there isn't one
	 *
	 * @param $sidebar_id
	 *
	 * @return array
	 */
	public function get_sidebar_contents_array( $sidebar_id ) {
		global $wp_registered_widgets;
		$output = array(); // Holds the final data to return
		$sidebars_widgets = wp_get_sidebars_widgets(); // A nested array in the format $sidebar_id => array( 'widget_id-1', 'widget_id-2' ... );

		if ( isset( $sidebars_widgets[ $sidebar_id ] ) ) {
			$widget_ids = $sidebars_widgets[ $sidebar_id ];
		} else {
			$widget_ids = null;
		}

		// return an empty array to avoid E_WARNING errors because there are no widgets in the sidebar
		if ( $widget_ids === null ) {
			return array();
		}

		// Loop over each widget_id so we can fetch the data out of the wp_options table.
		foreach ( $widget_ids as $id ) {
			// The name of the option in the database is the name of the widget class.
			$option_name = $wp_registered_widgets[ $id ]['callback'][0]->option_name;
			$widget_instance_id = $wp_registered_widgets[ $id ]['id'];

			// Widget data is stored as an associative array. To get the right data we need to get the right key which is stored in $wp_registered_widgets
			$key = $wp_registered_widgets[ $id ]['params'][0]['number'];

			$widget_data = get_option( $option_name );
			$widget_data = $widget_data[ $key ];

			if ( isset( $widget_data['position'] ) ) {
				$widget_position = $widget_data['position'];
			} else {
				$widget_position = '';
			}
			if ( isset( $widget_data['display_at_end'] ) ) {
				$widget_display_at_end = $widget_data['display_at_end'];
			} else {
				$widget_display_at_end = '';
			}
			if ( isset( $widget_data['device_display'] ) ) {
				$widget_device_display = $widget_data['device_display'];
			} else {
				$widget_device_display = '';
			}

			// set the other details in an array so we can use them too
			$output_details = array(
				'id'             => $widget_instance_id,
				'position'       => $widget_position,
				'display_at_end' => $widget_display_at_end,
				'device_display' => $widget_device_display,
			);

			// Add the widget data on to the end of the output array.
			$output[] = $output_details;
		}

		return $output;

	} // END public function get_sidebar_contents_array

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
		$params = array_merge(
			array(
				array_merge( $sidebar,
					array( 'widget_id'   => $widget_id,
						   'widget_name' => $wp_registered_widgets[ $widget_id ]['name'] )
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
		if ( ! isset( $instance['position'] ) ) $instance['position'] = null;
		if ( ! isset( $instance['display_at_end'] ) ) $instance['display_at_end'] = null;
		if ( ! isset( $instance['device_display'] ) ) $instance['device_display'] = array();

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
		<option value="desktop" <?php if ( in_array( 'desktop', $instance['device_display'] ) ) echo 'selected'; ?>>
			Desktop
		</option>
		<option value="tablet" <?php if ( in_array( 'tablet', $instance['device_display'] ) ) echo 'selected'; ?>>
			Tablet
		</option>
		<option value="mobile" <?php if ( in_array( 'mobile', $instance['device_display'] ) ) echo 'selected'; ?>>
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
		if ( ! isset( $new_instance['display_at_end'] ) ) $new_instance['display_at_end'] = null;
		if ( ! isset( $new_instance['device_display'] ) ) $new_instance['device_display'] = array();

		$instance['position'] = $new_instance['position'];
		$instance['display_at_end'] = $new_instance['display_at_end'];
		$instance['device_display'] = $new_instance['device_display'];

		return $instance;
	} // END function widget_options_update

	/**
	 * Enqueue scripts used on widgets.php
	 *
	 * @param $hook
	 */
	function admin_enqueue_scripts($hook) {

		if ( 'widgets.php' == $hook ) {
			wp_register_script( 'select2_js', self::$installed_url . 'assets/select2.min.js', 'jquery', '3.5.2', false );
			wp_register_style( 'select2_css', self::$installed_url . 'assets/select2.css', '', '3.5.2' );
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
			echo '<style type="text/css">
				#ml-inline-content form div.ml-content-position {
					display: block !important;
					margin-bottom: 12px;
				}
			</style>';
			echo "<script type='text/javascript'>
				jQuery(function($){
					$(document).on('ready widget-added widget-updated', function() {
						$('.widgets-sortables select.multiselect').select2().removeClass('multiselect').addClass('fancy-select');
					});
				});
				</script>";
		}

	} // END function admin_print_scripts
}

global $ml_inline_content;
$ml_inline_content = new ML_Inline_Content;