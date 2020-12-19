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
 * @class ForumDatabaseProviderMySQLi
 * @parent ForumDatabaseProvider
 * @access public
*/
class ForumDatabaseProviderMySQLi extends ForumDatabaseProvider
{
    /**
     * Database connection wrapper for PDO.
     *
     * @param string $db_server Forum Software database server.
     * @param string $db_user Forum Software database user.
     * @param string $db_passwd Forum Software database password.
     * @param string $db_name Forum Software database name.
     * @return bool True if connected, false if not.
     */
	public function connect(string $db_server, string $db_user, string $db_password, string $db_name)
	{
		try {
			$driver = new mysqli_driver();
			$driver->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;

			$this->db = @new mysqli($db_server, $db_user, $db_password, $db_name);

			if (!empty($this->db->connect_error))
			{
				$this->DatabaseError($this->db->connect_error);
				return false;
			}

			$this->loaded = true;
		} catch (\Exception $e) {
			$this->DatabaseError($e);
			return false;
		}

		return true;
	}

	/**
	 * Wrapper for MySQLi Query
	 *
	 * @param	string		$query The query we will perform against the database.
	 * @return	resource	Database resource
	 */
	public function query(string $query)
	{
		try {
			$request = $this->db->query($query);
		} catch (\mysqli_sql_exception $e) {
			$this->DatabaseError($e);
			return false;
		}

		return $request;
	}

	/**
	 * Wrapper for MySQLi fetch_assoc.
	 *
	 * @param	resource	$request Database resource
	 * @return	array|bool	The data returned.
	 */
	public function fetch_assoc($request)
	{
		if (empty($request))
			return false;

		try {
			$row = $request->fetch_assoc();
		} catch (\mysqli_sql_exception $e) {
			$this->FSDBError($e);
			return false;
		}

		return $row;
	}

	/**
	 * Wrapper for MySQLi fetch_row.
	 *
	 * @param	resource	$request Database resource
	 * @return	array|bool	The data returned.
	 */
	public function fetch_row($request)
	{
		if (empty($request))
			return false;

		try {
			$row = $request->fetch();
		} catch (\mysqli_sql_exception $e) {
			$this->FSDBError($e);
			return false;
		}

		return $row;
	}

	/**
	 * Wrapper for MySQLi num_rows.
	 *
	 * @param	resource	$request Database resource
	 * @return	int|bool	The number of rows, false if an error occured.
	 */
	public function fetch_num_rows($request)
	{
		if (empty($request))
			return 0;

		try {
			return $request->num_rows;
		} catch (\mysqli_sql_exception $e) {
			$this->FSDBError($e);
			return 0;
		}	
	}

	/**
	 * Wrapper for MySQLi free_result.
	 *
	 * @param	resource	$request Database resource
	 * @return	bool		False if request contained nothing, otehrwise we assume it worked.
	 */
	public function free(&$request)
	{
		if (empty($request))
			return false;

		try {
			$request->free_result();
		} catch (\mysqli_sql_exception $e) {
			$this->FSDBError($e);
		}	

		return true;
	}

	/**
	 * Wrapper for MySQLi real_escape_string.
	 *
	 * @param	string	$string The string we are escaping
	 * @return	string	The string escaped and ready for usage in the database.
	 */
	public function quote($string)
	{
		if (empty($string))
			return false;

		try {
			$escaped = $this->db->real_escape_string($string);
		} catch (\mysqli_sql_exception $e) {
			$this->FSDBError($e);

			// better than nothing?
			return (string) addslashes($string);
		}	

		return (string) $escaped;
	}

}