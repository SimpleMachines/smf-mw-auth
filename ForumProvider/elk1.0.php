<?php
/**
 * Forum SSO Provider for MediaWiki
 *
 * @package		ForumSSOProvider
 * @author		Simple Machines https://www.simplemachines.org
 * @author		SleePy (sleepy@simplemachines.org)
 * @author		Vekseid (vekseid@elliquiy.com)
 * @copyright	2020 Simple Machines
 * @license		BSD https://opensource.org/licenses/BSD-3-Clause
 *     (See LICENCE.md file)
 *
*/

/**
 * Provides a handler for Elk 1.0, which is similar to SMF 2.0.
 *
 * @class	ForumSoftwareProviderelk10
 * @parent	ForumSoftwareProvidersmf20
 * @access	public
*/
class ForumSoftwareProviderelk10 extends ForumSoftwareProvidersmf20
{
	/**
	 * Decodes the forum software cookie returning the id and password.
	 *
	 * @return	array	The ID and password.
	*/
	public function decodeCookie()
	{
		return json_decode($_COOKIE[$this->ForumSettings['cookiename']], true);
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
	public function getRedirectURL(string $action, string $wiki_url, bool $do_return = false)
	{
		$forum_action = $this->validRedirectActions[$action];

		$forum_url =
			$this->ForumSettings['boardurl']
			. '/index.php?action=' . $forum_action;

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
	public function cookiePasswordIsValid(array $user, array $cookie)
	{
		return $cookie['password'] === hash('sha256', $user['passwd'] . $user['password_salt']);
	}

	/*
	 * Check if the member is banned in the forum software.  If so let the SSO know to prevent them from
	 * logging into the wiki.
	 *
	 * @param	array	$member The set of forum member data previsouly returned by getForumMember.  Do not
	 					depend on the data from getForumMember having existed due to caching.
	 * @return	bool	True if banned, false otherwise.
	*/
	public function checkBans(array $member)
	{
		$banned = $this->__check_basic_ban((int) $member['id_member']);
		return false;
	}

	/*
	 * Elk does not need to use the legacy conversion for the MediaWiki SMF_Auth.php
	 *
	 * @param	array	$member The set of forum member data previsouly returned by getForumMember.  Do not
	 *					depend on the data from getForumMember having existed due to caching.
	 * @param	object	$wikiuser The wiki user object provided for updating options.  Note while getOption/setOption
	 *					are deprecated, we still use them here as this legacy option should not be required in the future.
	 * @return	bool	True if we had to convert the setting and update the user, false otherwise.
	*/
	public function legacyUpdateWikiUser(array $member, array $wikiUser)
	{
		return false;
	}
}