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
 * @class	ForumSoftwareProvider
 * @parent
 * @access	public
*/
class ForumSoftwareProvider
{
	protected /*object*/	$MWlogger;
	protected /*object*/	$db;
	protected /*array*/		$ForumSettings;

	/**
	 * Starts our forum provider handler.
	 * Saves our logger and database handler for future use.
	 * Saves our Forum Settings for future use
	 *
	 * @param	object		$MWlogger MediaWiki logger object.
	 * @param	object|null	$db The database object, this may be null when it is first created.
	 * @param	array		$ForumSettings The array of settings for the form.
	 * @return	void		No return is generated.
	*/
	public function __construct(object &$MWlogger, &$db, array &$ForumSettings)
	{
		$this->MWlogger = &$MWlogger;
		$this->db = &$db;
		$this->ForumSettings = &$ForumSettings;
	}

	/**
	 * Validate that the configuration file exists and is readable.
	 *
	 * @param	string		$basepath The base path to the forum configuraiton file.  The forum handler will load the appropriate configuraiton file.
	 * @return	bool		True if its valid, false otherwise.
	*/
	public function configurationFileIsValid(string $basepath): bool
	{
		return false;
	}

	/**
	 * Reads the configuration file and loads the data into $ForumSetings for use later.
	 *
	 * @param	string		$basepath The base path to the forum configuraiton file.  The forum handler will load the appropriate configuraiton file.
	 * @return	void|bool	Null if we loaded up data, false if it failed.
	*/
	public function readConfigurationFile(string $basepath)
	{
		return false;
	}

	/**
	 * Determines if the cookie for the forum software exists.
	 *
	 * @return	bool	True if the forum software cookie exists, false otherwise.
	*/
	public function cookieExists(): bool
	{
		return false;
	}

	/**
	 * Decodes the forum software cookie returning the id and password.
	 *
	 * @return	array	The ID and password.
	*/
	public function decodeCookie(): array
	{
		return ['id' => 0, 'password' => ''];
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
		// The base.
		$forum_url =
			$this->ForumSettings['boardurl']
			. '/index.php?action=' . $action;

		return $forum_url;
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
		return false;
	}

	/*
	 * Retrieves the member ID as defined by the forum software.  Typically this is the same as the ID provided
	 * by the cookie but may differ depending on forum softwares.
	 *
	 * @param	array	$member The set of forum member data previsouly returned by getForumMember.  Do not
	 					depend on the data from getForumMember having existed due to caching.
	 * @return	array	All data from the forum database.
	*/
	public function getForumMember(int $id_member): array
	{
		return [];
	}

	/*
	 * Retrieves the member ID as defined by the forum software.  Typically this is the same as the ID provided
	 * by the cookie but may differ depending on forum softwares.
	 *
	 * @param	array		$member The set of forum member data previsouly returned by getForumMember.  Do not
	 						depend on the data from getForumMember having existed due to caching.
	 * @return	array	All data from the forum database.
	*/
	public function getMemberID(array $member): int
	{
		return 0;
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
		return 'Guest';
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
		return [];
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
		return 'guest@example.com';
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
		return 'Guest';
	}

	/*
	 * Check if the member is banned in the forum software.  If so let the SSO know to prevent them from
	 * logging into the wiki.
	 *
	 * @param	array	$member The set of forum member data previsouly returned by getForumMember.  Do not
	 					depend on the data from getForumMember having existed due to caching.
	 * @return	bool	True if banned, false otherwise.
	*/
	public function checkBans(array $member): bool
	{
		return false;
	}
}