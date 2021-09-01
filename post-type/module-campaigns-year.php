<?php

if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class DT_Campaign_Year extends DT_Module_Base {
    public $module = "campaigns_year";
    public $post_type = 'campaigns';
    public $magic_link_root = "campaign_app";
    public $magic_link_type = "year";

    private static $_instance = null;

    public static function instance(){
        if ( is_null( self::$_instance ) ){
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct(){
        $module_enabled = dt_is_module_enabled( "subscriptions_management", true );
        if ( !$module_enabled ){
            return;
        }
        parent::__construct();
        // register tiles if on details page
        add_filter( 'dt_campaign_types', [ $this, 'dt_campaign_types' ], 20, 1 );
        add_filter( 'dt_details_additional_tiles', [ $this, 'dt_details_additional_tiles' ], 30, 2 );
        add_action( 'dt_details_additional_section', [ $this, 'dt_details_additional_section' ], 30, 2 );
//        add_filter( 'dt_post_update_fields', [ $this, 'dt_post_update_fields' ], 20, 3 );

        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
    }

    public function dt_details_additional_tiles( $tiles, $post_type = "" ){

        return $tiles;
    }

    public function dt_campaign_types( $types ) {
        $types['year'] = [
            'label' => __( 'Year Prayer Calendar', 'disciple_tools' ),
            'description' => __( 'Cover a Year with Prayer', 'disciple_tools' ),
            "visibility" => __( "Collaborators", 'disciple_tools' ),
            'color' => "#4CAF50",
        ];
        return $types;
    }

    public function dt_details_additional_section( $section, $post_type ) {
        // test if campaigns post type and campaigns_app_module enabled
        if ( $post_type === $this->post_type ) {
            $record = DT_Posts::get_post( $post_type, get_the_ID() );
            if ( !isset( $record['type']['key'] ) || $this->magic_link_type !== $record['type']['key'] ){
                return;
            }
            $link = '';
            if ( method_exists( "DT_Magic_URL", "get_link_url_for_post" ) ){
                $link = DT_Magic_URL::get_link_url_for_post( $post_type, get_the_ID(), $this->magic_link_root, $record['type']['key'] );
            }

            if ( 'status' === $section ){
                ?>
                <div class="cell small-12 medium-4">
                    <div class="section-subheader">
                        <?php esc_html_e( 'Magic Link', 'disciple_tools' ); ?>
                    </div>
                    <a class="button hollow small" target="_blank" href="<?php echo esc_html( $link ); ?>"><?php esc_html_e( 'Open Link', 'disciple_tools' ); ?></a>
                </div>
                <?php
            }
        }
    }
}
