<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class DT_Prayer_Campaign_Year_Magic_Link extends DT_Magic_Url_Base {

    public $post_type = 'campaigns';
    public $page_title = "Sign up to pray";

    public $magic = false;
    public $parts = false;
    public $root = "campaign_app"; // define the root of the url {yoursite}/root/type/key/action
    public $type = 'year'; // define the type

    public $type_actions = [
        '' => "View",
        'manage' => 'Manage',
    ];

    public function __construct(){
        parent::__construct();
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );

        if ( !$this->check_parts_match() ){
            return;
        }
        if ( '' === $this->parts['action'] ){
            add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 100 );
            add_action( 'dt_blank_body', [ $this, 'dt_blank_body' ], 10 );
        } else {
            return; // fail if no valid action url found
        }

        add_filter( 'dt_magic_url_base_allowed_js', [ $this, 'dt_magic_url_base_allowed_js' ], 10, 1 );
        add_filter( 'dt_magic_url_base_allowed_css', [ $this, 'dt_magic_url_base_allowed_css' ], 10, 1 );


    }

    public function dt_blank_body(){
        ?>
        <div id="app"></div>
        <?php
    }

    public function wp_enqueue_scripts(){

        function get_hashed_file( $filename, $type = 'js' ){
            $regex = '/\/[\w-]+\.[\w-]+.*/i';
            $file_with_hash = glob( dirname( __FILE__ ) . '/dist/' . $type . '/' . $filename . '.*.' . $type )[0];
            preg_match( $regex, $file_with_hash, $matches );
            return $matches[0];
        }

        //Register scripts to use

        $css_file = plugin_dir_url( __FILE__ ) . 'dist/css' . get_hashed_file( 'app', 'css' );
        wp_enqueue_style( 'vue_app_css', $css_file, [] );
        $vue_app_deps_file = plugin_dir_url( __FILE__ ) . 'dist/js' . get_hashed_file( 'chunk-vendors' );
        wp_register_script( 'vue_app_deps', $vue_app_deps_file, [], get_hashed_file( 'chunk-vendors' ), true );
        $app_js_file = plugin_dir_url( __FILE__ ) . 'dist/js' . get_hashed_file( 'app' );
        wp_enqueue_script( 'vue_app_js', $app_js_file, [ 'vue_app_deps' ], get_hashed_file( 'app' ), true );


        wp_localize_script(
            'vue_app_js', 'campaign_objects', [
                'translations' => [],
                "parts" => $this->parts,
                "root" => rest_url(),
                "nonce" => wp_create_nonce( 'wp_rest' ),
            ]
        );
    }

    // add dt_campaign_core to allowed scripts
    public function dt_magic_url_base_allowed_js( $allowed_js ) {
        $allowed_js = [];
//        $allowed_js[] = 'dt_campaign_core';
        $allowed_js[] = 'dt_campaign';
        $allowed_js[] = 'vue_app_js';
        $allowed_js[] = 'vue_app_deps';
        return $allowed_js;
    }

    // add dt_campaign_core to allowed scripts
    public function dt_magic_url_base_allowed_css( $allowed_css ) {
        $allowed_css = [];
        $allowed_css[] = 'vue_app_css';
        return $allowed_css;
    }


    public function add_api_routes() {
        $namespace = $this->root . '/v1';
        register_rest_route(
            $namespace, '/'.$this->type, [
                [
                    'methods'  => "POST",
                    'callback' => [ $this, 'submit' ],
                    'permission_callback' => function( WP_REST_Request $request ){
                        $magic = new DT_Magic_URL( $this->root );
                        return $magic->verify_rest_endpoint_permissions_on_post( $request );
                    },
                ],
            ]
        );
    }

    public function submit( WP_REST_Request $request ){
        $params = $request->get_params();
        $params = dt_recursive_sanitize_array( $params );
        if ( !isset( $params["parts"]["post_id"], $params["email"], $params["name"] ) ){
            return new WP_Error( __METHOD__, "Missing parameters", [ 'status' => 400 ] );
        }

        return DT_Subscriptions::create_subscriber( $params["parts"]["post_id"], $params["email"], $params["name"], [] );
    }
}

