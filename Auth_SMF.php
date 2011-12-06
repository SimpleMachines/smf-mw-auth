<?php
/*
SMF and MediaWiki Integration
=============================
Author: SleePy (sleepy at simplemachines dot org)
Original Author: Ryan Wagoner (rswagoner at gmail dot com)
Version: 1.13

Copyright
=============================
Copyright © 2011 Simple Machines. All rights reserved.

 Developed by: Simple Machines Forum Project
 Simple Machines
 http://www.simplemachines.org

 Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
	[x] Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimers.
	[x] Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimers in the documentation and/or other materials provided with the distribution.
	[x] Neither the names of Simple Machines Forum, Simple Machines, nor the names of its contributors may be used to endorse or promote products derived from this Software without specific prior written permission.

Place this file in your wiki/extenstions folder. If you
encouter an issue be sure to read the known issues below.

Add to LocalSettings.php
========================
# This requires a user be logged into the wiki to make changes.
$wgGroupPermissions['*']['edit'] = false; // MediaWiki Setting

# If you experience the issue where you appear to be logged in
# eventhough you are logged out then disable the page cache.
#$wgEnableParserCache = false;
#$wgCachePages = false;

# SMF Authentication
# To get started you only need to configure wgSMFPath. 
# The rest of the settings are optional for advanced features.

# Relative path to the forum directory from the wiki
# Do not put a trailing /
# Example: /public_html/forum and /public_html/wiki -> ../forum
$wgSMFPath = "../forum"; 

# Use SMF's login system to automatically log you in/out of the wiki
# This works best if you are using SMF database sessions (default).
# Make sure "Use database driven sessions" is checked in the
# SMF Admin -> Server Settings -> Feature Configuration section
# NOTE: Make sure to configure the wgCookeDomain below
#$wgSMFLogin = true;

# Members in these SMF groups will not be allowed to sign into wiki.
# This is useful for denying access to wiki and a easy anti-spam
# method.  The group ID, which can be found in the url (;group=XXX)
# when viewing the group from the administrator control panel.
#$wgSMFDenyGroupID = array(4);

# Grant members of this SMF group(s) access to the wiki
# NOTE: The wgSMFDenyGroupID group supersedes this.
#wgSMFGroupID = array(2);

# Grant members of this SMF group(s) wiki sysop privileges
# NOTE: These members must be able to login to the wiki
#$wgSMFAdminGroupID = array(1, 3);

# SMF to wiki group translation.  This allows us to assign wiki groups
# to those in certain SMF groups.
#$wgSMFSpecialGroups = array(
#	// SMF Group ID => Wiki group name.
#	5 => 'autoconfirmed',
#);

# THIS MUST BE ADDED.  This prevents direct access to the Auth file.
define('SMF_IN_WIKI', true);

# Load up the extension
require_once "$IP/extensions/Auth_SMF.php";
$wgAuth = new Auth_SMF();

 */
if (!defined('SMF_IN_WIKI'))
	exit('Hacking attempt on SMF...');

error_reporting(E_ALL); // Debug

if(file_exists("$wgSMFPath/Settings.php"))
	require_once("$wgSMFPath/Settings.php");
else
	die('Check to make sure $wgSMFPath is correctly set in LocalSettings.php!');

$smf_settings['boardurl'] = $boardurl;
$smf_settings['cookiename'] = $cookiename;
$smf_settings['db_server'] = $db_server;
$smf_settings['db_name'] = $db_name;
$smf_settings['db_user'] = $db_user;
$smf_settings['db_passwd'] = $db_passwd;
$smf_settings['db_prefix'] = $db_prefix;

/**
 * Check the SMF cookie and automatically log the user into the wiki.
 *
 * @param User $user
 * @return bool
 * @public
 */
