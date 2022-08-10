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
 * @class	ForumDatabaseProvider
 * @parent
 * @access	public
*/
class ForumDatabaseProvider
{
	protected object	$MWlogger;
	protected bool		$loaded = false;
	protected object	$db;
	protected string	$db_type = 'mysql';

	/**
	 * Starts our database handler.
	 * Saves our logger for future use and our database type.
	 *
	 * @param	object	$MWlogger MediaWiki logger object.
	 * @param	string	$db_type The forum software database type.
	 * @return	void	No return is generated.
	*/
	public function __construct(object &$MWlogger, string $db_type)
	{
		$this->MWlogger = &$MWlogger;
		$this->db_type = $db_type === 'postgresql' ? 'postgresql' : 'mysql';
	}

	/**
	 * Checks if we have loaded and connected to a database session for our forum software.
	 *
	 * @return	bool	True if connected, otherwise false.
	*/
	public function isLoaded(): bool
	{
		return $this->loaded;
	}

	/**
	 * The database type.
	 *
	 * @return	string	The database type.
	*/
	public function getDbType(): string
	{
		return $this->db_type;
	}

    /**
     * Database error wrapper.  Sends errors to the MediaWiki logger.
     *
     * @param	Exception|string 	$error The error from the database, typically a exception object.
 	 * @return	void				No return is generated.
    */
	protected function DatabaseError(object $error): void
    {
		if (is_object($error))
			$this->MWlogger->debug(
				'Database Error ({FSDBCODE}): {FSDBERROR}',
				array(
					'FSDBCODE' => $error->getCode(),
					'FSDBERROR' => $error->getMessage()
			));
		else
			$this->MWlogger->debug($error);
    }
}