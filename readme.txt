=== CSVPiG - CSV Datafeed Import Plugin ===
Contributors: BlogPiG.com
Donate link: http://blogpig.com/
Tags: blogpig, csv, affiliate datafeed, affiliate datafeed software, affiliate marketing software, blog csv, cj datafeed, clickbank datafeed, csv blog, csv data feed, csv file, csv import, csv importer, csv plugin, data import, datafeed plugin, datafeed software, how to import wordpress, how to import wordpress blog, how to import wordpress database, wordpress affiliate, wordpress builder, wordpress csv, wordpress data feed, wordpress datafeed, wordpress import, wordpress importer, wordpress shop, wordpress store, wp csv, wp datafeed, wp import
Requires at least: 2.9
Tested up to: 3.0.3
Stable tag: trunk

CSVPiG is a CSV import plugin for WordPress that allows you to build blogs from affiliate datafeeds.

== Description ==

CSVPiG is a [datafeed import plugin](http://blogpig.com/products/csvpig/) for WordPress. It transforms affiliate datafeeds into professional WordPress blogs with thousands of content rich self-publishing posts.

You're not just limited to [importing affiliate datafeeds](http://blogpig.com/products/csvpig/) from the networks. You can import any CSV files using CSVPiG, automatically scheduling the posts so they appear naturally over time.

Your CSV can include descriptions, affiliate links, product images, bullet lists or absolutely anything you want, CSVPiG easily deals with anything via its Post Template engine. You can even design multiple post templates and have CSVPiG rotate through them as it publishes so each of your posts will look unique.

With CSVPiG on your blog you can:

    * Unlock unlimited sources of free high quality content
    * Import thousands of content rich posts to your blog easily & quickly
    * Schedule those posts to appear naturally over time so search engines come back day after day
    * Build automated blogs that last for years with no further intervention from you
    * Create [post templates](http://blogpig.com/products/csvpig/) to vary each posts appearance
    * Clone your custom settings across all the blogs in your network

There are 2 versions of CSVPiG available, Free & Pro. Not all features are available in the Free version. [Click here for more information](http://blogpig.com/products/csvpig/)

== Installation ==

1. Unzip the CSVPiG zip file, it will contain one folder called _csvpig

2. Use an FTP client to upload the unzipped _csvpig directory into your WordPress /wp-content/plugins/ folder

3. Logon to your WordPress administration area and go to the PLUGINS tab, scroll down to CSVPiG and click to activate the plugin

4. After activation a new BlogPiG tab will appear at the bottom of the sidebar in your blog's administration area. From here you can access the CSVPiG admin area.

CSVPiG requires a free BlogPiG API key to activate it. You can [get your API Key here](http://blogpig.com/api_key/)

== How to use it ==

Full usage instructions can be found in the [PDF user guide](http://blogpig.com/downloads/csvpig-user-guide.pdf).

== Screenshots ==

[Click here for screenshots.](http://blogpig.com/products/csvpig/)

== Frequently Asked Questions ==

[Visit our forums for FAQs](http://blogpig.com/help/)

== Changelog ==
= Version 2.4 (2010-12-14) =
* [+] [Pro] Added the parent page template field
* [+] [Pro] Allowed extended DIV tag attributes in templates
* [+] [Pro] Added a checkbox to prevent updating dates on post updates
* [+] [Pro] Added a new cron param: template name
* [*] [Pro] Fixed the problem with the custom separator char (e.g. |)
* [*] Fixed the problem with the temporary folder on safe mode servers
= Version 2.3.2 (2010-10-06) =
* [*] Fixed the problem with custom taxonomies
* [+] Added the new notices and FAQ links
= Version 2.3.1 (2010-08-20) =
* [+] [Pro] Added the post type template field
* [+] [Pro] Added the custom taxonomy template fields
* [+] [Pro] Added the blog ID template field for WP MultiSite
* [+] [Pro] Added the 'Verify Tokens' button for the selected template
* [*] [Pro] Enabled non-unique post custom/meta fields
* [+] Enabled tab-delimited CSV files
= Version 2.3 (2010-07-30) =
* [+] [Pro] Added the WYSIWYG template editor
* [+] [Pro] Added the 'Date' field to the templates
* [+] [Pro] Added the 'Author' field to the templates
* [+] [Pro] Added UI and code to handle post attachments
* [+] [Pro] Added the 'Excerpt' field to the templates
* [*] [Pro] Updated the code to handle attachments for updated posts as well
* [*] [Pro] Fixed the problem with $ signs followed by a digit in templates
* [*] [Pro] Fixed the problem with importing the initial settings file
* [+] Added the cron.php script with support for the main commands (upload, start, pause & stop)
* [+] CSV files can now be fetched directly from an URL
* [+] Enabled cron URL imports
* [+] Implemented subcategories in the following format: parent/child
* [+] Added a link to the ionCube helper files
* [-] Removed the nag screen for the invalid Pro API key
* [*] Fixed the unexpected chars problem on activation in WP 3.0
= Version 2.2.4 (2010-06-07) =
* [*] Fixed the problem with updating published posts with future dates
= Version 2.2.3 (2010-05-24) =
* [*] Fixed the problem with PHP 5.3 dynamic loaders
= Version 2.2.2 (2010-05-21) =
* [*] [Pro] Fixed the problem with missing token images
* [*] Fixed the problem with the UTF data encoding check function
= Version 2.2.1 (2010-04-26) =
* [*] [Pro] Fixed a template editor problem with JavaScript code inside a template field
= Version 2.2 (2010-04-14) =
* [*] Fixed a problem with importing mixed encoding data
* [*] Changed the source of BlogPiG News
* [+] Added Pro file and ionCube Loaders indicators
* [*] Renamed the main plugin file
* [*] Added a tweak for the WP 3.0 HTTP class
* [+] [Pro] Added code to allow CSV imports to update existing posts
* [+] [Pro] Added code to allow update matching by post title and custom fields
* [+] [Pro] Enabled importing CSV files with no column headers
* [+] [Pro] A custom separator for CSV files can be defined
* [+] [Pro] Templates now apply to custom fields' names, too
* [+] [Pro] Enabled token drag-and-drop for all browsers
* [+] [Pro] Added the ionCube helper files to the ioncube/ dir
* [*] [Pro] Fixed the problem with adding new templates in WP 3.0
* [*] [Pro] Minor fix for a template function call
* [*] [Pro] Fixed a problem with nameless custom fields
* [+] [Pro] Added code to disable generic Pro plugin updates
= Version 2.1 (2010-03-09) =
* [+] Added support for the post field templating code
* [-] Removed support for column mapping code
* [*] Minor UI tweaks
= Version 2.0.0.1048 (2010-02-18) =
* [*] Fixed a few problems when running with an invalid/missing API key
= Version 2.0.0.1021 (2010-01-26) =
* [*] Fixed a problem with Mac-style line endings in CSV files
* [*] Tweaked the config page
= Version 2.0.0.1008 (2010-01-15) =
* [*] Added a fix for publishing posts containing some special characters
= Version 2.0.0.1001 (2010-01-14) =
* [*] Pre-release cleanup
= Version 2.0.0.993 (2010-01-04) =
* [*] Redesigned the UI
* [*] Modified the structure of the DB table and changed the import & publish code accordingly
* [+] No uploads allowed while the plugin is publishing posts
* [-] Removed the logs dir
= Version 2.0.0.992 (2009-12-25) =
* [*] Completed the scheduling & pausing code
= Version 2.0.0.989 (2009-12-24) =
* [+] Added a button to pause publishing
* [+] Added the new UI features
* [*] Tweked the log functions
= Version 2.0.0.985 (2009-12-22) =
* [+] Additional Pro UI changes
* [+] Added code for Pro version integration
* [+] Changed the logging code to work with a DB table
* [*] A CURL request tweak
= Version 2.0.0.974 (2009-12-18) =
* [*] Modifications for the CSVPiG Pro branch
= Version 2.0.0.941 (2009-12-05) =
* [+] Added an alternate upload folder scanning function for older servers (PHP4)
= Version 2.0.0.934 (2009-12-01) =
* [+] Added support for multi-line CSV fields
* [*] Fixed the CREATE TABLE problem with some installations
= Version 2.0.0.919 (2009-11-26) =
* [+] Enabled importing complex HTML code into post custom fields
* [*] Fixed the problem with proxies on older WP installations
* [*] Tweaked some error messages
= Version 2.0.0.894 (2009-11-17) =
* [*] Changed the licensing code for the new license structure
* [*] Renamed the user manual file as WP had problems upgrading if it was uploaded
= Version 2.0.0.875 (2009-11-05) =
* [*] A small optimization of the API check code
= Version 2.0.0.856 (2009-10-30) =
* [+] Added an alternate command to create the DB table for older MySQL servers
= Version 2.0.0.855 (2009-10-29) =
* [+] New option added to the UI - disable `on publish` events
* [*] Changed the way WP's cron calls are invoked
* [*] Made a few tweaks to the CSV format detection code
= Version 2.0.0.850 (2009-10-28) =
* [+] Added proxy support to all HTTP functions
= Version 2.0.0.809 (2009-10-08) =
* [*] Fixed a problem when importing a file with column headers enclosed in quotes
= Version 2.0.0.796 (2009-09-25) =
* [-] Removed the `Disable Post Publishing` checkbox
* [+] Added a `Post Status` dropdown box with the following options: `Published`, `Pending Review`, `Draft` and `None`
* [*] Adjusted the initial settings file and some log messages
= Version 2.0.0.789 (2009-09-11) =
* [+] Added the PDF User Manual
* [*] Fixed a bug when importing with posting disabled
= Version 2.0.0.785 (2009-09-02) =
* [*] Improved log readability
* [*] Changed the initial configuration
= Version 2.0.0.775 (2009-08-19) =
* [+] New UI and licensing code

 == Upgrade Notice ==
 Please upgrade now to use the latest features.
