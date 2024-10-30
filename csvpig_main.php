<?php

/*
 * API Functions
 */

function csvpig_api_check_result_is_pass($check_result) {
  $result = false;

  if($check_result) {
    # A list of subscriptions for this plugin...
    $pass_array = array(
      'bronze',   # to be removed soon
      'free',
      'csvpig',
      '.*?pro',
    );

    # Compare...
    $tmp_list = strtolower(':' . str_replace(',', ':', str_replace(' ', '', $check_result)) . ':');
    foreach($pass_array as $pattern) {
      $result = preg_match("/:$pattern:/", $tmp_list);
      if($result) {
        break;
      }
    }

    unset($pass_array);
  }

  return $result;
}

function csvpig_api_check($force = false) {
  $result = false;

  if($_REQUEST['blogpig_api_key']) {
    $api_key = $_REQUEST['blogpig_api_key'];
  }
  else {
    $api_key = get_option('blogpig_api_key');
  }
  if($api_key) {
    $api_check_result = get_option('blogpig_api_check_result');
    $old_api_key = get_option('blogpig_old_api_key');
    $api_key_changed = $api_key != $old_api_key;
    $yesterday = time() - 24 * 60 * 60;
    if($api_key_changed ||                                              # api key changed since the last check or
       //!csvpig_api_check_result_is_pass($api_check_result) ||         # api key did not pass or
       $force ||                                                        # force refresh
       get_option('blogpig_api_check_date') < $yesterday) {             # the last check was more than 24h ago...
      $api_check_url = "http://blogpig.com/api_check_new.php?key={$api_key}&id=1";
      if(function_exists('curl_init')) { # try for CURL first...
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, "CSVPiG/2.4");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_URL, $api_check_url);

        # Proxy
        if(!class_exists('WP_HTTP_Proxy')) {
          @include_once(ABSPATH . WPINC. '/class-http.php');
        }
        if(class_exists('WP_HTTP_Proxy')) {
          $proxy = new WP_HTTP_Proxy();
          if($proxy->is_enabled() && $proxy->send_through_proxy($api_check_url)) {
            $isPHP5 = version_compare(PHP_VERSION, '5.0.0', '>=');
            if ($isPHP5) {
              curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
              curl_setopt($ch, CURLOPT_PROXY, $proxy->host());
              curl_setopt($ch, CURLOPT_PROXYPORT, $proxy->port());
            }
            else {
              curl_setopt($ch, CURLOPT_PROXY, $proxy->host() .':'. $proxy->port());
            }

            if($proxy->use_authentication()) {
              if ($isPHP5) {
                curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
              }
              curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy->authentication());
            }
          }
        }

        $api_check_result = curl_exec($ch);
        curl_close($ch);
        unset($ch);
      }
      else {
        $reply = false;
        if(!class_exists('WP_Http')) {
          @include_once(ABSPATH . WPINC. '/class-http.php');
        }
        if(class_exists('WP_Http')) {
          $request = new WP_Http;
          $reply = $request->request($api_check_url, array('user-agent' => 'CSVPiG/2.4'));
        }
        if($reply && is_array($reply)) {
          $api_check_result = $reply['body'];
        }
        else {
          $api_check_result = file_get_contents($api_check_url);
        }
      }
      # Does it have the ID?
      $idx = strpos($api_check_result, '|');
      if($idx !== false) {
        $api_member_id = substr($api_check_result, 0, $idx);
        update_option('blogpig_api_member_id', $api_member_id);
        $api_check_result = substr($api_check_result, $idx + 1);
      }
      update_option('blogpig_api_check_result', $api_check_result);
      update_option('blogpig_api_check_date', time());
      update_option('blogpig_old_api_key', $api_key);
    }
    $result = csvpig_api_check_result_is_pass($api_check_result);
  }
  else {
    update_option('blogpig_api_check_result', '[ no key ]');
  }

  return trim($result);
}


/*
 * Options Functions
 */

function wpcsvpig_default_options() {

  $options = array(
                   'wpcsvpig_post_status'       => 'draft',
                   'wpcsvpig_onpublish_disable' => 'on',
                   'wpcsvpig_last_file'         => '',

                   'wpcsvpig_post_use_template' => 'default',

                   'wpcsvpig_affiliate_footer'  => 'on',
                   'wpcsvpig_affiliate_sidebar' => 'on',
                  );

  return $options;
}

function wpcsvpig_read_default_settings() {
  $result = false;

  $options = wpcsvpig_default_options();
  if(function_exists('wpcsvpig_pro_add_options')) {
    wpcsvpig_pro_add_options($options);
  }

  foreach($options as $name => $value) {
    if($name) {
      # Write it to the DB... Should I check something first?
      update_option($name, $value);
    }
  }

  return $result;
}


/*
 * CSVPiG Functions
 */

if(!function_exists('wpcsvpig_show_message')) {
  function wpcsvpig_show_message($message, $strong = false) {
    $result = false;

    if($message) {
      echo '<div id="message" class="updated fade" style="padding:4px;">' .
           ($strong ? '<strong>' : '') .
           $message .
           ($strong ? '</strong>' : '') .
           '</div>';
    }

    return $result;
  }
}

if(!function_exists('csvpig_api_show_field')) {
  function csvpig_api_show_field($plugin_dir = '') {
    $result = false;

    $force = false;
    if($_POST['btnSubmitKey']) {
      update_option('blogpig_api_key', $_POST['blogpig_api_key']);
      $force = true;
    }

    $api_key = get_option('blogpig_api_key');
    $api_check_result = csvpig_api_check($force);
    if($api_key) {
      $api_key_info = trim(get_option('blogpig_api_check_result'));
    }
    else {
      $api_key_info = 'no key';
    }

    global $wp_version;

    echo "
      <div class='postbox ' >
        <h3 class='hndle' style='cursor:default;'>
          <span style='vertical-align: top;'>BlogPiG API Key</span>";
    csvpig_show_header_link('blogpig-api-key', $plugin_dir);
    echo "
        </h3>
        <div class='inside'>

          <p>
            <TABLE width='100%' style='margin-top:12px;'>
              <TR>
                <TD width='20%'>
                  API Key:
                </TD>
                <TD width='80%'>
                  <INPUT type='text' name='blogpig_api_key' id = 'blogpig_api_key' value='{$api_key}' size='35' />
                  <INPUT type='submit' class='button' name='btnSubmitKey' id='btnSubmitKey' value='Save Key' />
                  <BR />
                </TD>
              </TR>
              <TR>
                <TD width='20%'>
                  Your Licenses:
                </TD>
                <TD width='80%'>
      ";
    if(!$api_check_result) {
      echo '<span style="color:red; ">';
    }
    else {
      echo '<span style="color:green; ">';
    }
    echo "
                  {$api_key_info}</span>
                </TD>
              </TR>
            </TABLE>
          </p>
          <BR />

        </div> <!--- class='inside' --->
      </div> <!--- class='postbox ' --->
      ";


    return $result;
  }
}

function wpcsvpig_is_publish_scheduled() {
  $next_run = wp_next_scheduled('wpcsvpig_publish_posts_hook');
  return $next_run;
}

/*
 * Remove this plugin from the update list...
 */
