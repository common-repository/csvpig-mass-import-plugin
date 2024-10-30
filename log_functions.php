<?php

  if(!function_exists('get_option')) {
    include_once '../../../wp-config.php';
  }

  if(!function_exists('csvpig_log_show')) {
    function csvpig_log_show($limit = 50) {
      $result = false;

      echo "
              <TABLE width='100%'>
                <TR>
                  <TD width='100%'>
                    <DIV style='margin-top:12px; margin-bottom:12px; padding:8px; background:#e4f2fd;;'>
        ";

      global $wpdb;

      $table = $wpdb->prefix . "blogpig_log";
      $plugin_name = 'csvpig';
      $sql_select = "SELECT * FROM `{$table}` " .
                    "WHERE plugin_name = '{$plugin_name}' " .
                    "ORDER BY created_at DESC, id DESC LIMIT {$limit}  ";

      $rows = $wpdb->get_results($sql_select);

      if($rows) {
        foreach($rows as $row) {
          echo "{$row->created_at} :: " . ($row->status != 'INFO' ? "{$row->status} - " : "") . "{$row->event} <BR/>\n";
        }
        $result = true;
      }

      echo "
                    </DIV>
                  </TD>
                </TR>
                <TR valign=\"top\">
                  <TD colspan=\"2\" align=\"right\">
                    <input type=\"submit\" class=\"button\" name=\"wpcsvpig_clear_log\" value=\"Clear Log &raquo;\" />
                  </TD>
                </TR>
              </TABLE>
        ";

      return $result;
    }
  }

  if(!function_exists('wpcsvpig_log_message')) {
    function wpcsvpig_log_message($event, $status = 'INFO') {
      $result = false;

      if($event) {
        global $wpdb;

        $table = $wpdb->prefix . "blogpig_log";
        $plugin_name = 'csvpig';
        $event = addslashes($event);
        $status = addslashes($status);
        $sql_insert = "INSERT INTO `{$table}`(plugin_name, status, event, created_at) " .
                      "VALUES('{$plugin_name}', '{$status}', '{$event}', now()) ";
        $wpdb->query($sql_insert);
      }

      return $result;
    }
  }

  if(!function_exists('wpcsvpig_create_log_table')) {
    function wpcsvpig_create_log_table() {
      $result = false;

      global $wpdb;

      $table = $wpdb->prefix . "blogpig_log";
      $sql_drop = "DROP TABLE $table ";
      $wpdb->query($sql_drop);
      if($wpdb->get_var("SHOW TABLES LIKE `$table`") != $table) {
        $structure = "CREATE TABLE $table ( " .
                     "  id bigint NOT NULL primary key auto_increment, " .
                     "  plugin_name varchar(255) character set utf8 NOT NULL, " .
                     "  status varchar(255) character set utf8 NOT NULL, " .
                     "  event text character set utf8 NOT NULL, " .
                     "  created_at datetime NOT NULL, " .
                     "  INDEX idxPluginDate(plugin_name, created_at) " .
                     ") ";
        $wpdb->query($structure);
        $result = true;
      }

      return $result;
    }
  }

  if(!function_exists('csvpig_log_clear')) {
    function csvpig_log_clear() {
      $result = false;

      global $wpdb;

      $table = $wpdb->prefix . "blogpig_log";
      $plugin_name = 'csvpig';
      $sql_delete = "DELETE FROM `{$table}` " .
                    "WHERE plugin_name = '{$plugin_name}' ";
      $wpdb->query($sql_delete);
      $result = true;

      return $result;
    }
  }


  /*
   * Actions...
   */

  if($_REQUEST['wpcsvpig_clear_log']) {
    csvpig_log_clear();
  }

  /*
   * Debug Functions...
   */

  function csvpig_var_dump($var_name, $expression) {
    ob_start();
    var_dump($expression);
    wpcsvpig_log_message($var_name . ':: ' . ob_get_contents(), 'DEBUG');
    ob_end_clean();
  }

?>
