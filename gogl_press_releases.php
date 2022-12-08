<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Don't allow reentry.
$me = fopen(__FILE__,'r');
$ok = flock($me,LOCK_EX|LOCK_NB);
if (!$ok) {
 // Process already running, so don't continue.
 exit();
}

################### Initialization #################

$debug = false;


$ini = parse_ini_file("test_config.ini");

$rootdir = $ini['rootdir'];
$domain = $ini['domain'];

require_once("$rootdir/wp-load.php");
require_once("$rootdir/wp-admin/includes/taxonomy.php");
require_once("$rootdir/wp-admin/includes/post.php");

global $wpdb;




$URL = $ini['pressreleases_feed'];

// on startup manipulate this to do all import PMB 2019-04-02
// $URLextension = "/max/100/start/400";
$URLextension = "/max/20/";
$completeURL = $URL . $URLextension;

# echo $completeURL; exit();

$TRrootdir = $ini['TRrootdir'];
$xml = simplexml_load_file($completeURL);

$c = 0;

#################### Functions #######################

function gogl_set_acf_data($post_id, $title, $date, $content) {


	$report_words = array(
		"results",
		"quarter",		
		"q1",
		"q2",
		"q3",
		"q4"
	)


    $vals = array('field_602bb197b08d8' => $title);
    $ret = update_field('field_602bafba30e5d', $vals, $post_id);

    $vals = array('field_602baf37d5494' => $title);
    $ret = update_field('field_602bafe530e5e', $vals, $post_id);

	$fc_key = 'field_5d24066a6e08e';
	$fc_rows = [];
	$myarr = array( 'acf_fc_layout'=>'paragraph', 'text'=>$content);
	array_push($fc_rows, $myarr);
	update_field($fc_key, $fc_rows, $post_id);
}


#################### Execution #######################

$lastmodonfeed = (string)$xml->head->flastmod['date'];
$lastmodonfile = file_get_contents($TRrootdir . 'lastmod_pressreleases.txt');

/*
if (!$debug) {
	if ($lastmodonfeed == $lastmodonfile) {echo "No changes in feed" . "\n"; exit();}
}
*/

# check if title is correct to make sure this is the correct feed. PMB 2020-09-09
if (empty($ini['title_value'])) { # we do not test for title equality if empty in config, for backward compability PMB 2020-09-09
        echo "Warning. title is not specified in the config " . "\n";
}
else {
    # if the title in config does not match the title in xml, we exit PMB 2020-09-09
    if ($ini['title_value'] != $xml->head->title) { echo "The title does not match. We exit!" . "\n"; exit();}
}


// disable varnish plugin PMB 2018-08-10
#chdir($rootdir);
#system('wp plugin deactivate wordpress-varnish', $retval);


if (!$debug) {
	file_put_contents($TRrootdir . 'lastmod_pressreleases.txt', $lastmodonfeed);
}

# fill up an array of all previously read messages
# will be used at the end to delete all messages that are removed from the feed
$TRread = array();
$query_get_read = "select * from " . $wpdb->prefix . "postmeta where meta_key = 'TRid'";
$readres = $wpdb->get_results($query_get_read);
foreach($readres as $read) {
  $TRread[$read->meta_value] = $read->post_id;
}

