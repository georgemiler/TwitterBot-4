<?php
/* 
 * Bot Tool For Twitter
 * Version: 1.0
 * Credits: Beto Ayesa (contacto@phpninja.info)
 * FUNCTIONS: Follow Back, Unfollow no followers and Direct Message
 */

/*********** CONFIGURATION ***********/
/* 
 * Create a new app by going to https://dev.twitter.com/apps/new and create a new app with read & write permissions.
 * Once the app is created and you generated your access token, you will have the keys needed for below. 
 */
define('CONSUMER_KEY', 'YOUR_CONSUMER_KEY');
define('CONSUMER_SECRET', 'CONSUMER_SECRET');
define('ACCESS_TOKEN', 'ACCESS_TOKEN');
define('ACCESS_TOKEN_SECRET', 'ACCESS_TOKEN_SECRET');

// Anyone OR any tweet that matches your search, will automatically be followed by you
// For information on Twitter search operands, see 'Search Operators' in https://dev.twitter.com/docs/using-search
// Note: Search queries can be no more then 1000 characters and only the first 100 results/users will be followed
define('SEARCH_QUERY', 'Women OR travel OR Bali OR Thailand OR Brand OR Contest OR #NFB');

// DM (Direct Message) to send to new users that follow you (leave blank to not send anything)
// Note: Message can be no longer then 140 characters (HTTP URLs automatically count as 20 characters and HTTPS as 21 characters)
define("DIRECT_MESSAGE", ""); //Thank you for the follow. ☺ Be happy! ♥

/* DO NOT CHANGE ANYTHING BEYOND THIS LINE UNLESS YOU KNOW WHAT YOUR DOING */

if (!file_exists('/home/utopic/96levels.com/twitterbot/twitteroauth/twitteroauth.php'))
	die('twitteroauth.php not found. The library can be downloaded from https://github.com/abraham/twitteroauth');
	
if (!file_exists('/home/utopic/96levels.com/twitterbot/twitteroauth/OAuth.php'))
	die('OAuth.php not found. The library can be downloaded from https://github.com/abraham/twitteroauth');

require_once '/home/utopic/96levels.com/twitterbot/twitteroauth/twitteroauth.php';

// Prevent script from ending pre-maturely
set_time_limit(0);

// Contains new followers user ids
$newFollows = array();

// Authorize with Twitter
$toa = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_TOKEN_SECRET);

if (!is_object($toa))
	die('TwitterOAuth failed to initialize');

// Verify that were authorized
$toa->get('account/verify_credentials');
if ($toa->http_code != 200)
	die('Unable to authenticate with Twitter');

// Get the last 5000 people we've followed
$friends = $toa->get('friends/ids', array('cursor' => -1));
$friendIds = array();
foreach ($friends->ids as $id) {
	$friendIds[] = $id;
}

$favorites = $toa->get('favorites', array());
$favoritesIds = array();
foreach ($favorites as $fav) {
	$favoritesIds[] = $fav->id;
}
function favoriteTweet($id) {
	global $toa, $newFollows,$favoritesIds;
	echo "Tweet id:".$id;
	// Prevent duplicate requests
	//if (in_array($id, $newFollows))
	// return false;
	if (!in_array($id,$favoritesIds))
		$resp = $toa->post('https://api.twitter.com/1.1/favorites/create.json',array("id" => $id));
	
	return true;
	/*
if (!isset($resp->following))
		return false;
	
	if ($resp->following === true) {
		// Add user id to array to ensure we don't follow it again
		$newFollows[] = $id;
		return true;
	} else
		return false;
*/
}
// Gets the remaining number of hits
function getRateLimit() {
	global $toa;

	$ret = $toa->get('account/rate_limit_status');
	
	return $ret->remaining_hits;
}

// Follow by user id
function followUser($id) {
	global $toa, $newFollows;
	
	// Prevent duplicate requests
	if (in_array($id, $newFollows))
		return false;
	
	$resp = $toa->post('friendships/create', array('user_id' => $id));
	
	if (!isset($resp->following))
		return false;
	
	if ($resp->following === true) {
		// Add user id to array to ensure we don't follow it again
		$newFollows[] = $id;
		return true;
	} else
		return false;
}

// Follows anyone who matches your search query
function followSearch() {
	global $toa, $friendIds;

	$ret = $toa->get('http://search.twitter.com/search.json', array('q' => SEARCH_QUERY, 'rpp' => 100));

	if (is_array($ret->results)) {
		foreach ($ret->results as $result) {
			$from_user = $result->from_user_id;
				favoriteTweet( $result->id);
			// If user isnt alreay being followed -> follow user
			if (!in_array($from_user,$friendIds)) {
				if (getRateLimit() == 0)
					exit();
				
				// Follow user
				followUser($from_user);
			}
		}
	}
	
}

function unfollow_user($Userids)
{
global $toa;

	
	
}
function unfollow_not_followers(){
global $toa, $friendIds;
 $followers = $toa->get('followers/ids', array('cursor' => -1));
    $followerIds = array();
 
    foreach ($followers->ids as $id) {
        $followerIds[] = $id;
    }   
      
      $victims = array();
    for ($i=0;$i<count($friendIds);$i++){
    
        if (!in_array($friendIds[$i],$followerIds))
         $resp = $toa->post('friendships/destroy', array('user_id' => $friendIds[$i]));   
    }

                
        
        }

// Follow all users that you're not following back
function autoFollowBack(){
    global $toa, $friendIds;

    // Get the last 5000 followers
    $followers = $toa->get('followers/ids', array('cursor' => -1));
    $followerIds = array();
 
    foreach ($followers->ids as $id) {
        $followerIds[] = $id;
    }    
	
	foreach ($followerIds as $id) { 
		// If user isnt alreay being followed -> follow user
		if (!in_array($id,$friendIds)) {
			if (getRateLimit() == 0)
				exit(); 
			
			// Follow the user
			if (followUser($id)) {			
				// Send DM to user
				if (DIRECT_MESSAGE)
					$toa->post('direct_messages/new', array('user_id' => $id, 'text' => DIRECT_MESSAGE));
			}
		}
	}
}


if (isset($_GET['action'])) {
	// For calling script externally by going to http://www.example.com/auto-follow.php?action=[search|follow-back]
	if ($_GET['action'] == 'search')
		followSearch();
	else if ($_GET['action'] == 'follow-back')
		autoFollowBack();
} else {
	// For calling script internally via Cron every hour (see blog post for info on how to set it up)
	// Odd hours (1,3,5,etc) will run autoFollowBack() and even hours (0,2,4,etc) will run followSearch()
        autoFollowBack();
	 if (date("d") % 2 == 0)
	  unfollow_not_followers();
	 
	 if (date("G") < 15) followSearch();

	 
}

echo 'Following ' . count($newFollows) . ' new users' . PHP_EOL;
