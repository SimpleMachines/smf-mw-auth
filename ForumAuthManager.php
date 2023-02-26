<?php
/**
 * Forum SSO Provider for MediaWiki
 *
 * @package		ForumAuthManager
 * @author		Simple Machines https://www.simplemachines.org
 * @author		SleePy (sleepy@simplemachines.org)
 * @author		Vekseid (vekseid@elliquiy.com)
 * @copyright	2022 Simple Machines
 * @license		BSD https://opensource.org/licenses/BSD-3-Clause
 *     (See LICENCE.md file)
 *
*/

/**
 * This extends MediaWiki's ForumAuthManager and prevents changes to selected fields now
 * managed by the SSO provider.
 *
 * @class	ForumAuthManager
 * @parent	\MediaWiki\Auth\TemporaryPasswordPrimaryAuthenticationProvider
 * @access	public
*/
class ForumAuthManager extends \MediaWiki\Auth\TemporaryPasswordPrimaryAuthenticationProvider
{
	/**
	 * @param array $params
	 *  - emailEnabled: (bool) must be true for the option to email passwords to be present
	 *  - newPasswordExpiry: (int) expiraton time of temporary passwords, in seconds
	 *  - passwordReminderResendTime: (int) cooldown period in hours until a password reminder can
	 *    be sent to the same user again
	 */
	public function __construct( /*array*/ $params = [] ) {
		$loadBalancer = \MediaWiki\MediaWikiServices::getInstance()->getDBLoadBalancer();
		$userOptionsLookup = \MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup();

		parent::__construct( $loadBalancer, $userOptionsLookup, $params );
	}

	/**
	 * @deprecated since 1.37. For extension-defined authentication providers
	 * that were using this method to trigger other work, please override
	 * AbstractAuthenticationProvider::postInitSetup instead. If your extension
	 * was using this to explicitly change the Config of an existing
	 * AuthenticationProvider object, please file a report on phabricator -
	 * there is no non-deprecated way to do this anymore.
	 * @param Config $config
	 */
	public function setConfig( \Config $config )
	{
		parent::setConfig( $config );
	}

	/**
	 * Get password reset data, if any
	 *
	 * @stable to override
	 * @param string $username
	 * @param \stdClass|null $data
	 * @return \stdClass|null { 'hard' => bool, 'msg' => Message }
	 */
	protected function getPasswordResetData( /*string */ $username, $data ): bool
	{
		return false;
	}

	/**
	 * @param string $action
	 * @param array $options
	 *
	 * @return array
	 */
	public function getAuthenticationRequests( $action, array $options ): array
	{
		return [];
	}

	/*
	 * This is implanted just to disable password changes.
	 * Return StatusValue::newGood( 'ignored' ) if you don't support this
	 * AuthenticationRequest type.
	 *
	 * @param AuthenticationRequest $req
	 * @param bool $checkData If false, $req hasn't been loaded from the
	 *  submission so checks on user-submitted fields should be skipped.
	 *  $req->username is considered user-submitted for this purpose, even
	 *  if it cannot be changed via $req->loadFromSubmission.
	 * @return StatusValue
	*/
	public function providerAllowsAuthenticationDataChange(
		\MediaWiki\Auth\AuthenticationRequest $req, /*bool*/ $checkData = true
	)
	{
		$rest = \StatusValue::newGood();
		$rest->setOK(false);
		return $rest;
	}

	/*
	 * This one disables any other properties we need to block
	 * @see AuthManager::allowsPropertyChange()
	 * @param string $property
	 * @return bool
	*/
	public function providerAllowsPropertyChange( /*string*/ $property ): bool
	{
		if (in_array($property, array(
			'realname',
			'emailaddress'
		)))
			return false;
		return true;
	}
}