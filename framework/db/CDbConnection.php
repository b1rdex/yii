<?php
/**
 * CDbConnection class file
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @link http://www.yiiframework.com/
 * @copyright 2008-2013 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

/**
 * CDbConnection represents a connection to a database.
 *
 * CDbConnection works together with {@link CDbCommand}, {@link CDbDataReader}
 * and {@link CDbTransaction} to provide data access to various DBMS
 * in a common set of APIs. They are a thin wrapper of the {@link http://www.php.net/manual/en/ref.pdo.php PDO}
 * PHP extension.
 *
 * To establish a connection, set {@link setActive active} to true after
 * specifying {@link connectionString}, {@link username} and {@link password}.
 *
 * The following example shows how to create a CDbConnection instance and establish
 * the actual connection:
 * <pre>
 * $connection=new CDbConnection($dsn,$username,$password);
 * $connection->active=true;
 * </pre>
 *
 * After the DB connection is established, one can execute an SQL statement like the following:
 * <pre>
 * $command=$connection->createCommand($sqlStatement);
 * $command->execute();   // a non-query SQL statement execution
 * // or execute an SQL query and fetch the result set
 * $reader=$command->query();
 *
 * // each $row is an array representing a row of data
 * foreach($reader as $row) ...
 * </pre>
 *
 * One can do prepared SQL execution and bind parameters to the prepared SQL:
 * <pre>
 * $command=$connection->createCommand($sqlStatement);
 * $command->bindParam($name1,$value1);
 * $command->bindParam($name2,$value2);
 * $command->execute();
 * </pre>
 *
 * To use transaction, do like the following:
 * <pre>
 * $transaction=$connection->beginTransaction();
 * try
 * {
 *    $connection->createCommand($sql1)->execute();
 *    $connection->createCommand($sql2)->execute();
 *    //.... other SQL executions
 *    $transaction->commit();
 * }
 * catch(Exception $e)
 * {
 *    $transaction->rollback();
 * }
 * </pre>
 *
 * CDbConnection also provides a set of methods to support setting and querying
 * of certain DBMS attributes, such as {@link getNullConversion nullConversion}.
 *
 * Since CDbConnection implements the interface IApplicationComponent, it can
 * be used as an application component and be configured in application configuration,
 * like the following,
 * <pre>
 * array(
 *     'components'=>array(
 *         'db'=>array(
 *             'class'=>'CDbConnection',
 *             'connectionString'=>'sqlite:path/to/dbfile',
 *         ),
 *     ),
 * )
 * </pre>
 *
 * Use the {@link driverName} property if you want to force the DB connection to use a particular driver
 * by the given name, disregarding of what was set in the {@link connectionString} property. This might
 * be useful when working with ODBC connections. Sample code:
 *
 * <pre>
 * 'db'=>array(
 *     'class'=>'CDbConnection',
 *     'driverName'=>'mysql',
 *     'connectionString'=>'odbc:Driver={MySQL};Server=127.0.0.1;Database=test',
 *     'username'=>'',
 *     'password'=>'',
 * ),
 * </pre>
 *
 * @property bool $active Whether the DB connection is established.
 * @property PDO $pdoInstance The PDO instance, null if the connection is not established yet.
 * @property CDbTransaction|null $currentTransaction The currently active transaction. Null if no active transaction.
 * @property CDbSchema $schema The database schema for the current connection.
 * @property CDbCommandBuilder $commandBuilder The command builder.
 * @property string $lastInsertID The row ID of the last row inserted, or the last value retrieved from the sequence object.
 * @property int $columnCase The case of the column names.
 * @property int $nullConversion How the null and empty strings are converted.
 * @property bool $autoCommit Whether creating or updating a DB record will be automatically committed.
 * @property bool $persistent Whether the connection is persistent or not.
 * @property string $driverName Name of the DB driver. This property is read-write since 1.1.16.
 * Before 1.1.15 it was read-only.
 * @property string $clientVersion The version information of the DB driver.
 * @property string $connectionStatus The status of the connection.
 * @property bool $prefetch Whether the connection performs data prefetching.
 * @property string $serverInfo The information of DBMS server.
 * @property string $serverVersion The version information of DBMS server.
 * @property int $timeout Timeout settings for the connection.
 * @property array $attributes Attributes (name=>value) that are previously explicitly set for the DB connection.
 * @property array $stats The first element indicates the number of SQL statements executed,
 * and the second element the total time spent in SQL execution.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @package system.db
 * @since 1.0
 */
