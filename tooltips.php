<?php
  $tooltips = array(
    "select a file to upload" => "<p>Please, upload CSV files only</p>",
    "enter a datafeed url" => "<p>The URL should point to properly formatted CSV data</p>",
    "file has no headers" => "<p>Check this box if the CSV file you're uploading has no column headers</p>",
    "custom field separator" => "<p>Enter a custom separator if the fileds in your file are separated by a character other than comma (,) or semicolon (;)</p>",
    "existing data" => "<p>How many rows/posts is already in the temporary table waiting to be processed</p>",
    "unprocessed csv files" => "<p>Files uploaded by FTP to the uploads/ folder are shown here</p>",
    "upload mode" => "<p>Data can be appended only if the headers match the previously uploaded data</p>",
    "posts per day" => "<p>This parameter accepts ranges (e.g. `5-10`)</p>",
    "future posts" => "<p>Enter 0-100 :
      <p style='font-size:80%;'>
        <li style='margin-left:24px;font-size:80%;'>100% = ALL posts scheduled for future publication</li>
        <li style='margin-left:24px;font-size:80%;'>50% = HALF posts scheduled for future & HALF published with past dates</li>
        <li style='margin-left:24px;font-size:80%;'>0% = ALL posts published with past dates</li>
      </p>
    </p>",

    "templates" => "<p>Use the controls next to the template's name to edit or delete an existing template or add a new template</p>",

    "status" => "<p>This process runs in the background. When started it will publish posts from the uploaded CSV files</p>",
    "set post status" => "",
    "use template" => "",
    "disable all on publish events" => "<p>Check to prevent auto pinging/spinning/tagging of published posts and overloading your server and 3rd party services</p>",
    "update existing posts" => "<p>Check if you wish to update the existing posts based on the ID field, rather than publish new ones</p>",

    "initial settings" => "<p>Downloads the current settings for your plugin. Can be used to configure the initial settings on a different site</p>",

    "display in footer" => "",
    "display in sidebar" => "",
  );

  $headers = array(
    'blogpig-api-key' => '_UaNnq2KTRo', // 'how-to-enter-your-blogpig-api-key',
    'csvpig-upload' => 'http://blogpig.com/csvpig-upload/print',
    'csvpig-schedule-posts' => 'http://blogpig.com/csvpig-schedule-posts/print',
    'csvpig-post-templates' => 'http://blogpig.com/csvpig-post-templates/print',
    'csvpig-publish' => 'http://blogpig.com/csvpig-publish/print',
    'blogpig-clone-settings' => 'http://blogpig.com/blogpig-clone-settings/print',
    'blogpig-log' => 'http://blogpig.com/blogpig-log/print',
  );

  $faqs = array(
    'no-api-key' => array(
      'info' => 'http://blogpig.com/help/faqs/general/how-to-enter-your-blogpig-api-key',
      'free_key' => 'http://blogpig.com/api_key/',
      'message' => ': No API key found. You must enter your BlogPiG API key for the plugin to work. ',
    ),
    'invalid-api-key' => array(
      'info' => 'http://blogpig.com/help/faqs/general/invalid-api-key',
      'free_key' => 'http://blogpig.com/api_key/',
      'message' => ': No valid API key found. You must enter your BlogPiG API key for the plugin to work. ',
    ),
    'no-pro-file' => array(
      'info' => 'http://blogpig.com/help/faqs/general/missing-pro-file',
      'message' => ': No Pro file found. Download your Pro version from the BlogPiG member\'s area. ',

    ),
    'no-ioncube' => array(
      'info' => 'http://blogpig.com/help/faqs/ioncube/pro-features-not-activated',
      'message' => ': No ionCube loaders found. Pro features currently disabled. ',
    ),
    'no-pro-features' => array(
      'recheck' => '?page=' . $_REQUEST['page'] . '&btnSubmitKey=Save%20Key',
      'upgrade' => 'http://blogpig.com/products/csvpig/',
      'message' => ': Pro features are currently disabled. ',
    ),
  );
?>
