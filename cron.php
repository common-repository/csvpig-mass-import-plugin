<?php
  require_once(dirname(__FILE__) . '/../../../wp-config.php');
  @include_once(dirname(__FILE__) . '/../../../wp-admin/includes/plugin.php');

  $plugin = dirname(plugin_basename(__FILE__)) . '/csvpig.php';

  if(!function_exists('is_plugin_active') || is_plugin_active($plugin)) {
    nocache_headers();

    require_once( dirname(__FILE__) . '/csvpig.php' );
    echo('Running cron job <BR />');

    @set_time_limit(43200); # 12 hours

    $action = $_REQUEST['action'];
    $csvpig_params = array(
      'url' => $_REQUEST['url'],
      'template' => urldecode($_REQUEST['template']),

      'debug' => isset($_REQUEST['debug']) && $_REQUEST['debug'],
    );
    csvpig_cron_run($action, $csvpig_params);
  }
  else {
    echo "Plugin is not active: {$plugin} <br />";
  }

?>
