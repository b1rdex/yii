<?php
/**
 * CDataProvider is a base class that implements the {@link IDataProvider} interface.
 *
 * Derived classes mainly need to implement three methods: {@link fetchData},
 * {@link fetchKeys} and {@link calculateTotalItemCount}.
 *
 * @property string $id The unique ID that uniquely identifies the data provider among all data providers.
 * @property CPagination|false $pagination The pagination object. If this is false, it means the pagination is disabled.
 * @property CSort|false $sort The sorting object. If this is false, it means the sorting is disabled.
 * @property array $data The list of data items currently available in this data provider.
 * @property array $keys The list of key values corresponding to {@link data}. Each data item in {@link data}
 * is uniquely identified by the corresponding key value in this array.
 * @property int $itemCount The number of data items in the current page.
 * @property int $totalItemCount Total number of possible data items.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @package system.web
 * @since 1.1
 *
 * @template-covariant TValue
 * @implements IDataProvider<TValue>
 */
abstract class CDataProvider extends CComponent implements IDataProvider
{
	private $_id;
	private $_data;
	private $_keys;
	private $_totalItemCount;
	private $_sort;
	private $_pagination;

	/**
	 * Fetches the data from the persistent data storage.
	 * @return array list of data items
     * @phpstan-return list<TValue>
	 */
	abstract protected function fetchData();
	/**
	 * Fetches the data item keys from the persistent data storage.
	 * @return array list of data item keys.
	 */
	abstract protected function fetchKeys();
	/**
	 * Calculates the total number of data items.
	 * @return int the total number of data items.
	 */
	abstract protected function calculateTotalItemCount();

	/**
	 * Returns the ID that uniquely identifies the data provider.
	 * @return string the unique ID that uniquely identifies the data provider among all data providers.
	 */
	public function getId()
	{
		return $this->_id;
	}

	/**
	 * Sets the provider ID.
	 *
	 * @param string $value the unique ID that uniquely identifies the data provider among all data providers.
	 *
	 * @return void
	 */
	public function setId($value)
	{
		$this->_id=$value;
	}

	/**
     * @template T of CPagination
	 * Returns the pagination object.
	 * @param string $className the pagination object class name. Parameter is available since version 1.1.13.
     * @phpstan-param class-string<T> $className
     *
	 * @return CPagination|false the pagination object. If this is false, it means the pagination is disabled.
     * @phpstan-return T|false
	 */
	public function getPagination($className='CPagination')
	{
		if($this->_pagination===null)
		{
			$this->_pagination=new $className;
			if(($id=$this->getId())!='')
				$this->_pagination->pageVar=$id.'_page';
		}
		return $this->_pagination;
	}

	/**
	 * Sets the pagination for this data provider.
	 *
	 *
	 * You can configure this property same way as a component:
	 * <pre>
	 * array(
	 *     'class' => 'MyPagination',
	 *     'pageSize' => 20,
	 * ),
	 * </pre>
	 *
	 * @param \CPagination|array|false $value the pagination to be used by this data provider. This could be a {@link CPagination} object
	 * or an array used to configure the pagination object. If this is false, it means the pagination should be disabled.
	 *
	 * @return void
	 */
	public function setPagination($value)
	{
		if(is_array($value))
		{
			if(isset($value['class']))
			{
				$pagination=$this->getPagination($value['class']);
				unset($value['class']);
			}
			else
				$pagination=$this->getPagination();

			foreach($value as $k=>$v)
				$pagination->$k=$v;
		}
		else
			$this->_pagination=$value;
	}

	/**
     * @template T of CSort
	 * Returns the sort object.
	 * @param string $className the sorting object class name. Parameter is available since version 1.1.13.
     * @phpstan-param class-string<T> $className
     *
	 * @return CSort|false the sorting object. If this is false, it means the sorting is disabled.
     * @phpstan-return T|false
	 */
	public function getSort($className='CSort')
	{
		if($this->_sort===null)
		{
			$this->_sort=new $className;
			if(($id=$this->getId())!='')
				$this->_sort->sortVar=$id.'_sort';
		}
		return $this->_sort;
	}

	/**
	 * Sets the sorting for this data provider.
	 *
	 *
	 * You can configure this property same way as a component:
	 * <pre>
	 * array(
	 *     'class' => 'MySort',
	 *     'attributes' => array('name', 'weight'),
	 * ),
	 * </pre>
	 *
	 * @param array|\CSort|false $value the sorting to be used by this data provider. This could be a {@link CSort} object
	 * or an array used to configure the sorting object. If this is false, it means the sorting should be disabled.
	 *
	 * @return void
	 */
	public function setSort($value)
	{
		if(is_array($value))
		{
			if(isset($value['class']))
			{
				$sort=$this->getSort($value['class']);
				unset($value['class']);
			}
			else
				$sort=$this->getSort();

			foreach($value as $k=>$v)
				$sort->$k=$v;
		}
		else
			$this->_sort=$value;
	}

	/**
	 * Returns the data items currently available.
	 * @param bool $refresh whether the data should be re-fetched from persistent storage.
     *
	 * @return array the list of data items currently available in this data provider.
     * @phpstan-return list<TValue>
	 */
	public function getData($refresh=false)
	{
		if($this->_data===null || $refresh)
			$this->_data=$this->fetchData();
		return $this->_data;
	}

	/**
	 * Sets the data items for this provider.
	 *
	 * @param array $value put the data items into this provider.
     * @phpstan-param list<TValue> $value
	 *
	 * @return void
	 */
	public function setData($value)
	{
		$this->_data=$value;
	}

	/**
	 * Returns the key values associated with the data items.
	 * @param bool $refresh whether the keys should be re-calculated.
	 * @return array the list of key values corresponding to {@link data}. Each data item in {@link data}
	 * is uniquely identified by the corresponding key value in this array.
	 */
	public function getKeys($refresh=false)
	{
		if($this->_keys===null || $refresh)
			$this->_keys=$this->fetchKeys();
		return $this->_keys;
	}

	/**
	 * Sets the data item keys for this provider.
	 *
	 * @param array $value put the data item keys into this provider.
	 *
	 * @return void
	 */
	public function setKeys($value)
	{
		$this->_keys=$value;
	}

	/**
	 * Returns the number of data items in the current page.
	 * This is equivalent to <code>count($provider->getData())</code>.
	 * When {@link pagination} is set false, this returns the same value as {@link totalItemCount}.
	 * @param bool $refresh whether the number of data items should be re-calculated.
	 * @return int the number of data items in the current page.
	 */
	public function getItemCount($refresh=false)
	{
		return count($this->getData($refresh));
	}

	/**
	 * Returns the total number of data items.
	 * When {@link pagination} is set false, this returns the same value as {@link itemCount}.
	 * @param bool $refresh whether the total number of data items should be re-calculated.
	 * @return int total number of possible data items.
	 */
	public function getTotalItemCount($refresh=false)
	{
		if($this->_totalItemCount===null || $refresh)
			$this->_totalItemCount=$this->calculateTotalItemCount();
		return $this->_totalItemCount;
	}

	/**
	 * Sets the total number of data items.
	 * This method is provided in case when the total number cannot be determined by {@link calculateTotalItemCount}.
	 *
	 * @param int $value the total number of data items.
	 *
	 * @since 1.1.1
	 *
	 * @return void
	 */
	public function setTotalItemCount($value)
	{
		$this->_totalItemCount=$value;
	}

    public function __clone()
    {
        if ($this->_pagination !== null) {
            $this->_pagination = clone $this->_pagination;
        }

        if ($this->_sort !== null) {
            $this->_sort = clone $this->_sort;
        }
    }
}
