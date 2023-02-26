<?php
/**
 * Forum SSO Provider for MediaWiki
 *
 * @package		ForumSSOProvider
 * @author		Simple Machines https://www.simplemachines.org
 * @author		SleePy (sleepy@simplemachines.org)
 * @author		Vekseid (vekseid@elliquiy.com)
 * @copyright	2022 Simple Machines
 * @license		BSD https://opensource.org/licenses/BSD-3-Clause
 *     (See LICENCE.md file)
 *
*/

/**
 * This extends MediaWiki's SessionManager and integrates with a forum software
 * to authenticate a user into MediaWiki.  If a user does not exist in MediaWiki,
 * they will be created.  This will automatically manage selected groups based on
 * a desired configuration of group management.
 *
 * @class	ForumSsoProvider
 * @parent
 * @access	public
*/
class ForumSsoProvider extends \MediaWiki\Session\ImmutableSessionProviderWithCookie
{
	// Forum Session Provider Variables.
	protected $MWlogger;
	protected $db;
	protected $fs;

	// Forum Software Variables.
	protected /*array*/ $ForumCookie = [];
	protected /*array*/ $ForumMember;
	protected /*string*/ $ForumMemberNameCleaned;
	protected /*array*/ $ForumMemberGroups = [];
	protected /*array*/ $ForumSettings = [];
	protected /*string*/ $ForumSoftware;

	// MediaWiki Objects.
	protected $wikiUserInfo;
	protected $wikiMember;
	protected $wikiMemberOptions;
	protected $wikiMemberGroups;
	protected $wikiScriptPath = null;

	// Our caching time for updating forum groups in seconds
	private /*int*/ $update_groups_interval = 900;
	private /*int*/ $forum_member_cache_interval = 900;
	private /*int*/ $banned_check_interval = 300;

	/**
	 * Starts our session handler.  All the work starts here.
	 * Media wiki will complete this and call the method provideSessionInfo later.
	 * This will load up the settings from the forum, validate the cookie and start a database connection.
	 * If the user does not have a valid looking cookie, we don't try to start a database connection.
	 *
	 * @param	array	$params Session parmaters provided by MediaWiki, not used by this extension currently.
	 * @return	void	No return is generated, at this point, execution is returned to MediaWiki and we will continue in provideSessionInfo.
	*/
    public function __construct(array $params = [])
    {
		global $wgForumSessionProviderInstance, $wgScriptPath, $wgSMFLogin, $wgFSPSoftware;

		// Let the parent do its thing.
        parent::__construct($params);

		// We hand this off for later to be used in our static function calls.
		$wgForumSessionProviderInstance = $this;

		// We use MWLogger here as logger seems to get destroyed.  Sets up logging of this extension for debugging purposes.
        $this->MWlogger = \MediaWiki\Logger\LoggerFactory::getInstance('ForumSessionProvider');
		$this->MWlogger->debug('Constructor initialized.');

		// Set our software up.
		$this->ForumSoftware = !empty($wgSMFLogin) && empty($wgFSPSoftware) ? 'smf2.0' : (!empty($wgFSPSoftware) ? $wgFSPSoftware : null);

		// Load our settings.
		$this->wikiScriptPath = $wgScriptPath;
		$this->loadFSSettings();

		// Load up the correct forum software provider.
		$forumClass = 'ForumSoftwareProvider' . str_replace('.', '', $this->ForumSoftware);
		$this->fs = new $forumClass($this->MWlogger, $this->db, $this->ForumSettings);

		// Is this a legacy authentication plugin?.
		if (!empty($wgSMFLogin) && method_exists($this->fs, 'compatLegacy'))
			$this->fs->compatLegacy();

		// Make sure we can find the settings file.
		if ($this->fs->configurationFileIsValid($this->ForumSettings['path']))
		{
			$this->MWlogger->debug('Found Configuration File, attempting to loading.');

			// Read the Settings file in, use this layer to adjust what we need to bring in.
			$this->fs->readConfigurationFile($this->ForumSettings['path']);

			// Read the cookie
			$this->decodeCookie();

			// If we have a valid ID, lets connect to the database.
			if (!empty($this->ForumCookie['id']) && is_integer($this->ForumCookie['id']))
			{
				$this->MWlogger->debug('User detected, attempting to load the database.');

				$this->setupDatabaseProvider();
			}
			else
				$this->MWlogger->debug('No User detected, fall through to MediaWiki.');
		}
		else
		{
			$this->MWlogger->debug('Configuration File missing or not readable. Tried to load at {path}', array('path' =>  $this->ForumSettings['path']));
			$this->MWlogger->warning('Forum Software Integraiton invalid.');
		}
    }

