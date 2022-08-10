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
 * Provides a generic database handler
 *
 * @class	ForumSoftwareProvidersmf20
 * @parent	ForumSoftwareProvider
 * @access	public
*/
class ForumSoftwareProvidersmf20 extends ForumSoftwareProvider
{
	/*
	 * Settings File variables we need to include
	*/
	protected $settingsFileVariables = [
		'cookiename',
		'boardurl',
		'db_prefix',
		'db_type',
		'db_server',
		'db_user',
		'db_passwd',
		'db_name',
		'auth_secret',
		'cookie_no_auth_secret',
		'image_proxy_secret',
		'sourcedir'
	];

	protected $validRedirectActions = [
		'createaccount' => 'register',
		'userlogin' => 'login',
		'userlogout' => 'logout'
	];

	/**
	 * Validate that the configuration file exists and is readable.
	 *
	 * @param	string		$basepath The base path to the forum configuraiton file.  The forum handler will load the appropriate configuraiton file.
	 * @return	bool		True if its valid, false otherwise.
	*/
	public function configurationFileIsValid(string $basepath): bool
	{
		return !empty($basepath) && is_readable($basepath . '/Settings.php');
	}

	/**
	 * Reads the configuration file and loads the data into $ForumSetings for use later..
	 *
	 * @param	string		$basepath The base path to the forum configuraiton file.  The forum handler will load the appropriate configuraiton file.
	 * @return	void|bool	Null if we loaded up data, false if it failed.
	*/
	public function readConfigurationFile(string $basepath)
	{
		foreach ($this->settingsFileVariables as $key)
			global $$key;

		require ($basepath . '/Settings.php');

		// Put these away for later.
		foreach ($this->settingsFileVariables as $key)
			$this->ForumSettings[$key] = !empty($GLOBALS[$key]) ? $GLOBALS[$key] : null;
	}

	/*
	 *	A compatiblity layer for Auth_SMF.php extension settings.
	 *
	 * @return	void	No return is expected.
	*/
	public function compatLegacy(): void
	{
		global $wgSMFPath, $wgSMFDenyGroupID, $wgSMFGroupID, $wgSMFAdminGroupID, $wgSMFSpecialGroups, $wgFSPNameStyle, $wgFSPEnableBanCheck;

		$this->MWlogger->debug('Detected SMF_Auth settings, loading compatibilty layer.');

		$this->ForumSettings['path'] = isset($wgSMFPath) ? $wgSMFPath : '../forum';

		// We only need to load settings that where in Auth_SMF, loadFSSettings handles the standard settings.
		foreach (array(
			'LoginDeniedGroups' => 'wgSMFDenyGroupID',
			'LoginAllowedGroups' => 'wgSMFGroupID',
			'AdminGroups' => 'wgSMFAdminGroupID',
			'SuperAdminGroups' => 'wgSMFAdminGroupID',
			'InterfaceGroups' => 'wgSMFAdminGroupID',
			'SpecialGroups' => 'wgSMFSpecialGroups',
		) as $key => $value)
			$this->ForumSettings[$key] = !empty($$value) ? $$value : $this->ForumSettings[$key];

		// Set the login style to SMF
		if (empty($this->ForumSettings['NameStyle']))
			$this->ForumSettings['NameStyle'] = 'smf';

		// The old Auth_SMF plugin did ban checks, enable it unless specified.
		if (!isset($this->ForumSettings['EnableBanCheck']))
			$this->ForumSettings['EnableBanCheck'] = true;
	}

	/**
	 * Determines if the cookie for the forum software exists.
	 *
	 * @return	bool	True if the forum software cookie exists, false otherwise.
	*/
	public function cookieExists(): bool
	{
		return !empty($_COOKIE[$this->ForumSettings['cookiename']]);
	}

	/**
	 * Decodes the forum software cookie returning the id and password.
	 *
	 * @return	array	The ID and password.
	*/
	public function decodeCookie(): array
	{
		return (array) unserialize($_COOKIE[$this->ForumSettings['cookiename']]);
	}

	/*
	 * Figure out the URL that we need to send the user to in order to perform the requested action.
	 * The forum software should process the action and then return to MediaWiki.
	 *
	 * @param	string	$action The action that MediaWiki took under its special page.
	 * @param	string	$wiki_url The url we should return to once we have completed the action from the forum.
	 * @param	bool	$do_return If we should return to the wiki or not.
	 * @return	string	$forum_url The url to the forum we need to go do.
	*/
	public function getRedirectURL(string $action, string $wiki_url, bool $do_return = false): string
	{
		$forum_action = $this->validRedirectActions[$action];

		$forum_url =
			$this->ForumSettings['boardurl']
			. '/index.php?action=' . $forum_action
			. ';return_hash=' . hash_hmac('sha1', $wiki_url, $this->ForumSettings['image_proxy_secret'])
			. ';return_to=' . urlencode($wiki_url);

		return (string) $forum_url;
	}

