<?php
	/**
	 * Plugin Name: Video thumbnail
	 * Plugin URI:
	 * Description: This plugin allows you to associate a youtube video or vimeo the post and loads the thumbnail of the video image as automativamente highlight of the post.
	 * Author: leobaiano
	 * Author URI: http://lbideias.com.br
	 * Version: 1.0.0
	 * License: GPLv2 or later
	 * Text Domain: lb_video_thumbnail
 	 * Domain Path: /languages/
	 */
	if ( ! defined( 'ABSPATH' ) )
		exit; // Exit if accessed directly.

	/**
	 * Video_Thumbnail
	 *
	 * @author   Leo Baiano <leobaiano@lbideias.com.br>
	 */
	class Lb_Video_Thumbnail {

		/**
		 * Pluglin Slug
		 * @var strng
		 */
		public static $plugin_slug = 'lb_video_thumbnail';

		/**
		 * Instance of this class.
		 *
		 * @var object
		 */
		protected static $instance = null;

		/**
		 * Initialize the plugin
		 */
		private function __construct() {
			// Load plugin text domain
			add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );

			// Add metabox for custom field
			add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

			// Save custom field video
			add_action( 'save_post', array( $this, 'save_custom_field' ) );
		}

		/**
		 * Return an instance of this class.
		 *
		 * @return object A single instance of this class.
		 */
		public static function get_instance() {
			// If the single instance hasn't been set, set it now.
			if ( null == self::$instance ) {
				self::$instance = new self;
			}
			return self::$instance;
		}

		/**
		 * Load the plugin text domain for translation.
		 *
		 * @return void
		 */
		public function load_plugin_textdomain() {
			load_plugin_textdomain( self::$plugin_slug, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		/**
		 * Create meta box
		 */
		public function add_meta_box() {
			add_meta_box( self::$plugin_slug . '_video', __( 'Vídeo', self::$plugin_slug ), array( $this, 'create_custom_field' ), 'post', 'side', 'default', '' );
		}

		/**
		 * Create custom field
		 */
		public function create_custom_field() {
			global $post;
			wp_nonce_field( 'my_meta_box_nonce', 'meta_box_nonce' );
			$values = get_post_custom( $post->ID );
			$text = isset( $values[self::$plugin_slug . '_video'] ) ? esc_attr( $values[self::$plugin_slug . '_video'][0] ) : '';
			echo '<label for="video">' . _e( 'URL do vídeo', self::$plugin_slug ) . '</label><br />';
    		echo '<input type="text" name="' . self::$plugin_slug . '_video" id="video" value="' . $text . '" />';
		}

		/**
		 * Get url with curl
		 * Perform a GET request to a given URL.
		 * Uses `allow_url_fopen` if supported, or curl as a fallback.
		 */
		public function get($url) {
			if ( ini_get('allow_url_fopen' ) ) return file_get_contents( $url );
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			$result = curl_exec( $ch );
			curl_close( $ch );
			return $result;
		}

		/**
		 * Save custom field
		 */
		public function save_custom_field( $post_id ) {
			// Bail if we're doing an auto save
   			if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

    		// if our nonce isn't there, or we can't verify it, bail
    		if( !isset( $_POST['meta_box_nonce'] ) || !wp_verify_nonce( $_POST['meta_box_nonce'], 'my_meta_box_nonce' ) ) return;

    		// if our current user can't edit this post, bail
    		if( ! current_user_can( 'edit_post', $post_id ) ) return;

    		if( isset( $_POST[self::$plugin_slug . '_video'] ) ) {
       			update_post_meta( $post_id, self::$plugin_slug . '_video', wp_kses( $_POST[self::$plugin_slug . '_video'], $_POST[self::$plugin_slug . '_video'] ) );

       			if ( !has_post_thumbnail( $post_id ) ) {
       				$parse_url = parse_url( $_POST[self::$plugin_slug . '_video'] );
       				$domain = $parse_url['host'];
	       			parse_str( parse_url( $_POST[self::$plugin_slug . '_video'], PHP_URL_QUERY ), $url_vars );

	       			if ( 'www.youtube.com' == $domain || 'youtube.com' == $domain ) {
						$id_youtube = $url_vars['v'];
						$thumbnail = 'http://img.youtube.com/vi/' . $id_youtube . '/maxresdefault.jpg';
					} elseif ( 'vimeo.com' == $domain || 'www.vimeo.com' == $domain ) {
						$url_json_vimeo = 'https://vimeo.com/api/oembed.json?url=' . esc_url( $_POST[self::$plugin_slug . '_video'] );
						$data_vimeo = json_decode( $this->get( $url_json_vimeo ), true );
						$thumbnail = $data_vimeo['thumbnail_url'];
					} else {
						return;
					}
					$dia = date( "d" );
					$mes = date( "m" );
					$ano = date( "Y" );
					$caminho_int = ABSPATH . "wp-content/uploads/" . $ano . "/" . $mes . "/" . md5( 'dmYHi' ) . '.jpg';
					if(!is_dir(ABSPATH . "wp-content/uploads/" . $ano . "/" . $mes)) {
						mkdir(ABSPATH . "wp-content/uploads/" . $ano . "/" . $mes, 0777, true );
					}
					copy( $thumbnail, $caminho_int );
					$wp_filetype = wp_check_filetype( basename( $caminho_int ), null );
					$attachment = array(
						'guid' => basename( $caminho_int ),
						'post_mime_type' => $wp_filetype['type'],
						'post_title' => preg_replace('/\.[^.]+$/', '', basename( $caminho_int ) ),
						'post_content' => '',
						'post_status' => 'publish'
					);
					$attach_id = wp_insert_attachment( $attachment, $caminho_int, $post_id );

					require_once( ABSPATH . 'wp-admin/includes/image.php' );

					$attach_data = wp_generate_attachment_metadata( $attach_id, $caminho_int );
					wp_update_attachment_metadata( $attach_id, $attach_data );
					set_post_thumbnail( $post_id, $attach_id );
				}
    		}
		}
	}
	add_action( 'plugins_loaded', array( 'Lb_Video_Thumbnail', 'get_instance' ), 0 );
