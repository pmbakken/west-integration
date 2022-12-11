<?php

// Don't allow reentry.
$me = fopen(__FILE__,'r');
$ok = flock($me,LOCK_EX|LOCK_NB);
if (!$ok) {
 // Process already running, so don't continue.
 exit();
}


$ini = parse_ini_file("test_config.ini");

$rootdir = $ini['rootdir'];
require_once("$rootdir/wp-load.php");
require_once("$rootdir/wp-admin/includes/taxonomy.php");
require_once("$rootdir/wp-admin/includes/post.php");

$TRrootdir = $ini['TRrootdir'];

$URL = $ini['reports_feed'];

// on startup manipulate this to do all import PMB 2019-04-02
//$URLextension = "/max/100/start/200";
$URLextension = "/max/20";
$completeURL = $URL . $URLextension;
$xml = simplexml_load_file($completeURL);

# mapping: report type in feed => post category in wp
$reporttypes = array(
		     "Interim report" => $ini['quarterly_cat'], 
		     "Annual Report" => $ini['annual_cat'],
		     "Presentation of company" => $ini['presentation_cat'],
		     "Presentation of Interim report" => $ini['presentation_cat'],
		     "Presentation of Annual report" => $ini['presentation_cat'],
		     "Presentation of company" => $ini['presentation_cat'],
		     "Presentation" => $ini['presentation_cat'],
		     "Other" => $ini['other_cat'],
		     "Regular attachment" => $ini['other_cat']
		     );


$lastmodonfeed = (string)$xml->head->flastmod['date'];
$lastmodonfile = file_get_contents($TRrootdir . 'lastmod_reports.txt');

echo "Last feed mod:" . $lastmodonfeed . "\n";
echo "Last file mod:" . $lastmodonfile . "\n";

# only do work when feed has changed PMB 2022-12-09
if ($lastmodonfeed == $lastmodonfile) {
  echo "No changes in feed" . "\n";
  exit();
}

# check if title is correct to make sure this is the correct feed. PMB 2020-09-09
if (empty($ini['title_value'])) { # we do not test for title equality if empty in config, for backward compability PMB 2020-09-09
        echo "Warning. title is not specified in the config " . "\n";
}
else {
    # if the title in config does not match the title in xml, we exit PMB 2020-09-09
    if ($ini['title_value'] != $xml->head->title) { echo "The title does not match. We exit!" . "\n"; exit();}
}


# write back to file the last modified date of the feed PMB 2022-12-12
file_put_contents($TRrootdir . 'lastmod_reports.txt', $lastmodonfeed);


# Loop all reports
foreach ($xml->body->reports->report as $rep) {
  $TRid = (string)$rep['id'];

  // do not handle if the releases are older than the date where we started import
  if ($TRid < 1112289) {
  	echo "release with id " . $TRid . " is before cut off date and is manually handled.\n";
  	continue;
  }

  # check for type so we can work with correct post type
  # in the feed all entries are reports, but in wordpress there are
  # separate post types for reports and presentations

  $post_type = ""; // this we will try to populate

  # type is a string in the feed. we have an associative array to map to post type
  $type = (string)$rep->files->file['type']; 
  $repcat = $reporttypes[$type];

  # check if this is report, presentation or other
  if ($repcat == $ini['quarterly_cat'] || $repcat == $ini['annual_cat']) {
  	$post_type = "reports";
  }
  else if ($repcat == $ini['presentation_cat']) {
  	$post_type = "presentations";
  }
  else {
  	# we can not identify post type, so we must skip this one
  	echo "Could not determine report or presentation for this report. Skipping.\n";
  	continue;
  }


  # we find some data fields that we will use later with an insert or an update
  $repname = (string)$rep->files->file->file_headline;
  $repurl = (string)$rep->files->file->location['href'];
  $repdate = date("Y-m-d", strtotime($rep->published['date']));


  # next we check if we already have the report/presentation imported
  # we use post meta TRreportID to keep track
  $q = "select p.post_status, m.post_id, m.meta_key, m.meta_value, m.meta_id from " . $wpdb->prefix . "postmeta m join " . $wpdb->prefix . "posts p on m.post_id = p.id where m.meta_value = $TRid AND m.meta_key = 'TRreportID'";

  $res = $wpdb->get_results($q);

  # if we find more than 0, then we have imported before, and do an update
  if (sizeof($res) > 0) {
    $post_id = $res[0]->post_id;

    $post = array(
		 'ID' => $post_id,
		  'post_title' => $repname
		  );
    wp_update_post($post);

    echo "Found one with type:" . $post_type . " with ID $TRid. doing update\n";

    update_field('field_6040c22e47cb9', $repname, $post_id);
    update_field('field_6040c22e47cfe', $repdate, $post_id);
    update_field('field_6040c22e47d3a', $repurl, $post_id);

  }
  else {
    echo "None found with ID = " . $TRid . ". Type: " . $post_type . ". Going for insert." . "\n";

    $post = array(
		  'comment_status' => 'closed',
		  'ping_status' =>  'closed',
		  'post_author' => 0,
		  'post_date' => $repdate,
		  'post_date_gmt' => $repdate,
		  'post_excerpt' => '',
		  'post_name' => $repname, 
		  'post_status' => 'publish',// [ 'draft' | 'publish' | 'pending'| 'future' | 'private' ] //Set the status of the new post. 
		  'post_title' => $repname,
		  'post_type' => $post_type
		  );  

   
    $post_id = wp_insert_post( $post, 0);
    add_post_meta($post_id, 'TRreportID', $TRid, true);

    update_field('field_6040c22e47cb9', $repname, $post_id);
    update_field('field_6040c22e47cfe', $repdate, $post_id);
    update_field('field_6040c22e47d3a', $repurl, $post_id);

    # set the correct category PMB 2022-12-12
    # but this is only relevant for reports
    if ($post_type == 'reports' && ($repcat == $ini['quarterly_cat'] || $repcat == $ini['annual_cat'])) {
      wp_set_object_terms($post_id, (int)$repcat, 'report_category');
    }

  }


}





?>