function AutoAuthenticateSMF ($initial_user_data, &$user)
{
	global $wgAuth, $smf_settings, $modSettings, $smf_member_id, $user_settings, $ID_MEMBER;

	// As to why we need to do this makes no sense really.
	// Thanks to Norv of SimpleMachines.org for the fix.
	$ID_MEMBER = 0;
	$user = $initial_user_data;

	if (isset($_COOKIE[$smf_settings['cookiename']]))
	{
		$_COOKIE[$smf_settings['cookiename']] = stripslashes($_COOKIE[$smf_settings['cookiename']]);

		// MediaWiki doesn't support PHP4 since 1.6.12, so no check for a security issue is needed.
		list ($ID_MEMBER, $password) = @unserialize($_COOKIE[$smf_settings['cookiename']]);
		$ID_MEMBER = !empty($ID_MEMBER) && strlen($password) > 0 ? (int) $ID_MEMBER : 0;
	}

	// Only load this stuff if the user isn't a guest.
	if ($ID_MEMBER != 0)
	{
		if (empty($_SESSION['user_settings']) || empty($_SESSION['user_settings_time']) || time() > $_SESSION['user_settings_time'] + 900)
		{
			$request = $wgAuth->query("		
				SELECT id_member, member_name, email_address, real_name,
					is_activated, passwd, password_salt,
					id_group, id_post_group, additional_groups
				FROM $smf_settings[db_prefix]members
				WHERE id_member = '{$ID_MEMBER}'
					AND is_activated = 1
				LIMIT 1");

			$user_settings = mysql_fetch_assoc($request);

			$_SESSION['user_settings'] = serialize($user_settings);
			mysql_free_result($request);
		}
		else
			$user_settings = unserialize($_SESSION['user_settings']);

		// Did we find 'im?  If not, junk it.
		if (!empty($user_settings))
		{
			// SHA-1 passwords should be 40 characters long.
			if (strlen($password) == 40)
				$check = sha1($user_settings['passwd'] . $user_settings['password_salt']) == $password;
			else
				$check = false;

			// Wrong password or not activated - either way, you're going nowhere.
			$ID_MEMBER = $check && ($user_settings['is_activated'] == 1 || $user_settings['is_activated'] == 11) ? $user_settings['id_member'] : 0;
		}
		else
			$ID_MEMBER = 0;

		// This just simplifies things further on.
		$user_settings['smf_groups'] = array_merge(array($user_settings['id_group'], $user_settings['id_post_group']), explode(',', $user_settings['additional_groups']));
	}

	// Log out guests or members with invalid cookie passwords.
	if($ID_MEMBER == 0)
	{
		// A bug seems to exist in isLoggedIn when it calls getId.
		// getId appears to try to load user data, which may not exist at this point.
		// Why getId just doesn't return $this->mId, I have no idea.
		$user->doLogout();
		return false;
	}

	// Do we know the SMF member id yet?
	if (empty($smf_member_id))
		$smf_member_id = $user->getOption('smf_member_id');

	// If the username has an underscore or space accept the first registered user.
	if(empty($smf_member_id) && (strpos($user_settings['member_name'], ' ') !== false || strpos($user_settings['member_name'], '_') !== false))
	{
		$request = $wgAuth->query("
			SELECT id_member 
			FROM $smf_settings[db_prefix]members
			WHERE member_name = '" . $user_settings['member_name'] . "'
			ORDER BY date_registered ASC
			LIMIT 1");

		list($id) = mysql_fetch_row($request);
		mysql_free_result($request);

		// Sorry your name was taken already!
		if($id != $ID_MEMBER)
		{
			if($user->isLoggedIn())
				$user->logout();
			return true;
		}
	}

	// Lastly check to see if they are not banned and allowed to login
	if (!$wgAuth->isNotBanned($ID_MEMBER) || !$wgAuth->canLogin())
	{
		if($user->isLoggedIn())
			$user->logout();
		return true;
	}

	// Convert to wiki standards
	$username = ucfirst(str_replace('_', '\'', $user_settings['member_name']));
	// Wiki doesn't allow [] and SMF does, SMF doesn't allow =" and Wiki does.
	// We do it like this so we can reverse it to find the original name if needed.
	$username = strtr($username, array('[' => '=', ']' => '"'));

	// Only poll the database if no session or username mismatch.
	if(!($user->isLoggedIn() && $user->getName() == $username))
	{
       	$user->setId($user->idFromName($username));

		// No ID we need to add this member to the wiki database.
		if ($user->getID() == 0)
		{
			// getID clears out the name set above.
			$user->setName($username);
			$user->setEmail($user_settings['email_address']);
			$user->setRealName($user_settings['real_name']);

			// Let wiki know that their email has been verified.
			$user->mEmailAuthenticated = wfTimestampNow(); 

			// Finally create the user.
			$user->addToDatabase();

			// Don't worry about clearing the cache, the setEmail will do that.
			$user->setOption('smf_member_id', $ID_MEMBER);
			$user->setOption('smf_last_update', time());

			// Some reason addToDatabase doesn't set options.  So we do this manually.
			$user->saveSettings();
		}
	}

	// Do we know the SMF member id yet?
	if (empty($smf_member_id))
		$smf_member_id = $user->getOption('smf_member_id');

	// We have tried all we can, but the data just doesn't match up.
	if (empty($smf_member_id) || $smf_member_id != $ID_MEMBER)
	{
		// TODO: Log errors if the ids don't match?

		if ($user->isLoggedIn())
			$user->logout();	
		return true;
	}

	// Keep their email and real name up to date with SMF
	$last_update = (int) $user->getOption('smf_last_update', 0);

	if (empty($last_update) || time() > ($last_update + 900))
	{
		$user->setEmail($user_settings['email_address']);
		$user->setRealName($user_settings['real_name']);

		// We have some sort of group change.
		$wgAuth->isGroupAllowed($user_settings['member_name'], &$user);
		$wgAuth->setAdminGroup($user, $smf_member_id);

		// Save!
		$user->setOption('smf_last_update', time());
		$user->saveSettings();
	}

	// Go ahead and log 'em in
	$user->setupSession();
	$user->setCookies();

	return true;
}

/**
 * Redirect them to the SMF login page.
 *
 * @param User $user
 * @public
 */
function UserLoginFormSMF (&$user)
{
	smf_sessionSetup();
	smf_redirectWrapper('old_url', 'login');
}

/**
 * Redirect and utilize the SMF logout function.
 * This also destroys the wiki session, preventing issues
 * where wiki still believes a user is logged in.
 *
 * @param User $user
 * @public
 */
function UserLogoutSMF (&$user)
{
	global $wgCookiePrefix, $wgSessionName;

	// Log them out of wiki first.
	$user->doLogout();

	// Destory their session.
	$wgCookiePrefix = strtr($wgCookiePrefix, "=,; +.\"'\\[", "__________");
	$old_session = session_name(isset($wgSessionName) ? $wgSessionName : $wgCookiePrefix . '_session');
	session_destroy();

	// Destroy the cookie!
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);

	// Back to whatever we had (we hope mediawiki).
	session_name($old_session);

	// Now SMFs turn.
	smf_sessionSetup();

	// This means we have no SMF session data or unable to find it.
	if (empty($_SESSION['session_var']))
		return true;

	smf_redirectWrapper('logout_url', 'logout;' . $_SESSION['session_var'] . '=' . $_SESSION['session_value']);
}

/**
 * Redirect and utilize the SMF register function.
 *
 * @public
 */
function UserRegisterSMF (&$template)
{
	smf_sessionSetup();
	smf_redirectWrapper('old_url', 'register');
}

/**
 * Wrapper to configure the SMF session and perform the redirect.
 *
 * @public
 */
function smf_redirectWrapper($session, $action)	{
	global $wgScriptPath, $smf_settings;

	$page = !empty($_GET['returnto']) ? '?title=' . urlencode($_GET['returnto']) . '&' : '?';
	$_SESSION[$session] = 'http://' . $_SERVER['SERVER_NAME'] . $wgScriptPath . '/index.php' . $page . 'board=redirect';

	// Do the actual redirect.
	header ('Location: ' . $smf_settings['boardurl'] . '/index.php?action=' . $action);
	exit();
}

/**
 * If the user has visited the forum during the browser session
 * then load up the exisiting session. Otherwise start a new
 * session that SMF can use.
 *
 * @public
 */
function smf_sessionSetup()
{
	global $wgSessionsInMemcached, $wgCookieDomain, $smf_settings;

	// Clean out the existing session. This should have no affect
	// since we are going to redirct the user to the SMF page.
	@session_write_close();

	// We can guess if wiki is using memcache sessions, so is SMF.
	if ($wgSessionsInMemcached)
		@ini_set('session.save_handler', 'memcache');

	// Why MediaWiki doesn't store the original.
	$old_session = session_name();
	session_name(ini_get('session.name'));

	// Start your engines.
	session_start();

	// Load up the SMF session and set the redirect URL.
	if(isset($_COOKIE[$smf_settings['cookiename']]))
		session_decode($_COOKIE[$smf_settings['cookiename']]);
	// No exisiting session, create one
	else
	{
		// Grab us a unique ID for SMF.
		session_regenerate_id();

		// Needed for SMF checks.
		$_SESSION['rand_code'] = md5(session_id() . rand());
		$_SESSION['USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];

		// Set the cookie.
		$data = serialize(array(0, '', 0));
		setcookie($smf_settings['cookiename'], $data, time() + 3600, '/', $wgCookieDomain, 0);
	}

	// Restore the old session.
	session_name($old_session);
}

// First check if class has already been defined.
if (!class_exists('AuthPlugin'))
	require_once "$IP/includes/AuthPlugin.php";

class Auth_SMF extends AuthPlugin
{
	var $conn = 0;

	/**
	 * Class constructor that will initialize the hooks and database connection.
	 */
	function Auth_SMF()
	{
		global $wgSMFLogin, $wgHooks, $wgDefaultUserOptions;

		// Integrate with SMF login / logout?
		if(isset($wgSMFLogin) && $wgSMFLogin)
		{
			$wgHooks['AutoAuthenticate'][] = 'AutoAuthenticateSMF';
			$wgHooks['UserLoadFromSession'][] = 'AutoAuthenticateSMF';
			$wgHooks['UserLoginForm'][] = 'UserLoginFormSMF';
			$wgHooks['UserLogout'][] = 'UserLogoutSMF';
		}

		// Default some settings we us.
		$wgDefaultUserOptions['smf_member_id'] = 0;
		$wgDefaultUserOptions['smf_last_update'] = 0;

		// Always redirect registration to SMF.
		$wgHooks['UserCreateForm'][] = 'UserRegisterSMF';

		// Connect to the database.
		$this->connect();
	}

	/**
	 * Check whether there exists a user account with the given name.
	 * The name will be normalized to MediaWiki's requirements, so
	 * you might need to munge it (for instance, for lowercase initial
	 * letters).
	 *
	 * @param $username String: username.
	 * @return bool
	 * @public
	 */
	public function userExists($username)
	{
		global $smf_settings, $smf_member_id;

		// Check if we did this already recently.
		if (isset($_SESSION['smf_uE_t'], $_SESSION['smf_uE']) && time() < ($_SESSION['smf_uE'] + 300))
			return $_SESSION['smf_uE'];
		$_SESSION['smf_uE'] = time();

		$username = $this->fixUsername($username);
		$request = $this->query("
			SELECT member_name
			FROM $smf_settings[db_prefix]members
			WHERE id_member = '{$smf_member_id}'
			LIMIT 1");

		list ($user) = mysql_fetch_row($request);
		mysql_free_result($request);

		// Play it safe and double check the match.
		$_SESSION['smf_uE'] = strtolower($user) == strtolower($username) ? true : false;

		return $_SESSION['smf_uE'];
	}

	/**
	 * Check if a username+password pair is a valid login.
	 * The name will be normalized to MediaWiki's requirements, so
	 * you might need to munge it (for instance, for lowercase initial
	 * letters).
	 *
	 * @param $username String: username.
	 * @param $password String: user password.
	 * @return bool
	 * @public
	 */
	public function authenticate($username, $password)
	{
		global $smf_settings, $smf_member_id;

		// No id, you must be unauthorized.
		if ($smf_member_id == 0)
			return false;
	
		$username = $this->fixUsername($username);
		$request = $this->query("
			SELECT member_name, passwd
			FROM $smf_settings[db_prefix]members
			WHERE id_member = '{$smf_member_id}'
				AND is_activated = 1
			LIMIT 1");

		list($member_name, $passwd) = mysql_fetch_row($request);
		mysql_free_result($request);

		$pw = sha1(strtolower($username) . $password);

		// Check for password match, the user is not banned, and the user is allowed.
		if($pw == $passwd && $this->isNotBanned($smf_member_id) && $this->isGroupAllowed($username))
			return true;

		return false;
	}

	/**
	 * Modify options in the login template.
	 *
	 * @param $template UserLoginTemplate object.
	 * @public
	 */
	public function modifyUITemplate(&$template)
	{
		$template->set('usedomain',   false); // We do not want a domain name.
		$template->set('create',      false); // Remove option to create new accounts from the wiki.
		$template->set('useemail',    false); // Disable the mail new password box.
	}

	/**
	 * Set the domain this plugin is supposed to use when authenticating.
	 *
	 * @param $domain String: authentication domain.
	 * @public
	 */
	public function setDomain($domain)
	{
		$this->domain = $domain;
	}

	/**
	 * Check to see if the specific domain is a valid domain.
	 *
	 * @param $domain String: authentication domain.
	 * @return bool
	 * @public
	 */
	public function validDomain( $domain)
	{
		return true;
	}

	/**
	* This allows us to disable properties we don't want to allow users to modify
	* @param $prop String: property to disallow
	* @return bool
	* @public
	*/
	public function allowPropChange($prop)
	{
		if ($prop == 'emailaddress')
			return false;

		return true;
	}

	/**
	 * When a user logs in, optionally fill in preferences and such.
	 * For instance, you might pull the email address or real name from the
	 * external user database.
	 *
	 * The User object is passed by reference so it can be modified; don't
	 * forget the & on your function declaration.
	 *
	 * @param User $user
	 * @public
	 */
	public function updateUser( &$user)
	{
		global $smf_settings, $smf_member_id;

		// No id, you must be unauthorized.
		if ($smf_member_id == 0)
			return false;

		$username = $this->fixUsername($user->getName());
		$request = $this->query("
			SELECT email_address, real_name
			FROM $smf_settings[db_prefix]members
			WHERE id_member = '{$smf_member_id}'
			LIMIT 1");

		while($row = mysql_fetch_assoc($request))
		{
			$user->setRealName($row['real_name']);
			$user->setEmail($row['email_address']);

			$this->setAdminGroup($user);

			$user->setOption('smf_last_update', time());		
			$user->saveSettings();
		}
		mysql_free_result($request);
	
		return true;
	}


	/**
	 * Return true if the wiki should create a new local account automatically
	 * when asked to login a user who doesn't exist locally but does in the
	 * external auth database.
	 *
	 * If you don't automatically create accounts, you must still create
	 * accounts in some way. It's not possible to authenticate without
	 * a local account.
	 *
	 * This is just a question, and shouldn't perform any actions.
	 *
	 * @return bool
	 * @public
	 */
	public function autoCreate()
	{
		return true;
	}

	/**
	 * Can users change their passwords?
	 *
	 * @return bool
	 */
	public function allowPasswordChange()
	{
		global $wgSMFLogin;

		// Only allow password change if not using auto login.
		// Otherwise we would need a bunch of code to rewrite
		// the SMF login cookie with the new password.
		if(isset($wgSMFLogin) && $wgSMFLogin)
			return false;

		return true;
	}

	/**
	 * Update user information in the external authentication database.
	 * Return true if successful.
	 *
	 * @param $user User object.
	 * @return bool
	 * @public
	 */
	public function updateExternalDB($user)
	{
		return true;
	}

	/**
	 * Check to see if external accounts can be created.
	 * Return true if external accounts can be created.
	 * @return bool
	 * @public
	 */
	public function canCreateAccounts()
	{
		return false;
	}

	/**
	 * Add a user to the external authentication database.
	 * Return true if successful.
	 *
	 * @param User $user - only the name should be assumed valid at this point
	 * @param string $password
	 * @param string $email
	 * @param string $realname
	 * @return bool
	 * @public
	 */
	public function addUser($user, $password, $email='', $realname='')
	{
		return true;
	}


	/**
	 * Return true to prevent logins that don't authenticate here from being
	 * checked against the local database's password fields.
	 *
	 * This is just a question, and shouldn't perform any actions.
	 *
	 * @return bool
	 * @public
	 */
	public function strict()
	{
		return true;
	}

	/**
	 * When creating a user account, optionally fill in preferences and such.
	 * For instance, you might pull the email address or real name from the
	 * external user database.
	 *
	 * The User object is passed by reference so it can be modified; don't
	 * forget the & on your function declaration.
	 *
	 * @param $user User object.
	 * @param $autocreate bool True if user is being autocreated on login
	 * @public
	 */
	public function initUser( $user, $autocreate = false)
	{
		global $smf_settings, $smf_member_id;

		// No id, you must be unauthorized.
		if ($smf_member_id == 0)
			return false;

		// Check what time we last did this.
		$last_update = $user->getOption('smf_last_update', 0);
		if (!empty($last_update) && time() > $last_update + 900)
			return true;

		$username = $this->fixUsername($user->getName());
		$request = $this->query("
			SELECT id_member, email_address, real_name
			FROM $smf_settings[db_prefix]members
			WHERE id_member = '{$smf_member_id}'
			LIMIT 1");

		while($row = mysql_fetch_assoc($request))
		{
			$user->setRealName($row[real_name]);
			$user->setEmail($row[email_address]);

			// Let wiki know that their email has been verified.
			$user->mEmailAuthenticated = wfTimestampNow(); 

			$this->setAdminGroup($user);

			$user->setOption('smf_last_update', time());
			$user->saveSettings();
		}	
		mysql_free_result($request);

		return true;
	}

	/**
	 * If you want to munge the case of an account name before the final
	 * check, now is your chance.
	 *
	 * @public
	 */
	public function getCanonicalName($username)
	{
		/**
		 * wiki converts username (john_doe -> John doe)
		 * then getCanonicalName is called
		 * user not in wiki database call userExists
		 * lastly call authenticate
		 */
		return $username;
	}

	/**
	 * The wiki converts underscores to spaces. Attempt to work around this
	 * by checking for both cases. Hopefully we'll only get one match.
	 * Otherwise the first registered SMF account takes priority.
	 *
	 * @public
	 */
	public function fixUsername($username)
	{
		global $smf_settings, $smf_member_id;
		static $fixed_name = '';

		// No space no problem.
		if(strpos($username, ' ') === false)
			return $username;

		// We may have done this once already.
		if (!empty($fixed_name))
			return $fixed_name;

		// Look for either case sorted by date.
		$request = $this->query("
			SELECT member_name 
			FROM $smf_settings[db_prefix]members
			WHERE member_name = '{$username}' 
				OR member_name = '" . strtr($username, array(' ' => '_', '[' => '=', ']' => '"')) . "'
			ORDER BY date_registered ASC
			LIMIT 1");

		list($user) = mysql_fetch_row($request);
		mysql_free_result($request);

		// No result play it safe and return the original.
		$fixed_name = $user;
		return !isset($user) ? $username : $user;
	}

	/**
	 * Check to see if the user is banned partially
	 * restricting their ability to post or login.
	 *
	 * @public
	 */
	public function isNotBanned($id_member)
	{
		global $smf_settings, $smf_member_id;

		// Perhaps we have it cached in the session.
		if (isset($_SESSION['smf_iNB_t'], $_SESSION['smf_iNB']) && time() < ($_SESSION['smf_iNB_t'] + 900))
			return $_SESSION['smf_iNB'] ? true : false;

		$request = $this->query("
			SELECT id_ban
			FROM $smf_settings[db_prefix]ban_items AS i
			LEFT JOIN $smf_settings[db_prefix]ban_groups AS g
				ON (i.id_ban_group = g.id_ban_group)
			WHERE i.id_member = '{$id_member}'
				AND (g.cannot_post = 1 OR g.cannot_login = 1)");

		$banned = mysql_num_rows($request);
		mysql_free_result($request);

		$_SESSION['smf_iNB_t'] = time();
		$_SESSION['smf_iNB'] = $banned ? false : true;

		return $_SESSION['smf_iNB'];
	}

	/**
	 * Check to see if the user is able to login.
	 *
	 * @public
	 */
	public function canLogin()
	{
		global $wgSMFDenyGroupID, $user_settings;

		if (isset($_SESSION['smf_cL_t'], $_SESSION['smf_cL']) && time() < ($_SESSION['smf_cL_t'] + 900))
			return $_SESSION['smf_cL'] ? true : false;

		$_SESSION['smf_iNB_t'] = time();
		$_SESSION['smf_cL'] = true;

		if (!empty($wgSMFDenyGroupID) && array_intersect($user_settings['smf_groups'], $wgSMFDenyGroupID) != array())
			$_SESSION['smf_cL'] = false;

		return $_SESSION['smf_cL'];
	}

	/**
	 * Check to see if the user should have sysop rights.
	 * Either they are an administrator or are in one
	 * of the define groups.
	 *
	 * To save database queries the fixed username is used.
	 *
	 * @public
	 */
	public function setAdminGroup(&$user)
	{
		global $wgSMFAdminGroupID, $user_settings;
		static $already_done = false;

		// Loop prevention.
		if ($already_done)
			return;
		$already_done = true;

		// Check if we did this already recently.
		if (isset($_SESSION['smf_sAG']) && time() < ($_SESSION['smf_sAG'] + 900))
			return;
		$_SESSION['smf_sAG'] = time();

		// Administrator always get admin rights.
		if (!in_array(1, $wgSMFAdminGroupID))
			$wgSMFAdminGroupID[] = 1;

		// Search through all groups, if match give them admin rights.
		if (!empty($wgSMFAdminGroupID) && array_intersect($user_settings['smf_groups'], $wgSMFAdminGroupID) != array())
		{
			if (!in_array("sysop", $user->getEffectiveGroups()))
				$user->addGroup("sysop");

			return;
		}

		// No go! Make sure they are not a sysop.
		if (in_array("sysop", $user->getEffectiveGroups()))
			$user->removeGroup("sysop");
		return;
	}


	/**
	 * Check to see if the user is allowed to log in.
	 * Either they are an administrator or are in one
	 * of the define groups.
	 *
	 * @public
	 */
	public function isGroupAllowed($username, &$user)
	{
		global $wgSMFGroupID, $wgSMFDenyGroupID, $wgSMFSpecialGroups, $user_settings;

		// Check if we did this already recently.
		if (isset($_SESSION['smf_iSA_t'], $_SESSION['smf_iSA']) && time() < ($_SESSION['smf_iSA_t'] + 900))
			return $_SESSION['smf_iSA'];
		$_SESSION['smf_iSA_t'] = time();

		// This allows us to wiki assign groups based on SMF member groups.
		if (!empty($wgSMFSpecialGroups))
		{
			// This is done for speed purposes when working with a large array.
			$temp_groups = explode(',', $user_settings['additional_groups']);

			foreach ($wgSMFSpecialGroups as $smf_group => $wiki_group)
			{ 
				if (in_array($smf_group, $temp_groups) && !in_array($wiki_group, $user->getEffectiveGroups()))
				{
					$user->addGroup($wiki_group);
					$group_added = true;
				}
			}
		}

		// Do they happen to be in a deny group?
		if (!empty($wgSMFDenyGroupID) && array_intersect($user_settings['smf_groups'], $wgSMFDenyGroupID) != array())
			$_SESSION['smf_iSA'] = false;
		// This comes from the group add above.
		elseif (!empty($group_added))
			$_SESSION['smf_iSA'] = true;
		// No limitations.
		elseif (empty($wgSMFGroupID))
			$_SESSION['smf_iSA'] = true;
		// Search through all groups, if match give them admin rights.
		elseif (!empty($wgSMFGroupID) && array_intersect($user_settings['smf_groups'], $wgSMFGroupID) != array())
			$_SESSION['smf_iSA'] = true;
		else
			// No go!
			$_SESSION['smf_iSA'] = false;

		return $_SESSION['smf_iSA'];
	}

	/**
	 * Connect to the database. Use the settings from smf.
	 *
	 * {@source}
	 * @return resource
	 */
	public function connect()
	{
		global $smf_settings;

		// Connect to database.
		$this->conn = @mysql_pconnect($smf_settings['db_server'], $smf_settings['db_user'],
	 		$smf_settings['db_passwd'], true);

		// Check if we are connected to the database.
		if (!$this->conn)
			$this->mysqlerror("SMF was unable to connect to the database.<br />\n");

		// Select database: this assumes the wiki and smf are in the same database.
		$db_selected = @mysql_select_db($smf_settings['db_name'], $this->conn);

		// Check if we were able to select the database.
		if (!$db_selected)
			$this->mysqlerror("SMF was unable to connect to the database.<br />\n");
	}

	/**
	 * Run the query and if applicable display the mysql error.
	 *
	 * @param string $query
	 * @return resource
	 */
	public function query($query)
	{
		$request = mysql_query($query, $this->conn);

		if(!$request)
			$this->mysqlerror('Unable to view external table.');

		return $request;
	}

	/**
	 * Display an error when a mysql error is found.
	 *
	 * @param string $message
	 * @access public
	 */
	public function mysqlerror($message)
	{
		global $wgSMFDebug;

		echo $message . "<br /><br />\n\n";

		// Only if we are debugging.
		if ($wgSMFDebug)
			echo 'mySQL error number: ', mysql_errno(), "<br />\n", 'mySQL error message: ', mysql_error(), "<br /><br />\n\n";

		exit;
	}
}

// Set the default options for a few settings we add.
// These are here as they do not need to be configurable.
$wgDefaultUserOptions['smf_member_id'] = 0;
$wgDefaultUserOptions['smf_last_update'] = 0;
$wgHooks['UserSaveOptions'][] = 'wfProfileSMFID';
$wgHiddenPrefs[] = 'smf_member_id';
$wgHiddenPrefs[] = 'smf_last_update';

// This prevents your SMF member ID from being lost when preferences are saved.
function wfProfileSMFID($user, &$saveOptions)
{
	global $ID_MEMBER;

	// Preserve our member id.
	if (empty($saveOptions['smf_member_id']))
		$saveOptions['smf_member_id'] = $user->mOptionOverrides['smf_member_id'];

	// Still empty, maybe we can save the day.
	if (empty($saveOptions['smf_member_id']) && !empty($ID_MEMBER))
		$saveOptions['smf_member_id'] = (int) $ID_MEMBER;

	// Note: We do not protect smf_last_update from being lost since we disabled
	// changing emails means it would be lost and causes an error.  This way the
	// Auth will restore it on the next page load (ie right after the page save).

	return true;
}
