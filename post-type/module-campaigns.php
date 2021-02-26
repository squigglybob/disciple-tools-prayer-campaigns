<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

/**
 * Class DT_Subscriptions_Base
 * Load the core post type hooks into the Disciple Tools system
 */
class DT_Campaigns_Base extends DT_Module_Base {

    /**
     * Define post type variables
     * @var string
     */
    public $post_type = "campaigns";
    public $module = "campaigns_base";
    public $single_name = 'Campaign';
    public $plural_name = 'Campaigns';
    public static function post_type(){
        return 'campaigns';
    }

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        parent::__construct();
        if ( !self::check_enabled_and_prerequisites() ){
            return;
        }

        //setup post type
        add_action( 'after_setup_theme', [ $this, 'after_setup_theme' ], 100 );
        add_filter( 'dt_set_roles_and_permissions', [ $this, 'dt_set_roles_and_permissions' ], 20, 1 ); //after contacts

        //setup tiles and fields
        add_action( 'p2p_init', [ $this, 'p2p_init' ] );
        add_filter( 'dt_custom_fields_settings', [ $this, 'dt_custom_fields_settings' ], 10, 2 );
        add_filter( 'dt_details_additional_tiles', [ $this, 'dt_details_additional_tiles' ], 10, 2 );
        add_action( 'dt_details_additional_section', [ $this, 'dt_details_additional_section' ], 20, 2 );
        add_filter( 'desktop_navbar_menu_options', [$this, 'desktop_navbar_menu_options'], 1000, 1 );
//        add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ], 99 );

        // hooks
//        add_action( "post_connection_removed", [ $this, "post_connection_removed" ], 10, 4 );
//        add_action( "post_connection_added", [ $this, "post_connection_added" ], 10, 4 );
//        add_action( "dt_comment_created", [ $this, "dt_comment_created" ], 10, 4 );
        add_filter( "dt_post_update_fields", [ $this, "dt_post_update_fields" ], 10, 3 );
        add_filter( "dt_post_create_fields", [ $this, "dt_post_create_fields" ], 10, 2 );
        add_action( "dt_post_created", [ $this, "dt_post_created" ], 10, 3 );