	/**
	 * MediaWiki will call t his when loading a special page.  We only need to grab a few pages
	 * and redirect them to the forum for handling.  These are login, logout and registering a new account.
	 * This just returns to the objecct via $wgForumSessionProviderInstance and calls doRedirect;
	 *
	 * @param	object 		$special The special page called.
	 * @param	string|null	$subPage Subpage string, or null if no subpage was specified
	 * @hook	MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook::onSpecialPageBeforeExecute
	 * @return	void If this matches, we issue a redirect, otherwise we return nothing.
	 */
	public static function onSpecialPageBeforeExecute($special, $subPage): void
	{
		global $wgForumSessionProviderInstance;

		// Ensure its callable.
		if (!is_callable(array($wgForumSessionProviderInstance, 'doRedirect')))
			return;

		// The case of some of these isn't always consistent with what shows up in the url.
		$special_action = strtolower($special->getName());

		// If this is a valid action, let the redirector know.
		if (in_array($special_action, array('createaccount', 'userlogin', 'userlogout')))
			$wgForumSessionProviderInstance->doRedirect($special_action, true);
	}

	/**
	 * Actually do the redirect.  We setup where we are at in the wiki and then ask the forum software
	 * to handle redirecting us.  The forum software is responsible for handling the action and returning
	 * to the proper location in MediaWiki.
	 *
	 * @param	string	$action The action we are calling.
	 * @param	bool $do_return if we should return or not.
	 * @return	void We will be doing a redirect and exiting execution here.
	*/
	public function doRedirect(string $action, bool $do_return = false): void
	{
		global $wgScriptPath;

		// The wiki URL.
		$page = !empty($_GET['returnto']) ? '?title=' . $_GET['returnto'] . '&' : '?';
		$wiki_url = 'http://' . $_SERVER['SERVER_NAME'] . $wgScriptPath . '/index.php' . $page . 'board=redirect';

		// Send this to the forum handler to give us the proper redirect url.
		$redirect_url = $this->fs->getRedirectURL($action, $wiki_url, $do_return);

		// Redirect and leave this.
		header ('Location: ' . $redirect_url);
		exit;
	}

