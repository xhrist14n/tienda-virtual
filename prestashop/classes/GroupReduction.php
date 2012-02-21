<?php
/*
* 2007-2011 PrestaShop 
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2011 PrestaShop SA
*  @version  Release: $Revision: 10128 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class GroupReductionCore extends ObjectModel
{
	public	$id_group;
	public	$id_category;
	public	$reduction;

 	protected $fieldsRequired = array('id_group', 'id_category', 'reduction');
 	protected $fieldsValidate = array('id_group' => 'isUnsignedId', 'id_category' => 'isUnsignedId', 'reduction' => 'isPrice');

	protected $table = 'group_reduction';
	protected $identifier = 'id_group_reduction';

	protected static $reductionCache = array();
	
	public function getFields()
	{
		parent::validateFields();
		$fields['id_group'] = (int)($this->id_group);
		$fields['id_category'] = (int)($this->id_category);
		$fields['reduction'] = (float)($this->reduction);
		return $fields;
	}

	public function add($autodate = true, $nullValues = false)
	{
		return (parent::add($autodate, $nullValues) AND $this->_setCache());
	}

	public function update($nullValues = false)
	{
		return (parent::update($nullValues) AND $this->_updateCache());
	}

	public function delete()
	{
		$resource = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('
			SELECT p.`id_product`
			FROM `'._DB_PREFIX_.'product` p
			WHERE p.`id_category_default` = '.(int)($this->id_category)
		, false);
		
		while ($row = Db::getInstance()->nextRow($resource))
		{
			$query = 'DELETE FROM `'._DB_PREFIX_.'product_group_reduction_cache` WHERE `id_product` = '.(int)($row['id_product']);
			if (Db::getInstance()->Execute($query) === false)
				return false;
		}
		return (parent::delete());
	}

	protected function _clearCache()
	{
		return Db::getInstance()->Execute('DELETE FROM `'._DB_PREFIX_.'product_group_reduction_cache` WHERE `id_group` = '.(int)($this->id_group));
	}

	protected function _setCache()
	{
		$resource = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('
			SELECT p.`id_product`
			FROM `'._DB_PREFIX_.'product` p
			WHERE p.`id_category_default` = '.(int)($this->id_category)
		, false);
		
		$query = 'INSERT INTO `'._DB_PREFIX_.'product_group_reduction_cache` (`id_product`, `id_group`, `reduction`) VALUES ';
		$updated = false;
		while ($row = Db::getInstance()->nextRow($resource))
		{
			$query .= '('.(int)($row['id_product']).', '.(int)($this->id_group).', '.(float)($this->reduction).'), ';
			$updated = true;
		}
		
		if ($updated)
			return (Db::getInstance()->Execute(rtrim($query, ', ')));
		return true;
	}

	protected function _updateCache()
	{
		$resource = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('
			SELECT p.`id_product`
			FROM `'._DB_PREFIX_.'product` p
			WHERE p.`id_category_default` = '.(int)($this->id_category)
		, false);
		
		while ($row = Db::getInstance()->nextRow($resource))
		{
			$query = 'UPDATE `'._DB_PREFIX_.'product_group_reduction_cache`
                                  SET `reduction` = '.(float)($this->reduction).'
                                  WHERE `id_product` = '.(int)($row['id_product']).' AND `id_group` = '.(int)($this->id_group);
			if (Db::getInstance()->Execute($query) === false)
				return false;
		}
		return true;
	}

	public static function getGroupReductions($id_group, $id_lang)
	{
		return Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('
			SELECT gr.`id_group_reduction`, gr.`id_group`, gr.`id_category`, gr.`reduction`, cl.`name` AS category_name
			FROM `'._DB_PREFIX_.'group_reduction` gr
			LEFT JOIN `'._DB_PREFIX_.'category_lang` cl ON (cl.`id_category` = gr.`id_category` AND cl.`id_lang` = '.(int)($id_lang).')
			WHERE `id_group` = '.(int)($id_group)
		);
	}

	public static function getValueForProduct($id_product, $id_group)
	{
		if (!isset(self::$reductionCache[$id_product.'-'.$id_group]))
			self::$reductionCache[$id_product.'-'.$id_group] = Db::getInstance()->getValue(
																'SELECT `reduction`
																FROM `'._DB_PREFIX_.'product_group_reduction_cache`
																WHERE `id_product` = '.(int)($id_product).' AND `id_group` = '.(int)($id_group));
		return self::$reductionCache[$id_product.'-'.$id_group];
	}

	public static function doesExist($id_group, $id_category)
	{
		return (bool)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT `id_group`
                                                                           FROM `'._DB_PREFIX_.'group_reduction`
                                                                           WHERE `id_group` = '.(int)($id_group).' AND `id_category` = '.(int)($id_category));
	}
	
	public static function getGroupByCategoryId($id_category)
	{
		return Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
			SELECT gr.`id_group` as id_group, gr.`reduction` as reduction
			FROM `'._DB_PREFIX_.'group_reduction` gr
			WHERE `id_category` = '.(int)$id_category
		, false);
	}
	
	public static function getGroupReductionByCategoryId($id_category)
	{
		return Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
			SELECT gr.`id_group_reduction` as id_group_reduction
			FROM `'._DB_PREFIX_.'group_reduction` gr
			WHERE `id_category` = '.(int)$id_category
		, false);
	}

	public static function setProductReduction($id_product, $id_group, $id_category, $reduction)
	{
		$row = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
			SELECT pgr.`id_product`, pgr.`id_group`, pgr.`reduction` 
			FROM `'._DB_PREFIX_.'product_group_reduction_cache` pgr
			WHERE pgr.`id_product` = '.(int)$id_product
		);
		
		if (Db::getInstance()->NumRows() == 0)
			$query = 'INSERT INTO `'._DB_PREFIX_.'product_group_reduction_cache` (`id_product`, `id_group`, `reduction`)
                                  VALUES ('.(int)($id_product).', '.(int)($id_group).', '.(float)($reduction).')';
		else
			$query = 'UPDATE `'._DB_PREFIX_.'product_group_reduction_cache`
                                  SET `reduction` = '.(float)($reduction).'
                                  WHERE `id_product` = '.(int)($id_product).' AND `id_group` = '.(int)($id_group);

		return (Db::getInstance()->Execute($query));
	}

	public static function deleteProductReduction($id_product)
	{
		$query = 'DELETE FROM `'._DB_PREFIX_.'product_group_reduction_cache` WHERE `id_product` = '.(int)($id_product);
		if (Db::getInstance()->Execute($query) === false)
			return false;
		return true;
	}
	
	public static function duplicateReduction($id_product_old, $id_product)
	{
		$row = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
			SELECT pgr.`id_product`, pgr.`id_group`, pgr.`reduction` 
			FROM `'._DB_PREFIX_.'product_group_reduction_cache` pgr
			WHERE pgr.`id_product` = '.(int)$id_product_old
		);
		if (!$row)
			return true;
		
		$query = 'INSERT INTO `'._DB_PREFIX_.'product_group_reduction_cache` (`id_product`, `id_group`, `reduction`) VALUES ';
		$query .= '('.(int)($id_product).', '.(int)($row['id_group']).', '.(float)($row['reduction']).')';
		return (Db::getInstance()->Execute($query));
	}
	
	public static function deleteCategory($id_category)
	{
		$query = 'DELETE FROM `'._DB_PREFIX_.'group_reduction` WHERE `id_category` = '.(int)($id_category);
		if (Db::getInstance()->Execute($query) === false)
			return false;
		return true;
	}
}
