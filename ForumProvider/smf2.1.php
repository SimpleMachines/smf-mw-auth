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
 * Provides a generic database handler
 *
 * @class	ForumSoftwareProvidersmf21
 * @parent	ForumSoftwareProvidersmf20
 * @access	public
*/
class ForumSoftwareProvidersmf21 extends ForumSoftwareProvidersmf20
{
	/**
	 * Decodes the forum software cookie returning the id and password.
	 *
	 * @return	array	The ID and password.
	*/
	public function decodeCookie()
	{
		return (array) json_decode($_COOKIE[$this->ForumSettings['cookiename']], true);
	}

	/*
	 * Figure out the URL that we need to send the user to in order to perform the requested action.
	 * The forum software should process the action and then return to MediaWiki.
	*/
	public function getRedirectURL(string $action, string $wiki_url, bool $do_return = false)
	{
		$forum_action = $this->$validRedirectActions[$action];

		$forum_url =
			$this->ForumSettings['boardurl']
			. '/index.php?action=' . $forum_action
			. ';return_hash=' . hash_hmac('sha1', $wiki_url, $this->ForumSettings['auth_secret'])
			. ';return_to=' . base64_encode($wiki_url);

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
		if (empty($user) || empty($cookie))
			return false;

		if (!empty($this->ForumSettings['auth_secret']) && empty($this->ForumSettings['cookie_no_auth_secret']))
			return hash_equals(hash_hmac('sha512', $user['passwd'], $this->ForumSettings['auth_secret'] . $user['password_salt']), $cookie['password']);
		else
			return $cookie['password'] === hash('sha512', $user['passwd'] . $user['password_salt']);
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
	public function checkBans(array $member)
	{
		$banned = isset($profile['is_activated']) ? $profile['is_activated'] >= 10 : 0;

		if (empty($banned))
			$banned = $this->__check_basic_ban((int) $member['id_member']);

		if (empty($banned))
			$banned = $this->__check_email_ban((string) $member['email_address']);

		if (empty($banned))
		{
			$ips = array(
				$member['member_ip'],
				$member['member_ip2'],
				$_SERVER['REMOTE_ADDR'],
			);

			// SMF 2.0 only supports IPv4.
			foreach ($ips as $ip)
			{
				$banned = $this->__check_ip_ban($ip, []);

				if ($banned)
					continue;
			}
		}

		return $banned;
	}

	/*
	 * Check if the member is banned in the forum software by checking for the IP address.
	 *
	 * @param	string	$ip The forum members email address.
	 * @return	bool	True if banned, false otherwise.
	*/
	protected function __check_ip_ban(string $ip, array $void)
	{
		$ip_bin = bin2hex(inet_pton($ip));

		$sql = '
			SELECT id_ban
			FROM ' . $this->ForumSettings['db_prefix'] . 'ban_items AS bi
			LEFT JOIN ' . $this->ForumSettings['db_prefix'] . 'ban_groups AS bg
				ON (bi.id_ban_group = bg.id_ban_group)';

		// Postgresql uses ::inet
		if ($this->db->getDbType() === 'postgresql')
			$sql .= '
			WHERE (' . (string) $this->db->quote($ip_bin) . '::inet) BETWEEN bi.ip_low AND bi.ip_high)';
		else
			$sql .= '
			WHERE (unhex(' . (string) $this->db->quote($ip_bin) . ') BETWEEN bi.ip_low AND bi.ip_high)';

		$sql .= '
				AND (bg.cannot_post = 1 OR bg.cannot_login = 1)';

		$request = $this->db->query($sql);
		$banned = (int) $this->db->fetch_assoc($request);
		$this->db->free($request);

		return $banned !== 0;
	}
}