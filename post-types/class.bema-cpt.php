<?php

if( ! class_exists( 'BEMA_Post_Type' )){
    class BEMA_Post_Type{
        public function __construct(){
            add_action( 'init', array( $this, 'create_post_type' ) );
            // add_action( 'init', array( $this, 'create_taxonomy' ) );
            add_action( 'init', array( $this, 'register_metadata_table' ) );
            add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

            add_action( 'wp_insert_post', array( $this, 'save_post' ), 10, 2 );
            add_action( 'delete_post', array( $this, 'delete_post' ) );
            
            add_filter( 'manage_bema_crm_posts_columns', array($this, 'bema_crm_columns' ) );
            add_action( 'manage_bema_crm_posts_custom_column', array($this, 'bema_crm_custom_columns'), 10, 2);
            add_filter('manage_edit-bema_crm_sortable_columns', array($this, 'bema_crm_sortable_columns' ));
        }

        public function create_post_type(){
            register_post_type(
                'bema_crm',
                array(
                    'label' => esc_html__( 'BEMA', 'bema_crm' ),
                    'description'   => esc_html__( 'BEMA', 'bema_crm' ),
                    'labels' => array(
                        'name'  => esc_html__( 'BEMA', 'bema_crm' ),
                        'singular_name' => esc_html__( 'BEMA', 'bema_crm' ),
                    ),
                    'public'    => true,
                    'supports'  => array( 'title', 'editor' ),
                    'rewrite'   => array( 'slug' => 'bema' ),
                    'hierarchical'  => false,
                    'show_ui'   => true,
                    'show_in_menu'  => false,
                    'menu_position' => 5,
                    'show_in_admin_bar' => true,
                    'show_in_nav_menus' => true,
                    'can_export'    => true,
                    'has_archive'   => true,
                    'exclude_from_search'   => false,
                    'publicly_queryable'    => true,
                    'show_in_rest'  => true,
                    'menu_icon' => 'dashicons-database',
                )
            );
        }

        // public function create_taxonomy(){
        //     register_taxonomy(
        //         'artist',
        //         'bema_crm',
        //         array(
        //             'labels' => array(
        //                 'name'  => __( 'Artists', 'bema_crm' ),
        //                 'singular_name' => __( 'Artist', 'bema_crm' ),
        //             ),
        //             'hierarchical' => true,
        //             'show_in_rest' => true,
        //             'public'    => true,
        //             'show_admin_column' => true
        //         )
        //     );
        // }

        public function register_metadata_table(){
            global $wpdb;
            $wpdb->bemameta = $wpdb->prefix . 'bemacrmmeta';
        }

        public function add_meta_boxes(){
            add_meta_box(
                'bema_meta_box',
                esc_html__( 'Bema Options', 'bema_crm' ),
                array( $this, 'add_inner_meta_boxes' ),
                'bema_crm',
                'normal',
                'high'
            );
        }

        public function bema_crm_columns( $columns ) {
            $columns['bema_tier'] = esc_html__( 'Tier' ,'bema_crm');
            $columns['bema_purchase_indicator'] = esc_html__( 'Purchase Indicator' ,'bema_crm');
            $columns['bema_campaign'] = esc_html__( 'Campaign' ,'bema_crm');
            $columns['bema_subscriber'] = esc_html__( 'Subscriber' ,'bema_crm');
            return $columns;
        }

        public function bema_crm_custom_columns( $column, $post_id){
            global $wpdb;
            $query = $wpdb->prepare( 
                "SELECT * FROM $wpdb->bemameta
                WHERE bema_id = %d",
                $post_id
            );
            $results = $wpdb->get_results( $query, ARRAY_A );
            $idx = count($results) - 1;

            switch( $column ){
                case 'bema_tier':
                    echo (isset($results[$idx]['tier']) ? esc_html($results[$idx]['tier']) : "");

                break;
                case 'bema_purchase_indicator':
                    echo (isset($results[$idx]['purchase_indicator']) ? esc_html($results[$idx]['purchase_indicator']) : "");
                break;
                case 'bema_campaign':
                    echo (isset($results[$idx]['campaign']) ? esc_html($results[$idx]['campaign']) : "");
                break;
                case 'bema_subscriber':
                    echo (isset($results[$idx]['subscriber']) ? esc_html($results[$idx]['subscriber']) : "");
                break;
            }
        }

        public function bema_crm_sortable_columns( $columns ) {
            $columns['bema_tier'] = 'bema_tier';
            $columns['bema_purchase_indicator'] = 'bema_purchase_indicator';
            $columns['bema_campaign'] = 'bema_campaign';
            $columns['bema_subscriber'] = 'bema_subscriber';
            return $columns;
        }

        public function add_inner_meta_boxes( $post ){
            require_once( BEMA_PATH . 'views/bema_crm_metabox.php' );
        }

        public static function save_post( $post_id, $post ){
            if( isset( $_POST['bema_nonce'] ) ){
                if( ! wp_verify_nonce( $_POST['bema_nonce'], 'bema_nonce' ) ){
                    return;
                }
            }

            if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ){
                return;
            }

            if( isset( $_POST['post_type'] ) && $_POST['post_type'] === 'bema_crm' ){
                if( ! current_user_can( 'edit_page', $post_id ) ){
                    return;
                }elseif( ! current_user_can( 'edit_post', $post_id ) ){
                    return;
                }
            }

            if (isset($_POST['action']) && $_POST['action'] == 'editpost') {

                $tier = sanitize_text_field( $_POST['bema_tier'] );
                $purchase_indicator = sanitize_text_field( $_POST['bema_purchase_indicator'] );
                $campaign = sanitize_text_field( $_POST['bema_campaign'] );
                $mailerlite_group_id = sanitize_text_field( $_POST['bema_mailerlite_group_id'] );
                $date_added = sanitize_text_field( $_POST['bema_date_added'] );
                $candidate = sanitize_text_field( $_POST['bema_candidate'] );
                $subscriber = sanitize_text_field( $_POST['bema_subscriber'] );
                $source = sanitize_text_field( $_POST['bema_source'] );

                global $wpdb;
                if( $_POST['bema_action'] == 'save' ){
                    if( get_post_type( $post ) == 'bema_crm' && 
                        $post->post_status != 'trash' &&
                        $post->post_status != 'auto-draft' &&
                        $post->post_status != 'draft') {
                        $wpdb->insert(
                            $wpdb->bemameta,
                            array(
                                'bema_id'    => $post_id,
                                'tier'  => $tier,
                                'purchase_indicator' => $purchase_indicator,
                                'campaign'  => $campaign,
                                'mailerlite_group_id'  => $mailerlite_group_id,
                                'date_added'  => $date_added,
                                'candidate'  => $candidate,
                                'subscriber'  => $subscriber,
                                'source' => $source
                            ),
                            array(
                                '%d', '%s', '%d', '%s', '%s','%s','%s','%s, %s'
                            )
                        );
                    }
                }
                // else{
                //     if( get_post_type( $post ) == 'bema_crm' ){
                //         $wpdb->update(
                //             $wpdb->BEMAmeta,
                //             array(
                //                 'meta_value'    => $transliteration
                //             ),
                //             array(
                //                 'BEMA_id'    => $post_id,
                //                 'meta_key'  => 'mv_BEMA_transliteration',   
                //             ),
                //             array( '%s' ),
                //             array( '%d', '%s' )
                //         );
                //         $wpdb->update(
                //             $wpdb->BEMAmeta,
                //             array(
                //                 'meta_value'    => $video
                //             ),
                //             array(
                //                 'BEMA_id'    => $post_id,
                //                 'meta_key'  => 'mv_BEMA_video_url',   
                //             ),
                //             array( '%s' ),
                //             array( '%d', '%s' )
                //         );
                //     }
                // }

            }
        }

        public function delete_post( $post_id ){
            if( ! current_user_can( 'delete_posts' ) ){
                return;
            }
            if( get_post_type( $post_id ) == 'bema_crm' ){
                global $wpdb;
                $wpdb->delete(
                    $wpdb->bemameta,
                    array( 'bema_id' => $post_id ),
                    array( '%d' )
                );
            }
        }
    }
}