	/**
	 * Sets up the the session for MediaWiki and returns it.
	 * MediaWiki will call this directly when it is ready to load up the user.
	 * This will validate the user is logged into the forum, perform any updates and ban checks,
	 * then let MediaWiki know this session is valid.
	 *
	 * @param	WebRequest			The request information provided by MediaWIki.
	 * @return	SessionInfo|null	A valid session handler is returned if the user is logged in, otherwise null.
	*/
	public function provideSessionInfo(WebRequest $request)
	{
		// Can't do this without a database connection, they are a guest now.
		if (empty($this->db) || empty($this->db->isLoaded()))
		{
			$this->MWlogger->debug('Unable to provide session, database not loaded.');
			return null;
		}
		else
			$this->MWlogger->debug('Database loaded, attempting to load forum member.');

		// Fetch the user.
		$this->ForumMember = $this->getForumMember($request);

		// Can't find this member.
		if (empty($this->ForumMember))
		{
			$this->MWlogger->debug('Member id, {FSID}, not found in forum database', array('FSID' => $this->ForumCookie['id']));
			return null;
		}
		else
			$this->MWlogger->debug('Forum member found, verifying cookie of {FSID}', array('FSID' => $this->ForumCookie['id']));

		// Password not valid?
		if (!$this->fs->cookiePasswordIsValid($this->ForumMember, $this->ForumCookie))
		{
			$this->MWlogger->debug('Member ID, {FSID}, failed to validate password under {USERIP}', array(
				'FSID' => $this->ForumCookie['id'],
				'USERIP' => $_SERVER['REMOTE_ADDR'],
			));
			return null;
		}
		else
			$this->MWlogger->debug('Member found and verified, verifying access.');

		// Cleanup the username.
		$this->ForumMemberNameCleaned = $this->cleanupUserName($this->fs->getMemberName($this->ForumMember));

		// Invalid name?
		if (is_null($this->ForumMemberNameCleaned))
		{
			$this->MWlogger->debug('Invalid username, aborting integraiton.');
			return null;
		}

		// Get all of our Forum Software groups.
		$this->ForumMemberGroups = $this->fs->getMemberGroups($this->ForumMember);

		// Try to access this user.
		$this->MWlogger->debug('Attempting to locate a valid user in MediaWiki or create one if it does not exist');
		$this->wikiUserInfo = \MediaWiki\Session\UserInfo::newFromName($this->ForumMemberNameCleaned, true);
		$this->wikiMember = $this->wikiUserInfo->getUser();

		// If they are not logged in or the username doesnt match.
		if (!($this->wikiMember->isRegistered() && $this->wikiMember->getName() === $this->ForumMemberNameCleaned))
		{
			$this->MWlogger->debug('Attempting to login a mediawiki user, if the user does not exist, this fails silently.');

			$this->wikiMember->setId($this->wikiMember->idFromName($this->ForumMemberNameCleaned));

			// The user doesn't exist yet in the wiki? Create them.
			if ($this->wikiMember->getID() === 0)
			{
				$this->MWlogger->debug('User does not exist, attemtping to create it.');
				$this->createWikiUser();
			}
		}

		// Make sure if we have a id match, its valid.
		if ($this->getUserOption('forum_member_id') !== 0 && $this->getUserOption('forum_member_id') !== $this->ForumCookie['id'])
		{
			$this->MWlogger->debug('Member ID, {FSID}, failed to match forum provider check under {USERIP}', array(
				'FSID' => $this->ForumCookie['id'],
				'USERIP' => $_SERVER['REMOTE_ADDR'],
			));
			return null;
		}
		else
			$this->MWlogger->debug('Forum Provider check validated.');

		// Check the ban status here.
		if ($this->memberIsBannedOnForum())
		{
			$this->MWlogger->debug('Member was matched as banned.');
			return null;
		}

		// Configure all of our groups, but only every 15 minutes.
		if (time() > ((int) $this->getUserOption('forum_last_update_groups') + $this->update_groups_interval))
			$this->updateWikiUserGroups();

		// If any user data has changed, go ahead and update it now.
		$this->updateWikiUser();

		// Denied Login?
		if (
			!empty($this->ForumSettings['LoginDeniedGroups'])
			&& is_array($this->ForumSettings['LoginDeniedGroups'])
			&& array_intersect($this->ForumSettings['LoginDeniedGroups'], $this->ForumMemberGroups) !== array()
		)
		{
			$this->MWlogger->debug('Member was found in Login Deny Groups, rejected...');
			return null;
		}

		// Not apart of a login group?
		$tempGroups = (array) $this->ForumSettings['LoginAllowedGroups'];
		$tempGroups += (array) $this->ForumSettings['AdminGroups'];
		if (!empty($this->ForumSettings['LoginAllowedGroups']) && array_intersect($tempGroups, $this->ForumMemberGroups) === array())
		{
			$this->MWlogger->debug('Member is not apart of any login groups...');
			return null;
		}

		$this->MWlogger->debug('Everything is valid, returning valid session for wiki...');

		// This was in the original code and sessionCookieName is not defined anywhere.
		if ($this->sessionCookieName === null)
		{
			$id = $this->hashToSessionId($this->ForumMemberNameCleaned);
			$persisted = false;
			$forceUse = true;
		}
		else
		{
			$id = $this->getSessionIdFromCookie($request);
			$persisted = $id !== null;
			$forceUse = false;
		}

		// Stand up a new session for MediaWiki.
		return new \MediaWiki\Session\SessionInfo(\MediaWiki\Session\SessionInfo::MAX_PRIORITY, array(
			'provider' => $this,
			'id' => $id,
			'userInfo' => $this->wikiUserInfo,
			'persisted' => $persisted,
			'forceUse' => $forceUse,
		));
	}

