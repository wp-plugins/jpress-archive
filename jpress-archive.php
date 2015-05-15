<?php
/**
 * Plugin Name: jPress Archive
 * Plugin URI:
 * Text Domain: jpress-archive
 * Description: Manual archive features for all your post types using post status features.
 * Author: Johary Ranarimanana (Netapsys)
 * Author URI: http://www.netapsys.fr/
 * Version: 1.0.0
 * License: GPLv2 or later
 * Domain Path: /languages/
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

require_once 'admin/class-jpress-archive-settings.php';
global $jpress_archive_settings;

// register post status archive
add_action( 'init', 'jpress_archive_post_status' );
function jpress_archive_post_status() {
    global $jpress_archive_settings;
    $jpress_archive_settings->load_settings();

    register_post_status( 'archived', array(
        'label'                     => __( 'Archived', 'jpress-archive' ),
        'public'                    => $jpress_archive_settings->general_settings['public_option'],
        'exclude_from_search'       => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Archived <span class="count">(%s)</span>', 'Archived <span class="count">(%s)</span>', 'jpress-archive' ),
    ) );
}

//plugin register traduction
add_action( 'plugins_loaded', 'jpress_archive_plugins_loaded' );
function jpress_archive_plugins_loaded() {
    global $jpress_archive_settings;
    //localisation
    load_plugin_textdomain( 'jpress-archive', false, dirname(plugin_basename(__FILE__)).'/languages/' );

    //admin page
    $jpress_archive_settings = new Jpress_Archive_Settings();
}

//create sublink archive for all post types menus
add_action( 'admin_menu', 'jpress_archive_admin_menu' );
function jpress_archive_admin_menu() {
    global $submenu, $wp_post_types;

    foreach ( $wp_post_types as $pt => $data ) {
        switch( $pt ) {
            case 'post':
                $submenu['edit.php'][] = array( 'Archives', 'edit_posts', 'edit.php?post_type=post&post_status=archived' );
                break;
            default:
                $submenu['edit.php?post_type=' . $pt][] = array( 'Archives', 'edit_posts', 'edit.php?post_type=' . $pt . '&post_status=archived' );
                break;
        }
    }
}

//add row action archive and restore for all post types
add_filter( 'page_row_actions','jpress_archive_add_archive_link', 10, 2 );
add_filter( 'post_row_actions','jpress_archive_add_archive_link', 10, 2 );
function jpress_archive_add_archive_link( $actions, $id ) {
    global $post;

    $post_type_object = get_post_type_object( $post->post_type );

    if ( ! current_user_can( $post_type_object->cap->delete_post, $post->ID ) )
        return $actions;

    if ( $post->post_status != 'archived' ) {
        $archive_link       = admin_url( 'admin.php?post=' . $post->ID . '&action=jpress_archive' );
        $archive_link       = wp_nonce_url( $archive_link, "jpress_archive-{$post->post_type}_{$post->ID}" );
        $actions['archive'] = '<a href="' . $archive_link
                            . '" title="'
                            . esc_attr( __( 'Move to archive', 'jpress-archive'  ) )
                            . '">' . __( 'Archive', 'jpress-archive'  )
                            . '</a>';
    } else {
        $archive_link       = admin_url( 'admin.php?post=' . $post->ID . '&action=jpress_unset_archive' );
        $archive_link       = wp_nonce_url( $archive_link, "jpress_unset_archive-{$post->post_type}_{$post->ID}" );
        $actions['restore'] = '<a href="' . $archive_link
                            . '" title="'
                            . esc_attr( __( 'Restore', 'jpress-archive'  ) )
                            . '">' . __( 'Restore', 'jpress-archive'  )
                            . '</a>';
    }

    return $actions;
}

//archive process action
add_action( 'admin_action_-1', 'jpress_archive_action'  );
add_action( 'admin_action_jpress_archive', 'jpress_archive_action'  );
function jpress_archive_action() {
    if ( ! (
        isset( $_GET['post']) &&
        (
            ( isset( $_REQUEST['action']) || isset( $_REQUEST['action2']) ) &&
            ( 'jpress_archive' == $_REQUEST['action'] || 'jpress_archive' == $_REQUEST['action2'] )
        )
    ) ) {
        wp_die( __( 'No post to archive !',  'jpress-archive'  ) );
    }

    $ids = ( isset( $_GET['post'] ) ? $_GET['post'] : $_REQUEST['post'] );
    $ids = ( ! is_array( $ids ) ? ( (array)$ids ) : $ids );
    if ( !empty($ids) ) {
        foreach ( $ids as $id ) {
            // add old post_status to post meta
            $pst = get_post( $id );
            add_post_meta( $id, 'jpress_archive_old_post_status', $pst->post_status, TRUE );
            // change post status
            jpress_archive_change_post_status( $id, 'archived' );
        }
        $redirect_post_type = '';
        $archived_post_type = get_post_type( $ids[0] );
        if ( ! empty( $archived_post_type ) ) {
            $redirect_post_type = 'post_type=' . $archived_post_type . '&';
        }
        wp_redirect( admin_url( 'edit.php?' . $redirect_post_type . '&post_status=archived&archived=1&ids=' . implode( ',', $ids ) ) );
        exit;
    } else {
        wp_die( __( 'Sorry, no ID specified.', 'jpress-archive' ) );
    }

}

//restore action process
add_action( 'admin_action_jpress_unset_archive', 'jpress_archive_restore_action'  );
function jpress_archive_restore_action() {

    if ( ! (
        isset( $_GET['post'] ) &&
        ( isset( $_REQUEST['action'] ) && 'jpress_unset_archive' == $_REQUEST['action'] )
    ) ) {
        wp_die( __('No post to restore !', 'jpress-archive' ) );
    }

    $id = (int)( isset( $_GET['post'] ) ? $_GET['post'] : $_REQUEST['post'] );
    if ( $id ) {
        $redirect_post_type = '';
        // get archived post type
        $archived_post_type     = get_post_type( $id );
        $archived_post_status   = get_post_meta( $id, 'jpress_archive_old_post_status', TRUE );
        $archived_post_status   = empty( $archived_post_status ) ? 'publish' : $archived_post_status;

        if ( ! empty( $archived_post_status ) ){
            $redirect_post_type = 'post_type=' . $archived_post_type . '&';
        }
        // change post status to old archived post status
        jpress_archive_change_post_status( $id, $archived_post_status );
        // remove archived post type on post meta
        delete_post_meta( $id, 'jpress_archive_old_post_status' );
        // redirect to edit-page od post type
        wp_redirect( admin_url( 'edit.php?' . $redirect_post_type . 'unset_archived=1&ids=' . $id ) );
        exit;
    } else {
        wp_die( __( 'Sorry, no ID specified.', 'jpress-archive' ) );
    }

}

//change post status for post
function jpress_archive_change_post_status( $id, $ps ) {
    global $wpdb;
    //compatibility with jcpt create post table
    if ( function_exists( 'jcpt_whois' ) ) {
        $post_type = jcpt_whois( $id );
        $wpdb->update( $wpdb->prefix . $post_type, array( 'post_status' => $ps ), array( 'ID' => $id ) );
    } else {
        $wpdb->update( $wpdb->posts, array( 'post_status' => $ps ), array( 'ID' => $id ) );
    }
}

//add admin notice settings
add_action( 'admin_notices', 'jpress_archive_get_admin_notices' );
function jpress_archive_get_admin_notices() {
    settings_errors( 'archived_message' );
    settings_errors( 'unset_archived_message' );
}

//admin message and notice after archive/restore action
add_action( 'admin_init', 'jpress_archive_add_settings_error' );
function jpress_archive_add_settings_error() {

    $message_archived       = NULL;
    $message_unset_archived = NULL;

    //message for archive action
    if (
            isset( $_REQUEST['ids'] ) &&
            (
                isset( $_REQUEST['archived'] ) ||
                (
                    ( isset( $_REQUEST['action'] ) || isset( $_REQUEST['action2'] ) ) &&
                    ( 'jpress_archive' == $_REQUEST['action'] || 'jpress_archive' == $_REQUEST['action2'] )
                )
            )
        ) {
        $nbrelt = sizeof( explode( ',', $_REQUEST['ids'] ) );
        $message_archived = sprintf(
            _n( 'The item was moved to the archives.',
                '%s items were moved to the archives.',
                $nbrelt,
                'jpress-archive'
            ),
            $nbrelt
        );

        add_settings_error(
            'archived_message',
            'archived',
            $message_archived,
            'updated'
        );
    }

    //message for restore action
    if ( isset( $_REQUEST['unset_archived'] ) ) {
        $nbrelt = sizeof( explode( ',', $_REQUEST['ids'] ) );
        $message_unset_archived = sprintf(
            _n( 'The item has been restored : %2$s.',
                '%1$s items were restored : %2$s.',
                $nbrelt,
                'jpress-archive'
            ),
            $nbrelt,
            '<code>' . get_post_type( $_REQUEST['ids'] ) . '</code>'
        );

        add_settings_error(
            'unset_archived_message',
            'unset_archived',
            $message_unset_archived,
            'updated'
        );
    }

}

//add post status 'archived' to where part of request in list page
add_filter( 'posts_where', 'jpress_archive_posts_where' );
function jpress_archive_posts_where( $sql ) {
    global $pagenow, $wpdb, $jpress_archive_settings;

    //if show archived post in defaut admin list view
    if ( $jpress_archive_settings->general_settings['admin_list_option'] ) {
        if ( is_admin() &&
            $pagenow == 'edit.php' &&
            ! isset( $_REQUEST['post_status'] ) &&
            strpos( $sql, "post_status = 'publish' OR" )
        ) {
            $sql = preg_replace( "/AND \(([a-zA-Z_]+).post_status = 'publish' OR/", "AND ($1.post_status = 'publish' OR $1.post_status = 'archived' OR", $sql );
        }
    } else {
        if ( is_admin() &&
            $pagenow == "edit.php" &&
            strpos( $sql, "post_status = 'archived'" )
        ) {
            $sql = str_replace( "OR {$wpdb->prefix}posts.post_status = 'archived'", ' ', $sql );
        }
    }

    return $sql;
}

//add 'Archived' information for archived post
add_filter( 'display_post_states', 'jpress_archive_display_post_states', 10, 2 );
function jpress_archive_display_post_states( $post_states, $post ) {
    if ( $post->post_status == 'archived' ) {
        $post_states['archived'] = "<span style='color:#D54E21;'>" . __( 'Archived', 'jpress-archive' ) . "</span>";
    }
    return $post_states;
}

//add admin scripts
add_action( 'admin_footer', 'jpress_archive_append_post_status_list' );
function jpress_archive_append_post_status_list() {
    global $post, $pagenow;
    $complete   = '';
    $label      = '';
    if ( $pagenow == 'post.php' || $pagenow == 'post-new.php' ) {
        if( $post->post_status == 'archived' ) {
            $complete   = ' selected="selected" ';
            $label      = '<span id="post-status-display">' . __( 'Archived','jpress-archive' ) . '</span>';
        }
        ?>
          <script type="text/javascript">
              //add post status archived to edit page
              jQuery( document ).ready( function() {
                   jQuery( "select#post_status" ).append( '<option value="archived" <?php echo $complete; ?>><?php _e( 'Archived', 'jpress-archive' ); ?></option>' );
                   jQuery( ".misc-pub-section label" ).append( '<?php echo $label; ?>' );
              });
          </script>
        <?php
    } elseif ( $pagenow == 'edit.php' ) {
          ?>
          <script type="text/javascript">
                //add inline edit post status 'archived'
                jQuery( ".inline-edit-status select" ).append( "<option value='archived'><?php _e( 'Archived', 'jpress-archive' ); ?></option>" );

                //add bulk action 'archive'
                jQuery( document ).ready( function() {
                    jQuery('<option>').val( 'jpress_archive' ).text( '<?php _e('Archive','jpress-archive'); ?>' ).appendTo( "select[name='action']" );
                    jQuery('<option>').val( 'jpress_archive' ).text( '<?php _e('Archive','jpress-archive'); ?>' ).appendTo( "select[name='action2']" );
                });
          </script>
          <?php
    }
}

//add widget to display post archive
add_action( 'widgets_init', 'jpress_archive_widgets_init' );
function jpress_archive_widgets_init() {
    require_once( 'widget/class-jpress-widget-archives.php' );
    register_widget( 'Jpress_Widget_Archives' );
}

//distinct query archive for custom post type
add_filter( 'parse_query', 'jpress_archive_parse_query' );
function jpress_archive_parse_query( $q ) {
    if( !is_admin() &&
        $q->is_archive &&
        isset( $_REQUEST['post_status'] ) &&
        'archived' == $_REQUEST['post_status']  &&
        isset( $_REQUEST['post_type'] )
    ) {
       $q->query_vars['post_status']    = 'archived';
       $q->query_vars['post_type']      = $_REQUEST['post_type'];
    }
    return $q;
}

//////// cron job for purge///////////////////////////////////////////////
if ( ! wp_next_scheduled( 'jpress_archive_cron_hook' ) ) {
    wp_schedule_event( time(), 'daily', 'jpress_archive_cron_hook' );
}
add_action( 'jpress_archive_cron_hook', 'jpress_archive_cron_action' );
function jpress_archive_cron_action() {
    global $jpress_archive_settings, $wpdb;
    $jpress_archive_settings->load_settings();

    $delay  = $jpress_archive_settings->purge_settings['purge_option'];
    $pt     = $jpress_archive_settings->purge_settings['purge_type_option'];
    //if no post type to delete
    if( ! isset($pt) || empty( $pt ) ) exit;

    $nbr    = intval( $delay );
    $str    = preg_replace( '/[0-9]+/', '', $delay );
    $str    = ( $str == 'M' ) ? 'month' : 'year';

    //get a date query post
    $newquery = new WP_Query( array(
        'post_type'     => $pt,
        'number_posts'  => -1,
        'post_status'   => 'archived',
        'date_query'    => array(
            array(
                'column' => 'post_date',
                'before' => $nbr . ' ' . $str . ' ago',
            ),
        ),
        'fields'        => 'ids'
    ));

    $ids_to_delete = $newquery->get_posts();
    if ( $ids_to_delete && ! empty( $ids_to_delete ) ) {
        $ids_to_delete = array_map( 'intval', $ids_to_delete );
        $str_ids = implode( ',', $ids_to_delete );
        $sql = "DELETE FROM " . $wpdb->posts . " WHERE ID IN (" .  $str_ids . ")";
        $wpdb->query( $sql );
    }
}