function csvpig_no_updates($r, $url) {
  if(0 !== strpos( $url, 'http://api.wordpress.org/plugins/update-check'))
    return $r; // Not a plugin update request. Bail immediately.
  $plugins = unserialize($r['body']['plugins']);
  $file = dirname(__FILE__) . '/csvpig.php';
  unset($plugins->plugins[plugin_basename($file)]);
  unset($plugins->active[array_search(plugin_basename($file), $plugins->active)]);
  $r['body']['plugins'] = serialize($plugins);
  return $r;
}
add_filter('http_request_args', 'csvpig_no_updates', 5, 2);

/*
 * Tooltips...
 */

function csvpig_show_tooltip($param, $plugin_dir = '', $image = 'tooltip') {
  $result = false;
  if($param) {
    @include(ABSPATH . "/wp-content/plugins/{$plugin_dir}tooltips.php");
    if($tooltips[$param]) {
      echo "
        <a href='#' onclick='return false;' class='bptooltips'><img src='" . get_option('siteurl') . "/wp-content/plugins/{$plugin_dir}images/{$image}.png' /><span class='bptooltips'>" . $tooltips[$param] . "</span></a>
      ";
    }
  }
  return $result;
}

function csvpig_show_header_link($section_name, $plugin_dir = '', $section_ref = '', $color = false, $link_text = 'more info') {
  $result = false;
  if($section_name) {
    @include(ABSPATH . "/wp-content/plugins/{$plugin_dir}tooltips.php");
    $href = "http://www.youtube.com/v/{$headers[$section_name]}?version=3&enablejsapi=1&fs=1&hd=1&cc_load_policy=1&feature=player_embedded&autoplay=1";
    if(preg_match('@^http://@i', $headers[$section_name])) {
      $href = $headers[$section_name];
    }
    else {
      // Get video title...
      $link_text = get_option('blogpig_header_link_title-' . $headers[$section_name], $link_text);
      if(!$link_text || $link_text == 'more info') {
        if(!class_exists('WP_Http')) {
          include_once(ABSPATH . WPINC. '/class-http.php');
        }
        $http = new WP_Http;
        if($http) {
          $reply = $http->request('http://gdata.youtube.com/feeds/api/videos/' . $headers[$section_name]);
          $gdata = ($reply && is_array($reply) ? $reply['body'] : '');
          if($gdata) {
            $found = array();
            if(preg_match('@<title.*?>(.*?)</title>@i', $gdata, $found)) {
              $link_text = $found[1];
              update_option('blogpig_header_link_title-' . $headers[$section_name], $link_text);
            }
            unset($found);
          }
        }
      }
    }

    /*
    echo "
      &nbsp;<a class='colorboxtips' " . ($color ? "style='color:{$color};'" : "") . " href='{$href}' title='{$link_text}'><IMG src='" . get_option('siteurl') . "/wp-content/plugins/{$plugin_dir}images/" . ($color ? "pro_" : "") . "camera.png' ></a>
    ";
    */
  }
  return $result;
}