	/**
	 * Load up all MediaWiki settings for the Forum Session Provider extension.
	 * This will simply passt them into a localized array for processing later.
	 *
	 * @return	void	No return is expected.
	*/
	private function loadFSSettings(): void
	{
		global $wgFSPPath, $wgFSPDenyGroups, $wgFSPAllowGroups, $wgFSPAdminGroups, $wgFSPSuperGroups, $wgFSPInterfaceGroups, $wgFSPSpecialGroups, $wgFSPNameStyle, $wgFSPEnableBanCheck;

		$this->MWlogger->debug('Loading Forum System Settings.');

		// Some standard settings and if they do not exist, provide a default.
		$this->ForumSettings['path'] = isset($wgFSPPath) ? $wgFSPPath : '../forum';
		$this->ForumSettings['NameStyle'] = !empty($wgFSPNameStyle) ? strtolower($wgFSPNameStyle) : 'default';
		$this->ForumSettings['EnableBanCheck'] = !empty($wgFSPEnableBanCheck) ? true : false;
		$this->ForumSettings['ForumDatabaseProvider'] = !empty($wgFSPDatabaseProvider) ? strtolower($wgFSPDatabaseProvider) : 'mysql';

		// Bring grous in, if they do not exist, default to a empty array.
		foreach (array(
			'LoginDeniedGroups' => 'wgFSPDenyGroups',
			'LoginAllowedGroups' => 'wgFSPAllowGroups',
			'AdminGroups' => 'wgFSPAdminGroups',
			'SuperAdminGroups' => 'wgFSPSuperGroups',
			'InterfaceGroups' => 'wgFSPInterfaceGroups',
			'SpecialGroups' => 'wgFSPSpecialGroups',
		) as $key => $value)
			$this->ForumSettings[$key] = !empty($$value) ? $$value : array();
	}

	/**
	 * Decode the forum software cookies.
	 * We wil handle some basics here then send off to the forum software provider
	 * to do the decoding and reeturn a id and password to be validated later.
	 * We ensure that the ID is a int and password a string.
	 *
	 * @return	void	No return is expected.
	*/
	private function decodeCookie(): void
	{
		// Set the defaults.
		$this->ForumCookie['id'] = 0;
		$this->ForumCookie['password'] = null;

		$this->MWlogger->debug('Loading the cookie using provider: {software}', array('software' => $this->ForumSoftware));

		// No cookie? No luck!
		if (!$this->fs->cookieExists())
		{
			$this->MWlogger->debug('No Cookie present, aborting integration.');
			return;
		}

		// This should validate the cookie and return the id/password.
		list($this->ForumCookie['id'], $this->ForumCookie['password']) = $this->fs->decodeCookie();

		$this->ForumCookie['id'] = (int) $this->ForumCookie['id'];
		$this->ForumCookie['password'] = (string) $this->ForumCookie['password'];

		$this->MWlogger->debug('Read the cookie, possible member ID "{FSID}" found', array('FSID' => $this->ForumCookie['id']));
	}

