<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/*
 * settings tabs
 */
class Jpress_Archive_Settings {

    /*
     * keys settings declarations
     */
    private $general_settings_key   = 'archive_settings';
    private $purge_settings_key     = 'purge_action';
    private $archive_center_key     = 'archives_center';
    private $archive_settings_tabs  = array();

    /*
     * Constructor fired during plugins_loaded
     */
    function __construct() {
        add_action( 'init', array( &$this, 'load_settings' ) );
        add_action( 'admin_init', array( &$this, 'register_general_settings' ) );
        add_action( 'admin_init', array( &$this, 'register_purge_action' ) );
        add_action( 'admin_menu', array( &$this, 'add_admin_menus' ) );
    }

    /*
     * general and purge settings
     */
    function load_settings() {
        $this->general_settings = (array) get_option( $this->general_settings_key );
        $this->purge_settings   = (array) get_option( $this->purge_settings_key );

        // Merge with defaults
        $this->general_settings = array_merge(
            array(
                'public_option'     => '0',
                'admin_list_option' => '0',
            ),
            $this->general_settings
        );

        $this->purge_settings = array_merge(
            array(
                'purge_option'      => '6M',
                'purge_type_option' => array( ),
            ),
            $this->purge_settings
        );
    }

    /*
     * Registers the archive settings via the Settings API
     */
    function register_general_settings() {
        $this->archive_settings_tabs[$this->general_settings_key] = __( 'Settings', 'jpress-archive' );

        register_setting( $this->general_settings_key, $this->general_settings_key );
        add_settings_section( 'section_settings', __( 'General Archive Settings', 'jpress-archive' ), NULL, $this->general_settings_key );
        add_settings_field( 'public_option', __( 'Front End visibility', 'jpress-archive' ), array( &$this, 'field_public_option' ), $this->general_settings_key, 'section_settings' );
        add_settings_field( 'admin_list_option', __( 'Back End visibility','jpress-archive' ), array( &$this, 'field_admin_list_option' ), $this->general_settings_key, 'section_settings' );
    }

    /*
     * Registers the purge settings
     */
    function register_purge_action() {
        $this->archive_settings_tabs[$this->purge_settings_key] = __( 'Purge', 'jpress-archive' );

        register_setting( $this->purge_settings_key, $this->purge_settings_key );
        add_settings_section( 'section_purge', __( 'Purge Settings', 'jpress-archive' ), NULL , $this->purge_settings_key );
        add_settings_field( 'purge_option', __( 'Delete automaticaly items older than', 'jpress-archive' ), array( &$this, 'field_purge_option' ), $this->purge_settings_key, 'section_purge' );
        add_settings_field( 'purge_type_option', __( 'Items to delete', 'jpress-archive' ), array( &$this, 'field_purge_type_option' ), $this->purge_settings_key, 'section_purge' );
    }

    /*
     * archive public field
     */
    function field_public_option() {
        ?>
        <label>
            <input type="checkbox" name="<?php echo $this->general_settings_key; ?>[public_option]" value="1" <?php if ( $this->general_settings['public_option']): ?>checked<?php endif; ?>/>
            <?php _e( 'Show archived post in front end', 'jpress-archive' );?>
        </label>
        <?php
    }

    /*
     * archive admin default list field
     */
    function field_admin_list_option(){
        ?>
        <label>
            <input type="checkbox" name="<?php echo $this->general_settings_key; ?>[admin_list_option]" value="1" <?php if ( $this->general_settings['admin_list_option']): ?>checked<?php endif; ?>/>
            <?php _e( 'Show archived post in default admin list view (without filtering post status)', 'jpress-archive' ); ?>
        </label>
        <?php
    }

    /*
     * purge date field
     */
    function field_purge_option() {
        $older_than = apply_filters( 'jpress_archive_older_than' , array(
            '3M',
            '6M',
            '1Y',
            '2Y',
            '3Y',
            '5Y',
            '10Y'
        ) );
        ?>
        <select name="<?php echo $this->purge_settings_key; ?>[purge_option]">
            <?php foreach ( $older_than as $dt ): ?>
                <option value="<?php echo $dt; ?>" <?php if ( $this->purge_settings['purge_option'] == $dt): ?>selected<?php endif; ?>>
                    <?php
                        $nbr = intval( $dt );
                        $str = preg_replace( '/[0-9]+/', '', $dt );
                        $str = str_replace( 'Y', _n( 'year', 'years', $nbr, 'jpress-archive' ) ,$str );
                        $str = str_replace( 'M', _n( 'month', 'months', $nbr , 'jpress-archive' ), $str );
                        echo $nbr . ' ' . $str;
                    ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    //post type field
    function field_purge_type_option() {
        global $wp_post_types;
        foreach ( $wp_post_types as $pt => $ob ):
            if( ! $ob->show_in_menu || $pt == 'attachment' ) continue;
            ?>
            <label>
                <input type="checkbox" name="<?php echo $this->purge_settings_key; ?>[purge_type_option][]" value="<?php echo $pt; ?>" <?php if ( in_array( $pt, $this->purge_settings['purge_type_option'] ) ): ?>checked<?php endif; ?>/>
                <?php echo ucfirst( $pt ); ?>
            </label>
            <br>
        <?php endforeach;
    }

    //add an admin page for purge archive and more features
    function add_admin_menus() {
        add_menu_page( __( 'Archives manager', 'jpress-archive' ), __( 'Archives manager', 'jpress-archive' ), apply_filters( 'jpress_archive_manager_capabilities', 'edit_posts'), $this->archive_center_key, array( &$this, 'archive_options_page' ), plugins_url() . '/jpress-archive/archive-white.png' );
    }

    /*
     * template for admin tabs  and pages
     */
    function archive_options_page() {
        $tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->general_settings_key;
        ?>
        <div class="wrap">
            <h2><?php _e( 'Archives center', 'jpress-archive' ); ?></h2>
            <?php $this->archive_options_tabs(); ?>
            <form method="post" action="options.php">
                <?php wp_nonce_field( 'update-options' ); ?>
                <?php settings_fields( $tab ); ?>
                <?php do_settings_sections( $tab ); ?>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /*
     * Renders our tabs in the archive options page,
     */
    function archive_options_tabs() {
        $current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->general_settings_key;

        echo '<h2 class="nav-tab-wrapper">';
        foreach ( $this->archive_settings_tabs as $tab_key => $tab_caption ) {
            $active = $current_tab == $tab_key ? 'nav-tab-active' : '';
            echo '<a class="nav-tab ' . $active . '" href="?page=' . $this->archive_center_key . '&tab=' . $tab_key . '">' . $tab_caption . '</a>';
        }
        echo '</h2>';
    }
};