class CDbConnection extends CApplicationComponent
{
	/**
	 * @var string The Data Source Name, or DSN, contains the information required to connect to the database.
	 * @see http://www.php.net/manual/en/function.PDO-construct.php
	 *
	 * Note that if you're using GBK or BIG5 then it's highly recommended to
	 * update to PHP 5.3.6+ and to specify charset via DSN like
	 * 'mysql:dbname=mydatabase;host=127.0.0.1;charset=GBK;'.
	 */
	public $connectionString;
	/**
	 * @var string the username for establishing DB connection. Defaults to empty string.
	 */
	public $username='';
	/**
	 * @var string the password for establishing DB connection. Defaults to empty string.
	 */
	public $password='';
	/**
	 * @var int number of seconds that table metadata can remain valid in cache.
	 * Use 0 or negative value to indicate not caching schema.
	 * If greater than 0 and the primary cache is enabled, the table metadata will be cached.
	 * @see schemaCachingExclude
	 */
	public $schemaCachingDuration=0;
	/**
	 * @var array list of tables whose metadata should NOT be cached. Defaults to empty array.
	 * @see schemaCachingDuration
	 */
	public $schemaCachingExclude=array();
	/**
	 * @var string the ID of the cache application component that is used to cache the table metadata.
	 * Defaults to 'cache' which refers to the primary cache application component.
	 * Set this property to false if you want to disable caching table metadata.
	 */
	public $schemaCacheID='cache';
	/**
	 * @var int number of seconds that query results can remain valid in cache.
	 * Use 0 or negative value to indicate not caching query results (the default behavior).
	 *
	 * In order to enable query caching, this property must be a positive
	 * integer and {@link queryCacheID} must point to a valid cache component ID.
	 *
	 * The method {@link cache()} is provided as a convenient way of setting this property
	 * and {@link queryCachingDependency} on the fly.
	 *
	 * @see cache
	 * @see queryCachingDependency
	 * @see queryCacheID
	 * @since 1.1.7
	 */
	public $queryCachingDuration=0;
	/**
	 * @var CCacheDependency|ICacheDependency the dependency that will be used when saving query results into cache.
	 * @see queryCachingDuration
	 * @since 1.1.7
	 */
	public $queryCachingDependency;
	/**
	 * @var int the number of SQL statements that need to be cached next.
	 * If this is 0, then even if query caching is enabled, no query will be cached.
	 * Note that each time after executing a SQL statement (whether executed on DB server or fetched from
	 * query cache), this property will be reduced by 1 until 0.
	 * @since 1.1.7
	 */
	public $queryCachingCount=0;
	/**
	 * @var string the ID of the cache application component that is used for query caching.
	 * Defaults to 'cache' which refers to the primary cache application component.
	 * Set this property to false if you want to disable query caching.
	 * @since 1.1.7
	 */
	public $queryCacheID='cache';
	/**
	 * @var bool whether the database connection should be automatically established
	 * the component is being initialized. Defaults to true. Note, this property is only
	 * effective when the CDbConnection object is used as an application component.
	 */
	public $autoConnect=true;
	/**
	 * @var string the charset used for database connection. The property is only used
	 * for MySQL, MariaDB and PostgreSQL databases. Defaults to null, meaning using default charset
	 * as specified by the database.
	 *
	 * Note that if you're using GBK or BIG5 then it's highly recommended to
	 * update to PHP 5.3.6+ and to specify charset via DSN like
	 * 'mysql:dbname=mydatabase;host=127.0.0.1;charset=GBK;'.
	 */
	public $charset;
	/**
	 * @var bool whether to turn on prepare emulation. Defaults to false, meaning PDO
	 * will use the native prepare support if available. For some databases (such as MySQL),
	 * this may need to be set true so that PDO can emulate the prepare support to bypass
	 * the buggy native prepare support. Note, this property is only effective for PHP 5.1.3 or above.
	 * The default value is null, which will not change the ATTR_EMULATE_PREPARES value of PDO.
	 */
	public $emulatePrepare;
	/**
	 * @var bool whether to log the values that are bound to a prepare SQL statement.
	 * Defaults to false. During development, you may consider setting this property to true
	 * so that parameter values bound to SQL statements are logged for debugging purpose.
	 * You should be aware that logging parameter values could be expensive and have significant
	 * impact on the performance of your application.
	 */
	public $enableParamLogging=false;
	/**
	 * @var bool whether to enable profiling the SQL statements being executed.
	 * Defaults to false. This should be mainly enabled and used during development
	 * to find out the bottleneck of SQL executions.
	 */
	public $enableProfiling=false;
	/**
	 * @var string the default prefix for table names. Defaults to null, meaning no table prefix.
	 * By setting this property, any token like '{{tableName}}' in {@link CDbCommand::text} will
	 * be replaced by 'prefixTableName', where 'prefix' refers to this property value.
	 * @since 1.1.0
	 */
	public $tablePrefix;
	/**
	 * @var array list of SQL statements that should be executed right after the DB connection is established.
	 * @since 1.1.1
	 */
	public $initSQLs;
	/**
	 * @var array mapping between PDO driver and schema class name.
	 * A schema class can be specified using path alias.
	 * @since 1.1.6
	 */
	public $driverMap=array(
		'cubrid'=>'CCubridSchema',  // CUBRID
		'pgsql'=>'CPgsqlSchema',    // PostgreSQL
		'mysqli'=>'CMysqlSchema',   // MySQL
		'mysql'=>'CMysqlSchema',    // MySQL,MariaDB
		'sqlite'=>'CSqliteSchema',  // sqlite 3
		'sqlite2'=>'CSqliteSchema', // sqlite 2
		'mssql'=>'CMssqlSchema',    // Mssql driver on windows hosts
		'dblib'=>'CMssqlSchema',    // dblib drivers on linux (and maybe others os) hosts
		'sqlsrv'=>'CMssqlSchema',   // Mssql
		'oci'=>'COciSchema',        // Oracle driver
	);

