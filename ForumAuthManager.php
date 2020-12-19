<?php
/**
 * Forum SSO Provider for MediaWiki
 *
 * @package		ForumAuthManager
 * @author		Simple Machines https://www.simplemachines.org
 * @author		SleePy (sleepy@simplemachines.org)
 * @author		Vekseid (vekseid@elliquiy.com)
 * @copyright	2020 Simple Machines
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
	public function __construct( $params = [] ) {
		parent::__construct( $params );
	}

	public function setConfig( \Config $config ) {
		parent::setConfig( $config );
	}

	protected function getPasswordResetData( $username, $data ) {
		return false;
	}

	public function getAuthenticationRequests( $action, array $options ) {
		return [];
	}

	/*
	 * This is implanted just to disable password changes.
	*/
	public function providerAllowsAuthenticationDataChange(
		\MediaWiki\Auth\AuthenticationRequest $req, $checkData = true
	) {
		$rest = \StatusValue::newGood();
		$rest->setOK(false);
		return $rest;
	}

	/*
	 * This one disables any other properties we need to block
	*/
	public function providerAllowsPropertyChange( $property )
	{
		if (in_array($property, array(
			'realname',
			'emailaddress'
		)))
			return false;
		return true;
	}
}