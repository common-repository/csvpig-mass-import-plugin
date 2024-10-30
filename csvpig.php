<?php
/*
Plugin Name: <img style="vertical-align:middle;" src="http://blogpig.com/favicon.ico"> CSVPiG
Description: CSV Import Plugin for WordPress. <a href="http://blogpig.com/api_key" target="_blank">You need a BlogPiG API key</a> to use it. Instantly turn affiliate datafeeds into thousands of scheduled posts. Don't forget to try out our other <a href="http://blogpig.com/products/" target="_blank">WordPress Automation Plugins.</a>
Plugin URI:  http://blogpig.com/products/csvpig
Version:     2.4
Author:      BlogPiG
Author URI:  http://blogpig.com
*/

function wpcsvpig_activate() {
  $this_app = 'csvpig';

  $installed_apps_string = trim(get_option('blogpig_apps'));
  $installed_apps = array();
  if($installed_apps_string != '') {
    $installed_apps = explode(',', get_option('blogpig_apps'));
  }
  if(array_search($this_app, $installed_apps) == FALSE) {
    array_push($installed_apps, $this_app);
    $installed_apps = array_unique($installed_apps);
    update_option('blogpig_apps', implode(',',  $installed_apps));
  }
  unset($installed_apps);

  # Initial Settings
  if(function_exists('wpcsvpig_read_initial_settings')) {
    $filename = dirname(__FILE__) . "/initial_settings.txt";
    wpcsvpig_read_initial_settings($filename);
  }
  else {
    wpcsvpig_read_default_settings();
  }

}

function wpcsvpig_create_tables() {
  wpcsvpig_activate();

  # DB Tables...
  global $wpdb;
  $table = $wpdb->prefix . "csv_items";
  $sql_drop = "DROP TABLE IF EXISTS $table ";
  $wpdb->query($sql_drop);
  if($wpdb->get_var("SHOW TABLES LIKE `$table`") != $table) {
    $structure = "CREATE TABLE $table ( " .
                 "  id bigint NOT NULL primary key auto_increment, " .
                 "  custom_fields text character set utf8 NOT NULL DEFAULT '', " .
                 "  created_at datetime NOT NULL, " .
                 "  post_id bigint NOT NULL DEFAULT 0, " .
                 "  INDEX idxCreatedPostID(created_at, post_id) " .
                 ") ";
    $wpdb->query($structure);
  }

  # Create the global log table...
  include_once('log_functions.php');
  wpcsvpig_create_log_table();
}
register_activation_hook(__FILE__, 'wpcsvpig_create_tables');

function wpcsvpig_deactivate() {
  $this_app = 'csvpig';
  $installed_apps = explode(',', get_option('blogpig_apps'));
  if(count($installed_apps) > 0) {
    $output_apps = array();
    foreach($installed_apps as $app) {
      if($app != $this_app) {
        array_push($output_apps, $app);
      }
    }
    update_option('blogpig_apps', implode(',',  $output_apps));
    unset($output_apps);
  }
  unset($installed_apps);
}
register_deactivation_hook(__FILE__, 'wpcsvpig_deactivate');

function blogpig_csv_conf(){
  $pluginpath = dirname(__FILE__);
  include("$pluginpath/config.php");
}

function blogpig_csv_not_conf(){
  echo "Not Available!";
}

function add_blogpig_csv_to_submenu() {
  # CSVPiG
  if(function_exists('blogpig_csv_conf')) {
    define('CSV_CONF_FUNCTION', 'blogpig_csv_conf', TRUE);
  }
  else {
    define('CSV_CONF_FUNCTION', 'blogpig_csv_not_conf', TRUE);
  }
  if(!defined('BLOGPIG_CONF_PARENT')) {
    define('BLOGPIG_CONF_PARENT', __FILE__, TRUE);
    add_menu_page('BlogPiG Page', 'BlogPiG', 8, __FILE__, CSV_CONF_FUNCTION);
  }
  add_submenu_page(BLOGPIG_CONF_PARENT, 'CSVPiG Page', 'CSVPiG', 8, __FILE__, CSV_CONF_FUNCTION);
}
add_action('admin_menu', 'add_blogpig_csv_to_submenu');

/*
 * Add a plugin meta link
 */
function wpcsvpig_set_plugin_meta($links, $file) {
  $plugin = plugin_basename(__FILE__);
  if ($file == $plugin) {
    return array_merge(
      $links,
      array(sprintf('<a href="admin.php?page=%s">%s</a>', $plugin, __('Settings')))
    );
  }
  return $links;
}
add_filter('plugin_row_meta', 'wpcsvpig_set_plugin_meta', 10, 2);

require_once dirname(__FILE__) . "/csvpig_main.php";

# Add the PRO functionality...
$pro_file = dirname(__FILE__) . "/csvpig_pro.php";
csvpig_load_ioncube();
if(file_exists($pro_file) && (extension_loaded('ionCube Loader') || strpos(file_get_contents($pro_file), 'csvpigpro'))) {
  @include_once "$pro_file";
}

# Add the notices...
$notices_file = dirname(__FILE__) . "/csvpig_notices.php";
@include_once "$notices_file";

?>
