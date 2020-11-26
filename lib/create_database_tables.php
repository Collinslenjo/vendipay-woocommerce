<?php
/*
 *
 * This File contains the database Scripts
 *
*/

function create_database_tables() {
    global $wpdb;

    $table_name = $wpdb->prefix . "vendipay_lipa_na_mpesa";

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "
        CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            order_id int(11) UNIQUE NOT NULL,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            phone_number varchar(50) NOT NULL,
            receipt varchar(50) UNIQUE NOT NULL,
            amount int(11) NOT NULL,
            currency varchar(50) NOT NULL,
            transaction_timestamp varchar(255) NOT NULL,
            transaction_type varchar(50) NOT NULL,
            account varchar(255) NOT NULL,
            used int(1) NOT NULL,
            duplicate int(1) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
