<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.


/**
 * Class Disciple_Tools_24Hour_Stats_Magic_Link
 */
class Disciple_Tools_24Hour_Stats_Magic_Link {

    public $magic = false;
    public $parts = false;
    public $page_title = 'Starter - Magic Links - Post Type';
    public $page_description = 'Post Type - Magic Links.';
    public $root = "campaign_app";
    public $type = '24hour';
    public $post_type = 'campaigns';
    private $meta_key = '';
    public $show_bulk_send = false;
    public $show_app_tile = true;

    private static $_instance = null;
    public $meta = []; // Allows for instance specific data.

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct( $parts ) {

        $this->parts = $parts;

        $this->meta_key = $this->root . '_' . $this->type . '_magic_key';
//        parent::__construct();

        /**
         * post type and module section
         */
        add_action( 'dt_details_additional_section', [ $this, 'dt_details_additional_section' ], 30, 2 );
        add_action( 'rest_api_init', [ $this, 'add_endpoints' ] );


        /**
         * tests if other URL
         */
        $url = dt_get_url_path();
        if ( strpos( $url, $this->root . '/' . $this->type ) === false ) {
            return;
        }
        // load if valid url
        add_action( 'dt_blank_body', [ $this, 'body' ] ); // body for no post key
        add_filter( 'dt_magic_url_base_allowed_css', [ $this, 'dt_magic_url_base_allowed_css' ], 10, 1 );
        add_filter( 'dt_magic_url_base_allowed_js', [ $this, 'dt_magic_url_base_allowed_js' ], 10, 1 );
        add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 100 );
    }

    public function wp_enqueue_scripts(){
        wp_enqueue_script( 'magic_link_scripts', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'magic-link.js', [
            'jquery',
            'lodash',
        ], filemtime( plugin_dir_path( __FILE__ ) . 'magic-link.js' ), true );
        wp_localize_script(
            'magic_link_scripts', 'jsObject', [
                'map_key' => DT_Mapbox_API::get_key(),
                'root' => esc_url_raw( rest_url() ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'parts' => $this->parts,
                'translations' => [
                    'add' => __( 'Add Magic', 'disciple-tools-plugin-starter-template' ),
                ],
                'rest_namespace' => $this->root . '/v1/' . $this->type,
            ]
        );
        wp_enqueue_style( 'magic_link_css', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'magic-link.css', [],
        filemtime( plugin_dir_path( __FILE__ ) . 'magic-link.css' ) );

        $lang = "en_US";
        if ( isset( $_GET["lang"] ) && !empty( $_GET["lang"] ) ){
            $lang = sanitize_text_field( wp_unslash( $_GET["lang"] ) );
        } elseif ( isset( $_COOKIE["dt-magic-link-lang"] ) && !empty( $_COOKIE["dt-magic-link-lang"] ) ){
            $lang = sanitize_text_field( wp_unslash( $_COOKIE["dt-magic-link-lang"] ) );
        }
        dt_24hour_campaign_register_scripts([
            "root" => $this->root,
            "type" => $this->type,
            "public_key" => $this->parts["public_key"],
            "meta_key" => $this->parts["meta_key"],
            "post_id" => $this->parts["post_id"],
            "rest_url" => rest_url(),
            "lang" => $lang,
        ]);
    }

    public function dt_magic_url_base_allowed_js( $allowed_js ) {
        $allowed_js[] = 'magic_link_scripts';
        $allowed_js[] = 'dt_campaign_core';
        $allowed_js[] = 'luxon';
        $allowed_js[] = 'dt_campaign';
        return $allowed_js;
    }

    public function dt_magic_url_base_allowed_css( $allowed_css ) {
        $allowed_css[] = 'magic_link_css';
        $allowed_css[] = 'dt_campaign_style';
        return $allowed_css;
    }


    public function dt_details_additional_section( $section, $post_type ) {
        // test if campaigns post type and campaigns_app_module enabled
        if ( $post_type === $this->post_type ) {
            if ( 'campaign_magic_links' === $section ) {
                $link = DT_Magic_URL::get_link_url_for_post( $post_type, get_the_ID(), $this->root, $this->type )
                ?>
                <a class="button hollow" style="display: block" href="<?php echo esc_html( $link ); ?>" target="_blank">Stats link</a>
                <?php
            }
        }
    }


    public function body(){
        if ( empty( $color ) ){
            $color = "dodgerblue";
        }
        ?>


        <div class="cp-progress-wrapper cp-wrapper">
            <div id="main-progress" class="cp-center">
                <div class="cp-center" style="margin: 0 auto 10px auto; background-color: #ededed; border-radius: 20px; height: 150px; width: 150px;"></div>
            </div>
            <div style="color: rgba(0,0,0,0.57); text-align: center"><?php esc_html_e( 'Percentage covered in prayer', 'disciple-tools-prayer-campaigns' ); ?></div>
        </div>

        <div class="cp-calendar-wrapper cp-wrapper">
            <div style="display: flex; flex-flow: wrap; justify-content: space-evenly; margin: 0">
                <div id="calendar-content"></div>
            </div>
        </div>

        <style>
            body {
                color: white !important;
            }
            .cp-wrapper.cp-progress-wrapper {
                width: fit-content;
            }
            .cp-wrapper.cp-calendar-wrapper {
                width: fit-content;
            }
            .cp-wrapper .month-title, .cp-calendar-wrapper .month-title {
                color: <?php echo esc_html( $color ) ?>;
            }
            .cp-wrapper .selected-day {
                background-color: <?php echo esc_html( $color ) ?>;
            }
            .cp-wrapper button {
                background-color: <?php echo esc_html( $color ) ?>;
            }
            .cp-wrapper button:hover {
                background-color: transparent;
                border-color: <?php echo esc_html( $color ) ?>;
                color: <?php echo esc_html( $color ) ?>;
            }

        </style>
        <?php

    }

    /**
     * Register REST Endpoints
     * @link https://github.com/DiscipleTools/disciple-tools-theme/wiki/Site-to-Site-Link for outside of wordpress authentication
     */
    public function add_endpoints() {
        $namespace = $this->root . '/v1/'. $this->type;
        register_rest_route(
            $namespace, '/stats', [
                [
                    'methods'  => "GET",
                    'callback' => [ $this, 'endpoint_get' ],
                    'permission_callback' => function( WP_REST_Request $request ){
                        $magic = new DT_Magic_URL( $this->root );

                        return $magic->verify_rest_endpoint_permissions_on_post( $request );
                    },
                ],
            ]
        );
        register_rest_route(
            $namespace, '/'.$this->type, [
                [
                    'methods'  => "POST",
                    'callback' => [ $this, 'update_record' ],
                    'permission_callback' => function( WP_REST_Request $request ){
                        $magic = new DT_Magic_URL( $this->root );

                        return $magic->verify_rest_endpoint_permissions_on_post( $request );
                    },
                ],
            ]
        );
    }

    public function update_record( WP_REST_Request $request ) {
        $params = $request->get_params();
        $params = dt_recursive_sanitize_array( $params );

        $post_id = $params["parts"]["post_id"]; //has been verified in verify_rest_endpoint_permissions_on_post()

        $args = [];
        if ( !is_user_logged_in() ){
            $args["comment_author"] = "Magic Link Submission";
            wp_set_current_user( 0 );
            $current_user = wp_get_current_user();
            $current_user->add_cap( "magic_link" );
            $current_user->display_name = "Magic Link Submission";
        }

        if ( isset( $params["update"]["comment"] ) && !empty( $params["update"]["comment"] ) ){
            $update = DT_Posts::add_post_comment( $this->post_type, $post_id, $params["update"]["comment"], "comment", $args, false );
            if ( is_wp_error( $update ) ){
                return $update;
            }
        }

        if ( isset( $params["update"]["start_date"] ) && !empty( $params["update"]["start_date"] ) ){
            $update = DT_Posts::update_post( $this->post_type, $post_id, [ "start_date" => $params["update"]["start_date"] ], false, false );
            if ( is_wp_error( $update ) ){
                return $update;
            }
        }

        return true;
    }

    public function endpoint_get( WP_REST_Request $request ) {
        $params = $request->get_params();
        if ( ! isset( $params['parts'], $params['action'] ) ) {
            return new WP_Error( __METHOD__, "Missing parameters", [ 'status' => 400 ] );
        }

        $data = [];

        $data[] = [ 'name' => 'List item' ]; // @todo remove example
        $data[] = [ 'name' => 'List item' ]; // @todo remove example

        return $data;
    }
}
//Disciple_Tools_24Hour_Stats_Magic_Link::instance();
