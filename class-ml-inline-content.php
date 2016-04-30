<?php

/**
 * ML Inline Content class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ML_Inline_Content {

	function __construct() {

		// add sidebar
		add_action( 'widgets_init', array( $this, 'register_inline_content_sidebar' ) );

		if ( is_admin() ) {
			require_once( MLIC_PLUGIN_DIR . 'class-ml-inline-content-admin.php' );
			new ML_Inline_Content_Admin();
		} else {
			add_filter( 'the_content', array( $this, 'inline_content_sidebar' ) );
		}

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

			include_once( MLIC_PLUGIN_DIR . '/class-mobile-detect.php' );
			$detect = new Mobile_Detect;

			// sort the $widget_details array by position number so we can go through in order
			usort( $widget_details, create_function( '$a, $b', 'if ($a["position"] == $b["position"]) return 0; return ($a["position"] < $b["position"]) ? -1 : 1;' ) );

			// reverse the array of widgets so we can go from bottom up & have an accurate count
			array_reverse( $widget_details );

			// grab the paragraphs from the content and break them out into an array
			$split_after          = '</p>';
			$paragraphs           = explode( $split_after, $content );
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

						$count ++;
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
		$output           = array(); // Holds the final data to return
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
			$option_name        = $wp_registered_widgets[ $id ]['callback'][0]->option_name;
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

}