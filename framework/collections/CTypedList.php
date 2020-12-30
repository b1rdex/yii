<?php
/**
 * This file contains CTypedList class.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @link http://www.yiiframework.com/
 * @copyright 2008-2013 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

/**
 * CTypedList represents a list whose items are of the certain type.
 *
 * CTypedList extends {@link CList} by making sure that the elements to be
 * added to the list is of certain class type.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @package system.collections
 * @since 1.0
 *
 * @todo: template T of object
 * @todo: extends ArrayAccess<int, T>
 */
class CTypedList extends CList
{
    /**
     * @var string
     * @todo: phpstan-var class-string<T>
     */
	private $_type;

	/**
	 * Constructor.
	 * @param class-string $type class type
     * @todo: phpstan-param class-string<T> $type
	 */
	public function __construct($type)
	{
		$this->_type=$type;
	}

	/**
	 * Inserts an item at the specified position.
	 * This method overrides the parent implementation by
	 * checking the item to be inserted is of certain type.
	 *
	 * @param int $index the specified position.
	 * @param mixed $item new item
     * @todo: phpstan-param T $item
	 *
	 * @throws CException If the index specified exceeds the bound,
	 * the list is read-only or the element is not of the expected type.
	 *
	 * @return void
	 */
	public function insertAt($index,$item)
	{
		if($item instanceof $this->_type)
			parent::insertAt($index,$item);
		else
			throw new CException(Yii::t('yii','CTypedList<{type}> can only hold objects of {type} class.',
				array('{type}'=>$this->_type)));
	}
}
