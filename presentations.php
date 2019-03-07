<?
$rootdir = "/kunder/wp/n/nat/wp/";
require_once("$rootdir/wp-load.php");
require_once("$rootdir/wp-admin/includes/taxonomy.php");
require_once("$rootdir/wp-admin/includes/post.php");


$URL = 'http://cws.huginonline.com/N/201/pr_feed.xml';

$xml = simplexml_load_file($URL);

# mapping: report type in feed => post category in wp
$reporttypes = array("Quarter" => 5, "Annual" => 6, "Presentation" => 7);

$logtxt = "";
$log = TRUE;

$c = 0;

$lastmodonfeed = (string)$xml->head->flastmod['date'];
$lastmodonfile = file_get_contents('lastmod_reports.txt');

if ($lastmodonfeed == $lastmodonfile) {$logtxt .= "No changes in feed" . "\n"; echo $logtxt; exit();}

file_put_contents('lastmod_reports.txt', $lastmodonfeed);
#echo "\n";

exit();

foreach ($xml->body->reports->report as $rep) {

  $TRid = (string)$rep['id'];

  $q = "select p.post_status, m.post_id, m.meta_key, m.meta_value, m.meta_id from wp_postmeta m join wp_posts p on m.post_id = p.id where m.meta_value = $TRid AND m.meta_key = 'TRid'";

  $res = mysql_query($q);

  if ($r = mysql_fetch_assoc($res)) {
    if ($log) {$logtxt .= "Found release with ID $TRid\n";}
#    if ($log) {$logtxt .= $r['meta_key'] . "=" . $r['meta_value'] . "\n";}
    $post_id = $r['post_id'];
    $q2 = "select  p.post_status, m.post_id, m.meta_key, m.meta_value, m.meta_id from wp_postmeta m join wp_posts p on m.post_id = p.id where m.post_id = $post_id and m.meta_key = 'TRmod'";
    $res2 = mysql_query($q2);
    $r2 = mysql_fetch_assoc($res2);
    
#    if ($log) {$logtxt .= $r2['meta_key'] . "=" . $r2['meta_value'] . "\n";}
    if ($r2['meta_value'] == $TRmod) {
      if ($log) {$logtxt .= "no changes!" . "\n";}
    }
    else {
      if ($log) {$logtxt .= "release with id $post_id changed. Doing update!" . "\n";}
      $prname = $prxml->body->press_releases->press_release->headline;
      $prcontent = $prxml->body->press_releases->press_release->main;

      foreach ($prxml->body->press_releases->press_release->files as $file) {
	$link = "<p><a href='" . (string)$file->file->location . "' target=_blank>" . $file->file->file_headline . "</a></p>";
	$prcontent .= $link;
      }

      $prdate = date("Y-m-d", strtotime($prxml->body->press_releases->press_release->published['date']));
      
      $post = array(
		    'ID' => $post_id,
		    'comment_status' => 'closed',
		    'ping_status' =>  'closed',
		    'post_author' => 0,
		    'post_category' => Array(3),
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
      
      wp_update_post( $post, 0);
      
      update_post_meta($id, 'TRmod', $TRmod, true);
    }
  }
  else {
#    if ($c == 0) {exit();}
    if($log) {$logtxt .= "no release found with ID = $TRid. Going for insert." . "\n";}
    $prname = $prxml->body->press_releases->press_release->headline;
    $prcontent = $prxml->body->press_releases->press_release->main;

      foreach ($prxml->body->press_releases->press_release->files as $file) {
	$link = "<p><a href='" . (string)$file->file->location . "' target=_blank>" . $file->file->file_headline . "</a></p>";
	$prcontent .= $link;
      }

    $prdate = date("Y-m-d", strtotime($prxml->body->press_releases->press_release->published['date']));
    
    $post = array(
		  'comment_status' => 'closed',
		  'ping_status' =>  'closed',
		  'post_author' => 0,
		  'post_category' => Array(3),
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
    
    $id = wp_insert_post( $post, 0);
    
    add_post_meta($id, 'TRid', $TRid, true);
    add_post_meta($id, 'TRmod', $TRmod, true);
  
    $c++;
  }
  

}

echo $logtxt;


?>