foreach ($xml->body->press_releases->press_release as $pr) {


  $pathtoxml = $pr->location['href'];

  $prxml = simplexml_load_file($pathtoxml);

  $TRid = (string)$prxml->body->press_releases->press_release['id'];
  $TRmod = (string)$prxml->head->flastmod['date'];

  // do not handle if the releases are older than the date where we started import
  if ($TRid < 2869454) {
  	echo "release with id " . $TRid . " is before cut off date and is manually handled.\n";
  	continue;
  }

  $q = "select p.post_status, m.post_id, m.meta_key, m.meta_value, m.meta_id from " . $wpdb->prefix . "postmeta m join " . $wpdb->prefix . "posts p on m.post_id = p.id where m.meta_value = $TRid AND m.meta_key = 'TRid'";

  $res = $wpdb->get_results($q);

  if (sizeof($res) > 0) {
    foreach ($res as $r) {
      echo "Found release with ID $TRid\n";
      $post_id = $r->post_id;
      $q2 = "select  p.post_status, m.post_id, m.meta_key, m.meta_value, m.meta_id from " . $wpdb->prefix . "postmeta m join " . $wpdb->prefix . "posts p on m.post_id = p.id where m.post_id = $post_id and m.meta_key = 'TRmod'";
      $res2 = $wpdb->get_results($q2);
      $r2 = $res[0];
    
      if ($r2->meta_value == $TRmod) {
      	echo "no changes!" . "\n";
      }
      else {
      	echo "release with id $post_id changed. Doing update!" . "\n";
	    $prname = (string)$prxml->body->press_releases->press_release->headline;
      	$prcontent = (string)$prxml->body->press_releases->press_release->main;

      	$prdate = (string)date("Y-m-d H:i:s", strtotime($prxml->body->press_releases->press_release->published['date']));
	
      	$post = array(
		      'ID' => $post_id,
		      'comment_status' => 'closed',
		      'ping_status' =>  'closed',
		      'post_author' => 0,
		      'post_category' => Array($ini['pressrelease_cat']),
		      'post_content' => $prcontent, 
		      'post_date' => $prdate,
		      'post_date_gmt' => $prdate,
		      'post_excerpt' => '',
		      'post_name' => $prname, 
		      //  'post_password' => [ ? ] //password for post?
		      'post_status' => 'publish',// [ 'draft' | 'publish' | 'pending'| 'future' | 'private' ] //Set the status of the new post. 
		      'post_title' => $prname,
		      'post_type' => 'press-release'
		      );  
	
# UPDATING WP BACKEND
      	if (!$debug) {
	        wp_update_post( $post, 0);
    	    update_post_meta($post_id, 'TRmod', $TRmod, true);
      	}

      	gogl_set_acf_data($post_id, $prname, $prdate, $prcontent);
		
	    print_r(get_field_objects($post_id));

      }
    }
  }
  else {
#    if ($c == 2) {exit();}
    echo "no report found with ID = $TRid. Going for insert." . "\n";
    $prname = (string)$prxml->body->press_releases->press_release->headline;
    $prcontent = (string)$prxml->body->press_releases->press_release->main;


    $prdate = (string)date("Y-m-d", strtotime($prxml->body->press_releases->press_release->published['date']));

    $post = array(
		  'comment_status' => 'closed',
		  'ping_status' =>  'closed',
		  'post_author' => 0,
		  'post_category' => Array($ini['pressrelease_cat']),
		  'post_content' => $prcontent, 
		  'post_date' => $prdate,
		  'post_date_gmt' => $prdate,
		  'post_excerpt' => '',
		  'post_name' => $prname, 
		  //  'post_password' => [ ? ] //password for post?
		  'post_status' => 'publish',// [ 'draft' | 'publish' | 'pending'| 'future' | 'private' ] //Set the status of the new post. 
		  'post_title' => $prname,
		  'post_type' => 'press-release'
		  );  

# INSERTING INTO WP BACKEND
    if (!$debug) {
	    $post_id = wp_insert_post( $post, 0);
    	add_post_meta($post_id, 'TRid', $TRid, true);
    	add_post_meta($post_id, 'TRmod', $TRmod, true);
    }


   	set_acf_data($post_id, $prname, $prdate, $prcontent);


/*
      	$vals = array('field_602bb197b08d8' => 'TEST3');
      	$ret = update_field('field_602bafba30e5d', $vals, $post_id);

      	$vals = array('field_602baf37d5494' => 'test3');
      	$ret = update_field('field_602bafe530e5e', $vals, $post_id);

		$fc_key = 'field_5d24066a6e08e';
		$fc_rows = [];
		$myarr = array( 'acf_fc_layout'=>'paragraph', 'text'=>'Hello world3');
		array_push($fc_rows, $myarr);
		update_field($fc_key, $fc_rows, $post_id);
*/		
	    print_r(get_field_objects($post_id));

    # Here we have the ID and can add content to the ACF fields PMB 2022-12-07
    # section_header_with_image_below -> title AND date
    # post_extract_paragraph -> text
    # modules (Content) -> add module "paragraph"

    $c++;
  }
  # remove this message from the array that contains prevously seen messages
  unset($TRread[$TRid]);

  exit();

}


// enable varnish plugin PMB 2018-08-10
if (!$debug) {
	chdir($rootdir);
	system('wp plugin activate wordpress-varnish', $retval);	
	system('curl -X BAN --header "Host: ' . $domain . '" "http://127.0.0.1/(.*)"');
}




?>