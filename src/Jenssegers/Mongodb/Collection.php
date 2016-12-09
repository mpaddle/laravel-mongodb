<?php namespace Jenssegers\Mongodb;

use Exception;
use MongoCollection;

class Collection {

    /**
     * The connection instance.
     *
     * @var Connection
     */
    protected $connection;

    /**
     * The MongoCollection instance..
     *
     * @var MongoCollection
     */
    protected $collection;

	/**
	 * Constructor.
	 * @param Connection $connection
	 * @param MongoCollection $collection
	 */
    public function __construct(Connection $connection, MongoCollection $collection)
    {
        $this->connection = $connection;

        $this->collection = $collection;
    }

	/**
	 * @param array $values
	 * @param array $options
	 * @return array|bool
	 */
	public function insert(array &$values, $options = [])
	{
		return $this->callInsertMethod('insert', $values, $options);
	}

	/**
	 * @param array $values
	 * @param array $options
	 * @return array|bool
	 */
	public function insertBatch(array &$values, $options = [])
	{
		return $this->callInsertMethod('insertBatch', $values, $options);
	}

	/**
	 * @param array $values
	 * @param array $options
	 * @return array|bool
	 */
	public function save(array &$values, $options = [])
	{
		return $this->callInsertMethod('save', $values, $options);
	}

	/**
     * Handle dynamic method calls.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
	    $query = $this->buildQueryString($parameters);

        $start = microtime(true);

	    if (in_array($method, ['insert', 'batchInsert', 'save'], true))
	    {
			// The adapter package requires a reference to the first parameter for `insert`, `batchInsert`, `save`.
		    $result = call_user_func_array([$this->collection, $method], [&$parameters[0], $parameters[1] ?? []]);
	    }
	    else
	    {
		    $result = call_user_func_array([$this->collection, $method], $parameters);
	    }

        // Once we have run the query we will calculate the time that it took to run and
        // then log the query, bindings, and execution time so we will report them on
        // the event that the developer needs them. We'll log time in milliseconds.
        $time = $this->connection->getElapsedTime($start);

        // Convert the query to a readable string.
        $queryString = $this->collection->getName() . '.' . $method . '(' . join(',', $query) . ')';

        $this->connection->logQuery($queryString, array(), $time);

        return $result;
    }

	/**
	 * @param array $parameters
	 * @return array
	 */
	private function buildQueryString(array $parameters): array
	{
		$query = [];

		// Build the query string.
		foreach ($parameters as $parameter)
		{
			try
			{
				$query[] = json_encode($parameter);
			}
			catch (Exception $e)
			{
				$query[] = '{...}';
			}
		}
		return $query;
	}

	/**
	 * @param string $method insert, insertBatch or save
	 * @param array $values
	 * @param $options
	 * @return array|bool
	 */
	private function callInsertMethod($method, array &$values, $options)
	{
		$query = $this->buildQueryString(func_get_args());

		$start = microtime(true);

		$result = $this->collection->insert($values, $options);

		// Once we have run the query we will calculate the time that it took to run and
		// then log the query, bindings, and execution time so we will report them on
		// the event that the developer needs them. We'll log time in milliseconds.
		$time = $this->connection->getElapsedTime($start);

		// Convert the query to a readable string.
		$queryString = $this->collection->getName() . '.' . $method . '(' . join(',', $query) . ')';

		$this->connection->logQuery($queryString, [], $time);

		return $result;
	}
}