	/**
	 * Sets up a database connection in the forum software.
	 * If we are using MySQL(i) and have the mysqli class avaiaiable, we use it, otherwise
	 * we simply use the generic PDO handler.
	 * We pass on the logger object handler and the current database type to the class.
	 * Database type should be mysql, mysqli or postgresql.
	 *
	 * @return	void	No return is expected.
	*/
	private function setupDatabaseProvider(): void
	{
		if (
			(!empty($this->ForumSettings['ForumDatabaseProvider']) && $this->ForumSettings['ForumDatabaseProvider'] == 'mysql')
			|| ($this->ForumSettings['db_type'] === 'mysql' && class_exists('mysqli'))
		)
			$databaseClass = 'ForumDatabaseProviderMySQLi';
		else
			$databaseClass = 'ForumDatabaseProviderPDO';

		$this->db = new $databaseClass($this->MWlogger, $this->ForumSettings['db_type']);

		$this->db->connect($this->ForumSettings['db_server'], $this->ForumSettings['db_user'], $this->ForumSettings['db_passwd'], $this->ForumSettings['db_name']);
	}

	/**
	 * Fetch the Forum Member information from the forum software database.
	 * This will attempt to cache this information for future usage to reduce queries against
	 * our forum software database.  This information is cached at the interval provided.
	 *
	 * @return	array	All the data provided by the forum software for this specific member.
	*/
	private function getForumMember(WebRequest $request): array
	{
		// Simple caching?
		try
		{
			if (method_exists(\MediaWiki\MediaWikiServices::getInstance(), 'getLocalServerObjectCache'))
				$cache = \MediaWiki\MediaWikiServices::getInstance()->getLocalServerObjectCache();
		} catch (MWException $e) {
		}

		// Use another caching method.
		if (!is_object($cache))
			$cache = new EmptyBagOStuff();
			
		// See if this queue is in Cache, makeKey uses wiki id, but not member id.
		if (is_object($cache))
			$key = $cache->makeKey(
				'SessionProviders',
				'ForumSessionProvider_' . ((int) $this->ForumCookie['id']) . filemtime(__FILE__)
			);

		// Attempt to retrieve this from the cache.
		$data = $cache->get($key);
		if (!empty($data))
		{
			$this->MWlogger->debug('Found a cached instance of this data, using it');
			$this->ForumMember = (array) $data;
			return (array) $data;
		}

		$this->MWlogger->debug('Querying Forum Provider for member data');

		// Ask the forum software for the information.
		$this->ForumMember = $this->fs->getForumMember((int) $this->ForumCookie['id']);

		// Cache this up.
		if (is_object($cache))
			$cache->set($key, $this->ForumMember, $this->forum_member_cache_interval);

		return $this->ForumMember;
	}

	/**
	 * Cleans up a username to a specific format and returns the cleaned up name for use later.
	 * Methods are:
	 *		smf: Cleans name by replacing characters incompatible in MediaWiki with characters invalid in SMF.
	 *		domain: Validates name matches a standard ASCII character set, rejects them if not.
	 *		default: Validates name matches a usable username by rejecting their name if it contains invalid MediaWiki characters.
	 *
	 * @param	string		$userName The original username from the forum.
	 * @return	string|null	The cleaned name or if invalid null.
	*/
	private function cleanupUserName(string $userName): string
	{
		$this->MWlogger->debug('Cleanup name "{FSNAME}" using method {FSMMETHOD}', array(
			'FSNAME' => $userName,
			'FSMMETHOD' => strtolower($this->ForumSettings['NameStyle']),
		));

		$userName = ucfirst($userName);

		// Does the forum provider have method we want to use.
		if (method_exists($this->fs, 'cleanupUserName'))
		{
			$userName = $this->fs->cleanupUserName($userName);

			// If we told false, we know to fail through, otherwise we will continue on below.
			if ($userName === false)
				return null;
		}

		switch (strtolower($this->ForumSettings['NameStyle']))
		{
			case 'smf':
				// Generally backwards compatible with former SMF/Elkarte Auth plugins.
				$userName = str_replace('_', '\'', $userName);
				$userName = strtr($userName, array('[' => '=', ']' => '"', '|' => '&', '#' => '\\', '{' => '==', '}' => '""', '@' => '&&', ':' => '\\\\'));
				break;
			case 'domain':
				// A more restrictive policy.
				if ($userName !== preg_replace('`[^a-zA-Z0-9 .-]+`i', '', $userName))
					return null;
				break;
			default:
				// Just kick them if they have an unusable username.
				if (preg_match('`[#<>[\]|{}@:]+`', $userName))
					return null;
		}

		$this->MWlogger->debug('Cleanuped name "{FSNAME}"', array('FSNAME' => $userName));

		return $userName;
	}

