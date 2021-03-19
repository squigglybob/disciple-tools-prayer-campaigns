<?php
/**
 * The cron runs hourly and looks at the next 12 hours for reminders needed.
 * Effectively this gives a soft buffer where most emails will go out between 10-12 hours
 * before the commitment time.
 */
if ( !wp_next_scheduled( 'dt_prayer_campaign_send_12hour_reminder' )) {
    wp_schedule_event( strtotime( '+1 hour' ), 'hourly', 'dt_prayer_campaign_send_12hour_reminder' );
}
add_action( 'dt_prayer_campaign_send_12hour_reminder', 'dt_prayer_campaign_send_12hour_reminder' );

function dt_prayer_campaign_send_12hour_reminder(){
    global $wpdb;

    // get all reminders needed in the next 12 hours that have not been sent
    $begin_time_range = time();
    $end_time_range = time() + 43200; // 43200 is the number of seconds in 12 hours

    /**
     * Get reports
     * That have been confirmed
     * Where notification has not been sent (no payload)
     * Of prayer time happening in the next 12 hours
     */
    $reminders = $wpdb->get_results($wpdb->prepare(
        "SELECT r.id, r.parent_id, r.post_id, r.time_begin, r.time_end, pm.meta_value as email, r.label, pk.meta_value as public_key, p.post_title as name
            FROM $wpdb->dt_reports r
            LEFT JOIN $wpdb->postmeta pm ON pm.post_id=r.post_id
                AND pm.meta_key LIKE %s
                AND pm.meta_key NOT LIKE %s
            LEFT JOIN $wpdb->postmeta pk ON pm.post_id=pk.post_id
                AND pk.meta_key = 'public_key'
            LEFT JOIN $wpdb->posts p ON p.ID=r.post_id
            WHERE r.post_type = 'subscriptions'
            AND r.time_begin >= %d
            AND r.time_begin <= %d
            AND r.value = '1'
            AND ( r.payload = '' || r.payload IS NULL )
            ORDER BY r.time_begin;
            ",
        $wpdb->esc_like( 'contact_email' ) . '%',
        '%' . $wpdb->esc_like( 'details' ),
        $begin_time_range,
        $end_time_range
    ), ARRAY_A );

    if ( empty( $reminders ) ){
        return;
    }

    // build nested array to separate campaigns and combine user reminders
    $grouped_reminders = [];
    $campaign_ids = [];
    foreach ( $reminders as $reminder ) {
        if ( ! isset( $grouped_reminders[$reminder['parent_id']] ) ) {
            $grouped_reminders[$reminder['parent_id']] = [];
            $campaign_ids[] = $reminder['parent_id'];
        }
        if ( ! isset( $grouped_reminders[$reminder['parent_id']][$reminder['post_id']] ) ) {
            $grouped_reminders[$reminder['parent_id']][$reminder['post_id']] = [];
        }
        $grouped_reminders[$reminder['parent_id']][$reminder['post_id']][] = $reminder;
    }


    // build message by campaign, and then by user, and grouping times per user message
    foreach ( $grouped_reminders as $campaign_id => $subscriber_values ) {
        // get campaign messages and links for the day
        $campaign_subject_line = 'Prayer Time reminder!';

        foreach ( $subscriber_values as $key => $reports ) {
            $e = [
                'to' => '',
                'subject' => $campaign_subject_line,
                'message' => '',
                'headers' => [
                    'Content-Type: text/html',
                    'charset=UTF-8'
                ]
            ];

            $commitment_list = '';
            $to = [];
            $public_key ="";
            $name = "";
            foreach ( $reports as $row ){
                $to[$row['email']] = $row['email'];
                $name = $row["name"];
                $public_key = $row['public_key'];
                $commitment_list .= gmdate( 'F d, Y', $row['time_begin'] )
                    . ' from <strong>'
                    . gmdate( 'H:i a', $row['time_begin'] )
                    . '</strong> to <strong>'
                    . gmdate( 'H:i a', $row['time_end'] ) .
                    '</strong> for ' . $row['label'] . '<br>';
            }
            $e['to'] = implode( ',', $to );

            $e['message'] =
                '
                <h3>Hello ' . esc_html( $name ) . ',</h3>
                <h4>Thank you for praying with us!</h4>
                <p>Here are your upcoming prayer times:</p>
                <p>'.$commitment_list.'</p>
                <br>
                <hr>
                <p><a href="'. trailingslashit( site_url() ) . 'subscriptions_app/manage/' . esc_html( $public_key ) .'">
                    Manage/edit your prayer times</a>
                </p>
            ';

            $sent = wp_mail( $e['to'], $e['subject'], $e['message'], $e['headers'] );

            if ( ! $sent ){
                dt_write_log( __METHOD__ . ': Unable to send email. ' . $to );
            } else {
                // note in report that email was sent
                foreach ( $reports as $row ){
                    $payload = maybe_serialize( [ '12hour_reminder_sent' => time() ] );
                    Disciple_Tools_Reports::update( [
                        'id' => $row['id'],
                        'payload' => $payload
                    ] );
                }
            }
        } // user loop

    } // campaign loop
}
