<?php
if(!defined("IN_MYBB"))
{
  die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook('forumdisplay_thread', 'manynewposts_thread');
$plugins->add_hook('forumdisplay_thread', 'manynewposts_style');

$plugins->add_hook('search_results_thread', 'manynewposts_thread');
$plugins->add_hook('search_results_thread', 'manynewposts_style');

/**
 * Many New Posts plugin info
 * @return Array some information about the plugin
 */
function manynewposts_info()
{
  return array(
    "name" => "Many New Posts",
    "description" => "This plugin counts how many new posts have been made after the thread had been view by user.",
    "website" => "https://github.com/jung3o/Many-New-Posts",
    "author" => "Jung Oh",
    "authorsite" => "http://jung3o.com",
    "version" => "2.0.2",
    "compatibility" => "18*",
  );
}

/**
 * Many New Posts plugin enable
 */
function manynewposts_activate()
{
  global $db;
  require_once MYBB_ROOT . "inc/adminfunctions_templates.php";

  $templates = array();
  $templates[] = array(
    "title" => "manynewposts",
    "template" => "<a href='{newposts_link}' class='newposts'>{newposts} new {posts}</a>"
  );
  $templates[] = array(
    "title" => "manynewposts_style",
    "template" => "
<style>
  a.newposts {
    display:inline-block !important;
    text-decoration:none !important;
    font-size:11px !important;
    font-family:sans-serif !important;
    color:#114477 !important;
    background:#ffff99 !important;
    padding:2px 3px !important;
    border:1px solid #ccc !important;
  }
  a.newposts:hover {
    background:#ffff55 !important;
    border:1px solid #666 !important;
  }
</style>"
  );


  foreach($templates as $template)
  {
    $insert = array(
      "title" => $db->escape_string($template['title']),
      "template" => $db->escape_string($template['template']),
      "sid" => "-1",
      "version" => "1600",
      "dateline" => TIME_NOW
    );
    $db->insert_query("templates", $insert);
  }

  find_replace_templatesets("forumdisplay_thread", "#".preg_quote('{$thread[\'multipage\']}')."#i", '{$thread[\'multipage\']}{$thread[\'manynewposts\']}');
  find_replace_templatesets("forumdisplay", "#".preg_quote('{$headerinclude}')."#i", '{$headerinclude}{$manynewposts_style}');

  find_replace_templatesets("search_results_threads_thread", "#".preg_quote('{$thread[\'multipage\']}')."#i", '{$thread[\'multipage\']}{$thread[\'manynewposts\']}');
  find_replace_templatesets("search_results_threads", "#".preg_quote('{$headerinclude}')."#i", '{$headerinclude}{$manynewposts_style}');
}

/**
 * Removes the plugin
 */
function manynewposts_deactivate()
{
  global $db;
  require_once MYBB_ROOT . "inc/adminfunctions_templates.php";

  $templates = array(
    "manynewposts",
    "manynewposts_style"
  );
  $templates = "'" . implode("','", $templates) . "'";
  $db->delete_query("templates", "title IN ({$templates})");

  find_replace_templatesets("forumdisplay_thread", "#".preg_quote('{$thread[\'manynewposts\']}')."#i", '', 0);
  find_replace_templatesets("forumdisplay", "#".preg_quote('{$manynewposts_style}')."#i", '', 0);

  find_replace_templatesets("search_results_threads_thread", "#".preg_quote('{$thread[\'manynewposts\']}')."#i", '', 0);
  find_replace_templatesets("search_results_threads", "#".preg_quote('{$manynewposts_style}')."#i", '', 0);
}

/**
 * Shows the new post present
 */
function manynewposts_thread() {
  global $thread, $templates, $db, $mybb, $settings;

  $query = $db->query("
    SELECT
      dateline, pid
    FROM " . TABLE_PREFIX . "posts
    WHERE
      tid='".$db->escape_string($thread['tid'])."'
    ");

  $lastpost = array();
  $lastpost_pid = array();

  while($result=$db->fetch_array($query))
  {
    $lastpost[] = $result['dateline'];
    $lastpost_pid[$result['dateline']] = $result['pid'];
  }

  $query = $db->query("
    SELECT
      tr.dateline AS `tr_dateline`,
      fr.dateline AS `fr_dateline`
    FROM " . TABLE_PREFIX . "posts p
    LEFT JOIN
      " . TABLE_PREFIX . "threadsread tr
      ON
        tr.tid = p.tid
    LEFT JOIN
      " . TABLE_PREFIX . "forumsread fr
      ON
        fr.fid = p.fid
    WHERE
      tr.uid='".$db->escape_string($mybb->user['uid'])."' and
      fr.uid='".$db->escape_string($mybb->user['uid'])."'
    ");

  $userlastview = array();
  $userlastview_forum = array();

  while($result=$db->fetch_array($query))
  {
    $userlastview[] = $result['tr_dateline'];
    $userlastview_forum[] = $result['fr_dateline'];
  }

  $userlastview = (count($userlastview) > 0) ? max($userlastview) : 0;
  $userlastview_forum = (count($userlastview_forum) > 0) ? max($userlastview_forum) : 0;

  $timelimit = $userlastview + ($settings['threadreadcut'] * 24 * 60 * 60);
  $newlastpost = array();

  var_dump($thread['subject']);

  if($userlastview && $timelimit > TIME_NOW) {
    foreach ($lastpost as $lastpost_number) {
      if($lastpost_number > $userlastview_forum && $lastpost_number > $userlastview) {
        $newlastpost[] = $lastpost_number;
      }
    }
  }

  $count = count($newlastpost);

  if($count == 0) return;

  $posts = $count == 1 ? 'post':'posts';

  $lastseenpost_pid = $lastpost_pid[min($newlastpost)];
  $manynewposts = "";

  if($mybb->user['uid']) {
    if($count > 0) {
      $manynewposts = $templates->get('manynewposts');
      $manynewposts = str_replace("{newposts_link}", "./showthread.php?tid=".$thread['tid']."&pid=".$lastseenpost_pid."#pid".$lastseenpost_pid, $manynewposts);
      $manynewposts = str_replace("{newposts}", $count, $manynewposts);
      $manynewposts = str_replace("{posts}", $posts, $manynewposts);
    }
  }
  $thread['manynewposts'] = $manynewposts;
}

/**
 * Outputs the css for the plugin
 */
function manynewposts_style() {
  global $manynewposts_style,$templates;

  eval("\$manynewposts_style = \"".$templates->get("manynewposts_style")."\";");
}