	/**
	 * Check if the name or email needs updated.  If so, we instruct MediaWiki to save the changes
	 * to MediaWiki.  If we have any legacy checks to make, we ask the forum software provider to
	 * make those.
	 *
	 * @return	void	No return is expected.
	*/
	private function updateWikiUser(): void
	{
		$this->MWlogger->debug('Updating wiki user.');

		$userChanged = false;
		if ($this->wikiMember->getEmail() !== $this->fs->getMemberEmailAddress($this->ForumMember))
		{
			$this->MWlogger->debug('Email Sync Reequired. "{OLD}" vs "{NEW}"', array(
				'OLD' => $this->wikiMember->getEmail(),
				'NEW' => $this->fs->getMemberEmailAddress($this->ForumMember),
			));

			$this->wikiMember->setEmail($this->fs->getMemberEmailAddress($this->ForumMember));
			$this->wikiMember->mEmailAuthenticated = wfTimestampNow();
			$userChanged = true;
		}

		if ($this->wikiMember->getRealName() !== $this->fs->getMemberRealName($this->ForumMember))
		{
			$this->MWlogger->debug('Real Name Sync Reequired. "{OLD}" vs "{NEW}"', array(
				'OLD' => $this->wikiMember->getRealName(),
				'NEW' => $this->fs->getMemberRealName($this->ForumMember),
			));

			$this->wikiMember->setRealName($this->fs->getMemberRealName($this->ForumMember));
			$userChanged = true;
		}

		// Do we have a legacy update to make?
		if (method_exists($this->fs, 'legacyUpdateWikiUser'))
			$userChanged |= $this->fs->legacyUpdateWikiUser($this->ForumMember, $this->wikiMember);

		// No need to save if nothing has happened
		if ($userChanged)
		{
			$this->MWlogger->debug('Saved wiki user changes.');

			$this->setUserOption('forum_last_update_user', time());
			$this->wikiMember->saveSettings();
		}
		else
			$this->MWlogger->debug('No changes to sync.');
	}

	/**
	 * Check if our member needs added or removed from specific groups to update
	 * the members access to MediaWiki.  This uses the standard group settings but also
	 * allows for extending to customized groups or non standard groups.
	 *
	 * @return	void	No return is expected.
	*/
	private function updateWikiUserGroups(): void
	{
		$this->MWlogger->debug('Updating wiki groups...');
		$this->MWlogger->debug('Current Forum Member Groups:' .implode(',', $this->ForumMemberGroups) . '...');
		$this->MWlogger->debug('Current Wiki Effective Groups:' .implode(',', $this->getUserEffectiveGroups()) . '...');

		// Wiki Group Name => Forum Group IDS
		$groupActions = array(
			'sysop' => $this->ForumSettings['AdminGroups'],
			'interface-admin' => $this->ForumSettings['InterfaceGroups'],
			'bureaucrat' => $this->ForumSettings['SuperAdminGroups'],
		);

		// Add in our special groups.
		foreach ($this->ForumSettings['SpecialGroups'] as $fs_group_id => $wiki_group_name)
		{
			// Group didn't exist?
			if (!isset($groupActions[$wiki_group_name]))
				$groupActions[$wiki_group_name] = array();

			// Add the Forum group into the wiki group.
			$groupActions[$wiki_group_name][] = $fs_group_id;
		}

		// Now we are going to check all the groups, ignoring updating if nothing has changed.
        $madeChange = false;
		foreach ($groupActions as $wiki_group_name => $fs_group_ids)
		{
			// No group ids, skip.
			if (empty($fs_group_ids) || $fs_group_ids == array())
			{
				$this->MWlogger->debug('Skipping ' . $wiki_group_name . ' due to no forum mappings...');
				continue;
			}

			// They are in the Forum group but not the wiki group?
			if (
				array_intersect($fs_group_ids, $this->ForumMemberGroups) != array()
				&& !in_array($wiki_group_name, $this->getUserEffectiveGroups())
			)
			{
				$this->MWlogger->debug('Adding ' . $wiki_group_name . ' as member is apart of forum group which grants access (' . implode(',', $fs_group_ids) . ')...');

				$this->wikiMember->addGroup($wiki_group_name);
				$madeChange = true;
			}
			// They are not in the Forum group, but in the wiki group
			elseif (
				array_intersect($fs_group_ids, $this->ForumMemberGroups) == array()
				&& in_array($wiki_group_name, $this->getUserEffectiveGroups())
			)
			{
				$this->MWlogger->debug('Removing ' . $wiki_group_name . ' as member is no longer apart of forum group which grants access (' . implode(',', $fs_group_ids) . ')...');

				$this->wikiMember->removeGroup($wiki_group_name);
				$madeChange = true;
			}
		}

		// No need to save if nothing has happened
		if ($madeChange)
		{
			$this->MWlogger->debug('Saved wiki group changes...');

			$this->setUserOption('forum_last_update_groups', time());
			$this->wikiMember->saveSettings();
		}
	}

