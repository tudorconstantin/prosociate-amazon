<?php
/**
 * Plugin database schema
 * WARNING:
 * 	dbDelta() doesn't like empty lines in schema string, so don't put them there;
 *  WPDB doesn't like NULL values so better not to have them in the tables;
 */

/**
 * The database character collate.
 * @var string
 * @global string
 * @name $charset_collate
 */
$charset_collate = '';

// Declare these as global in case schema.php is included from a function.
global $wpdb, $plugin_queries;

if ( ! empty($wpdb->charset))
	$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
if ( ! empty($wpdb->collate))
	$charset_collate .= " COLLATE $wpdb->collate";

$table_prefix = $wpdb->prefix.PROSSOCIATE_PREFIX;

$plugin_queries = <<<SCHEMA
CREATE TABLE {$table_prefix}campaigns (
	id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	name VARCHAR(255) NOT NULL DEFAULT '',
	options TEXT,
	search_results TEXT,
	post_options TEXT,
	campaign_settings TEXT,
	search_parameters TEXT,
	associated_posts TEXT,
	last_run_time INT(10),
	cron_mode VARCHAR(255) NOT NULL DEFAULT '',
	cron_page VARCHAR(255) NOT NULL DEFAULT '',
	cron_running VARCHAR(255) NOT NULL DEFAULT 'no',
	cron_last_run_time INT(10),
	PRIMARY KEY  (id)
) $charset_collate;
SCHEMA;
