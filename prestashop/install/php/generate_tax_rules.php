<?php

function generate_tax_rules()
{
	$ps_lang_default = Db::getInstance()->getValue('SELECT value FROM `'._DB_PREFIX_.'configuration`
		WHERE name="PS_LANG_DEFAULT"');
	$taxes = Db::getInstance('SELECT * from `'._DB_PREFIX_.'tax` WHERE active = 1');

	foreach ($taxes AS $tax)
	{
		$insert = '';
		$id_tax = $tax['id_tax'];
		$row = array(
			'active' => 1,
			'id_tax' => $id_tax,
			'name' => 'Rule '.$tax['rate'].'%',
		);
		Db::getInstance()->AutoExecute(_DB_PREFIX_.'category_group', $row, 'INSERT');
		$id_tax_rules_group = Db::getInstance()->insert_id;


		$countries = Db::getInstance()->ExecuteS('
		SELECT * FROM `'._DB_PREFIX_.'country` c
		LEFT JOIN `'._DB_PREFIX_.'zone` z ON (c.`id_zone` = z.`id_zone`)
		LEFT JOIN `'._DB_PREFIX_.'tax_zone` tz ON (tz.`id_zone` = z.`id_zone`)
		WHERE `id_tax` = '.(int)$id_tax
		);
		if ($countries)
		{
			foreach ($countries AS $country)
			{
					 $res = Db::getInstance()->Execute('
					 INSERT INTO `'._DB_PREFIX_.'tax_rule` (`id_tax_rules_group`, `id_country`, `id_state`, `state_behavior`, `id_tax`)
					 VALUES (
					 '.(int)$id_tax_rules_group.',
					 '.(int)$country['id_country'].',
					 0,
					 0,
					 '.(int)$id_tax.
					 ')');

			}
		}

		$states = Db::getInstance()->ExecuteS('
		SELECT * FROM `'._DB_PREFIX_.'states s
		LEFT JOIN `'._DB_PREFIX_.'tax_state ts ON (ts.`id_state` = s.`id_state`)
		WHERE `id_tax` = '.(int)$id_tax
		);

		if ($states)
		{
			foreach ($states AS $state)
			{
			    if (!in_array($state['tax_behavior'], array(PS_PRODUCT_TAX, PS_STATE_TAX, PS_BOTH_TAX)))
			        $tax_behavior = PS_PRODUCT_TAX;
			    else
			        $tax_behavior = $state['tax_behavior'];

					 $res = Db::getInstance()->execute('
					 INSERT INTO `'._DB_PREFIX_.'tax_rule` (`id_tax_rules_group`, `id_country`, `id_state`, `state_behavior`, `id_tax`)
					 VALUES (
					 '.(int)$id_tax_rules_group.',
					 '.(int)$state['id_country'].',
					 '.(int)$state['id_state'].',
					 '.(int)$tax_behavior.',
					 '.(int)$id_tax.
					 ')');
			}
		}

		Db::getInstance()->execute('
		UPDATE `'._DB_PREFIX_.'product`
		SET `id_tax_rules_group` = '.(int)$id_tax_rules_group.'
		WHERE `id_tax` = '.(int)$id_tax
		);

		Db::getInstance()->execute('
		UPDATE `'._DB_PREFIX_.'carrier`
		SET `id_tax_rules_group` = '.(int)$id_tax_rules_group.'
		WHERE `id_tax` = '.(int)$id_tax
		);


	$socolissimo_overcost_tax = Db::getInstance()->getValue('SELECT value FROM `'._DB_PREFIX_.'configuration`
		WHERE name="SOCOLISSIMO_OVERCOST_TAX"');
		if ($socolissimo_overcost_tax == $id_tax)
			$res &= Db::getInstance()->getValue('REPLACE INTO `'._DB_PREFIX_.'configuration`
				(name, value) VALUES ("PS_POUET", "'.$id_tax_rules_group.'"');
	}
}