	/*
	 * Validate that the cookie has a valid password for the user. This should ensure
	 * that forged cookies or if a password has changed that the cookie rejects the login.
	 *
	 * @param	array	$user The user data.
	 * @param	array	$cookie The cookie data.
	 * @return	bool	True if the cookie is valid, false otherwise.
	*/
	public function cookiePasswordIsValid(array $user, array $cookie): bool
	{
		// 2.0.16 added a hash secret for cookies, preventing forgeries of the cookie, use it if enabled.
		if (!empty($this->ForumSettings['auth_secret']) && empty($this->ForumSettings['cookie_no_auth_secret']))
			return $cookie['password'] === hash_hmac('sha1', sha1($user['passwd'] . $user['password_salt']), $this->ForumSettings['auth_secret']);
		else
			return $cookie['password'] === sha1($user['passwd'] . $user['password_salt']);
	}

	/*
	 * Retrieves the forum member data as defined by the forum.  This typically will use a database
	 * session and query the database for the required information.  The data is returned to the 
	 * main handler to be cached and used.
	 *
	 * @param	int		$id_member The id of the member to load from the database. This is determiend by the cookie.
	 * @return	array	All data from the forum database.
	*/
	public function getForumMember(int $id_member): array
	{
		$result = $this->db->query('
			SELECT
				id_member,
				member_name, email_address, real_name, passwd, password_salt,
				id_group, id_post_group, additional_groups,
				member_ip, member_ip2
			FROM ' . $this->ForumSettings['db_prefix'] . 'members
			WHERE
				id_member = ' . (int) $id_member . '
				AND is_activated = 1
			LIMIT 1');
		$member = $this->db->fetch_assoc($result);
		$this->db->free($result);

		return (array) $member;
	}

	/*
	 * Retrieves the member ID as defined by the forum software.  Typically this is the same as the ID provided
	 * by the cookie but may differ depending on forum softwares.
	 *
	 * @param	array	$member The set of forum member data previsouly returned by getForumMember.  Do not
	 					depend on the data from getForumMember having existed due to caching.
	 * @return	int		The ID of the member from the forum software.
	*/
	public function getMemberID(array $member): int
	{
		return (int) $member['id_member'];
	}

	/*
	 * Retrieves the member display name as defined by the forum software.
	 *
	 * @param	array	$member The set of forum member data previsouly returned by getForumMember.  Do not
	 					depend on the data from getForumMember having existed due to caching.
	 * @return	string	The member display name.
	*/
	public function getMemberName(array $member): string
	{
		return (string) $member['member_name'];
	}

	/*
	 * Retrieves the member groups as defined by the forum software.
	 *
	 * @param	array	$member The set of forum member data previsouly returned by getForumMember.  Do not
	 					depend on the data from getForumMember having existed due to caching.
	 * @return	array	An array of intergers of the forum group ids this member is apart of.
	*/
	public function getMemberGroups(array $member): array
	{
		$groups = array(
			(int) $member['id_group'],
			(int) $member['id_post_group']
		);

		if (!empty($member['additional_groups']))
			$groups = array_merge($groups, array_map('intval', explode(',', $member['additional_groups'])));

		return $groups;
	}

	/*
	 * Retrieves the member email address as defined by the forum software.
	 *
	 * @param	array	$member The set of forum member data previsouly returned by getForumMember.  Do not
	 					depend on the data from getForumMember having existed due to caching.
	 * @return	string	The member email address.
	*/
	public function getMemberEmailAddress(array $member): string
	{
		return (string) $member['email_address'];
	}

	/*
	 * Retrieves the member login name as defined by the forum software.  Typically this is the same as the ID provided
	 * by the cookie but may differ depending on forum softwares.
	 *
	 * @param	array	$member The set of forum member data previsouly returned by getForumMember.  Do not
	 					depend on the data from getForumMember having existed due to caching.
	 * @return	string	The member login name.
	*/
	public function getMemberRealName(array $member): string
	{
		return (string) $member['real_name'];
	}

	/*
	 * SMF Auth used a different option to track for account take overs.  This converts it to the method used
	 * by this extension.
	 *
	 * @param	array	$member The set of forum member data previsouly returned by getForumMember.  Do not
	 *					depend on the data from getForumMember having existed due to caching.
	 * @param	object	$wikiuser The wiki user object provided for updating options.  Note while getOption/setOption
	 *					are deprecated, we still use them here as this legacy option should not be required in the future.
	 * @return	bool	True if we had to convert the setting and update the user, false otherwise.
	*/
	public function legacyUpdateWikiUser(array $member, object $wikiUser): bool
	{
		// Convert the smf_member_id over?
		if ($this->ForumSettings['NameStyle'] == 'smf' && $wikiUser->getOption('forum_member_id', 0) == 0 && $wikiUser->getOption('smf_member_id', 0) != 0)
		{
			$this->MWlogger->debug('SMF Auth conversion to FS Provider. "{OLD}" vs "{NEW}"', array(
				'OLD' => $wikiUser->getOption('forum_member_id', 0),
				'NEW' => $wikiUser->getOption('smf_member_id', 0),
			));

			$wikiUser->setOption('forum_member_id', $member['id_member']);
			$wikiUser->setOption('smf_member_id', 0);
			return true;
		}

		return false;
	}

	/*
	 * Check if the member is banned in the forum software.  If so let the SSO know to prevent them from
	 * logging into the wiki.
	 *
	 * We first check to see if the is_activated is >= 10, which in SMF indicates they are banned.
	 * We then check the bans table to see if their is another ban to validate against incase their profile does not reflect the ban.
	 * We then check to see if their email.  This is used incase the email has changed but the ban rule is not updated.
	 * We then check to see if their IP matches.  This is used to ensure IP bans activate as expected.
	 *
	 * @param	array	$member The set of forum member data previsouly returned by getForumMember.  Do not
	 					depend on the data from getForumMember having existed due to caching.
	 * @return	bool	True if banned, false otherwise.
	*/
	public function checkBans(array $member): bool
	{
		$banned = isset($profile['is_activated']) ? $profile['is_activated'] >= 10 : 0;

		if (empty($banned))
			$banned = $this->__check_basic_ban((int) $member['id_member']);

		if (empty($banned))
			$banned = $this->__check_email_ban((string) $member['email_address']);

		if (empty($banned))
		{
			$ips = array(
				$member['ip'],
				$member['ip2'],
				$_SERVER['REMOTE_ADDR'],
			);

			// SMF 2.0 only supports IPv4.
			foreach ($ips as $ip)
				if (preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', $ip, $ip_parts) == 1)
				{
					$banned = $this->__check_ip_ban($ip, $ip_parts);

					if ($banned)
						continue;
				}
		}

		return $banned;
	}

	/*
	 * Check if the member is banned in the forum software by checking for the basic ban member.
	 *
	 * @param	int		$id_member The id of the member provided by the forum software.
	 * @return	bool	True if banned, false otherwise.
	*/
	protected function __check_basic_ban(int $id_member): bool
	{
		$request = $this->db->query('
			SELECT id_ban
			FROM ' . $this->ForumSettings['db_prefix'] . 'ban_items AS bi
			LEFT JOIN ' . $this->ForumSettings['db_prefix'] . 'ban_groups AS bg
				ON (bi.id_ban_group = bg.id_ban_group)
			WHERE bi.id_member = ' . $id_member . '
				AND (bg.cannot_post = 1 OR bg.cannot_login = 1)');

		$banned = (int) $this->db->fetch_assoc($request);
		$this->db->free($request);

		return $banned !== 0;
	}


	/*
	 * Check if the member is banned in the forum software by checking for the email address.
	 *
	 * @param	string	$email_address The forum members email address.
	 * @return	bool	True if banned, false otherwise.
	*/
	protected function __check_email_ban(string $email_address): bool
	{
		$request = $this->db->query('
			SELECT id_ban
			FROM ' . $this->ForumSettings['db_prefix'] . 'ban_items AS bi
			LEFT JOIN ' . $this->ForumSettings['db_prefix'] . 'ban_groups AS bg
				ON (bi.id_ban_group = bg.id_ban_group)
			WHERE "' . $this->db->quote($email_address) . '" LIKE bi.email_address
				AND (bg.cannot_post = 1 OR bg.cannot_login = 1)');

		$banned = (int) $this->db->fetch_assoc($request);
		$this->db->free($request);

		return $banned !== 0;
	}

	/*
	 * Check if the member is banned in the forum software by checking for the IP address.
	 *
	 * @param	string	$ip The forum members email address.
	 * @param	array	$ip_parts The IP exploded.
	 * @return	bool	True if banned, false otherwise.
	*/
	protected function __check_ip_ban(string $ip, array $ip_parts): bool
	{
		$request = $this->db->query('
			SELECT id_ban
			FROM ' . $this->ForumSettings['db_prefix'] . 'ban_items AS bi
			LEFT JOIN ' . $this->ForumSettings['db_prefix'] . 'ban_groups AS bg
				ON (bi.id_ban_group = bg.id_ban_group)
			WHERE ((' . (int) $ip_parts[1] . ' BETWEEN bi.ip_low1 AND bi.ip_high1)
					AND (' . (int) $ip_parts[2] . ' BETWEEN bi.ip_low2 AND bi.ip_high2)
					AND (' . (int) $ip_parts[3] . ' BETWEEN bi.ip_low3 AND bi.ip_high3)
					AND (' . (int) $ip_parts[4] . ' BETWEEN bi.ip_low4 AND bi.ip_high4))
				AND (bg.cannot_post = 1 OR bg.cannot_login = 1)');

		$banned = (int) $this->db->fetch_assoc($request);
		$this->db->free($request);

		return $banned !== 0;
	}
}