if(csvpig_api_check()) {

  #
  # Logging...
  ###

  include_once('log_functions.php');


  #
  # Helper functions
  ###

  if(!function_exists("range_to_value")){
    function range_to_value($range) {
      $result = $range;

      if($range) {
        $tmp_range = split('-', $range);
        $my_count = 0;
        if(count($tmp_range) == 2) {
          $my_count = rand($tmp_range[0], $tmp_range[1]);
        }
        else {
          $my_count = $tmp_range[0];
        }
        if(isset($my_count) && $my_count != '' && $my_count > 0) {
          $result = $my_count;
        }
      }

      return $result;
    }
  }

  function wpcsvpig_unhtmlentities($string) {
    $string = preg_replace('~&#x([0-9a-f]+);~ei', 'chr(hexdec("\\1"))', $string);
    $string = preg_replace('~&#([0-9a-f]+);~e', 'chr("\\1")', $string);
    $trans_tbl = get_html_translation_table(HTML_ENTITIES);
    $trans_tbl = array_flip($trans_tbl);
    $string = strtr($string,$trans_tbl);

    return $string;
  }

  function wpcsvpig_slashu_decode($input = '') {
    $result = $input;

    $found = array();
    $found_count = preg_match_all('#[\\\]u([0-9a-f]{4})#is', $result, $found);
    if($found_count > 0) {
      foreach($found[1] as $code) {
        #echo "code:: `" . htmlspecialchars($code) . "` <BR />\n";
        $result = str_replace('\u' . $code, chr(hexdec($code)), $result);
      }
    }

    return $result;
  }

  if(!function_exists('htmlspecialchars_decode')) {
    function htmlspecialchars_decode($string,$style=ENT_COMPAT) {
      $translation = array_flip(get_html_translation_table(HTML_SPECIALCHARS,$style));
      if($style === ENT_QUOTES) {
        $translation['&#039;'] = '\'';
      }
      return strtr($string, $translation);
    }
  }

  function wpcsvpig_clean_up($content, $do_trim = false) {
    $result = $content;

    if($result) {
      $result = wpcsvpig_unhtmlentities($result);
      $result = wpcsvpig_slashu_decode($result);
      $result = str_replace('<![CDATA[', '', $result);
      $result = str_replace(']]>', '', $result);

      $result = htmlspecialchars_decode($result);
      if($do_trim) {
        $result = trim($result);
      }
    }

    return $result;
  }

  function wpcsvpig_clean_up2($content, $do_trim = false) {
    $result = $content;

    if($result) {
      $search = array(
          '/[\x60\x82\x91\x92\xb4\xb8]/i',            // single quotes
          '/[\x84\x93\x94]/i',                        // double quotes
          '/[\x85]/i',                                // ellipsis ...
          '/[\x00-\x0d\x0b\x0c\x0e-\x1f\x7f-\x9f]/i'  // all other non-ascii
      );
      $replace = array(
          '\'',
          '"',
          '...',
          ''
      );
      $result = preg_replace($search, $replace, $result);
    }

    return $result;
  }

  function wpcsvpig_clean_up3($content, $do_trim = false) {
    $result = $content;

    $encoding = 'ASCII';
    if(function_exists('mb_detect_encoding')) {
      $encoding = mb_detect_encoding($content);
    }

    if($result && $encoding != 'UTF-8') {
      $search = array(
          '/([\x60\x82\x91\x92\xb4\xb8])/i',            // single quotes
          '/([\x84\x93\x94])/i',                        // double quotes
          '/([\x85])/i',                                // ellipsis ...
          '/([\x00-\x0d\x0b\x0c\x0e-\x1f\x7f-\xff])/i'  // all other non-ascii
      );
      $replace = array(
          '\'',
          '"',
          '...',
          chr(hexdec('\\1'))
      );
      $result = preg_replace($search, $replace, $result);
    }

    return $result;
  }


  #
  # Filter Functions
  ###

  function get_hook_functions($tag) {
    $result = false;

    if($tag) {
      global $wp_filter;
      $result = array($tag => $wp_filter[$tag]);
    }

    return $result;
  }

  function disable_all_hook_functions($list) {
    $result = false;

    if($list && is_array($list)) {
      $result = 0;
      foreach($list as $hook => $functions) {
        foreach($functions as $priority => $function) {
          foreach($function as $name => $params) {
            remove_action($hook, $params['function'], $priority, $params['accepted_args']);
            $result++;
          }
        }
      }
    }

    return $result;
  }

  function enable_all_hook_functions($list) {
    $result = false;

    if($list && is_array($list)) {
      $result = 0;
      foreach($list as $hook => $functions) {
        foreach($functions as $priority => $function) {
          foreach($function as $name => $params) {
            add_action($hook, $params['function'], $priority, $params['accepted_args']);
            $result++;
          }
        }
      }
    }

    return $result;
  }


  /*
   * Publish scheduling functions...
   */

  function wpcsvpig_reschedule_publish($my_delay = 10) { # 10 seconds
    $next_run = false;

    # Schedule next run
    if($my_delay) {
      wpcsvpig_unschedule_publish();
      wp_schedule_single_event(time() + $my_delay, 'wpcsvpig_publish_posts_hook');
      $next_run = wp_next_scheduled('wpcsvpig_publish_posts_hook');
    }

    return $next_run;
  }

  function wpcsvpig_unschedule_publish() {
    $result = true;

    @set_time_limit(43200); # 12 hours

    # Unschedule next run
    $next_run = wp_next_scheduled('wpcsvpig_publish_posts_hook');
    while($next_run) {
      wp_unschedule_event($next_run, 'wpcsvpig_publish_posts_hook');
      $next_run = wp_next_scheduled('wpcsvpig_publish_posts_hook');
    }

    return $result;
  }


  #
  # CSV import functions...
  ###

  function wpcsvpig_process() {
    $result = false;

    global $wpdb;
    $table = $wpdb->prefix . "csv_items";

    @set_time_limit(3600); # 1 hour...

    $sql_select = "SELECT count(*) FROM {$table} ";
    $item_cnt = $wpdb->get_var($sql_select);
    if($item_cnt > 0) {
      wpcsvpig_log_message("Processing uploaded data");
      wpcsvpig_log_message("Found {$item_cnt} items...");

      # we'll go for 100 at a time...
      $max_batch = 100;
      $limit_posts = min($item_cnt, $max_batch);
      $cnt = 0;

      // Allow extended div attributes
      if(function_exists('csvpig_pro_extend_kses_div')) {
        csvpig_pro_extend_kses_div();
      }

      $sql_select = "SELECT * FROM {$table} WHERE post_id = 0 ORDER BY created_at, id LIMIT {$limit_posts} ";
      while($cnt < $limit_posts) {
        $rows = $wpdb->get_results($sql_select);
        $id_list = array();
        if($rows) {
          $status = get_option('wpcsvpig_post_status'); # 'publish';
          $do_disable = $status == 'publish' && get_option('wpcsvpig_onpublish_disable') == 'on';

          # Disable `on publish` functions if needed...
          $publish_functions = get_hook_functions('publish_post');
          if($do_disable) {
            disable_all_hook_functions($publish_functions);
            wpcsvpig_log_message("Disabling all publish events...");
          }

          foreach($rows as $row) {
            # publish it...

            # All the data is in the $row->custom_fields field now...
            if($row->custom_fields) {
              $fields = unserialize($row->custom_fields);
              if($fields) {
                $item = array();
                foreach($fields as $field) {
                  $data = explode(':::', $field, 2);
                  $item[$data[0]] = $data[1];
                  unset($data);
                }

                if(count($item) > 0) {
                  # The mapped fields now contain whole templates... :)
                  $mapped = wpcsvpig_get_mapped_fields(array_keys($item));
                  if($mapped && count($mapped) > 0) {

                    $row_blog_id = false;
                    if($wpdb->blogid) {
                      $row_blog_id = (int)($mapped['blog_id'] ? wpcsvpig_apply_template($mapped['blog_id'], $item) : 0);
                    }

                    if($row_blog_id > 0) {
                      if(function_exists('switch_to_blog')) {
                        switch_to_blog($row_blog_id);
                        wpcsvpig_log_message("Blog ID: " . print_r($row_blog_id, true));
                      }
                    }

                    $row_post_title = $mapped['title'] ? wpcsvpig_apply_template($mapped['title'], $item) : '';
                    $row_post_slug = $mapped['slug'] ? sanitize_title_with_dashes(wpcsvpig_apply_template($mapped['slug'], $item)) : '';
                    $row_post_type = $mapped['type'] ? sanitize_title_with_dashes(wpcsvpig_apply_template($mapped['type'], $item)) : 'post';
                    $row_post_content = $mapped['post'] ? wpcsvpig_apply_template($mapped['post'], $item) : '';
                    $row_post_cats = $mapped['cat'] ? wpcsvpig_apply_template($mapped['cat'], $item) : '';
                    $row_post_tags = $mapped['tags'] ? wpcsvpig_apply_template($mapped['tags'], $item) : '';
                    $row_custom_fields = false;
                    if($mapped['custom'] && $mapped['custom']['names']) {
                      $idx = 0;
                      while($idx < count($mapped['custom']['names'])) {
                        $custom_name = trim(wpcsvpig_apply_template($mapped['custom']['names'][$idx], $item));
                        if($custom_name) {
                          $row_custom_fields[$custom_name][] = wpcsvpig_apply_template($mapped['custom']['values'][$idx], $item);
                        }
                        $idx++;
                      }
                    }
                    $row_post_author = $mapped['author'] ? sanitize_user(wpcsvpig_apply_template($mapped['author'], $item)) : '';
                    $row_post_date = $mapped['date'] ? wpcsvpig_apply_template($mapped['date'], $item) : '';
                    $row_attachments = false;
                    if($mapped['attachments'] && $mapped['attachments']['names']) {
                      $idx = 0;
                      while($idx < count($mapped['attachments']['names'])) {
                        $attachment_name = trim(wpcsvpig_apply_template($mapped['attachments']['names'][$idx], $item));
                        if($attachment_name) {
                          $row_attachments[$attachment_name] = wpcsvpig_apply_template($mapped['attachments']['values'][$idx], $item);
                        }
                        $idx++;
                      }
                    }
                    $row_taxonomies = false;
                    //wpcsvpig_log_message("Mapped: " . print_r($mapped['taxonomies'], true));
                    if($mapped['taxonomies'] && $mapped['taxonomies']['types']) {
                      $idx = 0;
                      while($idx < count($mapped['taxonomies']['types'])) {
                        $taxonomy_type = trim(wpcsvpig_apply_template($mapped['taxonomies']['types'][$idx], $item));
                        if($taxonomy_type) {
                          $row_taxonomies[$taxonomy_type][] = wpcsvpig_apply_template($mapped['taxonomies']['values'][$idx], $item);
                        }
                        $idx++;
                      }
                    }
                    $row_post_excerpt = $mapped['excerpt'] ? wpcsvpig_apply_template($mapped['excerpt'], $item) : '';

                    # The values should be there - proceed...
                    if($row_post_content && $row_post_title) {
                      $parent = false;
                      if($row_post_cats) {
                        if($row_post_type == 'page') {
                          // Pages have no categories, so the category is actually the parent page title...
                          $parent = wpcsvpig_pro_get_page_by_title($row_post_cats);
                        }
                        else {
                          $category = explode(',', $row_post_cats);

                          if(!function_exists('wp_insert_category')) {
                            @include_once ABSPATH . 'wp-admin/includes/taxonomy.php';
                          }
                          if(function_exists('wp_insert_category')) {
                            # Allow subcategores to be defined, e.g. cat1,parent2/child2,parent3/middle3/child3
                            foreach($category as $idx => $cat) {
                              if(strpos($cat, '/') !== false) {
                                $child = '';
                                $parent = 0;

                                $tree = explode('/', $cat);
                                $length = count($tree);
                                $found = false;
                                while($length > 0 && !$found) {
                                  // Clear the cache as it returns invalid results...
                                  $last_changed = wp_cache_get('last_changed', 'terms');
                                  if ($last_changed) {
                                    wp_cache_delete('last_changed', 'terms');
                                    $last_changed = (int)$last_changed - 1000;
                                    wp_cache_set('last_changed', $last_changed, 'terms');
                                  }
                                  // Now search...
                                  $search_path = implode('/', array_slice($tree, 0, $length));
                                  $parent = get_category_by_path($search_path);
                                  if($parent) {
                                    $parent = $parent->cat_ID;
                                    $tree = array_slice($tree, $length);
                                    $found = true;
                                  }
                                  else {
                                    $parent = 0;
                                    $length--;
                                  }
                                }

                                if(!$tree || count($tree) < 1) {
                                  $category[$idx] = basename($cat);
                                }
                                else {
                                  foreach($tree as $child) {
                                    $child = trim($child);
                                    if($child) {
                                      $my_cat = array(
                                        'cat_name' => $child,
                                        'category_parent' => (int)$parent
                                      );
                                      $parent = @wp_insert_category($my_cat, true);
                                      unset($my_cat);
                                      $category[$idx] = $child;
                                    }
                                  }
                                }
                                unset($tree);
                              }
                            }
                          }
                        }
                      }
                      else {
                        $category = null;
                      }
                      $author_id = 1; # Should probably detect the admin user id...
                      if($row_post_author && function_exists('wpcsvpig_pro_create_author')) {
                        $tmp_id = wpcsvpig_pro_create_author($row_post_author);
                        if($tmp_id) {
                          $author_id = $tmp_id;
                        }
                      }

                      $post_date = null;
                      $last_date = get_option('wpcsvpig_pro_last_post_date', false);
                      if(function_exists('wpcsvpig_pro_get_post_date')) {
                        $post_date = wpcsvpig_pro_get_post_date($row_post_date);
                        if($post_date) {
                          if($post_date <= time()) {
                            $post_date = $post_date + (get_option( 'gmt_offset' ) * 3600);
                          }
                          $post_date = date("Y-m-d H:i:s", $post_date);
                        }
                      }

                      $allow_comments = 'open';
                      $allow_pings = 'open';
                      $my_post = array('post_title'              => $row_post_title,
                                       'post_name'               => $row_post_slug,
                                       'post_type'               => $row_post_type,
                                       'post_content'            => $row_post_content,
                                       'post_content_filtered'   => $row_post_content,
                                       'post_category'           => $category,
                                       'post_parent'             => $parent,
                                       'post_status'             => $status,
                                       'post_author'             => $author_id,
                                       'post_date'               => $post_date,
                                       'comment_status'          => $allow_comments,
                                       'ping_status'             => $allow_pings,
                                       'post_excerpt'            => $row_post_excerpt,
                                      );
                      $post_id = false;
                      if(function_exists('wpcsvpig_pro_update_existing_post')) {
                        $post_id = wpcsvpig_pro_update_existing_post($my_post, $row_custom_fields);
                      }
                      $do_update = false;
                      if($post_id) {
                        $my_post['ID'] = $post_id;
                        if(get_option('wpcsvpig_pro_update_existing_dates', 'off') != 'on') {
                          unset($my_post['post_date']);
                          update_option('wpcsvpig_pro_last_post_date', $last_date);
                        }

                        // FIX:: Published posts with future dates...
                        if(!function_exists('get_gmt_from_date')) {
                          @include_once(ABSPATH . 'wp-includes/formatting.php');
                        }
                        if(!function_exists('mysql2date')) {
                          @include_once(ABSPATH . 'wp-includes/functions.php');
                        }
                        $now = gmdate('Y-m-d H:i:59');
                        if(function_exists('get_gmt_from_date') && function_exists('mysql2date')) {
                          $post_date_gmt = get_gmt_from_date($my_post['post_date']);
                          $my_post['post_date_gmt'] = $post_date_gmt;
                          if($post_date_gmt && 'publish' == $my_post['post_status']) {
                            if(mysql2date('U', $post_date_gmt, false) > mysql2date('U', $now, false)) {
                              $my_post['post_status'] = 'future';
                            }
                          }
                        }
                        else {
                          $my_post['post_date_gmt'] = gmdate('Y-m-d H:i:s', strtotime($post_date));
                          if('publish' == $my_post['post_status']) {
                            if($my_post['post_date'] > $now) {
                              $my_post['post_status'] = 'future';
                            }
                          }
                        }

                        $post_id = wp_update_post($my_post);
                        if($post_id) {
                          wpcsvpig_log_message("Item updated (#{$row->id} to {$row_post_type} {$post_id} / " . ($row_post_slug ? $row_post_slug : $row_post_title) . ")", "INFO");
                          $do_update = true;
                        }
                      }
                      else {
                        $post_id = wp_insert_post($my_post);
                      }
                      unset($my_post);
                      if($post_id && $row->id) {
                        $sql_update = "UPDATE {$table} SET post_id = '{$post_id}' WHERE id = '{$row->id}' LIMIT 1 ";
                        $wpdb->query($sql_update);
                      }
                      # Set categories...
                      if($post_id && $category) {
                        wp_set_object_terms($post_id, $category, 'category', false);
                      }
                      # Set tags...
                      if($post_id && $row_post_tags) {
                        $tags = explode(',', $row_post_tags);
                        if(count($tags) > 0) {
                          wp_set_object_terms($post_id, $tags, 'post_tag', false);
                        }
                        unset($tags);
                      }
                      # Meta fields...
                      if($post_id && $row_custom_fields) {
                        # Remove the old fields first...
                        $keys = get_post_custom_keys($post_id);
                        if($keys) {
                          foreach((array)$keys as $key) {
                            @delete_post_meta($post_id, $key);
                          }
                        }
                        # Add the new fields...
                        foreach($row_custom_fields as $field_name => $field_values) {
                          $name = trim(trim($field_name), '"');
                          foreach($field_values as $field_value) {
                            $value = trim($field_value);
                            add_post_meta($post_id, $name, $value);
                          }
                        }
                      }
                      # And attachments...
                      if($post_id && $row_attachments) {
                        # Get the existing attachments, if any...
                        $existing = array();
                        if($do_update) {
                          $args = array(
                            'post_type' => 'attachment',
                            'numberposts' => -1,
                            'post_status' => null,
                            'post_parent' => $post_id,
                          );
                          $attachments = get_posts($args);
                          if($attachments) {
                            foreach($attachments as $attachment) {
                              $existing[$attachment->guid] = $attachment->ID;
                            }
                          }
                        }

                        $dir = get_option('upload_path');
                        if(!$dir) {
                          $dir = ABSPATH . '/wp-content/uploads';
                        }

                        # Add new attachments...
                        foreach($row_attachments as $name => $file_path) {
                          $filename = $dir . '/' . ltrim($file_path, '/');
                          if(file_exists($filename)) {
                            $guid = ltrim($file_path, '/');
                            $attachment = array(
                              'post_title' => $name,
                              'post_content' => '',
                              'post_status' => 'draft',
                              'guid' => $guid,
                            );
                            if($existing[$guid]) {
                              $attachment['ID'] = $existing[$guid];
                              unset($existing[$guid]);
                            }
                            $info = wp_check_filetype($filename);
                            if($info) {
                              $attachment['post_mime_type'] = $info['type'];
                            }
                            $attach_id = wp_insert_attachment($attachment, $filename, $post_id);
                            if(!function_exists('wp_generate_attachment_metadata')) {
                              @include_once(ABSPATH . '/wp-admin/includes/image.php');
                            }
                            $attach_data = @wp_generate_attachment_metadata($attach_id, $filename);
                            wp_update_attachment_metadata($attach_id,  $attach_data);
                            wpcsvpig_log_message("Attached file {$file_path} to post ID {$post_id}");
                          }
                        }

                        foreach($existing as $guid => $ID) {
                          $attachment = array(
                            'ID' => $ID,
                            'guid' => $guid,
                            'post_parent' => 0,
                          );
                          $filename = $dir . '/' . $guid;
                          @wp_insert_attachment($attachment, $filename);
                        }
                        unset($existing);
                      }

                      # And custom taxonomies as well...
                      //wpcsvpig_log_message("Taxonomies: " . print_r($row_taxonomies, true));
                      if($post_id && $row_taxonomies) {
                        foreach($row_taxonomies as $type => $values) {
                          foreach($values as $value) {
                            $taxes = explode(',', $value);
                            if(count($taxes) > 0) {
                              wp_set_object_terms($post_id, $taxes, $type, true);
                            }
                            unset($taxes);
                          }
                        }
                      }

                    }
                    else {
                      wpcsvpig_log_message("Item skipped (#{$row->id}) - no title or content!", "WARNING");
                    }

                    if($row_blog_id > 0) {
                      if(function_exists('restore_current_blog')) {
                        restore_current_blog();
                      }
                    }

                  }
                  else {
                    wpcsvpig_log_message("Item skipped (#{$row->id}) - invalid post template!", "WARNING");
                  }

                }

                unset($item);
              }
              unset($fields);
            }

            array_push($id_list, $row->id);
            $cnt++;
            if($cnt >= $limit_posts) {
              # enough :)
              break;
            }
          }

          # ...and re-enable `on publish` functions (again if needed)...
          if($do_disable) {
            enable_all_hook_functions($publish_functions);
          }
          unset($publish_functions);

          # Delete processed items? Yes...
          if(count($id_list) > 0) {
            $sql_delete = "DELETE FROM {$table} WHERE id IN (" . implode(", ", $id_list) . ") ";
            $cnt_del = $wpdb->query($sql_delete);
          }
          unset($id_list);
        }
        unset($rows);
      }

      wpcsvpig_log_message("Processed {$cnt} items in this batch...");

      if($item_cnt > $max_batch) {
        # Reschedule a cron hook to run immediately...
        $status = get_option('wpcsvpig_reschedule', false);
        if($status) {
          update_option('wpcsvpig_publish_status', true);
          wpcsvpig_log_message("Initiating the next batch of items to be moved...");
          $delay_trigger = 10;
          wpcsvpig_reschedule_publish($delay_trigger);
          # sleep and trigger that hook...
          $delay_sleep =  2 * $delay_trigger;
          sleep($delay_sleep);
          # trigger it - spawn_cron() should be working, but wp_remote_post() does the job...
          //$reply = spawn_cron();
          $cron_url = get_option( 'siteurl' ) . '/wp-cron.php?doing_wp_cron';
          // Trying out the nonce...
          if(function_exists('wp_nonce_url')) {
            $cron_url = wp_nonce_url($cron_url, 'csvpig-publish_next');
          }
          //wpcsvpig_log_message('cron_url(n) = ' . $cron_url, 'DEBUG');
          $reply = wp_remote_post( $cron_url, array('timeout' => 0.01, 'blocking' => false, 'sslverify' => apply_filters('https_local_ssl_verify', true)) );
        }
        else {
          wpcsvpig_unschedule_publish();
          update_option('wpcsvpig_publish_status', false);
          if(!get_option('wpcsvpig_publish_paused')) {
            if(function_exists('wpcsvpig_pro_clear_post_date')) {
              wpcsvpig_pro_clear_post_date();
            }
          }
          update_option('wpcsvpig_reschedule', false);
          wpcsvpig_log_message("Publishing paused/stopped...");
        }
      }
      else {
        wpcsvpig_unschedule_publish();
        update_option('wpcsvpig_publish_status', false);
        if(!get_option('wpcsvpig_publish_paused')) {
          if(function_exists('wpcsvpig_pro_clear_post_date')) {
            wpcsvpig_pro_clear_post_date();
          }
        }
        update_option('wpcsvpig_publish_paused', false);
        update_option('wpcsvpig_reschedule', false);
        wpcsvpig_log_message("Finished publishing posts", "DONE");
      }
    }
    else {
      wpcsvpig_unschedule_publish();
      update_option('wpcsvpig_publish_status', false);
      if(!get_option('wpcsvpig_publish_paused')) {
        if(function_exists('wpcsvpig_pro_clear_post_date')) {
          wpcsvpig_pro_clear_post_date();
        }
      }
      update_option('wpcsvpig_publish_paused', false);
      update_option('wpcsvpig_reschedule', false);
      wpcsvpig_log_message("Found no items to publish", "DONE");
    }

    return $result;
  }
  add_action('wpcsvpig_publish_posts_hook','wpcsvpig_process');

  function wpcsvpig_delete_non_csv_files(&$element) {
    $element = trim($element);
    if(!empty($element) && preg_match('/^[^\.]+\.csv$/i', $element)) {
      return $element;
    }
  }

  function wpcsvpig_apply_template($template, $data) {
    $result = false;

    if($template && $data) {
      $result = $template;
      foreach($data as $data_name => $data_value) {
        $pattern = '/%' . str_replace('/', '\/', preg_quote($data_name)) . '%/';
        $replacement = str_replace('$', '\$', $data_value);

        $tmp = preg_replace($pattern, $replacement, $result);
        if($tmp !== NULL) {
          $result = $tmp;
        }
      }
    }

    return $result;
  }

  function wpcsvpig_scandir($dir) {
    $result = false;

    if($dir) {
      $dh  = opendir($dir);
      while(false !== ($filename = readdir($dh))) {
        $result[] = $filename;
      }
      sort($result);
    }

    return $result;
  }

  function wpcsvpig_scan_upload_dir($upload_dir, $max_files = 1) {
    $result = false;

    if($upload_dir && file_exists($upload_dir)) {
      if(function_exists('scandir')) {
        $files = scandir($upload_dir);
      }
      else {
        $files = wpcsvpig_scandir($upload_dir);
      }
      $files = array_filter($files, 'wpcsvpig_delete_non_csv_files');
      $result = implode(',', $files);
      unset($files);
    }
    else {
      wpcsvpig_log_message("ERROR:: Could not locate the upload directory ({$upload_dir})");
    }

    return $result;
  }

  function wpcsvpig_get_max_loop() {
    $result = false;

    if(function_exists('wpcsvpig_pro_get_max_loop')) {
      $result = wpcsvpig_pro_get_max_loop();
    }
    else {
      $result = 0x3E8;
    }

    return $result;
  }


  /* BEGIN:: from http://php.net/manual/en/function.utf8-encode.php by squeegee */

  function init_byte_map(){
    global $byte_map;
    for($x=128;$x<256;++$x){
      $byte_map[chr($x)]=utf8_encode(chr($x));
    }
    $cp1252_map=array(
      "\x80"=>"\xE2\x82\xAC",    // EURO SIGN
      "\x82" => "\xE2\x80\x9A",  // SINGLE LOW-9 QUOTATION MARK
      "\x83" => "\xC6\x92",      // LATIN SMALL LETTER F WITH HOOK
      "\x84" => "\xE2\x80\x9E",  // DOUBLE LOW-9 QUOTATION MARK
      "\x85" => "\xE2\x80\xA6",  // HORIZONTAL ELLIPSIS
      "\x86" => "\xE2\x80\xA0",  // DAGGER
      "\x87" => "\xE2\x80\xA1",  // DOUBLE DAGGER
      "\x88" => "\xCB\x86",      // MODIFIER LETTER CIRCUMFLEX ACCENT
      "\x89" => "\xE2\x80\xB0",  // PER MILLE SIGN
      "\x8A" => "\xC5\xA0",      // LATIN CAPITAL LETTER S WITH CARON
      "\x8B" => "\xE2\x80\xB9",  // SINGLE LEFT-POINTING ANGLE QUOTATION MARK
      "\x8C" => "\xC5\x92",      // LATIN CAPITAL LIGATURE OE
      "\x8E" => "\xC5\xBD",      // LATIN CAPITAL LETTER Z WITH CARON
      "\x91" => "\xE2\x80\x98",  // LEFT SINGLE QUOTATION MARK
      "\x92" => "\xE2\x80\x99",  // RIGHT SINGLE QUOTATION MARK
      "\x93" => "\xE2\x80\x9C",  // LEFT DOUBLE QUOTATION MARK
      "\x94" => "\xE2\x80\x9D",  // RIGHT DOUBLE QUOTATION MARK
      "\x95" => "\xE2\x80\xA2",  // BULLET
      "\x96" => "\xE2\x80\x93",  // EN DASH
      "\x97" => "\xE2\x80\x94",  // EM DASH
      "\x98" => "\xCB\x9C",      // SMALL TILDE
      "\x99" => "\xE2\x84\xA2",  // TRADE MARK SIGN
      "\x9A" => "\xC5\xA1",      // LATIN SMALL LETTER S WITH CARON
      "\x9B" => "\xE2\x80\xBA",  // SINGLE RIGHT-POINTING ANGLE QUOTATION MARK
      "\x9C" => "\xC5\x93",      // LATIN SMALL LIGATURE OE
      "\x9E" => "\xC5\xBE",      // LATIN SMALL LETTER Z WITH CARON
      "\x9F" => "\xC5\xB8"       // LATIN CAPITAL LETTER Y WITH DIAERESIS
    );
    foreach($cp1252_map as $k=>$v){
      $byte_map[$k]=$v;
    }
  }

  function fix_latin($instr){
    if(function_exists('mb_check_encoding') && mb_check_encoding($instr,'UTF-8'))return $instr; // no need for the rest if it's all valid UTF-8 already
    global $nibble_good_chars,$byte_map;
    $outstr='';
    $char='';
    $rest='';
    while((strlen($instr))>0){
      if(1==preg_match($nibble_good_chars,$instr,$match)){
        $char=$match[1];
        $rest=$match[2];
        $outstr.=$char;
      }elseif(1==preg_match('@^(.)(.*)$@s',$instr,$match)){
        $char=$match[1];
        $rest=$match[2];
        $outstr.=$byte_map[$char];
      }
      $instr=$rest;
    }
    return $outstr;
  }

  $byte_map=array();
  init_byte_map();
  $ascii_char='[\x00-\x7F]';
  $cont_byte='[\x80-\xBF]';
  $utf8_2='[\xC0-\xDF]'.$cont_byte;
  $utf8_3='[\xE0-\xEF]'.$cont_byte.'{2}';
  $utf8_4='[\xF0-\xF7]'.$cont_byte.'{3}';
  $utf8_5='[\xF8-\xFB]'.$cont_byte.'{4}';
  $nibble_good_chars = "@^($ascii_char+|$utf8_2|$utf8_3|$utf8_4|$utf8_5)(.*)$@s";

  /* END:: from http://php.net/manual/en/function.utf8-encode.php by squeegee */


  function wpcsvpig_has_quote($str = "", $enc = "") {
    $result = false;
    if($str && $enc) {
      $c = substr_count($str, $enc);
      if(stristr(($c / 2), ".")) {
        $result = true;
      }
    }
    return $result;
  }

  if(!function_exists('sys_get_temp_dir')) {
    function sys_get_temp_dir() {
      if($temp = getenv('TMP'))
        return $temp;
      if($temp = getenv('TEMP'))
        return $temp;
      if($temp = getenv('TMPDIR'))
        return $temp;
      $temp = tempnam(__FILE__,'');
      if(file_exists($temp)) {
        unlink($temp);
        return dirname($temp);
      }
      return null;
    }
  }

  function wpcsvpig_import_csv_file($filename, $tmp_filename = false, $max_lines = false) {
    $result = false;

    @set_time_limit(3600); # 1 hour...

    global $wpdb;

    $use_file = $filename;
    if($tmp_filename && file_exists($tmp_filename)) {
      $use_file = $tmp_filename;
    }

    if(preg_match('/^http\:\/\//i', $filename)) {
      # It's an URL - get it into a file... :)
      $tmp_url = $filename;
      $tmp_url_parts = parse_url($tmp_url);
      $filename = basename($tmp_url_parts['path']);
      $use_file = @tempnam("/tmp", '_');
      if(!$use_file) {
        if(file_exists("/var/www/tmp")) {
          $use_file = @tempnam("/var/www/tmp", '_');
        }
        if(!$use_file) {
          $use_file = @tempnam(realpath(sys_get_temp_dir()), '_');
        }
      }

      if($use_file) {
        $fp = fopen($use_file, 'w');
        $reply = false;
        if(!class_exists('WP_Http')) {
          @include_once(ABSPATH . WPINC. '/class-http.php');
        }
        if(class_exists('WP_Http')) {
          $request = new WP_Http;
          $reply = $request->request($tmp_url, array('user-agent' => 'CSVPiG/2.4'));
        }
        if($reply) {
          if(is_wp_error($reply) && isset($reply->errors)) {
            wpcsvpig_log_message($reply->get_error_messages(), "ERROR");
          }
          else if($reply && is_array($reply)) {
            fwrite($fp, $reply['body']);
          }
        }
        else {
          fwrite($fp, file_get_contents($tmp_url));
        }
        fseek($fp, 0);
        fclose($fp);
      }
      else {
        wpcsvpig_log_message("Temporary file/folder not writable!", "ERROR");
      }
    }

    if(preg_match('/\.zip$/i', $filename)) {
      require_once dirname(__FILE__)."/csvpig_unzip.class.php";

      $event = "Unpacking ZIP file...";

      $zip = new CSVPiGUnZIP($use_file);
      $list = $zip->getList();
      $random_content = '';
      $use_file = false;
      foreach($list as $file_name => $file_info) {
        $target_file = dirname(__FILE__) . "/uploads/{$file_name}";
        $zip->unzip($file_name, $target_file);
        if(preg_match('/\.csv$/i', $target_file)) {
          $use_file = $target_file;
        }
      }
      unset($list);
      unset($zip->compressedList);
      unset($zip);
    }

    if($use_file) {
      if(file_exists($use_file)) {

        wpcsvpig_log_message("Importing file: {$filename}");

        ini_set('auto_detect_line_endings', true);

        $file = @fopen($use_file, "r");
        if($file) {

          $header = false;

          $delimiters = array(',', ';');
          if(function_exists('csvpig_pro_get_custom_separator')) {
            $custom_delimiter = csvpig_pro_get_custom_separator();
            if($custom_delimiter) {
              array_unshift($delimiters, $custom_delimiter);
            }
          }
          $delimiter = ',';
          $enclosure = '"';
          $sql_cnt = 0;
          $sql_insert = '';

          $line_prev = '';

          # Get the headers first...
          $line = $line_prev . trim(fgets($file));
          $line_prev = $line;
          if($line) {
            if(!$header) {
              $line_prev = '';
              $del_cnt = 0;
              $del_found = false;
              while(!$del_found && $del_cnt < count($delimiters)) {
                if(preg_match('/' . $delimiters[$del_cnt] . '/', $line)) {
                  $delimiter = $delimiters[$del_cnt];
                  $del_found = true;
                }
                $del_cnt++;
              }
              /*
              $header = explode($delimiter, strtolower($line));
              $hcnt = 0;
              while($hcnt < count($header)) {
                $header[$hcnt] = trim($header[$hcnt], $enclosure);
                $hcnt++;
              }
              */
              $tmp = preg_split("/[{$delimiter}]/", $line);

              $in_quote = false;
              $header = array();
              foreach($tmp as $key => $value) {
                if($in_quote) {
                  if(wpcsvpig_has_quote($value, $enclosure) ) {
                    $in_quote = false;
                    $value = substr_replace($value, '', -1, 1);
                  }
                  $key = (count($header) - 1);
                  $header[$key] .= "{$delimiter}" . $value;
                }
                else {
                  if(wpcsvpig_has_quote($value, $enclosure)) {
                    $in_quote = true;
                    $value = substr_replace($value, '', 0, 1);
                  }
                  else if(substr($value, 0, 1) == $enclosure AND substr($value, -1, 1) == $enclosure) {
                    $value = substr_replace($value, '', 0, 1);
                    $value = substr_replace($value, '', -1, 1);
                  }
                  $header[] = $value;
                }
              }
              unset($tmp);
            }
          }

          # No header?
          $first_line = false;
          if(function_exists('csvpig_pro_process_no_header')) {
            $first_line = csvpig_pro_process_no_header($header);
          }

          # Append or Replace?
          $proceed = true;
          if(function_exists('wpcsvpig_pro_append_or_replace')) {
            $proceed = wpcsvpig_pro_append_or_replace($header);
          }

          if($proceed) {
            $loop = 0;
            $max_loop = wpcsvpig_get_max_loop();
            while(!feof($file) && $loop < $max_loop) {
              if($first_line !== false) {
                $in_quote = false;
                $arr = array();
                foreach((array)$first_line as $value) {
                  $arr[] = $value;
                }
                unset($first_line);
                $first_line = false;
              }
              else {
                $line = $line_prev . trim(fgets($file));
                $line_prev = $line;
                if($line) {
                  # parse the line and get the data into the temp table...
                  $tmp = preg_split("/[{$delimiter}]/", rtrim($line));

                  $in_quote = false;
                  $arr = array();
                  foreach($tmp as $key => $value) {
                    if($in_quote) {
                      if(wpcsvpig_has_quote($value, $enclosure) ) {
                        $in_quote = false;
                        $value = substr_replace($value, '', -1, 1);
                      }
                      $key = (count($arr) - 1);
                      $arr[$key] .= "{$delimiter}" . $value;
                    }
                    else {
                      if(wpcsvpig_has_quote($value, $enclosure)) {
                        $in_quote = true;
                        $value = substr_replace($value, '', 0, 1);
                      }
                      else if(substr($value, 0, 1) == $enclosure AND substr($value, -1, 1) == $enclosure) {
                        $value = substr_replace($value, '', 0, 1);
                        $value = substr_replace($value, '', -1, 1);
                      }
                      $arr[] = $value;
                    }
                  }
                  unset($tmp);
                }
              }

              # Multi-row fields?
              if(count($arr) == count($header) && !$in_quote) {
                # Completed all fields...
                $line_prev = '';
                foreach ($arr as $key => $value) {
                  $arr[$key] = str_replace($enclosure . $enclosure, $enclosure, $value);
                  #$arr[$key] = $header[$key] . ":::" . preg_replace('#([\x81-\xFF])#', chr(hexdec('\\1')), $arr[$key]);
                  $arr[$key] = $header[$key] . ":::" . wpcsvpig_clean_up3(fix_latin($arr[$key]));
                }

                # use $arr to insert data into the DB...
                if($sql_insert) {
                  $sql_insert .= ', ';
                }
                $arr_string = mysql_real_escape_string(serialize($arr));
                $sql_insert .= '( ' .
                              '  "' . $arr_string . '", ' .
                              '  now() ' .
                              ') ';
                $sql_cnt++;
                $loop++;

                if($sql_cnt >= 1 || count($csv_array) == 0) {
                  $table = $wpdb->prefix . "csv_items";
                  $sql_insert = "INSERT IGNORE INTO {$table} (custom_fields, created_at) " .
                                "VALUES " . $sql_insert;
                  $res = $wpdb->query($sql_insert);
                  $import_cnt += $res;
                  if($res < $sql_cnt) {
                    # Failed to import...
                    wpcsvpig_log_message("sql_cnt = `" . print_r($sql_cnt, true) . "`", "DEBUG");
                    wpcsvpig_log_message("res = `" . print_r($res, true) . "`", "DEBUG");
                    wpcsvpig_log_message("sql_insert = `" . print_r($sql_insert, true) . "`", "FAILED");
                  }
                  $sql_insert = '';
                  $sql_cnt = 0;
                }
                unset($arr);
              }

              unset($line);
            }
            unset($sql_insert);
            unset($header);

            $import_message = "Imported " . ($import_cnt ? $import_cnt : '0') . " items from file `" . basename($filename) . "` into the DB";
            wpcsvpig_show_message($import_message);
            wpcsvpig_log_message($import_message);
            $result = $import_cnt;
          }

          fclose($file);
        }
        @unlink($use_file);

        wpcsvpig_log_message("CSV file upload finished", "DONE");

      }
    }

    return $result;
  }

  function wpcsvpig_get_mapped_fields($headers = false) {
    $result = false;

    if(function_exists('wpcsvpig_pro_get_mapped_fields')) {
      $result = wpcsvpig_pro_get_mapped_fields($headers);
    }

    if($headers) {
      # Look for the required header fields and fill the missing ones
      # using auto-detected values...
      if(!$result) {
        $result = array();
      }
      foreach($headers as $field) {
        $field_cmp = trim(strtolower($field));
        if($field_cmp == 'title' || $field_cmp == 'titles') {
          if(!$result['title']) {
            $result['title'] = '%' . $field . '%';
          }
        }
        else if($field_cmp == 'post' || $field_cmp == 'posts') {
          if(!$result['post']) {
            $result['post'] = '%' . $field . '%';
          }
        }
        else if($field_cmp == 'category' || $field_cmp == 'categories') {
          if(!$result['cat']) {
              $result['cat'] = '%' . $field . '%';
          }
        }
        else if($field_cmp == 'tag' || $field_cmp == 'tags') {
          if(!$result['tags']) {
            $result['tags'] = '%' . $field . '%';
          }
        }
        else {
          # nothing - ignore it...
        }
      }
    }

    return $result;
  }

  function wpcsvpig_get_stats() {
    $result = false;

    global $wpdb;
    $table = $wpdb->prefix . 'csv_items';
    $sql_select = "SELECT count(*) AS cnt FROM `{$table}` ";
    $item_cnt = $wpdb->get_var($sql_select);

    $result = array(
      'item_cnt' => $item_cnt,
    );

    return $result;
  }

  /*
   * Affiliate Links
   */

  function wpcsvpig_affiliate_show_footer() {
    $result = false;

    $do_show = get_option('wpcsvpig_affiliate_footer', 'on');
    if($do_show == 'on') {
      $aff_id = get_option('blogpig_api_member_id');
      if($aff_id) {
        echo "<div>Powered by <a href=\"http://blogpig.com/members/go.php?r={$aff_id}&i=l0\">BlogPiG</a></div>";
      }
      else {
        echo "<div>Powered by <a href=\"http://blogpig.com/\">BlogPiG</a></div>";
      }
    }

    return $result;
  }
  #add_action('wp_footer', 'wpcsvpig_affiliate_show_footer');

  function wpcsvpig_affiliate_show_sidebar($args) {
    extract($args);

    echo $before_widget . $before_title . $after_title;

    $do_show = get_option('wpcsvpig_affiliate_sidebar', 'on');
    if($do_show == 'on') {
      $aff_id = get_option('blogpig_api_member_id');
      if($aff_id) {
        echo "<div style='text-align:center;' >Powered by <a href=\"http://blogpig.com/members/go.php?r={$aff_id}&i=l0\">BlogPiG</a></div>";
      }
      else {
        echo "<div style='text-align:center;' >Powered by <a href=\"http://blogpig.com/\">BlogPiG</a></div>";
      }
    }

    echo $after_widget;
  }

  function wpcsvpig_affiliate_init() {
    register_sidebar_widget(__('BlogPiG Affiliate Link'), 'wpcsvpig_affiliate_show_sidebar');
  }
  #add_action("plugins_loaded", "wpcsvpig_affiliate_init");

}

