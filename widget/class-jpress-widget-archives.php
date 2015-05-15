<?php
/**
 * create a widget to display archives
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * jPress Archives widget class,
 * extend the default widget WP_Widget_Archives class
 */
if ( class_exists( 'WP_Widget_Archives' ) ) {
    class Jpress_Widget_Archives extends WP_Widget_Archives {
        protected $_instance;

        public function __construct() {
            $widget_ops = array(
                'classname'     => 'widget_archive',
                'description'   => __( 'As monthly archive of your site&#8217;s Posts.', 'jpress-archive' )
            );
            parent::WP_Widget( 'jpress-archive', __( 'jPress Archives', 'jpress-archive' ), $widget_ops );
        }

        public function widget( $args, $instance ) {
            $this->_instance = $instance;
            //alter query and archive link only for the widgget
            add_filter( 'getarchives_where', array( $this, 'getarchives_where' ) );
            add_filter( 'get_archives_link', array( $this, 'get_archives_link' ) );
            parent::widget( $args, $instance );
            remove_filter( 'getarchives_where', array( $this,'getarchives_where' ) );
            remove_filter( 'get_archives_link', array( $this,'get_archives_link' ) );
        }

        public function getarchives_where( $sql ) {
            global $jpress_archive_settings;
            //if show archive in frontend
            if ( $jpress_archive_settings->general_settings['public_option'] ) {
                $sql = sprintf( "WHERE post_type = '%s' AND post_status = 'archived'", $this->_instance['type'] );
            } else {
                $sql = sprintf( "WHERE post_type = '%s' AND post_status = 'publish'", $this->_instance['type'] );
            }
            return $sql;
        }

        public function get_archives_link( $link_html ) {
            global $jpress_archive_settings;
            $post_status_request = '';

            //if show archive in frontend
            if ( $jpress_archive_settings->general_settings['public_option'] ) {
                $post_status_request = "&post_status=archived";
            }

            if ( strpos( $link_html, '<option' ) ) {
                $link_html = preg_replace( "/value='(.*?)'/", "value='$1?post_type=" . $this->_instance['type'] . $post_status_request . "'", $link_html );
            } else {
                $link_html = preg_replace( "/href='(.*?)'/", "href='$1?post_type=" . $this->_instance['type'] . $post_status_request . "'", $link_html );
            }
            return $link_html;
        }

        public function update( $new_instance, $old_instance ) {
            $instance           = parent::update( $new_instance, $old_instance );
            $instance['type']   = $new_instance['type'];
            return $instance;
        }

        public function form( $instance ) {
            $instance = wp_parse_args(
                (array)$instance,
                array(
                    'title'     => '',
                    'count'     => 0,
                    'dropdown'  => '',
                    'type'      => 'post'
                )
            );
            $type = strip_tags( $instance['type'] );
            parent::form( $instance );
            ?>
            <p>
                <label for="<?php echo $this->get_field_id( 'type' ); ?>"><?php _e( 'Post type :', 'jpress-archive' ); ?></label>
                <input class="widefat" id="<?php echo $this->get_field_id( 'type' ); ?>" name="<?php echo $this->get_field_name( 'type' ); ?>" type="text" value="<?php echo esc_attr( $type ); ?>" />
            </p>
            <?php
        }
    }
}