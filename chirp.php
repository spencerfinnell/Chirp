<?php
/**
 * Plugin Name: Chirp
 * Plugin URI: https://github.com/spencerfinnell/Chirp
 * Description: Simple Twitter widget. Fork of Wickett Twiter Widget for stand-alone use.
 * Version: 0.1
 * Author: Spencer Finnell
 * Author URI: http://spencerfinnell.com
 * License: GPLv2
 * Requires at least: 3.4
 * Tested up to: 3.5-alpha
 */

/*
 * Register the widget.
 * 
 * @since Chirp 0.1
 */
function chirp_widget_init() {
	register_widget( 'Chirp_Twitter_Widget' );
}
add_action( 'widgets_init', 'chirp_widget_init' );

/**
 * Custom widget for displaying recent tweets.
 *
 * @since Chirp 0.1
 */
class Chirp_Twitter_Widget extends WP_Widget {

	/**
	 * Constructor
	 *
	 * @since Chirp 0.1
	 *
	 * @return void
	 */
	function Chirp_Twitter_Widget() {
		$widget_ops = array( 
			'classname' => 'widget_chirp', 
			'description' => __( 'Use this widget to list your recent tweets', 'chirp' ) 
		);

		$this->WP_Widget( 'widget_chirp', __( 'Chirp Twitter Widget', 'chirp' ), $widget_ops );
		$this->alt_option_name = 'widget_chirp';
	}