/*
 * Cron function
 */
if(!function_exists('csvpig_cron_run')) {
  function csvpig_cron_run($action, $csvpig_params = array()) {
    $result = false;

    if($action) {
      if(csvpig_api_check()) {
        extract ($csvpig_params);

        if ($template != "")
        {
            // check if template is present on his template lists
            $temp_options = get_option('wpcsvpig_pro_templates');
            $temp_options = unserialize($temp_options);

            $check = 0;
            foreach ($temp_options as $key => $value)
            {
                if ($value['name'] == $template)
                {
                    $check += 1;
                }
            }

            if ($check > 0)
            {
                update_option('wpcsvpig_post_use_template', $template);
                echo "Selected template '" . $template . "' <BR />\n";
            } else
            {
                echo "error[5]:: template selection failed - template '" . $template . "' does not exist <BR />\n";
            }
        }

        if($action == 'process' || $action == 'upload' || $action == 'import') {
          # action: process / upload / import
          if(function_exists('csvpig_pro_cron_import')) {
            csvpig_pro_cron_import($action, $url);
          }
          else {
            echo "error[-4]:: action '$action' failed - invalid or missing PRO api key <BR />\n";
          }
        }
        else if($action == 'start' || $action == 'publish') {
          # action: start / publish

          $publish_status = get_option('wpcsvpig_publish_status');

          if(!$publish_status) {
            # It's stopped/paused and the user wants to start it...
            $delay_trigger = 10;
            wpcsvpig_reschedule_publish($delay_trigger);
            # sleep and trigger that hook...
            $delay_sleep =  2 * $delay_trigger;
            sleep($delay_sleep);
            # trigger it - spawn_cron() seems to be working...
            $reply = spawn_cron();
            #$cron_url = get_option( 'siteurl' ) . '/wp-cron.php?doing_wp_cron';
            #$reply = wp_remote_post($cron_url, array('timeout' => 0.01, 'blocking' => false, 'sslverify' => apply_filters('https_local_ssl_verify', true)));
            echo "$action:: the plugin will start publishing shortly <BR />\n";

            update_option('wpcsvpig_publish_paused', false);
            $publish_status = wpcsvpig_is_publish_scheduled();
            update_option('wpcsvpig_publish_status', $publish_status);
          }
          else {
            echo "error[3]:: action '$action' failed - the plugin is already publishing <BR />\n";
          }
        }
        else if($action == 'stop') {
          # action: stop

          $publish_status = get_option('wpcsvpig_publish_status');

          if($publish_status) {
            # It's running and the user wants to stop it...
            wpcsvpig_unschedule_publish();
            update_option('wpcsvpig_publish_paused', false);
            if(function_exists('wpcsvpig_pro_clear_post_date')) {
              wpcsvpig_pro_clear_post_date();
            }
            echo "$action:: the plugin will stop publishing shortly <BR />\n";

            $publish_status = wpcsvpig_is_publish_scheduled();
            update_option('wpcsvpig_publish_status', $publish_status);
          }
          else {
            echo "error[4]:: action '$action' failed - the plugin is already stopped/paused <BR />\n";
          }
        }
        else if($action == 'pause') {
          # action: pause
          if(function_exists('csvpig_pro_cron_pause')) {
            csvpig_pro_cron_pause($action);
          }
          else {
            echo "error[-3]:: action '$action' failed - invalid or missing PRO api key <BR />\n";
          }
        }
        else {
          echo "error[-2]:: unknown action '$action' <BR />\n";
        }
      }
      else {
        echo "error[-1]:: action '$action' failed - invalid or missing api key <BR />\n";
      }
    }
    else {
      echo "error[0]:: no action defined <BR />\n";
    }

    echo "done.";

    return $result;
  }
}


function csvpig_load_ioncube() {
  if(!extension_loaded('ionCube Loader')){
    $__oc=strtolower(substr(php_uname(),0,3));
    $__ln='/ioncube/ioncube_loader_'.$__oc.'_'.substr(phpversion(),0,3).(($__oc=='win')?'.dll':'.so');
    $__oid=$__id=realpath(ini_get('extension_dir'));
    $__here=dirname(__FILE__);
    if((@$__id[1])==':'){
      $__id=str_replace('\\','/',substr($__id,2));
      $__here=str_replace('\\','/',substr($__here,2));
    }
    $__rd=str_repeat('/..',substr_count($__id,'/')).$__here.'/';
    $__i=strlen($__rd);
    while($__i--){
      if($__rd[$__i]=='/'){
        $__lp=substr($__rd,0,$__i).$__ln;
        if(file_exists($__oid.$__lp)){
          $__ln=$__lp;
          break;
        }
      }
    }
    if(function_exists('dl')) {
      @dl($__ln);
    }
  }
}

?>