	/**
	 * Create our user in MediaWiki.
	 * This also sets a security check to attempt to prevent account takeovers by later on
	 * checking the ids match prior to authorizing the user.
	 *
	 * @return	void	No return is expected.
	*/
	private function createWikiUser(): void
	{
		$this->MWlogger->debug('User does not exist in wiki, creating user...');

		$this->wikiMember->setName($this->ForumMemberNameCleaned);
		$this->wikiMember->setEmail($this->fs->getMemberEmailAddress($this->ForumMember));
		$this->wikiMember->setRealName($this->fs->getMemberRealName($this->ForumMember));
		$this->wikiMember->mEmailAuthenticated = wfTimestampNow();

		$this->wikiMember->addToDatabase();

		// This is so we can validate which wiki members are attributed to which forum members.
		// Could be used used in the future to prevent account takeovers due to account renames.
		$this->setUserOption('forum_member_id', $this->fs->getMemberID($this->ForumMember));

		$this->setUserOption('forum_last_update', time());
		$this->wikiMember->saveSettings();
	}

	/**
	 * Checks if a member is banned on the forum software if enabled.
	 * As this may be extensive or resource intensive, this check is cached.
	 * This is handed off to the forum software provider.
	 *
	 * @return	bool	True if they are banned, false if they are not.
	*/
	private function memberIsBannedOnForum(): bool
	{
		// Disbled ban check?
		if (empty($this->ForumSettings['EnableBanCheck']))
			return false;

		$this->MWlogger->debug('Checking ban status.');

		// Check their ban once every 5 minutes.
		if (!(time() > ((int) $this->getUserOption('forum_last_update_banx') + $this->banned_check_interval)))
		{
			$this->MWlogger->debug('Cached banned status is {BAN}', array('BAN' => $this->getUserOption('forum_last_update_ban') !== 0 ? 'NOT banned' : 'banned'));
			return $this->getUserOption('forum_is_banned', 'bool');
		}

		// Ask the forum if this member is banned.
		$banned = $this->fs->checkBans($this->ForumMember);

		$this->MWlogger->debug('Ban check completed, User is {BAN}', array('BAN' => $banned !== 0 ? 'NOT banned' : 'banned'));

		// Cache this for future hits.
		$this->setUserOption('forum_last_update_ban', time());
		$this->setUserOption('forum_is_banned', $banned, 'boo');

		return $banned;
	}

