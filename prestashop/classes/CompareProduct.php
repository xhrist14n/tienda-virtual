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
*  @version  Release: $Revision: 9643 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class CompareProductCore extends ObjectModel
{
	public		$id_compare;
	
	public		$id_customer;
	
	public		$date_add;
	
	public		$date_upd;
	
	protected	$fieldRequired = array(
		'id_compare',
		'id_customer');
	
	protected 	$fieldsValidate = array(
		'id_compare' => 'isUnsignedInt',
		'id_customer' => 'isUnsignedInt'
	);
	
	protected $table = 'compare';
	
	protected $identifier = 'id_compare';

	
	/**
	 * Get all comapare products of the customer
	 * @param int $id_customer
	 * @return array
	 */
	public static function getCompareProducts($id_compare)
	{
		$results = Db::getInstance()->ExecuteS('
		SELECT DISTINCT `id_product`
		FROM `'._DB_PREFIX_.'compare` c
		LEFT JOIN `'._DB_PREFIX_.'compare_product` cp ON (cp.`id_compare` = c.`id_compare`)
		WHERE cp.`id_compare` = '.(int)($id_compare));

		$compareProducts = null;
		
		if ($results)
			foreach ($results as $result)
				$compareProducts[] = $result['id_product'];
		
		return $compareProducts; 
	}
	
	
	/**
	 * Add a compare product for the customer
	 * @param int $id_customer, int $id_product
	 * @return boolean
	 */
	public static function addCompareProduct($id_compare, $id_product)
	{
		global $cookie;
		
		if (!$id_compare)
		{
			$id_customer = false;
			if (isset($cookie->id_customer))
				$id_customer = $cookie->id_customer;
			$sql = Db::getInstance()->Execute('
			INSERT INTO `'._DB_PREFIX_.'compare` (`id_compare`, `id_customer`) VALUES (NULL, "'.($id_customer ? $id_customer: '0').'")');
			if ($sql)
			{
				$id_compare = Db::getInstance()->getValue('SELECT MAX(`id_compare`) FROM `'._DB_PREFIX_.'compare`');
				$cookie->id_compare = $id_compare;
			}
		}
		return Db::getInstance()->Execute('
			INSERT INTO `'._DB_PREFIX_.'compare_product` (`id_compare`, `id_product`, `date_add`, `date_upd`)
			VALUES ('.(int)($id_compare).', '.(int)($id_product).', NOW(), NOW())');
	}
	
	
	/**
	 * Remove a compare product for the customer
	 * @param int $id_compare, int $id_product
	 * @return boolean
	 */
	public static function removeCompareProduct($id_compare, $id_product)
	{
		return Db::getInstance()->Execute('
		DELETE cp FROM `'._DB_PREFIX_.'compare_product` cp, `'._DB_PREFIX_.'compare` c
		WHERE cp.`id_compare`=c.`id_compare`
		AND cp.`id_product` = '.(int)($id_product).'
		AND c.`id_compare` = '.(int)$id_compare);
	}
	
	/**
	 * Get the number of compare products of the customer
	 * @param int $id_compare
	 * @return int
	 */
	public static function getNumberProducts($id_compare)
	{
		return (int)(Db::getInstance()->getValue('
			SELECT count(`id_compare`)
			FROM `'._DB_PREFIX_.'compare_product`
			WHERE `id_compare` = '.(int)($id_compare)));
	}
	
	
	/**
	 * Clean entries which are older than the period
	 * @param string $period
	 * @return void
	 */
	public static function cleanCompareProducts($period = 'week')
	{
		if ($period === 'week')
			$interval = '1 WEEK';
		elseif ($period === 'month')
			$interval = '1 MONTH';
		elseif ($period === 'year')
			$interval = '1 YEAR';
		else
			return;
			
		if ($interval != null)
		{
			Db::getInstance()->Execute('
			DELETE cp, c FROM `'._DB_PREFIX_.'compare_product` cp, `'._DB_PREFIX_.'compare` c
			WHERE cp.date_upd < DATE_SUB(NOW(), INTERVAL 1 WEEK) AND c.`id_compare`=cp.`id_compare`');
		}
	}
	
	/**
	 * Get the id_compare by id_customer
	 * @param integer $id_customer
	 * @return integer $id_compare
	 */
	public static function getIdCompareByIdCustomer($id_customer)
	{
		return (int)Db::getInstance()->getValue('
		SELECT `id_compare`
		FROM `'._DB_PREFIX_.'compare`
		WHERE `id_customer`= '.(int)$id_customer);
	}
}