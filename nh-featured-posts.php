<?php
/*
Plugin Name: My Featured Posts Widget
Author: Nazanin Hesamzadeh
Author URI: https://profiles.wordpress.org/nazaninhesamzadeh
Description: Showing five recent featured posts in "Featured Posts Loader" widget.
version: 1.0.0
License: GPLv2
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

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('MFPW_SITE_URL' , get_site_url());

class MFPW_Admin{
    function __construct(){
        add_action( 'widgets_init', array($this,'load_widget'));
        add_action( 'add_meta_boxes', array($this,'custom_meta'));
        add_action( 'save_post', array($this,'custom_save_data'));
        add_filter( 'rest_post_query', array($this, 'modify_rest_post_query'), 10, 2 );
    }
	function load_widget() {
		register_widget( 'MFPW_widget' );
	}
	function custom_meta() {
		add_meta_box( 
			'MFPW_meta',
			__( 'Featured Posts', 'MFPW_ADMIN' ),
			array($this, 'meta_callback'),
			'post' ,
			'side',
			'high'
		);
	}
	function meta_callback( $post ) {
		wp_nonce_field( 'MFPW_custom_save_data' , 'custom_featured_nonce' );
		$featured = get_post_meta($post->ID, '_featured_post', true);
		echo "<label for='_featured_post'>".__('Featured Post ')."</label>";
		echo "<input type='checkbox' name='_featured_post' id='featured_post' value='1' " . checked(1, $featured, false) . " />";
		  
	}
	function custom_save_data( $post_id ) {
		if( ! isset( $_POST['custom_featured_nonce'] ) ){
			return;
		}
	
		if( ! wp_verify_nonce( $_POST['custom_featured_nonce'], 'MFPW_custom_save_data') ) {
			return;
		}
		if( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ){
			return;
		}
		if( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
	
		if ( isset( $_POST['_featured_post'] ) ) {
			update_post_meta( $post_id, '_featured_post', 1 );
		} else {
			delete_post_meta( $post_id, '_featured_post' );
		}
	}
	function modify_rest_post_query( $args, $request ){
		if ( $fpost = $request->get_param( '_featured_post' ) ) {
			$args['meta_key'] = '_featured_post';
			$args['meta_value'] = $fpost;
		}
		return $args;
	}
}


class MFPW_widget extends WP_Widget
{
    function __construct()
    {
        parent::__construct(
        'MFPW_widget',
        __('Featured Posts Loader', 'MFPW_widget'),
         array('description' => __('Featured Posts Loader Widget', 'MFPW_widget'),)
        );
    }
	public function get_posts_via_rest() {
		$allposts = '';
	   $response = wp_remote_get( MFPW_SITE_URL . '/wp-json/wp/v2/posts?_featured_post=1&orderby=date&order=desc&per_page=5' );
		if ( is_wp_error( $response ) ) {
			return;
		}
		$posts = json_decode( wp_remote_retrieve_body( $response ) );
		if ( empty( $posts ) ) {
			return;
		} else {
			foreach ( $posts as $post ) {
				$fordate = date('n/j/Y', strtotime($post->date));
				$allposts .= '<a href="' . esc_url($post->link) . '" target=\"_blank\">' . esc_html($post->title->rendered) . '</a> ('. esc_html($fordate) . ')<br/><div>'. wp_trim_words($post->content->rendered , 7) . '</div><hr />';
			}
			return $allposts;
		}
	}
	public function widget($args, $instance)
	{
		$title = apply_filters('widget_title', $instance['title']);
		echo $args['before_widget'];
		if (!empty($title)){
			echo $args['before_title'] . $title . $args['after_title'];
		}
		echo $this->get_posts_via_rest();
		echo $args['after_widget'];
	}
	public function form( $instance ) {
		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		}
		else {
			$title = __( 'New title', 'MFPW_widget' );
		}
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<?php
	}
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		return $instance;
	}
}

if(class_exists("MFPW_Admin") ){
    new MFPW_Admin();
}

?>