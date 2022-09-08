<?php
if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

/**
 * Class DT_Generic_Porch_Landing_Tab_Starter_Content
 */
class DT_Porch_Admin_Tab_Email_Settings {
    public $key = 'email-settings';
    public $title = 'Email Settings';
    private $selected_campaign;

    public function __construct() {
        $campaign = DT_Campaign_Settings::get_campaign();

        if ( !empty( $campaign ) ) {
            $this->selected_campaign = $campaign['ID'];
        }
    }

    public function content() {

        ?>
        <style>
            .metabox-table input {
                width: 100%;
            }
            .metabox-table select {
                width: 100%;
            }
            .metabox-table textarea {
                width: 100%;
                height: 100px;
            }
        </style>
        <div class="wrap">
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-1">
                    <div id="post-body-content">
                        <!-- Main Column -->

                        <?php $this->main_column(); ?>

                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function main_column() {

        /* If no campaign is selected then show the message */
        /* If a campaign is selected */

        $this->process_select_campaign();
        $this->process_campaign_email_settings();

        $this->campaign_selector();

        if ( !$this->selected_campaign || $this->selected_campaign === DT_Prayer_Campaigns_Campaigns::$no_campaign_key ) {
            DT_Porch_Admin_Tab_Base::message_box( 'Email Settings', 'You need to select a campaign above to start editing email settings' );
        } else {
            $this->email_settings_box();
        }
    }

    private function process_select_campaign() {
        if ( isset( $_POST['campaign_selection_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['campaign_selection_nonce'] ) ), 'campaign_selection' ) ) {
            if ( isset( $_POST['campaign-selection'] ) ) {
                $this->selected_campaign = sanitize_text_field( wp_unslash( $_POST['campaign-selection'] ) );
            }
        }
    }

    private function process_campaign_email_settings() {}

    private function campaign_selector() {
        ?>
        <form method="post" id="campaign-selection-form">
            <?php wp_nonce_field( 'campaign_selection', 'campaign_selection_nonce' ) ?>
            <table class="widefat striped metabox-table">
                <thead>
                    <tr>
                        <th>Select Campaign to edit the email settings</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <select name="campaign-selection" id="campaign-selection" onchange="this.form.submit()">
                                <?php DT_Prayer_Campaigns_Campaigns::campaign_options( $this->selected_campaign ) ?>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>
        <br>
        <?php
    }

    public function email_settings_box() {

        $langs = dt_campaign_list_languages();
        $campaign = DT_Campaign_Settings::get_campaign();

        $email_fields = [
            'campaign_description' => [
                'label' => __( 'Campaign Description', 'disciple-tools-prayer-campaigns' ),
                'translations' => [],
            ],
            'signup_content' => [
                'label' => __( 'Content to be added to the signup email', 'disciple-tools-prayer-campaigns' ),
                'translations' => [],
            ],
            'reminder_content' => [
                'label' =>  __( 'Content to be added to the reminder email', 'disciple-tools-prayer-campaigns' ),
                'translations' => [],
            ],
        ];

        $form_name = 'email_settings';
        ?>
        <form method="post" enctype="multipart/form-data" name="<?php echo esc_attr( $form_name ) ?>">
            <?php wp_nonce_field( 'email_settings', 'install_from_file_nonce' ) ?>
        <!-- Box -->
            <table class="widefat striped metabox-table">
                <thead>
                <tr>
                    <th>Email Settings</th>
                    <th></th> <!-- extends the header bottom border across the right hand column -->
                    <th></th>
                </tr>
                </thead>
                <tbody>

                    <?php

                        foreach ( $email_fields as $key => $field ) {
                            DT_Porch_Admin_Tab_Base::textarea( $langs, $key, $field, $form_name );
                        }

                    ?>

                </tbody>
            </table>
        </form>

        <?php dt_display_translation_dialog() ?>

        <?php
    }
}
