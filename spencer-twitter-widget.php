<?php
/**
 * Plugin Name: Spencer's Twitter Widget
 * Plugin URI: http://wordpress.org/extend/plugins/spencers-twitter-widget
 * Description: Fork of Wickett Twiter Widget for stand alone use.
 * Version: 0.1
 * Author: Spencer Finnell
 * Author URI: http://spencerfinnell.com
 * License: GPLv2
 * Requires at least: 3.4
 * Tested up to: 3.5-alpha
 */

/**
 * Custom widget for displaying recent tweets.
 *
 * Learn more: http://codex.wordpress.org/Widgets_API#Developing_Widgets
 *
 * @package Spencer's Twitter Widget
 * @since Spencer's Twitter Widget 0.1
 */
class Spencers_Twitter_Widget extends WP_Widget {

	/**
	 * Constructor
	 *
	 * @return void
	 **/
	function Spencers_Twitter_Widget() {
		$widget_ops = array( 
			'classname' => 'widget_spencers_twitter', 
			'description' => __( 'Use this widget to list your recent tweets', 'stw' ) 
		);

		$this->WP_Widget( 'widget_spencers_twitter', __( 'Spencer&#39;s Twitter Widget', 'stw' ), $widget_ops );
		$this->alt_option_name = 'widget_spencers_twitter';
	}

	/**
	 * Outputs the HTML for this widget.
	 *
	 * @param array An array of standard parameters for widgets in this theme
	 * @param array An array of settings for this widget instance
	 * @return void Echoes it's output
	 **/
	function widget( $args, $instance ) {
		if ( ! isset( $args['widget_id'] ) )
			$args['widget_id'] = null;

		ob_start();
		extract( $args, EXTR_SKIP );

		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Twitter', 'stw' ) : $instance['title'], $instance, $this->id_base);

		if ( ! isset( $instance['number'] ) )
			$instance['number'] = '10';

		if ( ! $number = absint( $instance['number'] ) )
 			$number = 10;

		
			echo $before_widget;
			echo $before_title;
			echo $title; // Can set this with a widget option, or omit altogether
			echo $after_title;

			echo $after_widget;

		// end check for ephemeral posts
		endif;
	}

	/**
	 * Deals with the settings when they are saved by the admin. Here is
	 * where any validation should be dealt with.
	 **/
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['number'] = (int) $new_instance['number'];

		return $instance;
	}

	/**
	 * Displays the form for this widget on the Widgets page of the WP Admin area.
	 **/
	function form( $instance ) {
		$title = isset( $instance['title']) ? esc_attr( $instance['title'] ) : '';
		$number = isset( $instance['number'] ) ? absint( $instance['number'] ) : 10;
?>
			<p><label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php _e( 'Title:', 'stw' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" /></p>

			<p><label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php _e( 'Number of posts to show:', 'stw' ); ?></label>
			<input id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="text" value="<?php echo esc_attr( $number ); ?>" size="3" /></p>
		<?php
	}
}