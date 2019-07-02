<?php

// Don't allow reentry.
$me = fopen(__FILE__,'r');
$ok = flock($me,LOCK_EX|LOCK_NB);
if (!$ok) {
 // Process already running, so don't continue.
 exit();
}


$ini = parse_ini_file("config.ini");


$rootdir = $ini['rootdir'];
$domain = $ini['domain'];

require_once("$rootdir/wp-load.php");
require_once("$rootdir/wp-admin/includes/taxonomy.php");
require_once("$rootdir/wp-admin/includes/post.php");

global $wpdb;




$URL = $ini['pressreleases_feed'];

// on startup manipulate this to do all import PMB 2019-04-02
// $URLextension = "/max/100/start/400";
$URLextension = "/max/20";
$completeURL = $URL . $URLextension;

# echo $completeURL; exit();

$TRrootdir = $ini['TRrootdir'];

$xml = simplexml_load_file($completeURL);

$logtxt = "";
$log = TRUE;

$c = 0;

$lastmodonfeed = (string)$xml->head->flastmod['date'];

// echo "lastmod: " . $lastmodonfeed; exit();

$lastmodonfile = file_get_contents($TRrootdir . 'lastmod_pressreleases.txt');

if ($lastmodonfeed == $lastmodonfile) {$logtxt .= "No changes in feed" . "\n"; echo $logtxt; exit();}


// disable varnish plugin PMB 2018-08-10
chdir($rootdir);
system('wp plugin deactivate wordpress-varnish', $retval);



file_put_contents($TRrootdir . 'lastmod_pressreleases.txt', $lastmodonfeed);

# fill up an array of all previously read messages
# will be used at the end to delete all messages that are removed from the feed
$TRread = array();
$query_get_read = "select * from wp_postmeta where meta_key = 'TRid'";
$readres = $wpdb->get_results($query_get_read);
foreach($readres as $read) {
  $TRread[$read->meta_value] = $read->post_id;
}

foreach ($xml->body->press_releases->press_release as $pr) {


  $pathtoxml = $pr->location['href'];

  $prxml = simplexml_load_file($pathtoxml);

  $TRid = (string)$prxml->body->press_releases->press_release['id'];
  $TRmod = (string)$prxml->head->flastmod['date'];

  $q = "select p.post_status, m.post_id, m.meta_key, m.meta_value, m.meta_id from wp_postmeta m join wp_posts p on m.post_id = p.id where m.meta_value = $TRid AND m.meta_key = 'TRid'";

  $res = $wpdb->get_results($q);

  if (sizeof($res) > 0) {
    foreach ($res as $r) {
      if ($log) {$logtxt .= "Found release with ID $TRid\n";}
#    if ($log) {$logtxt .= $r['meta_key'] . "=" . $r['meta_value'] . "\n";}
      $post_id = $r->post_id;
      $q2 = "select  p.post_status, m.post_id, m.meta_key, m.meta_value, m.meta_id from wp_postmeta m join wp_posts p on m.post_id = p.id where m.post_id = $post_id and m.meta_key = 'TRmod'";
      $res2 = $wpdb->get_results($q2);
      $r2 = $res[0];
    
#    if ($log) {$logtxt .= $r2['meta_key'] . "=" . $r2['meta_value'] . "\n";}
      if ($r2->meta_value == $TRmod) {
      	if ($log) {$logtxt .= "no changes!" . "\n";}
      }
      else {
      	if ($log) {$logtxt .= "release with id $post_id changed. Doing update!" . "\n";}
	      $prname = $prxml->body->press_releases->press_release->headline;
      	$prcontent = "<!--more-->" . $prxml->body->press_releases->press_release->main;

        // updated code to handle multiple attachments PMB 2018-08-10
        $files = $prxml->body->press_releases->press_release->files;


   if (!empty($files)) {
     foreach($files->file as $f) { 
      $i++;
      $link = "<p><a href='" . (string)$f->location['href'] . "' target=_blank>" . $f->file_headline . "</a></p>";
      $prcontent .= $link;
     }
   }


	$prdate = date("Y-m-d H:i:s", strtotime($prxml->body->press_releases->press_release->published['date']));
	
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
		      'post_type' => 'post'
		      );  
	
# UPDATING WP BACKEND
        wp_update_post( $post, 0);
        update_post_meta($id, 'TRmod', $TRmod, true);
      }
    }
  }
  else {
#    if ($c == 2) {exit();}
    if($log) {$logtxt .= "no report found with ID = $TRid. Going for insert." . "\n";}
    $prname = $prxml->body->press_releases->press_release->headline;
    $prcontent = "<!--more-->" . $prxml->body->press_releases->press_release->main;

    // updated code to handle multiple attachments PMB 2018-08-10
   $files = $prxml->body->press_releases->press_release->files;


   if (!empty($files)) {
     foreach($files->file as $f) { 
      $i++;
      $link = "<p><a href='" . (string)$f->location['href'] . "' target=_blank>" . $f->file_headline . "</a></p>";
      $prcontent .= $link;
     }
   }


    $prdate = date("Y-m-d", strtotime($prxml->body->press_releases->press_release->published['date']));

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
		  'post_type' => 'post'
		  );  

# INSERTING INTO WP BACKEND
    $id = wp_insert_post( $post, 0);
    add_post_meta($id, 'TRid', $TRid, true);
    add_post_meta($id, 'TRmod', $TRmod, true);

    $c++;
  }
  # remove this message from the array that contains prevously seen messages
  unset($TRread[$TRid]);


}


#remove all messages previously read that are no longer in the feed
#foreach ($TRread as $notinfeed) {
#  if ($log) {$logtxt .= "deleted press release with post_id $notinfeed" . "\n";}
#  wp_delete_post($notinfeed, true);
#}

// enable varnish plugin PMB 2018-08-10
chdir($rootdir);
system('wp plugin activate wordpress-varnish', $retval);

// purge in the end
system('curl -X BAN --header "Host: ' . $domain . '" "http://127.0.0.1/(.*)"');

echo $logtxt;


?>
