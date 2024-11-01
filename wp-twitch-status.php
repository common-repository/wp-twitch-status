<?php
/**
 * Plugin Name: WP Twitch Status
 * Plugin URI: https://wordpress.org
 * Description: A Twitch integration for WP that shows the status of a Twitch channel.
 * Version: 1.0.0
 * Author: Nicola Mustone
 * Author URI: https://nicola.blog
 * Requires at least: 4.4
 * Tested up to: 4.7
 *
 * Text Domain: wp-twitch-status
 * Domain Path: /languages/
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WP_Twitch_Status {
	/**
	 * Twitch App Client ID.
	 * @var    string
	 * @access private
	 */
	private $_client_id = '';

	/**
	 * Sets the App Client ID.
	 */
	public function __construct() {
		require_once __DIR__ . '/wp-twitch-status-widget.php';

		add_action( 'admin_init', array( $this, 'settings_init' ) );
		add_action( 'widgets_init', array( $this, 'register_widget' ) );

		$this->load_textdomain();

		$this->_client_id = get_option( 'wp_twitch_status_client_id' );

		add_shortcode( 'twitch_stream_status', array( $this, 'get_stream_status_html' ) );
	}

	/**
	 * Inits the settings page.
	 */
	public function settings_init() {
		add_settings_section(
			'wp_twitch_status_settings',
			esc_html__( 'WP Twitch Status', 'wp-twitch-status' ),
			array( $this, 'settings_callback'),
			'general'
		);

		add_settings_field(
			'wp_twitch_status_client_id',
			esc_html__( 'Client ID', 'wp-twitch-status' ),
			array( $this, 'print_client_id_field' ),
			'general',
			'wp_twitch_status_settings'
		);

		register_setting( 'general', 'wp_twitch_status_client_id' );
	}

	/**
	 * Prints the description in the settings page.
	 */
	public function settings_callback() {
		esc_html_e( 'Configure your WP Twitch Status instance to get the data of your stream.', 'wp-twitch-status' );
	}

	/**
	 * Prints the Client ID settings field.
	 */
	public function print_client_id_field() {
		$value = get_option( 'wp_twitch_status_client_id' );

		echo '<input type="text" name="wp_twitch_status_client_id" value="' . esc_attr( $value ) . '" style="width:300px;margin-right:10px;" />';
		echo '<span class="description">' . sprintf( esc_html__( 'The Client ID of your Twitch app. %1$sLearn more%2$s', 'wp-twitch-status' ), '<a href="https://blog.twitch.tv/client-id-required-for-kraken-api-calls-afbb8e95f843#119e">', '</a>' ) . '</span>';
	}

	/**
	 * Prints the HTML for the Twitch status.
	 *
	 * @param  array $atts
	 * @return string
	 */
	public function get_stream_status_html( $atts ) {
		extract( shortcode_atts( array(
			'channel'        => '',
			'wrap'           => 'div',
			'print_username' => 'yes',
			'print_game'     => 'no',
		), $atts ) );

		if ( empty( $channel ) ) {
			return;
		}

		$data   = $this->get_data( $channel, $print_game );
		$status = 'live' === $data['status'] ? esc_html__( 'Live', 'wp-twitch-status' ) : esc_html__( 'Offline', 'wp-twitch-status' );
		$intro  = sprintf( esc_html__( '%s is', 'wp-twitch-staus' ), '<a href="https://twitch.tv/' . $channel . '" target="_blank">' . ( 'yes' === $print_username ? $channel : esc_html__( 'Stream', 'wp-twitch-staus' ) ) . '</a>' );

		if ( 'yes' === $print_game && 'live' === $data['status'] ) {
			$between = 'Creative' === $data['game'] ? esc_html__( 'streaming', 'wp-twitch-status' ) : esc_html__( 'playing', 'wp-twitch-status' );
			$game    = ' ' . $between . ' ' . $data['game'];
		} else {
			$game = '';
		}

		$html  = '';
		$html .= '<' . esc_html( $wrap ) . ' class="wp-twitch-status">';
			if ( $wrap === 'div' ) {
				$html .= '<p>';
			}

			$html .= '<span class="wp-twitch-status-intro">' . $intro . ' </span>';
			$html .= '<span class="wp-twitch-status-status ' . esc_attr( strtolower( $status ) ) . '">' . esc_html( $status ) . $game . '</span>';

			if ( $wrap === 'div' ) {
				$html .= '</p>';
			}

		$html .= '</' . esc_html( $wrap ) . '>';

		return $html;
	}

	/**
	 * Checks if a Twitch channel is currently streaming or not.
	 *
	 * @param  string $channel
	 * @return bool
	 */
	public function get_data( $channel, $print_game = false ) {
		$channel = sanitize_title( $channel );
		$data    = wp_cache_get( 'wp-twitch-stream-' . $channel );

		if ( false !== $data ) {
			return $data;
		}

		$response = wp_remote_get( 'https://api.twitch.tv/kraken/streams/' . $channel . '?client_id=' . $this->_client_id );

		if ( ! is_wp_error( $response ) && 200 === $response['response']['code'] ) {
			$body = wp_remote_retrieve_body( $response );
			$body = json_decode( $body );

			$stream = $body->stream;

			if ( ! empty( $stream ) ) {
				$data['status'] = 'live';
				$data['game']   = $stream->game;
			} else {
				$data['status'] = 'offline';
			}

			wp_cache_set( 'wp-twitch-stream-' . $channel, $data, false, 60 * 30 );
		}

		return $data;
	}

	/**
	 * Registers the widget WP_Twitch_Status_Widget.
	 */
	public function register_widget() {
		register_widget( 'WP_Twitch_Status_Widget' );
	}

	/**
	 * Loads the plugin localization files.
	 */
	public function load_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'wp-twitch-status' );
		load_textdomain( 'wp-twitch-status', WP_LANG_DIR . '/wp-twitch-status/wp-twitch-status-' . $locale . '.mo' );
		load_plugin_textdomain( 'wp-twitch-status', false, plugin_basename( __DIR__ ) . '/languages' );
	}
}

new WP_Twitch_Status();
