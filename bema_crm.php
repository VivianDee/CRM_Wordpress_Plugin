<?php

/**
* Plugin Name: Bema CRM
* Plugin URI: https://www.wordpress.org/bema-crm
* Description: Bema Website Customer Relationship Model
* Version: 1.0
* Requires at least: 5.6
* Requires PHP: 7.0
* Author: Bema Integrated Services
* Author URI: https://bemamusic.com
* License: GPL v2 or later
* License URI: https://www.gnu.org/licenses/gpl-2.0.html
* Text Domain: bema-crm
* Domain Path: /languages
*/
/*
Bema CRM is open-source software released under the terms of the GNU General Public License, either version 2 of the License, or any later version.

Bema CRM is designed as a Customer Relationship Management (CRM) database plugin tailored for websites that specialize in selling albums. The plugin facilitates the creation of a comprehensive customer information table, enabling businesses to efficiently manage and organize customer data.

The software is distributed with the hope that it will be valuable to businesses, but it comes WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. For more details, refer to the GNU General Public License.

You should have received a copy of the GNU General Public License along with Bema CRM. If not, please visit https://www.gnu.org/licenses/gpl-2.0.html.
*/


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if( !class_exists( 'Bema_crm' )){

	class Bema_crm{

		public function __construct(){

		    $this->define_constants();

            add_action( 'admin_menu', array($this, 'add_menu'));

            require_once( BEMA_PATH . "post-types/class.bema-cpt.php" );
            $bemaranslationsPostType = new BEMA_Post_Type();

            require_once(BEMA_PATH . 'class.bema-settings.php');
            $Bema_Settings = new Bema_Settings();
		
        }

		public function define_constants(){
            // Path/URL to root of this plugin, with trailing slash.
			define ( 'BEMA_PATH', plugin_dir_path( __FILE__ ) );
            define ( 'BEMA_URL', plugin_dir_url( __FILE__ ) );
            define ( 'BEMA_VERSION', '1.0.0' );
		}

        /**
         * Activate the plugin
         */
        public static function activate(){
            update_option('rewrite_rules', '' );

            global $wpdb;

            $table_name = $wpdb->prefix . "bemacrmmeta";

            $bema_db_version = get_option( 'bemacrm_db_version' ) ;

            if( empty( $bema_db_version ) ){
                $query = "
                    CREATE TABLE $table_name (
                        meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        bema_id bigint(20) NOT NULL DEFAULT '0',
                        tier varchar(255) DEFAULT 'unassigned',
                        purchase_indicator bigint(20) NOT NULL DEFAULT '0',
                        campaign varchar(255) DEFAULT NULL,
                        mailerlite_group_id bigint(50) NOT NULL DEFAULT '0',
                        date_added DATETIME DEFAULT CURRENT_TIMESTAMP,
                        candidate varchar(255) DEFAULT NULL,
                        subscriber varchar(255) DEFAULT NULL,
                        source varchar(255) DEFAULT NULL,
                        PRIMARY KEY  (meta_id),
                        KEY bema_id (bema_id),
                        KEY tier (tier),
                        KEY purchase_indicator (purchase_indicator),
                        KEY campaign (campaign),
                        KEY mailerlite_group_id (mailerlite_group_id),
                        KEY date_added (date_added),
                        KEY candidate (candidate),
                        KEY subscriber (subscriber),
                        KEY source (source)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

                
                require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
                dbDelta( $query );

                $bema_db_version = '1.0';
                add_option( 'bemacrm_db_version', $bema_db_version );
            }

           /* if( $wpdb->get_row( "SELECT post_name FROM {$wpdb->prefix}posts WHERE post_name = 'submit-translation'" ) === null ){
                
                $current_user = wp_get_current_user();

                $page = array(
                    'post_title'    => __('Submit Translation', 'mv-translations' ),
                    'post_name' => 'submit-translation',
                    'post_status'   => 'publish',
                    'post_author'   => $current_user->ID,
                    'post_type' => 'page',
                    'post_content'  => '<!-- wp:shortcode -->[Bema_crm]<!-- /wp:shortcode -->'
                );
                wp_insert_post( $page );
            } */

        }

        public function add_menu() {
            add_menu_page (
                'Bema Options',
                'Bema CRM',
                'manage_options',
                'bema-crm_admin',
                array( $this, 'bema_crm_settings_page'),
                'dashicons-database',
                5
            );

            add_submenu_page(
                'bema-crm_admin',
                'Manage Bema Users',
                'Manage Bema Users',
                'manage_options',
                'edit.php?post_type=bema_crm', //from the slug of the slider post
                null, //we pass null bcos we dont want any content displayed bcos it is already displayed in our upper function
                null,  // for the position
            );

            add_submenu_page(
                'bema-crm_admin',
                'Add New CRM',
                'Add New CRM',
                'manage_options',
                'post-new.php?post_type=bema_crm', //from the slug of the slider post
                null, //we pass null bcos we dont want any content displayed bcos it is already displayed in our upper function
                null,  // for the position
            );

        }

        public function bema_crm_settings_page() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }
            if( isset( $_GET['settings-updated'] ) ){
                add_settings_error( 'bema_crm_options', 'bema_crm_message', 'Settings Saved', 'success' );
            }
            settings_errors('bema_crm_options');
            require(BEMA_PATH . 'views/settings-page.php');
        }

        /**
         * Deactivate the plugin
         */
        public static function deactivate(){
            flush_rewrite_rules();
            unregister_post_type( 'bema_crm' );
        }        

        /**
         * Uninstall the plugin
         */
        public static function uninstall(){

        }       

	}
}

// Plugin Instantiation
if (class_exists( 'Bema_crm' )){

    // Installation and uninstallation hooks
    register_activation_hook( __FILE__, array( 'Bema_crm', 'activate'));
    register_deactivation_hook( __FILE__, array( 'Bema_crm', 'deactivate'));
    register_uninstall_hook( __FILE__, array( 'Bema_crm', 'uninstall' ) );

    // Instatiate the plugin class
    $Bema_crm = new Bema_crm(); 
}