	/**
	 * Outputs the HTML for this widget.
	 *
	 * @since Chirp 0.1
	 *
	 * @param array An array of standard parameters for widgets in this theme
	 * @param array An array of settings for this widget instance
	 * @return void Echoes it's output
	 */
	function widget( $args, $instance ) {
		extract( $args );

		$account = trim( urlencode( $instance['account'] ) );
		if ( empty( $account ) )
			return;

		$title = apply_filters( 'widget_title', $instance[ 'title' ] );
		if ( empty( $title ) )
			$title = __( 'Twitter', 'chirp' );

		$show = absint( $instance[ 'number' ] );
		$show = $show > 200 ? 200 : $show;

		$hide_replies     = (bool) $instance[ 'hide_replies' ];
		$include_retweets = (bool) $instance[ 'include_retweets' ];

		$account_url = esc_url( sprintf( 'http://twitter.com/%s', $account ) );

		/** start the widget */
		echo $before_widget;

		/** title */
		echo $before_title;

			$title_display = sprintf( '<a href="%1$s">%2$s</a>', $account_url, esc_html( $title ) );
			echo apply_filters( 'chirp_title', $title_display, $title, $account_url );

		echo $after_title;

		if ( ! $tweets = wp_cache_get( 'chirp-' . $this->number , 'widget' ) ) {
			$params = array(
				'screen_name'      => $account,
				'trim_user'        => true,
				'include_entities' => false
			);

			if ( $hide_replies )
				$params[ 'exclude_replies' ] = true;
			else
				$params[ 'count' ] = $show;

			if ( $include_retweets )
				$params[ 'include_rts' ] = true;

			$twitter_json_url = esc_url_raw(
				'http://api.twitter.com/1/statuses/user_timeline.json?' . http_build_query( $params ),
				array( 'http', 'https' )
			);

			unset( $params );

			$response      = wp_remote_get( $twitter_json_url, array( 'User-Agent' => 'Chirp WordPress Twitter Widget' ) );
			$response_code = wp_remote_retrieve_response_code( $response );
			
			if ( 200 == $response_code ) {
				$tweets = wp_remote_retrieve_body( $response );
				$tweets = json_decode( $tweets, true );
				$expire = 900;

				if ( ! is_array( $tweets ) || isset( $tweets['error'] ) ) {
					$tweets = 'error';
					$expire = 300;
				}

				wp_cache_add( 'chirp-' . $this->number, $tweets, 'widget', $expire );
				wp_cache_add( 'chirp-response-code-' . $this->number, $response_code, 'widget', $expire );
			} else {
				$tweets = 'error';
				$expire = 300;

				wp_cache_add( 'chirp-response-code-' . $this->number, $response_code, 'widget', $expire );
			}
		}

		if ( 'error' != $tweets ) :
			/** before anything, but inside widget markup */
			do_action( 'chirp_list_before' );

			/** enclosing list markup. default: <ul class="tweets"> */
			echo apply_filters( 'chirp_list_start', '<ul class="tweets">' ) . "\n";

			$tweets_out = 0;

			foreach ( (array) $tweets as $tweet ) {
				if ( $tweets_out >= $show )
					break;

				if ( empty( $tweet[ 'text' ] ) )
					continue;

				$text = make_clickable( esc_html( $tweet['text'] ) );

				$text = preg_replace_callback( '/(^|[^0-9A-Z&\/]+)(#|\xef\xbc\x83)([0-9A-Z_]*[A-Z_]+[a-z0-9_\xc0-\xd6\xd8-\xf6\xf8\xff]*)/iu', array( $this, '_widget_twitter_hashtag' ), $text );

				$text = preg_replace_callback( '/([^a-zA-Z0-9_]|^)([@\xef\xbc\xa0]+)([a-zA-Z0-9_]{1,20})(\/[a-zA-Z][a-zA-Z0-9\x80-\xff-]{0,79})?/u', array( $this, '_widget_twitter_username' ), $text );

				if ( isset( $tweet[ 'id_str' ] ) )
					$tweet_id = urlencode( $tweet[ 'id_str' ] );
				else
					$tweet_id = urlencode( $tweet[ 'id' ] );

				/** before item start markup */
				do_action( 'chirp_item_before', $tweet );

				/** item start markup. default: <li> */
				echo apply_filters( 'chirp_item_start', '<li>', $tweet );

				$display = sprintf( '%1$s <a href="%2$s" class="timesince">%3$s ago</a>', $text, esc_url( "http://twitter.com/{$account}/statuses/{$tweet_id}" ), human_time_diff( strtotime( $tweet[ 'created_at' ] ), current_time( 'timestamp' ) ) ) . "\n";
	
				/** default display: {time} {text} */
				echo apply_filters( 'chirp_tweet', $display, $tweet, $text, $account );

				/** item end markup. default: </li> */
				echo apply_filters( 'chirp_item_end', '</li>', $tweet );

				/** after item end markup */
				do_action( 'chirp_item_after', $tweet );

				unset($tweet_id);

				$tweets_out++;
			}

			/** end enclosing list markup. default: </ul> */
			echo apply_filters( 'chirp_list_end', '</ul>' ) . "\n";

			/** after everything, still inside widget markup */
			do_action( 'chirp_list_after' );
		else :
			if ( 401 == wp_cache_get( 'chirp-response-code-' . $this->number , 'widget' ) )
				echo '<!-- Twitter widget failed ' . esc_html( sprintf( __( 'Error: Please make sure the Twitter account is <a href="%s">public</a>.'), 'http://support.twitter.com/forums/10711/entries/14016' ) ) . ' -->';
			else
				echo '<!-- Twitter widget failed ' . esc_html__( 'Error: Twitter did not respond. Please wait a few minutes and refresh this page.' ) . ' -->';
		endif;

		echo $after_widget;
	}

	/**
	 * Save and validate settings when the widget is updated.
	 *
	 * @since Chirp 0.1
	 *
	 * @param array $new_instance The modified settings
	 * @param array $old_instance The old/saved settings
	 * @return array $instance The validated settings
	 */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		
		$instance[ 'title' ]            = strip_tags( $new_instance[ 'title' ] );