	/**
	 * @var string Custom PDO wrapper class.
	 * @since 1.1.8
	 */
	public $pdoClass = 'PDO';

	/** @var string|null */
	protected $_driverName;
	protected $_attributes=array();
	protected $_active=false;
	/** @var \PDO|null */
	protected $_pdo;
	/** @var \CDbTransaction|null */
	protected $_transaction;
	/** @var \CDbSchema|null */
	protected $_schema;

	/**
	 * Constructor.
	 * Note, the DB connection is not established when this connection
	 * instance is created. Set {@link setActive active} property to true
	 * to establish the connection.
	 * @param string $dsn The Data Source Name, or DSN, contains the information required to connect to the database.
	 * @param string $username The user name for the DSN string.
	 * @param string $password The password for the DSN string.
	 * @see http://www.php.net/manual/en/function.PDO-construct.php
	 */
	public function __construct($dsn='',$username='',$password='')
	{
		$this->connectionString=$dsn;
		$this->username=$username;
		$this->password=$password;
	}

	/**
	 * Close the connection when serializing.
	 *
	 * @return string[]
	 *
	 * @throws \LogicException if there is transaction active
	 */
	public function __sleep()
	{
		if ($this->getCurrentTransaction() !== null) {
			throw new LogicException('You can\'t serialize this connection, because it has running transaction');
		}

		$this->close();

		return array_keys(get_object_vars($this));
	}

	/**
	 * Returns a list of available PDO drivers.
	 * @return array list of available PDO drivers
	 * @see http://www.php.net/manual/en/function.PDO-getAvailableDrivers.php
	 */
	public static function getAvailableDrivers()
	{
		return PDO::getAvailableDrivers();
	}

	/**
	 * Initializes the component.
	 * This method is required by {@link IApplicationComponent} and is invoked by application
	 * when the CDbConnection is used as an application component.
	 * If you override this method, make sure to call the parent implementation
	 * so that the component can be marked as initialized.
	 *
	 * @return void
	 */
	public function init()
	{
		parent::init();
		if($this->autoConnect)
			$this->setActive(true);
	}

	/**
	 * Returns whether the DB connection is established.
	 * @return bool whether the DB connection is established
	 */
	public function getActive()
	{
		return $this->_active;
	}