	/**
	 * Wraps up using the MediaWiki Options to get user options.
	 * It is deprecated to use the methods under the MediaWiki User Instance.
	 * This will create a object handler if needed.  This attempts to use proper methods based off the input type.
	 *
	 * @param	string	$option_name The name of the option we are attempting to load.
	 * @param	string	$type The type the option is.  Either string|s, bool|b or int|i (default).
	 * @return	mixed	If we don't have a member, we return null, otherwise we pass the return to the MediaWiki handler.
	*/
	private function getUserOption(string $option_name, string $type = 'int', $default = 0)
	{
		if (empty($this->wikiMember) || !is_object($this->wikiMember))
		{
			$this->MWlogger->debug('Attempted to call getUserOption prior to User Instance existing');

			return null;
		}

		if (empty($this->wikiMemberOptions) || !is_object($this->wikiMemberOptions))
			$this->wikiMemberOptions = \MediaWiki\MediaWikiServices::getInstance()->getUserOptionsManager();

		if ($type === 'string' || $type === 's')
			return $this->wikiMemberOptions->getOption($this->wikiMember, $option_name, $default);
		if ($type === 'bool' || $type === 'b')
			return $this->wikiMemberOptions->getBoolOption($this->wikiMember, $option_name, $default);
		else
			return $this->wikiMemberOptions->getIntOption($this->wikiMember, $option_name, $default);
	}

	/**
	 * Wraps up using the MediaWiki Options to set user options.
	 * It is deprecated to use the methods under the MediaWiki User Instance.
	 * This will create a object handler if needed.  This attempts to use proper methods based off the input type.
	 *
	 * @param	string	$option_name The name of the option we are attempting to load.
	 * @param	mixed	$value The value we are setting
	 * @param	string	$type The type the option is.  Either string|s, bool|b or int|i (default).
	 * @return	mixed	If we don't have a member, we return null, otherwise we pass the return to the MediaWiki handler.
	*/
	private function setUserOption(string $option_name, $value, string $type = 'int')
	{
		if (empty($this->wikiMember) || !is_object($this->wikiMember))
		{
			$this->MWlogger->debug('Attempted to call setUserOption prior to User Instance existing');

			return null;
		}

		if (empty($this->wikiMemberOptions) || !is_object($this->wikiMemberOptions))
			$this->wikiMemberOptions = \MediaWiki\MediaWikiServices::getInstance()->getUserOptionsManager();

		if ($type === 'string' || $type === 's')
			return $this->wikiMemberOptions->setOption($this->wikiMember, $option_name, (string) $value);
		elseif ($type === 'bool' || $type === 'b')
			return $this->wikiMemberOptions->setOption($this->wikiMember, $option_name, (bool) $value);
		else
			return $this->wikiMemberOptions->setOption($this->wikiMember, $option_name, (int) $value);
	}

	/**
	/**
	 * Wraps up using the MediaWiki User Groups to fetch effective groups.
	 * It is deprecated to use the methods under the MediaWiki User Instance.
	 * This will create a object handler if needed.  This attempts to use proper methods based off the input type.
	 *
	 * @param	string	$option_name The name of the option we are attempting to load.
	 * @param	mixed	$value The value we are setting
	 * @param	string	$type The type the option is.  Either string|s, bool|b or int|i (default).
	 * @return	mixed	If we don't have a member, we return null, otherwise we pass the return to the MediaWiki handler.
	*/
	private function getUserEffectiveGroups(bool $recache = false)
	{
		if (empty($this->wikiMember) || !is_object($this->wikiMember))
		{
			$this->MWlogger->debug('Attempted to call setUserOption prior to User Instance existing');

			return null;
		}

		if (empty($this->wikiMemberGroups) || !is_object($this->wikiMemberGroups))
			$this->wikiMemberGroups = \MediaWiki\MediaWikiServices::getInstance()->getUserGroupManager();

		return $this->wikiMemberGroups->getUserEffectiveGroups(
			$this->wikiMember,
			0 /* protected $queryFlagsUsed = self::READ_NORMAL; public const READ_NORMAL = 0;*/,
			$recache
		);
	}
}