		$instance[ 'account' ]          = trim( strip_tags( stripslashes( $new_instance[ 'account' ] ) ) );
		$instance[ 'account' ]          = str_replace( 'http://twitter.com/', '', $instance[ 'account' ] );
		$instance[ 'account' ]          = str_replace( '/', '', $instance[ 'account' ] );
		$instance[ 'account' ]          = str_replace( '@', '', $instance[ 'account' ] );
		$instance[ 'account' ]          = str_replace( '#!', '', $instance[ 'account' ] );

		$instance[ 'number' ]           = intval( $new_instance[ 'number' ] );

		$instance[ 'hide_replies' ]     = isset( $new_instance[ 'hide_replies' ] );
		$instance[ 'include_retweets' ] = isset( $new_instance[ 'include_retweets' ] );
		
		return $instance;
	}

	/**
	 * Displays the form for this widget on the Widgets page of the WP Admin area.
	 *
	 * @since Chirp 0.1
	 *
	 * @param array $instance The saved settings to be output
	 * @return void
	 */
	function form( $instance ) {
		$title            = isset( $instance[ 'title' ] ) ? esc_attr( $instance[ 'title' ] ) : '';
		$account          = isset( $instance[ 'account' ] ) ? esc_attr( $instance[ 'account' ] ) : '';
		$number           = isset( $instance[ 'number' ] ) ? intval( $instance[ 'number' ] ) : 5;
		$hide_replies     = isset( $instance[ 'hide_replies' ] ) && ! empty( $instance[ 'hide_replies' ] ) ? true : false;
		$include_retweets = isset( $instance[ 'include_retweets' ] ) && ! empty( $instance[ 'include_retweets' ] ) ? true : false;
?>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php _e( 'Title:', 'chirp' ); ?></label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'account' ) ); ?>"><?php _e( 'Twitter Username:', 'chirp' ); ?> <a href="https://support.twitter.com/articles/14609-how-to-change-your-username">(?)</a></label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'account' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'account' ) ); ?>" type="text" value="<?php echo esc_attr( $account ); ?>" />
			</p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php _e( 'Number of tweets to show:', 'chirp' ); ?></label>
				<select id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>">
					<?php for ( $i = 1; $i <=20; $i++ ) : ?>
					<option value="<?php echo absint( $i ); ?>" <?php selected( $i, $number ); ?>><?php echo absint( $i ); ?></option>
					<?php endfor; ?>
				</select>
			</p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'hide_replies' ) ); ?>">
					<input id="<?php echo esc_attr( $this->get_field_id( 'hide_replies' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'hide_replies' ) ); ?>" type="checkbox" <?php checked( true, $hide_replies ); ?>>
					<?php _e( 'Hide replies', 'chirp' ); ?>
				</label>
			</p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'include_retweets' ) ); ?>">
					<input id="<?php echo esc_attr( $this->get_field_id( 'include_retweets' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'include_retweets' ) ); ?>" type="checkbox" <?php checked( true, $include_retweets ); ?>>
					<?php _e( 'Include retweets', 'chirp' ); ?>
				</label>
			</p>
		<?php
	}

	/**
	 * Link a Twitter user mentioned in the tweet text to the user's page on Twitter.
	 *
	 * @since Chirp 0.1
	 * 
	 * @param array $matches regex match
	 * @return string Tweet text with inserted @user link
	 */
	private function _widget_twitter_username( $matches ) { // $matches has already been through wp_specialchars
		return "$matches[1]@<a href='" . esc_url( 'http://twitter.com/' . urlencode( $matches[3] ) ) . "'>$matches[3]</a>";
	}

	/**
	 * Link a Twitter hashtag with a search results page on Twitter.com
	 * 
	 * @since Chirp 0.1
	 *
	 * @param array $matches regex match
	 * @return string Tweet text with inserted #hashtag link
	 */
	private function _widget_twitter_hashtag( $matches ) { // $matches has already been through wp_specialchars
		return "$matches[1]<a href='" . esc_url( 'http://twitter.com/search?q=%23' . urlencode( $matches[3] ) ) . "'>#$matches[3]</a>";
	}
}