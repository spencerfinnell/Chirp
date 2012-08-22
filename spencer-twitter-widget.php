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

/*
 * Instantiating the widget
 * 
 * @since Spencer's Twitter Widget 0.1
 */
function spencers_twitter_widget_init() {
	register_widget( 'Spencers_Twitter_Widget' );
}
add_action( 'widgets_init', 'spencers_twitter_widget_init' );

/**
 * Custom widget for displaying recent tweets.
 *
 * @since Spencer's Twitter Widget 0.1
 */
class Spencers_Twitter_Widget extends WP_Widget {

	/**
	 * Constructor
	 *
	 * @since Spencer's Twitter Widget 0.1
	 *
	 * @return void
	 */
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
	 * @since Spencer's Twitter Widget 0.1
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
			$title = __( 'Twitter', 'stw' );

		$show = absint( $instance[ 'number' ] );
		$show = $show > 200 ? 200 : $show;

		$hide_replies     = $instance[ 'hide_replies' ];
		$include_retweets = $instance[ 'include_retweets' ];

		$account_url = esc_url( sprintf( 'http://twitter.com/%s', $account ) );

		/** start the widget */
		echo $before_widget;

		/** title */
		echo $before_title;
			printf( apply_filters( 'stw_title', '<a href="%1$s">%2$s</a>' ), $account_url, esc_html( $title ) );
		echo $after_title;

		if ( ! $tweets = wp_cache_get( 'stw-' . $this->number , 'widget' ) ) {
			$params = array(
				'screen_name'      => $account,
				'trim_user'        => true,
				'include_entities' => false
			);

			if ( 'yes' == $hide_replies )
				$params[ 'exclude_replies' ] = true;
			else
				$params[ 'count' ] = $show;

			if ( 'yes' == $include_retweets )
				$params[ 'include_rts' ] = true;

			$twitter_json_url = esc_url_raw(
				'http://api.twitter.com/1/statuses/user_timeline.json?' . http_build_query( $params ),
				array( 'http', 'https' )
			);

			unset( $params );

			$response      = wp_remote_get( $twitter_json_url, array( 'User-Agent' => 'Spencer&#39;s Twitter Widget' ) );
			$response_code = wp_remote_retrieve_response_code( $response );
			
			if ( 200 == $response_code ) {
				$tweets = wp_remote_retrieve_body( $response );
				$tweets = json_decode( $tweets, true );
				$expire = 900;

				if ( ! is_array( $tweets ) || isset( $tweets['error'] ) ) {
					$tweets = 'error';
					$expire = 300;
				}

				wp_cache_add( 'stw-' . $this->number, $tweets, 'widget', $expire );
				wp_cache_add( 'stw-response-code-' . $this->number, $response_code, 'widget', $expire );
			} else {
				$tweets = 'error';
				$expire = 300;

				wp_cache_add( 'stw-response-code-' . $this->number, $response_code, 'widget', $expire );
			}
		}

		if ( 'error' != $tweets ) :
			do_action( 'stw_list_before' );

			echo apply_filters( 'stw_list_start', '<ul class="tweets">' ) . "\n";

			$tweets_out = 0;

			foreach ( (array) $tweets as $tweet ) {
				if ( $tweets_out >= $show )
					break;

				if ( empty( $tweet[ 'text' ] ) )
					continue;

				$text = make_clickable( esc_html( $tweet['text'] ) );

				/*
				 * Create links from plain text based on Twitter patterns
				 * @link http://github.com/mzsanford/twitter-text-rb/blob/master/lib/regex.rb Official Twitter regex
				 */
				$text = preg_replace_callback( '/(^|[^0-9A-Z&\/]+)(#|\xef\xbc\x83)([0-9A-Z_]*[A-Z_]+[a-z0-9_\xc0-\xd6\xd8-\xf6\xf8\xff]*)/iu', array( $this, '_widget_twitter_hashtag' ), $text );

				$text = preg_replace_callback( '/([^a-zA-Z0-9_]|^)([@\xef\xbc\xa0]+)([a-zA-Z0-9_]{1,20})(\/[a-zA-Z][a-zA-Z0-9\x80-\xff-]{0,79})?/u', array( $this, '_widget_twitter_username' ), $text );

				if ( isset( $tweet[ 'id_str' ] ) )
					$tweet_id = urlencode( $tweet[ 'id_str' ] );
				else
					$tweet_id = urlencode( $tweet[ 'id' ] );

				do_action( 'stw_item_before' );

				echo apply_filters( 'stw_item_start', '<li>' );

				printf( apply_filters( 'stw_tweet', '%1$s <a href="%2$s" class="timesince">%3$s ago</a>' ), $text, esc_url( "http://twitter.com/{$account}/statuses/{$tweet_id}" ), human_time_diff( strtotime( $tweet[ 'created_at' ] ), current_time( 'timestamp' ) ) );

				echo apply_filters( 'stw_item_end', '</li>' );

				do_action( 'stw_item_after' );

				unset($tweet_id);

				$tweets_out++;
			}

