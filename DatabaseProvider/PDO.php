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
 * @class ForumDatabaseProviderPDO
 * @parent ForumDatabaseProvider
 * @access public
*/
class ForumDatabaseProviderPDO extends ForumDatabaseProvider
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
			$type = $this->db_type === 'postgresql' ? 'pgsql' : 'mysql';

			$this->db = new PDO(
				$type . ':host=' . $db_server . ';dbname=' . $db_name,
				$db_user,
				$db_user
			);

			if (!empty($this->db->connect_error))
			{
				throw new ErrorException($this->db->connect_error);
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
	 * Wrapper for PDO Query
	 *
	 * @param	string		$query The query we will perform against the database.
	 * @return	resource	Database resource
	 */
	public function query(string $request)
	{
		try {
			$statement = $this->db->prepare($query);
			$request = $statement->execute();
		} catch (\PDOException $e) {
			$this->DatabaseError($e);
			return false;
		}

		return $request;

	}

	/**
	 * Wrapper for PDO fetch using assoc.
	 *
	 * @param	resource	$request Database resource
	 * @return	array|bool	The data returned.
	 */
	public function fetch_assoc($request)
	{
		if (empty($request))
			return false;

		try {
			$row = $request->fetch(PDO::FETCH_ASSOC);
		} catch (\PDOException $e) {
			$this->FSDBError($e);
			return false;
		}

		return $row;
	}

	/**
	 * Wrapper for PDO fetch using row.
	 *
	 * @param	resource	$request Database resource
	 * @return	array|bool	The data returned.
	 */
	public function fetch_row($request)
	{
		if (empty($request))
			return false;

		try {
			$row = $request->fetch(PDO::FETCH_NUM);
		} catch (\PDOException $e) {
			$this->FSDBError($e);
			return false;
		}

		return $row;
	}

	/**
	 * Wrapper for PDO to fetch the number of rows.
	 *
	 * @param	resource	$request Database resource
	 * @return	int|bool	The number of rows, false if an error occured.
	 */
	public function fetch_num_rows($request)
	{
		if (empty($request))
			return 0;

		try {
			/*
				It should be noted that rowCount may not work on all databases.
				In MySQL at least this works
				References: https://www.php.net/manual/en/pdostatement.rowcount.php#example-1096
				References: https://stackoverflow.com/questions/11305230/alternative-for-mysql-num-rows-using-pdo#comment-32016656
			*/
			if ($this->db_type === 'mysql')
				return $request->rowCount();
			// Performance could be a issue here..
			else
			{
				$allRows = $request->fetchAll(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_COLUMN);
				$rowCount = count($allRows);
				unset($allRows);

				// Reset the counter and allow loops to work again.
				$request->closeCursor();
				$request->execute();

				return $rowCount;
			}
		} catch (\PDOException $e) {
			$this->FSDBError($e);
			return 0;
		}
	}

	/**
	 * Wrapper for PDO closeCursor.
	 *
	 * @param	resource	$request Database resource
	 * @return	bool		False if request contained nothing, otehrwise we assume it worked.
	 */
	public function free(&$request)
	{
		if (empty($request))
			return false;

		try {
			$request->closeCursor();
		} catch (\PDOException $e) {
			$this->FSDBError($e);
		}	

		return true;
	}

	/**
	 * Wrapper for PDO quote.
	 *
	 * @param	string	$string The string we are escaping
	 * @return	string	The string escaped and ready for usage in the database.
	 */
	public function quote($string)
	{
		if (empty($string))
			return false;

		try {
			$escaped = $this->db->quote($string);
		} catch (\PDOException $e) {
			$this->FSDBError($e);

			// better than nothing?
			return (string) addslashes($string);
		}	

		return (string) $escaped;
	}
}