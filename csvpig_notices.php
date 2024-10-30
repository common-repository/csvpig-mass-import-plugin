<?php

if(!function_exists('csvpig_show_notices')) {
  function csvpig_show_notices() {
    $result = false;

    $plugin_dir = basename(dirname(__FILE__)) . "/";
    //var_dump($plugin_dir);

    ob_start();
    csvpig_api_show_field($plugin_dir);
    ob_end_clean();

    $notices['no-api-key'] = trim(get_option('blogpig_api_key')) == '';
    $notices['invalid-api-key'] = !$notices['no-api-key'] &&
                                  !csvpig_api_check();
    $notices['no-pro-file'] = !$notices['no-api-key'] &&
                              !$notices['invalid-api-key'] &&
                              strpos($_REQUEST['page'], 'csvpig.php') !== false &&
                              !file_exists(ABSPATH . "wp-content/plugins/{$plugin_dir}csvpig_pro.php");
    $notices['no-ioncube'] = !$notices['no-api-key'] &&
                             !$notices['invalid-api-key'] &&
                             !$notices['no-pro-file'] &&
                             strpos($_REQUEST['page'], 'csvpig.php') !== false &&
                             !extension_loaded('ionCube Loader');
    $notices['no-pro-features'] = !$notices['no-api-key'] &&
                                  !$notices['invalid-api-key'] &&
                                  !$notices['no-pro-file'] &&
                                  !$notices['no-ioncube'] &&
                                  strpos($_REQUEST['page'], 'csvpig.php') !== false &&
                                  !csvpig_pro_api_check(false);
    //var_dump($notices);

    @include(ABSPATH . "/wp-content/plugins/{$plugin_dir}tooltips.php");
    //var_dump($faqs);

    if($faqs) {
      foreach($notices as $name => $value) {
        if($value && $faqs[$name]) {
          $message = $faqs[$name]['message'];
          $message .= $faqs[$name]['info'] ? 'Click <STRONG><a href="' . $faqs[$name]['info'] . '" target="_blank">HERE</a></STRONG> for more information' : '';
          $message .= $faqs[$name]['free_key'] ? ' or get your free API key <STRONG><a href="' . $faqs[$name]['free_key'] . '" target="_blank">HERE</a></STRONG>' : '';
          $message .= $faqs[$name]['recheck'] ? 'Click <STRONG><a href="' . $faqs[$name]['recheck'] . '">HERE</a></STRONG> to re-check your license' : '';
          $message .= $faqs[$name]['upgrade'] ? ' or purchase an upgrade <STRONG><a href="' . $faqs[$name]['upgrade'] . '" target="_blank">HERE</a></STRONG>' : '';

          echo '<div id="login_error" class="updated" style="padding:6px; border: solid 1px #c00; background-color: #ffebe8; ">' .
               '  <strong>CSVPiG</strong> ' .
               '  ' . $message . '. ' .
               '</div>';
        }
      }
    }

    return $result;
  }
  add_action('admin_notices', 'csvpig_show_notices');
}

// The old notice code...
/*
if(!function_exists('csvpig_api_show_error')) {
  function csvpig_api_show_error() {
    $result = false;

    $api_check_result = csvpig_api_check();
    if(!$api_check_result) {
      echo '<div id="login_error" class="updated" style="padding:6px; border: solid 1px #c00; background-color: #ffebe8; ">' .
           '<strong>CSVPiG Stopped :: Invalid BlogPiG API Key.</strong> Get your free key <strong><a href="http://www.blogpig.com/api_key" target="_blank">HERE</a></strong>.' .
           '</div>';
    }

    return $result;
  }
  add_action('admin_notices', 'csvpig_api_show_error');
}
*/

?>