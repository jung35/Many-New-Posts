<?php
/**
 * Many New Posts 1.0.0

 * Copyright 2012 Jung Oh

 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at

 ** http://www.apache.org/licenses/LICENSE-2.0

 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
**/

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook('forumdisplay_thread', 'manynewposts_thread');
$plugins->add_hook('forumdisplay_thread', 'manynewposts_style');

$plugins->add_hook('search_results_thread', 'manynewposts_thread');
$plugins->add_hook('search_results_thread', 'manynewposts_style');


/** 
Many New Posts PLUGIN info
*/
function manynewposts_info()
{
	return array(
		"name" => "Many New Posts",
		"description" => "This plugin counts how many new posts have been made after the thread had been view by user.",
		"website" => "",
		"author" => "Jung Oh",
		"authorsite" => "http://jung3o.com",
		"version" => "1.0.0",
		"compatibility" => "16*",
		"guid" => "de9ed989b4f83ba2a8283dade7127c37"
	);
}


/** 
Many New Posts PLUGIN install
*/
function manynewposts_install()
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
Many New Posts PLUGIN is_installed
*/
function manynewposts_is_installed()
{
	global $db;
	
	$query = $db->simple_select("templates", "template", "title='manynewposts'");
	$exists = $db->fetch_field($query);

	if($exists) {
		return true;
	}

	return false;
}


/** 
Many New Posts PLUGIN deactivate
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
Many New Posts PLUGIN Forum Display
*/
function manynewposts_thread() {
	global $thread,$templates,$db,$mybb,$settings;

	$lastpost = array();

	$query = $db->simple_select("posts","dateline,fid","tid='".$thread['tid']."'");
	while($result=$db->fetch_array($query))
	{
	   $lastpost[] = $result['dateline'];
	   $getfid = $result['fid'];
	}

	$query = $db->simple_select("threadsread", "dateline", "uid='".$mybb->user['uid']."' AND tid='".$thread['tid']."'");
	$userlastview = $db->fetch_field($query);

	$query = $db->simple_select("forumsread", "dateline", "uid='".$mybb->user['uid']."' AND fid='".$getfid."'");
	$userlastview_forum = $db->fetch_field($query);

	$count = 0;

	$newlastpost = array(0);

	$timelimit = $userlastview + ($settings['threadreadcut'] * 24 * 60 * 60);

	if($userlastview) {
		if($timelimit > time()) {
			foreach ($lastpost as $lastpost_number) {
				if($lastpost_number > $userlastview_forum) {
					if($lastpost_number > $userlastview) {
						$count++;
						$newlastpost[] = $lastpost_number;
					}
				}
			}
		}
	}

	if($count > 0) $count = $count-1;

	if($count == 1) {
		$posts = "post";
	} elseif($count > 1) {
		$posts = "posts";
	}

	$ppp = $mybb->user['ppp'];

	if(!$ppp) $ppp = $mybb->settings['postsperpage'];

	$lastseenpost = min($newlastpost);
	$query = $db->simple_select("posts", "pid", "tid='".$thread['tid']."' AND dateline='".$lastseenpost."'");
	$lastseenpost_pid = $db->fetch_field($query);

	if($mybb->user['uid']) {
			$whichpage = ceil($lastseenpost_pid/$ppp);
			if($count > 0) {
				$manynewposts = $templates->get('manynewposts');
				$manynewposts = str_replace("{newposts_link}", "./showthread.php?tid=".$thread['tid']."&page=".$whichpage."&pid=".$lastseenpost_pid."#pid".$lastseenpost_pid, $manynewposts);
				$manynewposts = str_replace("{newposts}", $count, $manynewposts);
				$manynewposts = str_replace("{posts}", $posts, $manynewposts);
			} else {
				$manynewposts = "";
			}
	} else {
		$manynewposts = "";

	}

	$thread['manynewposts'] = $manynewposts;
}


/**
Many New Posts PLUGIN Forum Display style
*/
function manynewposts_style() {
	global $manynewposts_style,$templates;

	eval("\$manynewposts_style = \"".$templates->get("manynewposts_style")."\";");
}