	/**
	 * Open or close the DB connection.
	 *
	 * @param bool $value whether to open or close DB connection
	 *
	 * @throws CException if connection fails
	 *
	 * @return void
	 */
	public function setActive($value)
	{
		if($value!=$this->_active)
		{
			if($value)
				$this->open();
			else
				$this->close();
		}
	}

	/**
	 * Sets the parameters about query caching.
	 * This method can be used to enable or disable query caching.
	 * By setting the $duration parameter to be 0, the query caching will be disabled.
	 * Otherwise, query results of the new SQL statements executed next will be saved in cache
	 * and remain valid for the specified duration.
	 * If the same query is executed again, the result may be fetched from cache directly
	 * without actually executing the SQL statement.
	 * @param int $duration the number of seconds that query results may remain valid in cache.
	 * If this is 0, the caching will be disabled.
	 * @param CCacheDependency|ICacheDependency $dependency the dependency that will be used when saving
	 * the query results into cache.
	 * @param int $queryCount number of SQL queries that need to be cached after calling this method. Defaults to 1,
	 * meaning that the next SQL query will be cached.
	 * @return static the connection instance itself.
	 * @since 1.1.7
	 */
	public function cache($duration, $dependency=null, $queryCount=1)
	{
		$this->queryCachingDuration=$duration;
		$this->queryCachingDependency=$dependency;
		$this->queryCachingCount=$queryCount;
		return $this;
	}

	/**
	 * Opens DB connection if it is currently not
	 *
	 * @throws CException if connection fails
	 *
	 * @return void
	 */
	protected function open()
	{
		if($this->_pdo===null)
		{
			if(empty($this->connectionString))
				throw new CDbException('CDbConnection.connectionString cannot be empty.');
			try
			{
				Yii::trace('Opening DB connection','system.db.CDbConnection');
				$this->_pdo=$this->createPdoInstance();
				$this->initConnection($this->_pdo);
				$this->_active=true;
			}
			catch(PDOException $e)
			{
				if(YII_DEBUG)
				{
					throw new CDbException('CDbConnection failed to open the DB connection: '.
						$e->getMessage(),(int)$e->getCode(),$e->errorInfo);
				}
				else
				{
					Yii::log($e->getMessage(),CLogger::LEVEL_ERROR,'exception.CDbException');
					throw new CDbException('CDbConnection failed to open the DB connection.',(int)$e->getCode(),$e->errorInfo);
				}
			}
		}
	}

	/**
	 * Closes the currently active DB connection.
	 * It does nothing if the connection is already closed.
	 *
	 * @return void
	 */
	protected function close()
	{
		Yii::trace('Closing DB connection','system.db.CDbConnection');
		$this->_pdo=null;
		$this->_active=false;
		$this->_schema=null;
	}

	/**
	 * Creates the PDO instance.
	 * When some functionalities are missing in the pdo driver, we may use
	 * an adapter class to provide them.
	 * @throws CDbException when failed to open DB connection
	 * @return PDO the pdo instance
	 */
	protected function createPdoInstance()
	{
		$pdoClass=$this->pdoClass;
		if(($driver=$this->getDriverName())!==null)
		{
			if($driver==='mssql' || $driver==='dblib')
				$pdoClass='CMssqlPdoAdapter';
			elseif($driver==='sqlsrv')
				$pdoClass='CMssqlSqlsrvPdoAdapter';
		}

		if(!class_exists($pdoClass))
			throw new CDbException(Yii::t('yii','CDbConnection is unable to find PDO class "{className}". Make sure PDO is installed correctly.',
				array('{className}'=>$pdoClass)));

		@$instance=new $pdoClass($this->connectionString,$this->username,$this->password,$this->_attributes);

		if(!$instance)
			throw new CDbException(Yii::t('yii','CDbConnection failed to open the DB connection.'));

		return $instance;
	}

