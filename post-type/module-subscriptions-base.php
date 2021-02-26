<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

/**
 * Class DT_Subscriptions_Base
 * Load the core post type hooks into the Disciple Tools system
 */
class DT_Subscriptions_Base extends DT_Module_Base {

    /**
     * Define post type variables
     * @var string
     */
    public $post_type = "subscriptions";
    public $module = "subscriptions_base";
    public $single_name = 'Subscriptions';
    public $plural_name = 'Subscriptions';
    public static function post_type(){
        return 'subscriptions';
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
        add_filter( 'dt_details_additional_tiles', [ $this, 'dt_details_additional_tiles' ], 20, 2 );
        add_action( 'dt_details_additional_section_post', [ $this, 'dt_details_additional_section' ], 20, 2 );

//        add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ], 99 );

        // hooks
        add_action( "post_connection_added", [ $this, "post_connection_added" ], 10, 4 );
        add_action( "post_connection_removed", [ $this, "post_connection_removed" ], 10, 4 );
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
     * Documentation
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/roles-permissions.md#rolesd
     */
    public function dt_set_roles_and_permissions( $expected_roles ){

        $expected_roles["subscriptions_admin"] = [
            "label" => __( 'Subscriptions Admin', 'disciple_tools' ),
            "description" => __( 'Subscriptions admin can administrate the prayer subscriptions section', 'disciple_tools' ),
            "permissions" => [
                'view_any_'.$this->post_type => true,
                'dt_all_admin_' . $this->post_type => true,
            ]
        ];

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
                'icon' => get_template_directory_uri() . "/dt-assets/images/nametag.svg",
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


            $fields['status'] = [
                'name'        => __( 'Status', 'disciple_tools' ),
                'description' => _x( 'Set the current status.', 'field description', 'disciple_tools' ),
                'type'        => 'key_select',
                'default'     => [
                    'inactive' => [
                        'label' => __( 'Inactive', 'disciple_tools' ),
                        'description' => _x( 'No longer active.', 'field description', 'disciple_tools' ),
                        'color' => "#F43636"
                    ],
                    'active'   => [
                        'label' => __( 'Active', 'disciple_tools' ),
                        'description' => _x( 'Is active.', 'field description', 'disciple_tools' ),
                        'color' => "#4CAF50"
                    ],
                ],
                'order' => 1,
                'tile'     => '',
                'icon' => get_template_directory_uri() . '/dt-assets/images/status.svg',
                "default_color" => "#366184",
                "show_in_table" => 10,
            ];


            $fields["contact_email"] = [
                "name" => __( 'Email', 'disciple_tools' ),
                "icon" => get_template_directory_uri() . "/dt-assets/images/email.svg",
                "type" => "communication_channel",
                "tile" => "details",
                "customizable" => false,
                "in_create_form" => true,
            ];
            $fields["contact_phone"] = [
                "name" => __( 'Phone', 'disciple_tools' ),
                'description' => __('Subscriber phone number', 'disciple_tools' ),
                "icon" => get_template_directory_uri() . "/dt-assets/images/phone.svg",
                "type" => "communication_channel",
                "tile" => "details",
                "customizable" => false,
                "in_create_form" => false,
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

            $fields['public_key'] = [
                'name'   => __( 'Private Key', 'disciple_tools' ),
                'description' => __('Private key for subscriber access', 'disciple_tools' ),
                'type'   => 'hash',
                'hidden' => true,
                "customizable" => false,
            ];
            $fields['subscriber_last_modified'] = [
                'name'   => __('Last Modified by Subscriber', 'disciple_tools' ),
                'description' => '',
                'type' => 'time',
                'default' => '',
                'hidden' => true,
                "customizable" => false,
            ];
            $fields['campaigns'] = [
                'name'        => __( 'Campaigns', 'disciple_tools' ),
                'description' => _x( 'Campaigns', 'Optional Documentation', 'disciple_tools' ),
                'type'        => 'multi_select',
                'default'     => [],
                'tile'        => '',
                "customizable" => true,
                "in_create_form" => true,
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
                "tile" => "details",
                "icon" => get_template_directory_uri() . "/dt-assets/images/location.svg",
            ];
            $fields['location_grid_meta'] = [
                'name'        => __( 'Locations', 'disciple_tools' ), //system string does not need translation
                'description' => _x( 'The general location where this contact is located.', 'Optional Documentation', 'disciple_tools' ),
                'type'        => 'location_meta',
                "tile"      => "details",
                'mapbox'    => false,
                'hidden' => true
            ];
            $fields["contact_address"] = [
                "name" => __( 'Address', 'disciple_tools' ),
                "icon" => get_template_directory_uri() . "/dt-assets/images/house.svg",
                "type" => "communication_channel",
                "tile" => "",
                'mapbox'    => false,
                "customizable" => false
            ];
            if ( DT_Mapbox_API::get_key() ){
                $fields["contact_address"]["hidden"] = true;
                $fields["contact_address"]["mapbox"] = true;
                $fields["location_grid"]["mapbox"] = true;
                $fields["location_grid_meta"]["mapbox"] = true;
            }
            // end locations

            $fields["contacts"] = [
                "name" => __( 'Contact', 'disciple_tools' ),
                'description' => _x( 'The contacts who are members of this group.', 'Optional Documentation', 'disciple_tools' ),
                "type" => "connection",
                "post_type" => "contacts",
                "p2p_direction" => "from",
                "tile" => "other",
                "customizable" => false,
                "p2p_key" => $this->post_type."_to_contacts",
            ];



        }

        if ( $post_type === "contacts" && current_user_can('view_all_'.$this->post_type ) ){
            $fields[$this->post_type] = [
                "name" => $this->plural_name,
                "description" => '',
                "type" => "connection",
                "post_type" => $this->post_type,
                "p2p_direction" => "from",
                "p2p_key" => $this->post_type."_to_contacts",
                "tile" => "other",
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
        /**
         * Group members field
         */
        p2p_register_connection_type(
            [
                'name'           => $this->post_type."_to_contacts",
                'from'           => 'contacts',
                'to'             => $this->post_type,
                'admin_box' => [
                    'show' => false,
                ],
                'title'          => [
                    'from' => __( 'Contacts', 'disciple_tools' ),
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
            $tiles["subscriptions"] = [ "label" => __( "Subscriptions", 'disciple-tools-subscriptions' ) ];
            $tiles["other"] = [ "label" => __( "Other", 'disciple_tools' ) ];
        }
        return $tiles;
    }

    /**
     * Documentation
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/field-and-tiles.md#add-custom-content
     */
    public function dt_details_additional_section( $section, $post_type ){

        if ( $post_type === $this->post_type && $section === "status" ){
            $record = DT_Posts::get_post( $post_type, get_the_ID() );

            if ( isset( $record['public_key'])) {
                $key = $record['public_key'];
            } else {
                $key = self::create_unique_key();
                update_post_meta( get_the_ID(), 'public_key', $key );
            }
            $link = trailingslashit( site_url() ) . 'subscriptions_app/manage/' . $key;
            ?>
            <div class="cell small-12 medium-4">
                <div class="section-subheader">
                    Subscriptions Management
                </div>
                <div class="grid-x">
                    <div class="cell small-6" style="padding:2px;">
                        <a class="button expanded hollow" onclick="copyToClipboard('<?php echo esc_url( $link ) ?>')">Copy Link</a>
                    </div>
                    <div class="cell small-6" style="padding:2px;">
                        <a class="button expanded hollow" href="<?php echo esc_url( $link ) ?>" target="_blank">Go To</a>
                    </div>
                </div>
            </div>
            <script>
                /* @todo move somewhere more global */
                const copyToClipboard = str => {
                    const el = document.createElement('textarea');
                    el.value = str;
                    el.setAttribute('readonly', '');
                    el.style.position = 'absolute';
                    el.style.left = '-9999px';
                    document.body.appendChild(el);
                    const selected =
                        document.getSelection().rangeCount > 0
                            ? document.getSelection().getRangeAt(0)
                            : false;
                    el.select();
                    document.execCommand('copy');
                    document.body.removeChild(el);
                    if (selected) {
                        document.getSelection().removeAllRanges();
                        document.getSelection().addRange(selected);
                    }
                    alert('Copied')
                };
            </script>
        <?php }

        if ( $post_type === $this->post_type && $section === "status" ){
            $record = DT_Posts::get_post( $post_type, get_the_ID() );
            $fields = DT_Posts::get_post_field_settings( $post_type );
            ?>
            <div class="cell small-12 medium-4">
                <?php render_field_for_display( "status", $fields, $record, true ); ?>
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

    public function post_connection_added( $post_type, $post_id, $field_key, $value ){
        if ( $post_type === $this->post_type ){
            if ( $field_key === "campaigns" ){
                // @todo change 'members'
                // execute your code here, if field key match
                dt_write_log( __METHOD__ . ' and field_key = members' );
            }
        }
        if ( $post_type === "contacts" && $field_key === $this->post_type ){
            // execute your code here, if a change is made in contacts and a field key is matched
            dt_write_log( __METHOD__ . ' and post_type = contacts & field_key = coaches' );
        }
    }

    //action when a post connection is removed during create or update
    public function post_connection_removed( $post_type, $post_id, $field_key, $value ){
        if ( $post_type === $this->post_type ){
            // execute your code here, if connection removed
            dt_write_log( __METHOD__ );
        }
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
            if ( ! isset( $fields['subscriber_last_modified'] ) ) {
                $fields['subscriber_last_modified'] = time();
            }
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
            if ( ! isset( $fields['subscriber_last_modified'] ) ) {
                $fields['subscriber_last_modified'] = time();
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

