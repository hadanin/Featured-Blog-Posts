<?php
/*
Plugin Name: Featured Blog
Author: S.Nazanin Hesam Zadeh
Description: Show Featured Blog Posts in "Featured Posts Loader" widget, showing 5 recent ones
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

define('SITE_URL' , get_site_url());
function wpb_load_widget() {
    register_widget( 'nh_fb_widget' );
}
add_action( 'widgets_init', 'wpb_load_widget' );

class nh_fb_widget extends WP_Widget
{

    function __construct()
    {
        parent::__construct(
        'nh_fb_widget',
        __('Featured Posts Loader', 'nh_fb_widget_domain'),
         array('description' => __('Wp Engine Featured Posts Loader', 'nh_fb_widget_domain'),)
        );
    }
    public function get_posts_via_rest() {
        $allposts = '';
        $response = wp_remote_get( SITE_URL . '/wp-json/wp/v2/posts?filter[orderby]=date&order=desc' );
        if ( is_wp_error( $response ) ) {
            return;
        }
        $posts = json_decode( wp_remote_retrieve_body( $response ) );
        if ( empty( $posts ) ) {
            return;
        } else {
            $counter = 0;
            foreach ( $posts as $post ) {
                if ( isset(($post->post_meta_fields)->meta_checkbox) && ($post->post_meta_fields)->meta_checkbox == array("yes") && $counter <5){
                    $counter++;
                    $fordate = date('n/j/Y', strtotime($post->date));
                    $allposts .= '<a href="' . esc_url($post->link) . '" target=\"_blank\">' . esc_html($post->title->rendered) . '</a> ('. esc_html($fordate) . ')<br/><div>'. wp_trim_words($post->content->rendered , 7) . '</div><hr />';
                }
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
            $title = __( 'New title', 'nh_fb_widget_domain' );
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


/* Adding Featured Blog CheckBox */
function nh_custom_meta() {
    add_meta_box( 'nh_meta', __( 'Featured Posts', 'nh-textdomain' ), 'nh_meta_callback', 'post' );
}
function nh_meta_callback( $post ) {
    $featured = get_post_meta( $post->ID );
    ?>

    <p>
    <div class="nh-row-content">
        <label for="meta_checkbox">
            <input type="checkbox" name="meta_checkbox" id="meta_checkbox" value="yes" <?php if ( isset ( $featured['meta_checkbox'] ) ) checked( $featured['meta_checkbox'][0], 'yes' ); ?> />
            <?php _e( 'Featured on WP Engine\'s blog', 'nh-textdomain' )?>
        </label>

    </div>
    </p>
    <?php
}
add_action( 'add_meta_boxes', 'nh_custom_meta' );

/* Store meta post */
function nh_meta_save( $post_id ) {
   if( isset( $_POST[ 'meta_checkbox' ] ) ) {
        update_post_meta( $post_id, 'meta_checkbox', 'yes' );
    } else {
        update_post_meta( $post_id, 'meta_checkbox', '' );
    }
}
add_action( 'save_post', 'nh_meta_save' );

/* recalling posts in REST with post meta data*/
add_action( 'rest_api_init', 'create_api_posts_meta_field' );
function create_api_posts_meta_field() {
    register_rest_field( 'post', 'post_meta_fields', array(
            'get_callback'    => 'get_post_meta_for_api',
            'schema'          => null,
        )
    );
}
function get_post_meta_for_api( $object ) {
    $post_id = $object['id'];
    return get_post_meta( $post_id );
}

?>