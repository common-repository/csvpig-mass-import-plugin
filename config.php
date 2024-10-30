<?php

  if(function_exists('mb_language')) {
    mb_language('uni');
    mb_internal_encoding('UTF-8');
  }

  global $wp_version;

  $my_product = 'csvpig';

  global $wpdb;

  @set_time_limit(43200); # 12 hours

  $plugin_dir = basename(dirname(__FILE__)) . "/";
  $plugin_file = "csvpig.php";
  $plugin_name = $plugin_dir . $plugin_file;
  $plugin_url = get_option('siteurl') . "/wp-content/plugins/{$plugin_dir}";
  $config_url = "?page={$plugin_name}";
  $settings_url = get_option('siteurl') . "/wp-admin/admin.php{$config_url}";
  $my_version = 'unknown';
  $plugins = get_plugins();
  if(is_array($plugins)) {
    $my_version = $plugins[$plugin_dir . $plugin_file]['Version'];
    $plugins_allowedtags = array('a' => array('href' => array(),'title' => array()),'abbr' => array('title' => array()),'acronym' => array('title' => array()),'code' => array(),'em' => array(),'strong' => array());
    $my_version = wp_kses($my_version, $plugins_allowedtags);
    unset($plugins_allowedtags);
  }

  $pro_link = "
    <A href=\"http://www.blogpig.com/api_key?type=csvpigpro\" title=\"Upgrade to CSVPiG PRO\" target=\"_blank\" >
      <IMG alt=\"pro\" src=\"{$plugin_url}images/pro_icon.png\" style=\"vertical-align:middle\" />
    </A>
    ";

  $options = wpcsvpig_default_options();
  if(function_exists('csvpig_pro_add_options')) {
    csvpig_pro_add_options($options);
  }

  ?>

  <?php

  if(!function_exists('wpcsvpig_scan_upload_dir')) {
    include_once('csvpig_main.php');
  }

  $upload_dir = ABSPATH . "wp-content/plugins/{$plugin_dir}uploads/";
  $unprocessed = array();
  if(function_exists('wpcsvpig_scan_upload_dir')) {
    $files = wpcsvpig_scan_upload_dir($upload_dir);
    if($files) {
      $unprocessed = explode(',', $files);
    }
    unset($files);
  }
  $cnt_unprocessed = count($unprocessed);

  # Save the options first...
  if ((isset($_REQUEST['submitProcess']) && $_REQUEST['submitProcess']) ||
      (isset($_POST['submitContent']) && $_POST['submitContent']) ||
      (isset($_POST['wpcsvpig_submit']) && $_POST['wpcsvpig_submit']) ||
      (isset($_POST['wpcsvpig_publish_start']) && $_POST['wpcsvpig_publish_start']) ||
      (isset($_POST['wpcsvpig_affiliate_save']) && $_POST['wpcsvpig_affiliate_save'])
     ) {

    if (function_exists('current_user_can') && !current_user_can('manage_options'))
      die(__('What are you doing here?!'));

    foreach($options as $key => $default) {
      if($key != 'wpcsvpig_pro_templates') {
        if(isset($_POST[$key])) {
          update_option($key, $_POST[$key]);
        }
        else {
          if($default == 'on') {
            update_option($key, 'off');
          }
          else {
            update_option($key, '');
          }
        }
      }
    }
  }

  $publish_status = get_option('wpcsvpig_publish_status');

  if (isset($_REQUEST['submitProcess'])) {
    if (function_exists('current_user_can') && !current_user_can('manage_options'))
      die(__('What are you doing here?!'));

    if(function_exists('wpcsvpig_pro_import_csv_file')) {
      if(!$publish_status) {
        # A new upload resets all statuses and dates...
        update_option('wpcsvpig_publish_paused', false);
        if(function_exists('wpcsvpig_pro_clear_post_date')) {
          wpcsvpig_pro_clear_post_date();
        }

        $run_result = wpcsvpig_pro_import_csv_file($unprocessed, $upload_dir);
        #$run_message = "Processing CSV Files... (" . $run_result . ")";
        #wpcsvpig_show_message($run_message);

        # Rescan upload folder...
        unset($unprocessed);
        $files = wpcsvpig_scan_upload_dir($upload_dir);
        if($files) {
          $unprocessed = explode(',', $files);
        }
        unset($files);
        $cnt_unprocessed = count($unprocessed);
      }
      else {
        wpcsvpig_show_message("WARNING: Can not process files while the plugin is publishing!");
      }
    }
  }

  if (isset($_POST['submitContent']) && $_POST['submitContent']) {
    if (function_exists('current_user_can') && !current_user_can('manage_options'))
      die(__('What are you doing here?!'));

    if(!$publish_status) {
      # A new upload resets all statuses and dates...
      update_option('wpcsvpig_publish_paused', false);
      if(function_exists('wpcsvpig_pro_clear_post_date')) {
        wpcsvpig_pro_clear_post_date();
      }

      $import_cnt = 0;

      if(csvpig_api_check()) {
        $tmp_name = $_FILES['wpcsvpig_file']['tmp_name'];
        $name = $_FILES['wpcsvpig_file']['name'];
        if(!$tmp_name) {
          $name = $_POST['wpcsvpig_url'];
        }
        wpcsvpig_import_csv_file($name, $tmp_name);
      }
    }
    else {
      wpcsvpig_show_message("WARNING: Can not import files while the plugin is publishing!");
    }
  }


  /*
   * Publishing controls
   */

  $publish_started = $_POST['wpcsvpig_publish_start'];
  $publish_stopped = $_POST['wpcsvpig_publish_stop'];
  $publish_paused = $_POST['wpcsvpig_publish_pause'];

  $publish_status = get_option('wpcsvpig_publish_status');

  if(!$publish_status && $publish_started) {
    # It's stopped/paused and the user wants to start it...
    update_option('wpcsvpig_reschedule', true);
    if(function_exists('wpcsvpig_reschedule_publish')) {
      $delay_trigger = 10;
      wpcsvpig_reschedule_publish($delay_trigger);
      # sleep and trigger that hook...
      $delay_sleep =  2 * $delay_trigger;
      sleep($delay_sleep);
      # trigger it - spawn_cron() seems to be working...
      //$reply = spawn_cron();
      $cron_url = get_option( 'siteurl' ) . '/wp-cron.php?doing_wp_cron';
      // Trying out the nonce...
      if(function_exists('wp_nonce_url')) {
        $cron_url = wp_nonce_url($cron_url, 'csvpig-publish_start');
      }
      //wpcsvpig_log_message('cron_url(s) = ' . $cron_url, 'DEBUG');
      $reply = wp_remote_post($cron_url, array('timeout' => 0.01, 'blocking' => false, 'sslverify' => apply_filters('https_local_ssl_verify', true)));
    }
    update_option('wpcsvpig_publish_paused', false);
  }
  //else if($publish_status && ($publish_stopped || $publish_paused)) {
  else if($publish_stopped || $publish_paused) {
    # It's running and the user wants to stop it...
    wpcsvpig_unschedule_publish();
    update_option('wpcsvpig_reschedule', false);
    if($publish_paused) {
      update_option('wpcsvpig_publish_paused', true);
    }
    else {
      update_option('wpcsvpig_publish_paused', false);
      if(function_exists('wpcsvpig_pro_clear_post_date')) {
        wpcsvpig_pro_clear_post_date();
      }
    }
  }
  $publish_status = wpcsvpig_is_publish_scheduled() || get_option('wpcsvpig_reschedule', false);
  update_option('wpcsvpig_publish_status', $publish_status);


  # Get the options...
  $my_options = array();
  foreach($options as $key => $default) {
    $value = get_option($key);
    if(isset($value) && $value != '') {
      $my_options[$key] = $value;
    }
    else {
      $my_options[$key] = $default;
    }

  }

  $stats = array(
    'item_cnt' => 0,
  );
  if(function_exists('wpcsvpig_get_stats')) {
    $stats = wpcsvpig_get_stats();
  }

  if(!empty($_POST['wpcsvpig_submit'])) {
    wpcsvpig_show_message(_e('Options saved.'));
  }

  ?>
    <div class="wrap">
      <div id="icon-plugins" class="icon32"><br /></div>
      <?php
        if(function_exists('csvpig_pro_show_title') && @csvpig_pro_api_check()) {
          csvpig_pro_show_title();
        }
        else {
          ?>
          <h2>CSVPiG</h2>
          <?php
        }

      ?>

      <form action="<?php echo $config_url; ?>" method="post" id="wpcsvpig-conf" enctype="multipart/form-data">

        <div id="poststuff" class="metabox-holder has-right-sidebar">

          <div id="side-info-column" class="inner-sidebar">

            <div id='side-sortables' class='meta-box-sortables'>

              <?php
                if(!class_exists('WP_Http')) {
                  @include_once(ABSPATH . WPINC. '/class-http.php');
                }
                $http = new WP_Http;
              ?>

              <div id="pagesubmitdiv" class="postbox " >
                <h3 class='hndle' style='cursor:default;'>
                  <span>Status</span>
                </h3>

                <div class="inside">

                  <div class="misc-pub-section misc-pub-section-first">
                    <label for="post_status">Your CSVPiG Version:</label>
                    <b><span id="post-status-display" style="vertical-align:middle;"><?php echo $my_version; ?></span></b>
                  </div>

                  <div class="misc-pub-section ">
                    <label for="post_status">Current CSVPiG Version:</label>
                    <b><span id="post-status-display" style="vertical-align:middle;">
                    <?php
                      if($http) {
                        $reply = $http->request('http://www.blogpig.com/includes/version.php?p=' . $my_product);
                        echo ($reply && is_array($reply) ? $reply['body'] : '');
                      }
                    ?>
                    </span></b>
                  </div>

                  <div class="misc-pub-section ">
                    <label for="post_status">Pro File:</label>
                      <b><span id="post-status-display" style="vertical-align:middle;"><?php
                       echo file_exists(ABSPATH . "wp-content/plugins/{$plugin_dir}csvpig_pro.php") ? "Found" : "Not Found";
                      ?></span></b>
                  </div>
                  <?php
                  csvpig_load_ioncube();
                  ?>
                  <div class="misc-pub-section misc-pub-section-last">
                    <label for="post_status">ionCube Loaders:</label>
                    <b><span id="post-status-display" style="vertical-align:middle;"><?php echo extension_loaded('ionCube Loader') ? "" : "Not"; ?> Found</span></b>
                    &nbsp; [ <a href="<?php echo get_option('siteurl') . "/wp-content/plugins/{$plugin_dir}ioncube/"; ?>">more info</a> ]
                  </div>

                </div> <!--- class="inside" --->
              </div> <!--- class="postbox " --->

              <div id="pageparentdiv" class="postbox " >
                <h3 class='hndle' style='cursor:default;'>
                  <span>BlogPiG Members</span>
                </h3>

                <div class="inside">
                  <p>
                    <ul>
                      <li><a href="http://blogpig.com/" target="_blank">BlogPiG Home</a></li>
                      <?php
                        if($http) {
                          $reply = $http->request('http://www.blogpig.com/includes/pages.php?xml=1');
                          if($reply && is_array($reply)) {
                            $pages = array();
                            $pages_count = preg_match_all('/<title>(.*?)<\/title>.*?<link>(.*?)<\/link>/is', $reply['body'], $pages);
                            if($pages_count > 0) {
                              $idx = 0;
                              while($idx < count($pages[0])) {
                                echo '<li><a href="' . $pages[2][$idx] . '" target="_blank">BlogPiG ' . $pages[1][$idx]. '</a></li>';
                                $idx++;
                              }
                            }
                          }

                        }
                      ?>
                    </ul>
                  </p>
                </div> <!--- class="inside" --->
              </div> <!--- class="postbox " --->

              <div id="pageparentdiv" class="postbox " >
                <h3 class='hndle' style='cursor:default;'>
                  <span>BlogPiG News</span>
                </h3>

                <div class="inside">
                  <p>
                    <ul>
                      <?php
                        if($http) {
                          $reply = $http->request('http://feeds.feedburner.com/blogpigcom');
                          if($reply && is_array($reply)) {
                            $news = array();
                            $news_count = preg_match_all('/<title>(.*?)<\/title>.*?<link>(.*?)<\/link>/is', $reply['body'], $news);
                            if($news_count > 1) {
                              $idx = 1;
                              while($idx < count($news[0])) {
                                echo '<li><a href="' . $news[2][$idx] . '" target="_blank">' . $news[1][$idx]. '</a></li>';
                                $idx++;
                              }
                            }
                          }

                        }
                      ?>
                    </ul>
                  </p>
                </div> <!--- class="inside" --->
              </div> <!--- class="postbox " --->

              <div id="pageparentdiv" class="postbox " >
                <h3 class='hndle' style='cursor:default;'>
                  <span>BlogPiG Software</span>
                </h3>
                <div class="inside">
                  <p>
                    <ul>
                      <?php
                        if($http) {
                          $reply = $http->request('http://blogpig.com/includes/products.php?xml=1');
                          if($reply && is_array($reply)) {
                            $products = array();
                            $products_count = preg_match_all('/<title>(.*?)<\/title>.*?<link>(.*?)<\/link>/is', $reply['body'], $products);
                            if($products_count > 0) {
                              $idx = 0;
                              while($idx < count($products[0])) {
                                echo '<li><a href="' . $products[2][$idx] . '" target="_blank">' . $products[1][$idx]. '</a></li>';
                                $idx++;
                              }
                            }
                          }

                        }
                      ?>
                    </ul>
                  </p>
                </div> <!--- class="inside" --->
              </div> <!--- class="postbox " --->

              <?php
                unset($http);
              ?>

            </div> <!---  class='meta-box-sortables' --->

          </div> <!--- class="inner-sidebar" --->


          <div id="post-body" class="has-sidebar">

            <div id="post-body-content" class="has-sidebar-content">

              <div id='normal-sortables' class='meta-box-sortables'>

                <!--- Tooltips --->
                <link type="text/css" media="screen" rel="stylesheet" href="<?php echo $plugin_url; ?>js/tooltips.css" />
                <!--- Colorboxes --->
                <link type="text/css" media="screen" rel="stylesheet" href="<?php echo $plugin_url; ?>js/colorbox.css" />
                <script type="text/javascript" src="<?php echo $plugin_url; ?>js/jquery.colorbox.js"></script>
                <script type="text/javascript">
                  jQuery(document).ready(function(){
                    jQuery(".colorboxtips").colorbox({innerWidth:"853px", innerHeight:"510px", iframe:true});
                  });
                </script>

                <!--- Adding new reg functions --->
                <?php csvpig_api_show_field($plugin_dir); ?>

                <!--- Content... --->
                <div id="pagecommentstatusdiv" class="postbox " >
                  <h3 class='hndle' style='cursor:default;'>
                    <span>Read Me</span>
                  </h3>
                  <div class="inside">
                    <p>
                      <TABLE width="100%" style="margin-top:12px;">
                        <TR valign="top">
                          <TD width="100%">
                            <P>
                              Congratulations on activating your CSVPiG plugin from BlogPiG.
                            </P>
                            <P>
                              <?php
                                if(!trim(get_option('blogpig_api_key'))) {
                                ?>
                                  Don't forget to enter your BlogPiG API Key in the box above. You can get it from your BlogPiG members area <a href="http://blogpig.com/members/">here</a>.
                                <?php
                                }
                                else if(!function_exists('csvpig_pro_api_check') || !@csvpig_pro_api_check()) {
                                ?>
                                  The features marked with <?php echo $pro_link; ?> icons are only available to CSVPiG Pro license holders. You can instantly upgrade to a CSVPiG Pro license <a href="http://blogpig.com/products/csvpig">here</a>.
                                <?php
                                }
                                ?>
                            </P>
                            <!---
                            <P>
                              You can view a short tutorial video for each section by clicking on the <IMG src="<?php //echo $plugin_url . '/images/camera.png'; ?>" style="vertical-align:middle; " />  icon.
                            </P>
                            --->
                            <P>
                              You can <!---also---> mouseover the <IMG src="<?php echo $plugin_url . '/images/tooltip.png'; ?>" style="vertical-align:middle; " /> icons for a short summary of each individual feature.
                            </P>
                            <P>
                              If you have any other questions just head on over to our help desk <a href="http://blogpig.com/help/" target="_blank">here</a> and we'll be more than happy to help.
                            </P>
                            <BR />
                          </TD>
                        </TR>
                      </TABLE>
                    </p>


                  </div>
                </div>

                <!--- Content... --->
                <div id="pagecommentstatusdiv" class="postbox " >
                  <h3 class='hndle' style='cursor:default;'>
                    <span style='vertical-align: top;'>Upload</span><?php csvpig_show_header_link('csvpig-upload', $plugin_dir); ?>
                  </h3>
                  <div class="inside">
                    <p>

                      <TABLE width="100%" style="margin-top:12px;">
                        <TR valign="top">
                          <TD width="30%">
                            Select a file to upload: <?php csvpig_show_tooltip('select a file to upload', $plugin_dir); ?> <BR />
                          </TD>
                          <TD width="70%">
                            <INPUT id="wpcsvpig_file" name="wpcsvpig_file" type="file" value="" >
                            <P style="font-size:80%; margin-top:0px;">
                            </P>
                          </TD>
                        </TR>
                        <TR valign="top">
                          <TD colspan="2" style="text-align:center; margin-botton:4px;">
                            <STRONG>-- or --</STRONG><BR/><BR/>
                          </TD>
                        </TR>
                        <TR valign="top">
                          <TD width="30%">
                            Enter a datafeed URL: <?php csvpig_show_tooltip('enter a datafeed url', $plugin_dir); ?> <BR />
                          </TD>
                          <TD width="70%">
                            <INPUT id="wpcsvpig_url" name="wpcsvpig_url" type="text" size="35" value="" >
                            <P style="font-size:80%; margin-top:0px;">
                            </P>
                          </TD>
                        </TR>

                        <TR valign="top" <?php if(function_exists('csvpig_pro_show_process') && @csvpig_pro_api_check()) echo " style='display:none;' "; ?> >
                          <TD width="30%">
                          </TD>
                          <TD width="70%">
                            <BR />
                            <STRONG>Free Version</STRONG>
                            <SPAN style="font-size:80%; margin-top:0px; margin-left:62px;">
                              <BR/><BR/>
                              <LI style="margin-left:12px;">1000 rows  <STRONG>x</STRONG>  4 columns</LI>
                              <LI style="margin-left:12px;">fixed headers - title, post, category, tags</LI>
                              <LI style="margin-left:12px;">your server is limited to <STRONG><?php echo ini_get('post_max_size'); ?></STRONG> uploads</LI>
                            </SPAN>
                          </TD>
                        </TR>

                        <TR valign="top" <?php if(function_exists('csvpig_pro_show_process') && @csvpig_pro_api_check()) echo " style='display:none;' "; ?> >
                          <TD width="30%">
                          </TD>
                          <TD width="70%">
                            <BR />
                            <?php
                              if(function_exists('csvpig_pro_show_process') && @csvpig_pro_api_check()) {
                                echo "<STRONG>Pro</STRONG>";
                              }
                              else {
                                echo $pro_link;
                              }
                            ?> <STRONG>Version</STRONG>
                            <SPAN style="font-size:80%; margin-top:0px; margin-left:62px;">
                              <BR/><BR/>
                              <LI style="margin-left:12px;">unlimited rows  <STRONG>x</STRONG>  unlimited columns</LI>
                              <LI style="margin-left:12px;">use any header text - use templates for all post fields including <STRONG>Custom Fields</STRONG></LI>
                              <LI style="margin-left:12px;">upload large or multiple CSV files via FTP
                                to the <STRONG><?php echo $upload_dir; ?></STRONG> directory and use the
                                <STRONG>Process CSV Files</STRONG> button below</LI>
                            </SPAN>
                            <BR />
                            <BR />
                          </TD>
                        </TR>

                        <TR valign="top">
                          <TD width="30%">
                            File has no headers: <?php csvpig_show_tooltip('file has no headers', $plugin_dir); ?> <BR />
                          </TD>
                          <TD width="70%">
                            <?php
                              if(function_exists('wpcsvpig_pro_show_no_header') && @csvpig_pro_api_check()) {
                                wpcsvpig_pro_show_no_header();
                              }
                              else {
                            ?>
                              <INPUT disabled type="checkbox" name="wpcsvpig_pro_no_header" id="wpcsvpig_pro_no_header" value="none" />
                              <?php echo $pro_link; ?>
                            <?php
                              }
                            ?>
                            <P style="font-size:80%; margin-top:0px;">
                            </P>
                            <BR />
                            <BR />
                          </TD>
                        </TR>

                        <TR valign="top">
                          <TD width="30%">
                            Field separator: <?php csvpig_show_tooltip('custom field separator', $plugin_dir); ?> <BR />
                          </TD>
                          <TD width="70%">
                            <?php
                              if(function_exists('wpcsvpig_pro_show_separator') && @csvpig_pro_api_check()) {
                                wpcsvpig_pro_show_separator();
                              }
                              else {
                            ?>
                              <INPUT disabled type="text" size="3" name="wpcsvpig_pro_separator" id="wpcsvpig_pro_separator" value="" />
                              <?php echo $pro_link; ?>
                            <?php
                              }
                            ?>
                            <P style="font-size:80%; margin-top:0px;">
                            </P>
                            <BR />
                            <BR />
                          </TD>
                        </TR>

                        <TR valign="top">
                          <TD width="30%">
                            Existing data: <?php csvpig_show_tooltip('existing data', $plugin_dir); ?> <BR />
                          </TD>
                          <TD width="70%">
                            items: <STRONG><?php echo $stats['item_cnt']; ?></STRONG> <BR/>
                            <P style="font-size:80%; margin-top:0px;">
                            </P>
                            <BR />
                            <BR />
                          </TD>
                        </TR>

                        <TR valign="top">
                          <TD width="30%">
                            Unprocessed CVS Files: <?php csvpig_show_tooltip('unprocessed csv files', $plugin_dir); ?> <BR />
                          </TD>
                          <TD width="70%">
                            uploaded: <STRONG><span id="post-status-display"><?php echo $cnt_unprocessed; ?></span></STRONG> <BR/>
                            <P style="font-size:80%; margin-top:0px;">
                              <?php
                                if($cnt_unprocessed > 0) {
                                  foreach((array)$unprocessed as $id => $value) {
                                    echo "<span style='margin-left:0px;'>[ " . ($id + 1) . " ] {$value}</span> <BR />\n";
                                  }
                                }
                              ?>
                            </P>
                            <BR />
                            <BR />
                          </TD>
                        </TR>

                        <TR valign="top">
                          <TD width="30%">
                            Upload mode: <?php csvpig_show_tooltip('upload mode', $plugin_dir); ?> <BR />
                          </TD>
                          <TD width="70%">
                            <?php
                              if(function_exists('wpcsvpig_pro_show_append') && @csvpig_pro_api_check()) {
                                wpcsvpig_pro_show_append();
                              }
                              else {
                            ?>
                              <INPUT disabled type="radio" name="wpcsvpig_pro_upload_mode" id="wpcsvpig_pro_upload_mode" value="replace" />
                                <LABEL>replace</LABEL>
                              <INPUT disabled type="radio" name="wpcsvpig_pro_upload_mode" id="wpcsvpig_pro_upload_mode" value="replace" />
                                <LABEL>append</LABEL>
                              <?php echo $pro_link; ?>
                            <?php
                              }
                            ?>
                            <P style="font-size:80%; margin-top:0px;">
                            </P>
                          </TD>
                        </TR>

                        <TR valign="top">
                          <TD width="100%" colspan="2" style="text-align:right; ">
                            <INPUT <?php echo ($publish_status ? "disabled" : ""); ?> type="submit" class="button" name="submitContent" value="Upload File &raquo;" />
                            <?php
                              if(function_exists('csvpig_pro_show_process') && @csvpig_pro_api_check()) {
                                csvpig_pro_show_process($publish_status);
                              }
                              else { ?>
                                <INPUT disabled type="submit" class="button" name="submitProcess" value="Process CSV Files &raquo;" /> <?php echo $pro_link; ?>
                            <?php } ?>
                          </TD>
                        </TR>

                      </TABLE>

                    </p>

                  </div>
                </div>


                <?php
                  # Schedule Posts...
                  if(function_exists('csvpig_pro_show_schedule') && @csvpig_pro_api_check()) {
                    csvpig_pro_show_schedule($plugin_dir);
                  }
                  else {
                ?>
                <div class='postbox ' style='border-color: #298cba !important; ' >
                  <h3 class='hndle' style='cursor:default; border-color: #298cba !important; background: #21759B url(../images/button-grad.png) repeat-x scroll left top; color: #FFF !important; font-weight: bold;' >
                    <span style='vertical-align: top;'>Schedule Posts</span><?php csvpig_show_header_link('csvpig-schedule-posts', $plugin_dir, '', '#AAAAAA'); ?>
                  </h3>
                  <div class='inside'>

                    <p>
                      <TABLE width='100%' style='margin-top:12px;'>
                        <TR>
                          <TD width='30%' valign='top'>
                            Posts per day: <?php csvpig_show_tooltip('posts per day', $plugin_dir); ?>
                          </TD>
                          <TD width='70%'>
                            <INPUT disabled type='text' size='5' name='wpcsvpig_pro_posts_per_day' id='wpcsvpig_pro_posts_per_day' value='' />
                            <P style='font-size:80%; margin-top:0px;'>
                            </P>
                            <BR />
                            <BR />
                          </TD>
                        </TR>
                        <TR>
                          <TD width='30%' valign='top'>
                            Future posts: <?php csvpig_show_tooltip('future posts', $plugin_dir); ?>
                          </TD>
                          <TD width='70%'>
                            <INPUT disabled type='text' size='5' name='wpcsvpig_pro_future_posts' id='wpcsvpig_pro_future_posts' value='' />%
                            <P style='font-size:80%; margin-top:0px;'>
                            </P>
                            <BR />
                            <BR />
                          </TD>
                        </TR>
                        <TR>
                          <TD width='30%' valign='top'>
                          </TD>
                          <TD width='70%' style='text-align:right;''>
                            <INPUT type='button' disabled class='button' name='wpcsvpig_pro_schedule_save' value='Save &raquo;' />
                              <?php echo $pro_link; ?>
                            <P style='font-size:80%; margin-top:8px;'>
                            </P>
                          </TD>
                        </TR>
                      </TABLE>
                    </p>

                  </div> <!--- class='inside' --->
                </div> <!--- class='postbox ' --->
                <?php
                  }
                ?>

                <?php
                  # Post Templates...
                  if(function_exists('csvpig_pro_show_templates') && @csvpig_pro_api_check()) {
                    csvpig_pro_show_templates($plugin_dir);
                  }
                  else {
                ?>
                <div class='postbox ' style='border-color: #298cba !important; ' >
                  <h3 class='hndle' style='cursor:default; border-color: #298cba !important; background: #21759B url(../images/button-grad.png) repeat-x scroll left top; color: #FFF !important; font-weight: bold;' >
                    <span style='vertical-align: top;'>Post Templates</span><?php csvpig_show_header_link('csvpig-post-templates', $plugin_dir, '', '#AAAAAA'); ?>
                  </h3>
                  <div class='inside'>

                    <p>
                      <TABLE width='100%' style='margin-top:12px;'>
                        <TR>
                          <TD width='30%' valign='top'>
                            Templates: <?php csvpig_show_tooltip('templates', $plugin_dir); ?>
                          </TD>
                          <TD width='70%'>
                            <div>[ no saved templates ]</div>
                            <BR />
                            <P style='font-size:80%; margin-top:0px;'>
                              [ <a href='' onclick='return false; '>add a new template</a> ]
                            </P>
                          </TD>
                        </TR>
                        <TR>
                          <TD width='30%' valign='top'>
                          </TD>
                          <TD width='70%' style='text-align:right;'>
                            <INPUT type='button' disabled class='button' name='wpcsvpig_pro_templates_save' value='Save &raquo;' />
                              <?php echo $pro_link; ?>
                            <P style='font-size:80%; margin-top:8px;'>
                            </P>
                          </TD>
                        </TR>
                      </TABLE>
                    </p>

                  </div> <!--- class='inside' --->
                </div> <!--- class='postbox ' --->
                <?php
                  }
                ?>

                <!--- Content... --->
                <div id="pagecommentstatusdiv" class="postbox " >
                  <h3 class='hndle' style='cursor:default;'>
                    <span style='vertical-align: top;'>Publish</span><?php csvpig_show_header_link('csvpig-publish', $plugin_dir); ?>
                  </h3>
                  <div class="inside">
                    <p>

                      <TABLE width="100%" style="margin-top:12px;">
                        <TR>
                          <TD width='30%' valign='top'>
                            Status: <?php csvpig_show_tooltip('status', $plugin_dir); ?>
                          </TD>
                          <TD width='70%'>
                            <STRONG><?php echo ($publish_status ? 'RUNNING' : (get_option('wpcsvpig_publish_paused') ? 'PAUSED' : 'STOPPED')); ?></STRONG>
                            <P style='font-size:80%; margin-top:8px;'>
                            </P>
                            <BR />
                            <BR />
                          </TD>
                        </TR>
                        <TR valign="top">
                          <TD width="30%">
                            Set post status: <?php csvpig_show_tooltip('set post status', $plugin_dir); ?> <BR />
                          </TD>
                          <TD width="70%">
                            <SELECT id="wpcsvpig_post_status" name="wpcsvpig_post_status" style="margin-top: -2px;">
                              <OPTION value="publish" <?php if('publish' == $my_options['wpcsvpig_post_status']) { echo "selected"; } ?>>Published</OPTION>
                              <OPTION value="pending" <?php if('pending' == $my_options['wpcsvpig_post_status']) { echo "selected"; } ?>>Pending Review</OPTION>
                              <OPTION value="draft" <?php if('draft' == $my_options['wpcsvpig_post_status']) { echo "selected"; } ?>>Draft</OPTION>
                              <!-- <OPTION value="none" <?php #if('none' == $my_options['wpcsvpig_post_status']) { echo "selected"; } ?>>None</OPTION> -->
                            </SELECT>
                            <P style="font-size:80%; margin-top:4px;">
                              <!-- Selecting <STRONG>`None`</STRONG> will keep the imported items in a DB table (e.g. <?php echo $wpdb->prefix . 'csv_items'; ?>) for further processing - the posts will <STRONG>NOT</STRONG> be visible from the Admin area. -->
                            </P>
                            <BR />
                            <BR />
                          </TD>
                        </TR>
                        <TR valign="top">
                          <TD width="30%">
                            Use template: <?php csvpig_show_tooltip('use template', $plugin_dir); ?> <BR />
                          </TD>
                          <TD width="70%">
                            <?php
                              if(function_exists('csvpig_pro_show_use_template') && @csvpig_pro_api_check()) {
                                csvpig_pro_show_use_template();
                              }
                              else { ?>
                                <SELECT disabled id="wpcsvpig_post_use_template" name="wpcsvpig_post_use_template" style="margin-top: -2px;">
                                  <OPTION value="default" <?php if('default' == $my_options['wpcsvpig_post_use_template']) { echo "selected"; } ?>>Default</OPTION>
                                </SELECT>
                                <?php echo $pro_link; ?>
                            <?php } ?>
                            <P style="font-size:80%; margin-top:4px;">
                            </P>
                            <BR />
                            <BR />
                          </TD>
                        </TR>

                        <TR valign="top">
                          <TD width="30%">
                            Disable all `on publish` events:&nbsp;<?php csvpig_show_tooltip('disable all on publish events', $plugin_dir); ?> <BR />
                          </TD>
                          <TD width="70%">
                            <INPUT type="checkbox" id="wpcsvpig_onpublish_disable" name="wpcsvpig_onpublish_disable" value="on" <?php if('on' == $my_options['wpcsvpig_onpublish_disable']) { echo "checked"; } ?> style="margin-top: -2px;">
                            <P style="font-size:80%; margin-top:4px;">
                            </P>
                            <BR />
                            <BR />
                          </TD>
                        </TR>
                        <TR valign="top">
                          <TD width="30%">
                            Update existing posts: <?php csvpig_show_tooltip('update existing posts', $plugin_dir); ?> <BR />
                          </TD>
                          <TD width="70%">
                            <?php
                              if(function_exists('csvpig_pro_show_update_existing') && @csvpig_pro_api_check()) {
                                csvpig_pro_show_update_existing();
                              }
                              else { ?>
                                <INPUT disabled type="checkbox" id="wpcsvpig_pro_update_existing" name="wpcsvpig_pro_update_existing" value="on" <?php if('on' == $my_options['wpcsvpig_pro_update_existing']) { echo "checked"; } ?> style="margin-top: -2px;">
                                using the
                                <SELECT disabled id="wpcsvpig_pro_update_existing_field" name="wpcsvpig_pro_update_existing_field" style="margin-top: -2px;">
                                  <OPTION value="slug" selected >Slug</OPTION>
                                </SELECT>
                                field
                                <?php echo $pro_link; ?>
                                <BR/>
                                <INPUT disabled type="checkbox" id="wpcsvpig_pro_update_existing_dates" name="wpcsvpig_pro_update_existing_dates" value="on" <?php if('on' == $my_options['wpcsvpig_pro_update_existing_dates']) { echo "checked"; } ?> style="margin-top: -2px;">
                                also update post dates
                            <?php } ?>
                            <P style="font-size:80%; margin-top:4px;">
                            </P>
                            <BR />
                          </TD>
                        </TR>

                        <TR>
                          <TD width='30%'>
                            &nbsp;
                          </TD>
                          <TD width='70%' align='right'>
                            <input type='submit' class='button' <?php echo ($publish_status ? 'disabled' : ''); ?> name='wpcsvpig_publish_start' value='Start &raquo;' />
                            <input type='submit' class='button' <?php echo ($publish_status ? '' : 'disabled'); ?> name='wpcsvpig_publish_stop' value='Stop &raquo;' />
                            <?php
                              if(function_exists('wpcsvpig_pro_show_pause') && @csvpig_pro_api_check()) {
                                wpcsvpig_pro_show_pause($publish_status);
                              }
                              else { ?>
                                <INPUT disabled type="submit" class="button" name="wpcsvpig_publish_pause" value="Pause &raquo;" /> <?php echo $pro_link; ?>
                            <?php } ?>
                          </TD>
                        </TR>
                      </TABLE>

                    </p>

                  </div>
                </div>

                <?php
                  # Clone Settings...
                  if(function_exists('csvpig_pro_show_clone') && @csvpig_pro_api_check()) {
                    csvpig_pro_show_clone($plugin_dir);
                  }
                  else {
                ?>
                <div class='postbox ' style='border-color: #298cba !important; ' >
                  <h3 class='hndle' style='cursor:default; border-color: #298cba !important; background: #21759B url(../images/button-grad.png) repeat-x scroll left top; color: #FFF !important; font-weight: bold;' >
                    <span style='vertical-align: top;'>Clone Settings</span><?php csvpig_show_header_link('blogpig-clone-settings', $plugin_dir, '', '#AAAAAA'); ?>
                  </h3>
                  <div class='inside'>

                    <p>
                      <TABLE width='100%' style='margin-top:12px;'>
                        <TR>
                          <TD width='30%' valign='top'>
                            Initial Settings: <?php csvpig_show_tooltip('initial settings', $plugin_dir); ?>
                          </TD>
                          <TD width='70%'>
                            <INPUT type="button" disabled class="button" name="wpcsvpig_pro_clone_setings" value="Export Settings &raquo;" />
                              <?php echo $pro_link; ?>
                            <P style='font-size:80%; margin-top:8px;'>

                            </P>
                          </TD>
                        </TR>
                      </TABLE>
                    </p>

                  </div> <!--- class='inside' --->
                </div> <!--- class='postbox ' --->
                <?php
                  }
                ?>

                <!--- Content... --->
                <!---
                <div id="pagecommentstatusdiv" class="postbox " >
                  <h3 class='hndle' style='cursor:default;'>
                    <span style='vertical-align: top;'>Affiliate Links</span><?php #csvpig_show_header_link('blogpig-affiliate-links', $plugin_dir); ?>
                  </h3>
                  <div class="inside">
                    <p>
                      <TABLE width='100%' style='margin-top:12px;'>
                        <TR>
                          <TD width='30%'>
                            Display in Footer: <?php #csvpig_show_tooltip('display in footer', $plugin_dir); ?>
                          </TD>
                          <TD width='70%'>
                            <INPUT type='checkbox' name='wpcsvpig_affiliate_footer' id='wpcsvpig_affiliate_footer' value='on' <?php if('on' == $my_options['wpcsvpig_affiliate_footer']) { echo "checked"; } ?> />
                          </TD>
                        </TR>
                        <TR>
                          <TD width='30%'>
                            Display in Sidebar: <?php #csvpig_show_tooltip('display in sidebar', $plugin_dir); ?>
                          </TD>
                          <TD width='70%'>
                            <INPUT type='checkbox' name='wpcsvpig_affiliate_sidebar' id='wpcsvpig_affiliate_sidebar' value='on' <?php if('on' == $my_options['wpcsvpig_affiliate_sidebar']) { echo "checked"; } ?> />
                          </TD>
                        </TR>
                        <TR>
                          <TD width='30%' valign='top'>
                          </TD>
                          <TD width='70%' style='text-align:right;'>
                            <INPUT type='submit' class='button' name='wpcsvpig_affiliate_save' value='Save &raquo;' />
                            <P style='font-size:80%; margin-top:8px;'>
                            </P>
                          </TD>
                        </TR>
                      </TABLE>
                    </p>

                  </div>
                </div>
                --->


                <!--- Content... --->
                <div id="pagecommentstatusdiv" class="postbox " >
                  <h3 class='hndle' style='cursor:default;'>
                    <span style='vertical-align: top;'>Log</span><?php #csvpig_show_header_link('blogpig-log', $plugin_dir); ?>
                  </h3>
                  <div class="inside">

                    <p>
                      <!--- Displaying the log file --->
                      <?php if(function_exists('csvpig_log_show')) { csvpig_log_show(); } ?>
                    </p>

                  </div>
                </div>

                <input type="hidden" name="wpblogpig_active" value="wpcsvpig" />
                <BR /><BR />


              </div> <!--- class='meta-box-sortables' --->

            </div> <!--- class="has-sidebar-content" --->

          </div> <!--- class="has-sidebar" --->

        </div> <!--- class="metabox-holder" --->

      </form>

  <?php

  unset($unprocessed);
  unset($my_options);
  unset($options);
  unset($stats);

?>