			echo apply_filters( 'stw_list_end', '</ul>' ) . "\n";

			do_action( 'stw_list_after' );
		else :
			if ( 401 == wp_cache_get( 'stw-response-code-' . $this->number , 'widget' ) )
				echo '<!-- Twitter widget failed ' . esc_html( sprintf( __( 'Error: Please make sure the Twitter account is <a href="%s">public</a>.'), 'http://support.twitter.com/forums/10711/entries/14016' ) ) . ' -->';
			else
				echo '<!-- Twitter widget failed ' . esc_html__('Error: Twitter did not respond. Please wait a few minutes and refresh this page.') . ' -->';
		endif;

		echo $after_widget;
	}

	/**
	 * Deals with the settings when they are saved by the admin. Here is
	 * where any validation should be dealt with.
	 *
	 * @since Spencer's Twitter Widget 0.1
	 */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		
		$instance[ 'title' ]            = strip_tags( $new_instance[ 'title' ] );
		$instance[ 'account' ]          = trim( strip_tags( stripslashes( $new_instance[ 'account' ] ) ) );
		$instance[ 'account' ]          = str_replace( 'http://twitter.com/', '', $instance[ 'account' ] );
		$instance[ 'account' ]          = str_replace( '/', '', $instance[ 'account' ] );
		$instance[ 'account' ]          = str_replace( '@', '', $instance[ 'account' ] );
		$instance[ 'account' ]          = str_replace( '#!', '', $instance[ 'account' ] );
		$instance[ 'number' ]           = (int) $new_instance[ 'number' ];
		$instance[ 'hide_replies' ]     = isset( $new_instance[ 'hide_replies' ] ) ? 'yes' : 'no';
		$instance[ 'include_retweets' ] = isset( $new_instance[ 'include_retweets' ] ) ? 'yes' : 'no';
		
		return $instance;
	}

	/**
	 * Displays the form for this widget on the Widgets page of the WP Admin area.
	 *
	 * @since Spencer's Twitter Widget 0.1
	 */
	function form( $instance ) {
		$title            = isset( $instance[ 'title' ]) ? esc_attr( $instance[ 'title' ] ) : '';
		$account          = isset( $instance[ 'account' ] ) ? esc_attr( $instance[ 'account' ] ) : '';
		$number           = isset( $instance[ 'number' ] ) ? absint( $instance[ 'number' ] ) : 5;
		$hide_replies     = ( 'yes' == $instance[ 'hide_replies' ] ) ? true : false;
		$include_retweets = ( 'yes' == $instance[ 'include_retweets' ] ) ? true : false;
?>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php _e( 'Title:', 'stw' ); ?></label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'account' ) ); ?>"><?php _e( 'Twitter Username:', 'stw' ); ?> <a href="https://support.twitter.com/articles/14609-how-to-change-your-username">(?)</a></label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'account' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'account' ) ); ?>" type="text" value="<?php echo esc_attr( $account ); ?>" />
			</p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php _e( 'Number of tweets to show:', 'stw' ); ?></label>
				<select id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>">
					<?php for ( $i = 1; $i <=20; $i++ ) : ?>
					<option value="<?php echo absint( $i ); ?>" <?php selected( $i, $number ); ?>><?php echo absint( $i ); ?></option>
					<?php endfor; ?>
				</select>
			</p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'hide_replies' ) ); ?>">
					<input id="<?php echo esc_attr( $this->get_field_id( 'hide_replies' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'hide_replies' ) ); ?>" type="checkbox" <?php checked( true, $hide_replies ); ?>>
					<?php _e( 'Hide replies', 'stw' ); ?>
				</label>
			</p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'include_retweets' ) ); ?>">
					<input id="<?php echo esc_attr( $this->get_field_id( 'include_retweets' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'include_retweets' ) ); ?>" type="checkbox" <?php checked( true, $include_retweets ); ?>>
					<?php _e( 'Include retweets', 'stw' ); ?>
				</label>
			</p>
		<?php
	}

	/**
	 * Link a Twitter user mentioned in the tweet text to the user's page on Twitter.
	 *
	 * @since Spencer's Twitter Widget 0.1
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
	 * @since Spencer's Twitter Widget 0.1
	 *
	 * @param array $matches regex match
	 * @return string Tweet text with inserted #hashtag link
	 */
	private function _widget_twitter_hashtag( $matches ) { // $matches has already been through wp_specialchars
		return "$matches[1]<a href='" . esc_url( 'http://twitter.com/search?q=%23' . urlencode( $matches[3] ) ) . "'>#$matches[3]</a>";
	}
}