	/**
	 * Initializes the open db connection.
	 * This method is invoked right after the db connection is established.
	 * The default implementation is to set the charset for MySQL, MariaDB and PostgreSQL database connections.
	 *
	 * @param PDO $pdo the PDO instance
	 *
	 * @return void
	 */
	protected function initConnection($pdo)
	{
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		if($this->emulatePrepare!==null && constant('PDO::ATTR_EMULATE_PREPARES'))
			$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,$this->emulatePrepare);
		if($this->charset!==null)
		{
			$driver=strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
			if(in_array($driver,array('pgsql','mysql','mysqli')))
				$pdo->exec('SET NAMES '.$pdo->quote($this->charset));
		}
		if($this->initSQLs!==null)
		{
			foreach($this->initSQLs as $sql)
				$pdo->exec($sql);
		}
	}

	/**
	 * Returns the PDO instance.
	 *
	 * @return PDO|null the PDO instance, null if the connection is not established yet
	 */
	public function getPdoInstance()
	{
		return $this->_pdo;
	}

	/**
	 * Creates a command for execution.
	 * @param string|array|null $query the DB query to be executed. This can be either a string representing a SQL statement,
	 * or an array representing different fragments of a SQL statement. Please refer to {@link CDbCommand::__construct}
	 * for more details about how to pass an array as the query. If this parameter is not given,
	 * you will have to call query builder methods of {@link CDbCommand} to build the DB query.
	 * @return CDbCommand the DB command
	 */
	public function createCommand($query=null)
	{
		$this->setActive(true);
		return new CDbCommand($this,$query);
	}

	/**
	 * Returns the currently active transaction.
	 * @return CDbTransaction|null the currently active transaction. Null if no active transaction.
	 */
	public function getCurrentTransaction()
	{
		if($this->_transaction!==null)
		{
			if($this->_transaction->getActive())
				return $this->_transaction;
		}
		return null;
	}

	/**
	 * Starts a transaction.
	 * @return CDbTransaction the transaction initiated
	 */
	public function beginTransaction()
	{
		Yii::trace('Starting transaction','system.db.CDbConnection');
		$this->setActive(true);
		$this->_pdo->beginTransaction();
		return $this->_transaction=new CDbTransaction($this);
	}

	/**
	 * Returns the database schema for the current connection
	 * @throws CDbException if CDbConnection does not support reading schema for specified database driver
	 * @return CDbSchema the database schema for the current connection
	 */
	public function getSchema()
	{
		if($this->_schema!==null)
			return $this->_schema;
		else
		{
			$driver=$this->getDriverName();
			if(isset($this->driverMap[$driver]))
				return $this->_schema=Yii::createComponent($this->driverMap[$driver], $this);
			else
				throw new CDbException(Yii::t('yii','CDbConnection does not support reading schema for {driver} database.',
					array('{driver}'=>$driver)));
		}
	}

	/**
	 * Returns the SQL command builder for the current DB connection.
	 * @return CDbCommandBuilder the command builder
	 */
	public function getCommandBuilder()
	{
		return $this->getSchema()->getCommandBuilder();
	}

	/**
	 * Returns the ID of the last inserted row or sequence value.
	 * @param string $sequenceName name of the sequence object (required by some DBMS)
	 * @return string the row ID of the last row inserted, or the last value retrieved from the sequence object
	 * @see http://www.php.net/manual/en/function.PDO-lastInsertId.php
	 */
	public function getLastInsertID($sequenceName='')
	{
		$this->setActive(true);
		return $this->_pdo->lastInsertId($sequenceName);
	}

	/**
	 * Quotes a string value for use in a query.
	 *
	 * @param float|int|string $str string to be quoted
	 *
	 * @return float|int|string the properly quoted string
	 *
	 * @see http://www.php.net/manual/en/function.PDO-quote.php
	 */
	public function quoteValue($str)
	{
		if(is_int($str) || is_float($str))
			return $str;

		$this->setActive(true);
		if(($value=$this->_pdo->quote($str))!==false)
			return $value;
		else  // the driver doesn't support quote (e.g. oci)
			return "'" . addcslashes(str_replace("'", "''", $str), "\000\n\r\\\032") . "'";
	}

	/**
	 * Quotes a value for use in a query using a given type.
	 * @param mixed $value the value to be quoted.
	 * @param int $type The type to be used for quoting.
	 * This should be one of the `PDO::PARAM_*` constants described in
	 * {@link http://www.php.net/manual/en/pdo.constants.php PDO documentation}.
	 * This parameter will be passed to the `PDO::quote()` function.
	 * @return string the properly quoted string.
	 * @see http://www.php.net/manual/en/function.PDO-quote.php
     * @since 1.1.18
	 */
	public function quoteValueWithType($value, $type)
	{
		$this->setActive(true);
		if(($quoted=$this->_pdo->quote($value, $type))!==false)
			return $quoted;
		else  // the driver doesn't support quote (e.g. oci)
			return "'" . addcslashes(str_replace("'", "''", $value), "\000\n\r\\\032") . "'";
	}

	/**
	 * Quotes a table name for use in a query.
	 * If the table name contains schema prefix, the prefix will also be properly quoted.
	 * @param string $name table name
	 * @return string the properly quoted table name
	 */
	public function quoteTableName($name)
	{
		return $this->getSchema()->quoteTableName($name);
	}

	/**
	 * Quotes a column name for use in a query.
	 * If the column name contains prefix, the prefix will also be properly quoted.
	 * @param string $name column name
	 * @return string the properly quoted column name
	 */
	public function quoteColumnName($name)
	{
		return $this->getSchema()->quoteColumnName($name);
	}

	/**
	 * Determines the PDO type for the specified PHP type.
	 * @param string $type The PHP type (obtained by gettype() call).
	 * @return int the corresponding PDO type
	 */
	public function getPdoType($type)
	{
		static $map=array
		(
			'boolean'=>PDO::PARAM_BOOL,
			'integer'=>PDO::PARAM_INT,
			'string'=>PDO::PARAM_STR,
			'resource'=>PDO::PARAM_LOB,
			'NULL'=>PDO::PARAM_NULL,
		);
		return isset($map[$type]) ? $map[$type] : PDO::PARAM_STR;
	}

	/**
	 * Returns the case of the column names
	 * @return int|null the case of the column names
	 * @see http://www.php.net/manual/en/pdo.setattribute.php
	 */
	public function getColumnCase()
	{
		return $this->getAttribute(PDO::ATTR_CASE);
	}

	/**
	 * Sets the case of the column names.
	 *
	 * @param int $value the case of the column names
	 *
	 * @see http://www.php.net/manual/en/pdo.setattribute.php
	 *
	 * @return void
	 */
	public function setColumnCase($value)
	{
		$this->setAttribute(PDO::ATTR_CASE,$value);
	}

	/**
	 * Returns how the null and empty strings are converted.
	 * @return mixed how the null and empty strings are converted
	 * @see http://www.php.net/manual/en/pdo.setattribute.php
	 */
	public function getNullConversion()
	{
		return $this->getAttribute(PDO::ATTR_ORACLE_NULLS);
	}

	/**
	 * Sets how the null and empty strings are converted.
	 *
	 * @param mixed $value how the null and empty strings are converted
	 *
	 * @see http://www.php.net/manual/en/pdo.setattribute.php
	 *
	 * @return void
	 */
	public function setNullConversion($value)
	{
		$this->setAttribute(PDO::ATTR_ORACLE_NULLS,$value);
	}

	/**
	 * Returns whether creating or updating a DB record will be automatically committed.
	 * Some DBMS (such as sqlite) may not support this feature.
	 * @return bool whether creating or updating a DB record will be automatically committed.
	 */
	public function getAutoCommit()
	{
		return $this->getAttribute(PDO::ATTR_AUTOCOMMIT);
	}

	/**
	 * Sets whether creating or updating a DB record will be automatically committed.
	 * Some DBMS (such as sqlite) may not support this feature.
	 *
	 * @param bool $value whether creating or updating a DB record will be automatically committed.
	 *
	 * @return void
	 */
	public function setAutoCommit($value)
	{
		$this->setAttribute(PDO::ATTR_AUTOCOMMIT,$value);
	}

	/**
	 * Returns whether the connection is persistent or not.
	 * Some DBMS (such as sqlite) may not support this feature.
	 * @return bool whether the connection is persistent or not
	 */
	public function getPersistent()
	{
		return $this->getAttribute(PDO::ATTR_PERSISTENT);
	}

	/**
	 * Sets whether the connection is persistent or not.
	 * Some DBMS (such as sqlite) may not support this feature.
	 * @param bool $value whether the connection is persistent or not
	 */
	public function setPersistent($value)
	{
		return $this->setAttribute(PDO::ATTR_PERSISTENT,$value);
	}

	/**
	 * Returns the name of the DB driver.
	 *
	 * @return string name of the DB driver.
	 */
	public function getDriverName()
	{
		if($this->_driverName!==null)
			return $this->_driverName;
		elseif(($pos=strpos($this->connectionString,':'))!==false)
			return $this->_driverName=strtolower(substr($this->connectionString,0,$pos));
		// todo: does this actually return string|null or just string from top if/elseif?
		//return $this->getAttribute(PDO::ATTR_DRIVER_NAME);
	}

	/**
	 * Changes the name of the DB driver. Overrides value extracted from the {@link connectionString},
	 * which is behavior by default.
	 *
	 * @param string $driverName to be set. Valid values are the keys from the {@link driverMap} property.
	 *
	 * @see getDriverName
	 * @see driverName
	 *
	 * @since 1.1.16
	 *
	 * @return void
	 */
	public function setDriverName($driverName)
	{
		$this->_driverName=strtolower($driverName);
	}

	/**
	 * Returns the version information of the DB driver.
	 * @return string the version information of the DB driver
	 */
	public function getClientVersion()
	{
		return $this->getAttribute(PDO::ATTR_CLIENT_VERSION);
	}

	/**
	 * Returns the status of the connection.
	 * Some DBMS (such as sqlite) may not support this feature.
	 * @return string the status of the connection
	 */
	public function getConnectionStatus()
	{
		return $this->getAttribute(PDO::ATTR_CONNECTION_STATUS);
	}

	/**
	 * Returns whether the connection performs data prefetching.
	 * @return bool whether the connection performs data prefetching
	 */
	public function getPrefetch()
	{
		return $this->getAttribute(PDO::ATTR_PREFETCH);
	}

	/**
	 * Returns the information of DBMS server.
	 * @return string the information of DBMS server
	 */
	public function getServerInfo()
	{
		return $this->getAttribute(PDO::ATTR_SERVER_INFO);
	}

	/**
	 * Returns the version information of DBMS server.
	 * @return string the version information of DBMS server
	 */
	public function getServerVersion()
	{
		return $this->getAttribute(PDO::ATTR_SERVER_VERSION);
	}

	/**
	 * Returns the timeout settings for the connection.
	 * @return int timeout settings for the connection
	 */
	public function getTimeout()
	{
		return $this->getAttribute(PDO::ATTR_TIMEOUT);
	}

	/**
	 * Obtains a specific DB connection attribute information.
	 * @param int $name the attribute to be queried
	 * @return mixed|null the corresponding attribute information
	 * @see http://www.php.net/manual/en/function.PDO-getAttribute.php
	 */
	public function getAttribute($name)
	{
		$this->setActive(true);
		return $this->_pdo->getAttribute($name);
	}

	/**
	 * Sets an attribute on the database connection.
	 *
	 * @param int $name the attribute to be set
	 * @param mixed $value the attribute value
	 *
	 * @see http://www.php.net/manual/en/function.PDO-setAttribute.php
	 *
	 * @return bool
	 */
	public function setAttribute($name,$value)
	{
		if($this->_pdo instanceof PDO)
			return $this->_pdo->setAttribute($name,$value);
		else
			$this->_attributes[$name]=$value;
		return true;
	}

	/**
	 * Returns the attributes that are previously explicitly set for the DB connection.
	 * @return array attributes (name=>value) that are previously explicitly set for the DB connection.
	 * @see setAttributes
	 * @since 1.1.7
	 */
	public function getAttributes()
	{
		return $this->_attributes;
	}

	/**
	 * Sets a set of attributes on the database connection.
	 *
	 * @param array $values attributes (name=>value) to be set.
	 *
	 * @see setAttribute
	 *
	 * @since 1.1.7
	 *
	 * @return void
	 */
	public function setAttributes($values)
	{
		foreach($values as $name=>$value)
			$this->_attributes[$name]=$value;
	}

	/**
	 * Returns the statistical results of SQL executions.
	 * The results returned include the number of SQL statements executed and
	 * the total time spent.
	 * In order to use this method, {@link enableProfiling} has to be set true.
	 *
	 * @return array{0: int, 1: float|int} the first element indicates the number of SQL statements executed, and the second element the total time spent in SQL execution.
	 */
	public function getStats()
	{
		$logger=Yii::getLogger();
		$timings=$logger->getProfilingResults(null,'system.db.CDbCommand.query');
		$count=count($timings);
		$time=array_sum($timings);
		$timings=$logger->getProfilingResults(null,'system.db.CDbCommand.execute');
		$count+=count($timings);
		$time+=array_sum($timings);
		return array($count,$time);
	}
}