        //list
        add_filter( "dt_user_list_filters", [ $this, "dt_user_list_filters" ], 10, 2 );
        add_filter( "dt_filter_access_permissions", [ $this, "dt_filter_access_permissions" ], 20, 2 );

    }

    public function after_setup_theme(){
        if ( class_exists( 'Disciple_Tools_Post_Type_Template' )) {
            new Disciple_Tools_Post_Type_Template( $this->post_type, $this->single_name, $this->plural_name );
        }
    }

    /**
     * Modifies the top tabs to add campaigns under the drop down
     * @param $tabs
     * @return mixed
     */
    public function desktop_navbar_menu_options( $tabs ) {
        if ( isset( $tabs[$this->post_type]) ) {
            unset($tabs[$this->post_type]);
        }
        if ( $tabs['subscriptions'] ) {
            $tabs['subscriptions']['submenu']['subscriptions'] = $tabs['subscriptions'];
            $tabs['subscriptions']['submenu'][$this->post_type] = [
                "link" => site_url( "/$this->post_type/" ),
                "label" => $this->plural_name,
                'icon' => '',
                'hidden' => false,
            ];
        }
        return $tabs;
    }

    /**
     * Documentation
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/roles-permissions.md#rolesd
     */
    public function dt_set_roles_and_permissions( $expected_roles ){

        $expected_roles["prayer_admin"] = [
            "label" => __( 'Subscriptions Admin', 'disciple_tools' ),
            "description" => __( 'Subscriptions admin can administrate the prayer subscriptions section', 'disciple_tools' ),
            "permissions" => [
                'view_any_'.$this->post_type => true,
                'dt_all_admin_' . $this->post_type => true,
            ]
        ];

        // @note removed multiplier role because these people are not expected to be interacted with.
//        if ( !isset( $expected_roles["multiplier"] ) ){
//            $expected_roles["multiplier"] = [
//                "label" => __( 'Multiplier', 'disciple-tools-training' ),
//                "permissions" => []
//            ];
//        }
        if ( !isset( $expected_roles["dt_admin"] ) ){
            $expected_roles["dt_admin"] = [
                "label" => __( 'Disciple.Tools Admin', 'disciple-tools-training' ),
                "description" => "All D.T permissions",
                "permissions" => []
            ];
        }
        if ( !isset( $expected_roles["administrator"] ) ){
            $expected_roles["administrator"] = [
                "label" => __( 'Administrator', 'disciple-tools-training' ),
                "description" => "All D.T permissions plus the ability to manage plugins.",
                "permissions" => []
            ];
        }

        // if the user can access contact they also can access this post type
        foreach ( $expected_roles as $role => $role_value ){
            if ( isset( $expected_roles[$role]["permissions"]['access_contacts'] ) && $expected_roles[$role]["permissions"]['access_contacts'] ){
                $expected_roles[$role]["permissions"]['access_' . $this->post_type ] = true;
                $expected_roles[$role]["permissions"]['create_' . $this->post_type] = true;
            }
        }


        if ( isset( $expected_roles["administrator"] ) ){
            $expected_roles["administrator"]["permissions"]['view_any_'.$this->post_type ] = true;
            $expected_roles["dt_admin"]["permissions"][ 'dt_all_admin_' . $this->post_type] = true;
        }
        if ( isset( $expected_roles["dt_admin"] ) ){
            $expected_roles["dt_admin"]["permissions"]['view_any_'.$this->post_type ] = true;
            $expected_roles["dt_admin"]["permissions"][ 'dt_all_admin_' . $this->post_type] = true;
        }

        return $expected_roles;
    }

    /**
     * Documentation
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/fields.md
     */
    public function dt_custom_fields_settings( $fields, $post_type ){
        if ( $post_type === $this->post_type ){
            /**
             * Basic framework fields used by post-type base
             * recommended to leave these alone
             */
            $fields['tags'] = [
                'name'        => __( 'Tags', 'disciple_tools' ),
                'description' => _x( 'A useful way to group related items.', 'Optional Documentation', 'disciple_tools' ),
                'type'        => 'multi_select',
                'default'     => [],
                'tile'        => 'other',
                'custom_display' => true,
                "customizable" => false,
            ];
            $fields["follow"] = [
                'name'        => __( 'Follow', 'disciple_tools' ),
                'type'        => 'multi_select',
                'default'     => [],
                'section'     => 'misc',
                'hidden'      => true,
                "customizable" => false,
            ];
            $fields["unfollow"] = [
                'name'        => __( 'Un-Follow', 'disciple_tools' ),
                'type'        => 'multi_select',
                'default'     => [],
                'hidden'      => true,
                "customizable" => false,
            ];
            $fields['tasks'] = [
                'name' => __( 'Tasks', 'disciple_tools' ),
                'type' => 'post_user_meta',
                "customizable" => false,
            ];
            $fields["duplicate_data"] = [
                "name" => 'Duplicates', //system string does not need translation
                'type' => 'array',
                'default' => [],
                "customizable" => false,
            ];
            $fields['status'] = [
                'name'        => __( 'Status', 'disciple_tools' ),
                'description' => _x( 'Set the current status.', 'field description', 'disciple_tools' ),
                'type'        => 'key_select',
                'default'     => [
                    'active'   => [
                        'label' => __( 'Active', 'disciple_tools' ),
                        'description' => _x( 'Is active.', 'field description', 'disciple_tools' ),
                        'color' => "#4CAF50"
                    ],
                    'inactive' => [
                        'label' => __( 'Inactive', 'disciple_tools' ),
                        'description' => _x( 'No longer active.', 'field description', 'disciple_tools' ),
                        'color' => "#F43636"
                    ],
                ],
                'tile'     => 'status',
                'icon' => get_template_directory_uri() . '/dt-assets/images/status.svg',
                "default_color" => "#366184",
                "show_in_table" => 10,
            ];
            $fields['type'] = [
                'name'        => __( 'Campaign Type', 'disciple_tools' ),
                'description' => _x( 'Set the current type.', 'field description', 'disciple_tools' ),
                'type'        => 'key_select',
                'default'     => apply_filters( 'dt_campaign_types', [] ),
                'tile'     => 'status',
//                'icon' => get_template_directory_uri() . '/dt-assets/images/status.svg',
                "default_color" => "#F43636",
                "show_in_table" => 10,
            ];
//            $fields['assigned_to'] = [
//                'name'        => __( 'Assigned To', 'disciple_tools' ),
//                'description' => __( "Select the main person who is responsible for reporting on this record.", 'disciple_tools' ),
//                'type'        => 'user_select',
//                'default'     => '',
//                'tile' => 'status',
//                'icon' => get_template_directory_uri() . '/dt-assets/images/assigned-to.svg',
//                "show_in_table" => 16,
//                'custom_display' => true,
//            ];
//            $fields["requires_update"] = [
//                'name'        => __( 'Requires Update', 'disciple_tools' ),
//                'description' => '',
//                'type'        => 'boolean',
//                'default'     => false,
//            ];
            // end basic framework fields


            $fields['notes'] = [
                'name'   => __( 'Notes', 'disciple_tools' ),
                'description' => __('Permanent notes about the campaign', 'disciple_tools' ),
                'type'   => 'text',
                "tile" => "details",
                'default' => '',
                "customizable" => true,
                "in_create_form" => true,
            ];
            $fields["languages"] = [
                'name' => __( 'Languages', 'disciple_tools' ),
                'description' => __('Subscriber preferred language', 'disciple_tools' ),
                'type' => 'key_select',
                "tile" => "details",
                "in_create_form" => true,
                'default' => dt_get_option( "dt_working_languages" ) ?: ['en'],
                'icon' => get_template_directory_uri() . "/dt-assets/images/languages.svg",
            ];
            $fields["peoplegroups"] = [
                "name" => __( 'People Groups', 'disciple_tools' ),
                'description' => _x( 'The people groups connected to this record.', 'Optional Documentation', 'disciple_tools' ),
                "type" => "connection",
                "post_type" => $this->post_type,
                "tile" => "details",
                "p2p_direction" => "to",
                "p2p_key" => $this->post_type."_to_peoplegroups"
            ];

            $fields['start_date'] = [
                'name'        => __( 'Start Date', 'disciple_tools' ),
                'description' => '',
                'type'        => 'date',
                'default'     => time(),
                'tile' => 'time',
                'icon' => get_template_directory_uri() . '/dt-assets/images/date-start.svg',
            ];
            $fields['end_date'] = [
                'name'        => __( 'End Date', 'disciple_tools' ),
                'description' => '',
                'type'        => 'date',
                'default'     => '',
                'tile' => 'time',
                'icon' => get_template_directory_uri() . '/dt-assets/images/date-end.svg',
            ];


            $fields['public_key'] = [
                'name'   => __( 'Private Key', 'disciple_tools' ),
                'description' => __('Private key for subscriber access', 'disciple_tools' ),
                'type'   => 'hash',
                'default' => DT_Subscriptions_Base::instance()->create_unique_key(),
                'hidden' => true,
                "customizable" => false,
            ];

            /**
             * location elements
             */
            $fields['location_grid'] = [
                'name'        => __( 'Locations', 'disciple_tools' ),
                'description' => _x( 'The general location where this contact is located.', 'Optional Documentation', 'disciple_tools' ),
                'type'        => 'location',
                'mapbox'    => false,
                "customizable" => false,
                "in_create_form" => true,
                "tile" => "",
                "icon" => get_template_directory_uri() . "/dt-assets/images/location.svg",
            ];
//            $fields['location_grid_meta'] = [
//                'name'        => __( 'Locations', 'disciple_tools' ), //system string does not need translation
//                'description' => _x( 'The general location where this contact is located.', 'Optional Documentation', 'disciple_tools' ),
//                'type'        => 'location_meta',
//                "tile"      => "locations",
//                'mapbox'    => false,
//                'hidden' => true,
//                "icon" => get_template_directory_uri() . "/dt-assets/images/location.svg",
//            ];
//            $fields["contact_address"] = [
//                "name" => __( 'Address', 'disciple_tools' ),
//                "icon" => get_template_directory_uri() . "/dt-assets/images/house.svg",
//                "type" => "communication_channel",
//                "tile" => "",
//                'mapbox'    => false,
//                "customizable" => false
//            ];
//            if ( DT_Mapbox_API::get_key() ){
//                $fields["contact_address"]["hidden"] = true;
//                $fields["contact_address"]["mapbox"] = true;
//                $fields["location_grid"]["mapbox"] = true;
//                $fields["location_grid_meta"]["mapbox"] = true;
//            }

            $fields["subscriptions"] = [
                "name" => __( 'Subscriptions', 'disciple_tools' ),
                'description' => _x( 'The contacts who are members of this group.', 'Optional Documentation', 'disciple_tools' ),
                "type" => "connection",
                "post_type" => "subscriptions",
                "p2p_direction" => "from",
                "customizable" => false,
                'hidden' => true,
                "p2p_key" => 'campaigns_to_subscriptions',
                'icon' => get_template_directory_uri() . "/dt-assets/images/nametag.svg",
            ];

        }

        if ( $post_type === "subscriptions" ){
            $fields['campaigns'] = [
                "name" => $this->plural_name,
                "description" => '',
                "type" => "connection",
                "post_type" => $this->post_type,
                "p2p_direction" => "to",
                "p2p_key" => 'campaigns_to_subscriptions',
                "tile" => "subscriptions",
                'icon' => get_template_directory_uri() . "/dt-assets/images/group-type.svg",
                'create-icon' => get_template_directory_uri() . "/dt-assets/images/add-group.svg",
                "show_in_table" => 35,
                "customizable" => false,
            ];
        }

        return $fields;
    }

    /**
     * Documentation
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/fields.md#declaring-connection-fields
     */
    public function p2p_init(){

        p2p_register_connection_type(
            [
                'name'           => "campaigns_to_subscriptions",
                'from'           => 'campaigns',
                'to'             => 'subscriptions',
                'admin_box' => [
                    'show' => false,
                ],
                'title'          => [
                    'from' => __( 'Campaigns', 'disciple_tools' ),
                    'to'   => 'Subscriptions',
                ]
            ]
        );
        p2p_register_connection_type(
            [
                'name'        => $this->post_type."_to_peoplegroups",
                'from'        => $this->post_type,
                'to'          => 'peoplegroups',
                'title'       => [
                    'from' => __( 'People Groups', 'disciple_tools' ),
                    'to'   => $this->plural_name,
                ]
            ]
        );
    }

    /**
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/field-and-tiles.md
     */
    public function dt_details_additional_tiles( $tiles, $post_type = "" ){
        if ( $post_type === $this->post_type ){
            $tiles["location"] = [ "label" => __( "Geo Focus", 'disciple_tools' ) ];
            $tiles["time"] = [ "label" => __( "Time Range", 'disciple_tools' ) ];
            $tiles["other"] = [ "label" => __( "Other", 'disciple_tools' ) ];
        }
        return $tiles;
    }

    /**
     * Documentation
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/field-and-tiles.md#add-custom-content
     */
    public function dt_details_additional_section( $section, $post_type ){



        if ( $post_type === $this->post_type && $section === "location" ){
            $fields = DT_Posts::get_post_field_settings( $post_type );
            ?>
            <div class="cell small-12">
                <div class="section-subheader">
                    <img src="<?php echo get_template_directory_uri() . "/dt-assets/images/location.svg" ?>" /> <?php echo $fields['location_grid']['name'] ?>
                </div>
                <div class="dt_location_grid" data-id="location_grid">
                    <var id="location_grid-result-container" class="result-container"></var>
                    <div id="location_grid_t" name="form-location_grid" class="scrollable-typeahead typeahead-margin-when-active">
                        <div class="typeahead__container">
                            <div class="typeahead__field">
                                    <span class="typeahead__query">
                                        <input class="js-typeahead-location_grid input-height"
                                               data-field="location_grid"
                                               data-field_type="location"
                                               name="location_grid[query]"
                                               placeholder="<?php echo esc_html( sprintf( _x( "Search %s", "Search 'something'", 'disciple_tools' ), $fields['location_grid']['name'] ) )?>"
                                               autocomplete="off" />
                                    </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php }

        if ( $post_type === $this->post_type && $section === "time" ){
//            $record = DT_Posts::get_post( $post_type, get_the_ID() );
//            $fields = DT_Posts::get_post_field_settings( $post_type );
            ?>
            <div class="cell small-12">
                <div class="section-subheader">

                </div>

            </div>

        <?php }

        if ( $post_type === $this->post_type && $section === "other" ) :
            $fields = DT_Posts::get_post_field_settings( $post_type );
            ?>
            <div class="section-subheader">
                <?php echo esc_html( $fields["tags"]["name"] ) ?>
            </div>
            <div class="tags">
                <var id="tags-result-container" class="result-container"></var>
                <div id="tags_t" name="form-tags" class="scrollable-typeahead typeahead-margin-when-active">
                    <div class="typeahead__container">
                        <div class="typeahead__field">
                            <span class="typeahead__query">
                                <input class="js-typeahead-tags input-height"
                                       name="tags[query]"
                                       placeholder="<?php echo esc_html( sprintf( _x( "Search %s", "Search 'something'", 'disciple_tools' ), $fields["tags"]['name'] ) )?>"
                                       autocomplete="off">
                            </span>
                            <span class="typeahead__button">
                                <button type="button" data-open="create-tag-modal" class="create-new-tag typeahead__image_button input-height">
                                    <img src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/tag-add.svg' ) ?>"/>
                                </button>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif;

    }

    //filter when a comment is created
    public function dt_comment_created( $post_type, $post_id, $comment_id, $type ){
        if ( $post_type === $this->post_type ){
//            if ( $type === "comment" ){
//                self::check_requires_update( $post_id );
//            }
        }
    }

    //filter at the start of post update
    public function dt_post_update_fields( $fields, $post_type, $post_id ){
        if ( $post_type === $this->post_type ){

        }
        return $fields;
    }

    // filter at the start of post creation
    public function dt_post_create_fields( $fields, $post_type ){
        if ( $post_type === $this->post_type ) {
            if ( !isset( $fields["status"] ) ) {
                $fields["status"] = "active";
            }
            if ( !isset( $fields['public_key'] ) ) {
                $fields['public_key'] = $this->create_unique_key();
            }

        }
        return $fields;
    }

    public function create_unique_key() : string {
        try {
            $hash = hash( 'sha256', bin2hex( random_bytes( 256 ) ) );
        } catch ( Exception $exception ) {
            $hash = hash( 'sha256', bin2hex( rand( 0, 1234567891234567890 ) . microtime() ) );
        }
        return $hash;
    }

    //action when a post has been created
    public function dt_post_created( $post_type, $post_id, $initial_fields ){
        if ( $post_type === $this->post_type ){
            do_action( "dt_'.$this->post_type.'_created", $post_id, $initial_fields );
//            $post_array = DT_Posts::get_post( $this->post_type, $post_id, true, false );
//            if ( isset( $post_array["assigned_to"] )) {
//                if ( $post_array["assigned_to"]["id"] ) {
//                    DT_Posts::add_shared( $this->post_type, $post_id, $post_array["assigned_to"]["id"], null, false, false, false );
//                }
//            }
        }
    }

    //list page filters function
    private static function get_all_status_types(){
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare( "
                SELECT pm.meta_value as status, count(pm.post_id) as count
                FROM $wpdb->postmeta pm
                JOIN $wpdb->posts a ON( a.ID = pm.post_id AND a.post_type = %s and a.post_status = 'publish' )
                WHERE pm.meta_key = 'status'
                GROUP BY pm.meta_value;
            ", self::post_type() ), ARRAY_A );

        return $results;
    }

    //build list page filters
    public static function dt_user_list_filters( $filters, $post_type ){
        if ( $post_type === self::post_type() && current_user_can( 'view_any_'.self::post_type() ) ){

            $fields = DT_Posts::get_post_field_settings( $post_type );

            $counts = self::get_all_status_types();
            $active_counts = [];
            $update_needed = 0;
            $status_counts = [];
            $total_all = 0;
            foreach ( $counts as $count ){
                $total_all += $count["count"];
                dt_increment( $status_counts[$count["status"]], $count["count"] );
                if ( $count["status"] === "active" ){
                    dt_increment( $active_counts[$count["status"]], $count["count"] );
                }
            }
            $filters["tabs"][] = [
                "key" => "all",
                "label" => _x( "All", 'List Filters', 'disciple_tools' ),
                "count" => $total_all,
                "order" => 10
            ];
            // add assigned to me filters
            $filters["filters"][] = [
                'ID' => 'all',
                'tab' => 'all',
                'name' => _x( "All", 'List Filters', 'disciple_tools' ),
                'query' => [
                    'sort' => '-post_date'
                ],
                "count" => $total_all
            ];

            foreach ( $fields["status"]["default"] as $status_key => $status_value ) {
                if ( isset( $status_counts[$status_key] ) ){
                    $filters["filters"][] = [
                        "ID" => 'all_' . $status_key,
                        "tab" => 'all',
                        "name" => $status_value["label"],
                        "query" => [
                            'status' => [ $status_key ],
                            'sort' => '-post_date'
                        ],
                        "count" => $status_counts[$status_key]
                    ];
                    if ( $status_key === "active" ){
                        if ( $update_needed > 0 ){
                            $filters["filters"][] = [
                                "ID" => 'all_update_needed',
                                "tab" => 'all',
                                "name" => $fields["requires_update"]["name"],
                                "query" => [
                                    'status' => [ 'active' ],
                                    'requires_update' => [ true ],
                                ],
                                "count" => $update_needed,
                                'subfilter' => true
                            ];
                        }
                    }
                }
            }
        }
        return $filters;
    }

    // access permission
    public static function dt_filter_access_permissions( $permissions, $post_type ){
        if ( $post_type === self::post_type() ){
            if ( DT_Posts::can_view_all( $post_type ) ){
                $permissions = [];
            }
        }
        return $permissions;
    }
}
