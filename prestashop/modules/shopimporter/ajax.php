<?php

include_once('../../config/config.inc.php');
include_once('../../init.php');
include_once('../../modules/shopimporter/shopimporter.php');


$moduleName = Tools::getValue('moduleName');

if (!Tools::getValue('ajax') || Tools::getValue('token') != sha1(_COOKIE_KEY_.'ajaxShopImporter')  || (!empty($moduleName) && !ctype_alnum($moduleName)))
	die;


$className = Tools::getValue('className');
$getMethod = Tools::getValue('getMethod');
$limit = Tools::getValue('limit');
$nbr_import = Tools::getValue('nbr_import');
$server = Tools::getValue('server');
$user = Tools::getValue('user');
$password = Tools::getValue('password');
$database = Tools::getValue('database');
$prefix = Tools::getValue('prefix');
$save = Tools::getValue('save');

$url = Tools::getValue('url');
$loginws = Tools::getValue('loginws');
$apikey = Tools::getValue('apikey');

if (Tools::isSubmit('checkAndSaveConfig'))
{
	//cleans the database if an import has already been done
	$shopImporter = new shopImporter();
	foreach($shopImporter->supportedImports as $key => $import)
		if (array_key_exists('alterTable', $import))
			$columns = Db::getInstance()->ExecuteS('SHOW COLUMNS FROM `'._DB_PREFIX_.bqSQL($import['table']).'`');
			foreach ($columns as $column)
				if ($column['Field'] == $import['identifier'].'_'.$moduleName)
					Db::getInstance()->Execute('ALTER IGNORE TABLE `'._DB_PREFIX_.bqSQL($import['table']).'` DROP `'.bqSQL($import['identifier'].'_'.$moduleName).'`');
	if ($link = @mysql_connect(Tools::getValue('server'), Tools::getValue('user'), Tools::getValue('password')))
	{
		if (!@mysql_select_db(Tools::getValue('database'), $link))
			die('{"hasError" : true, "error" : ["'.Tools::displayError('The database selection cannot be made.').'"]}');
		else
		{
			@mysql_close($link);
			die('{"hasError" : false, "error" : []}');
		}
	}
	else
		die('{"hasError" : true, "error" : ["'.Tools::displayError('Link to database cannot be established.').'"]}');
	
}

if (Tools::isSubmit('getData') || Tools::isSubmit('syncLang') || Tools::isSubmit('syncCurrency'))
{		
	if (Tools::isSubmit('syncLang'))
		$save = true;
	
	if (file_exists('../../modules/'.$moduleName.'/'.$moduleName.'.php'))
	{
		require_once('../../modules/'.$moduleName.'/'.$moduleName.'.php');
		$importModule = new $moduleName();
		$importModule->server = $server;
		$importModule->user = $user;
		$importModule->passwd = $password;
		$importModule->database = $database;
		$importModule->prefix = $prefix;
		if (!method_exists($importModule, $getMethod))
			die('{"hasError" : true, "error" : ["not_exist"], "datas" : []}');
		else
		{
			$return = call_user_func_array(array($importModule, $getMethod), array($limit, $nbr_import));
			$shopImporter = new shopImporter();
			$shopImporter->genericImport($className, $return, (bool)$save);
		}
	}
}
if (Tools::isSubmit('getDataWS') || Tools::isSubmit('syncLangWS') || Tools::isSubmit('syncCurrencyWS'))
{		
	if (Tools::isSubmit('syncLangWS'))
		$save = true;
		
	if (file_exists('../../modules/'.$moduleName.'/'.$moduleName.'.php'))
	{
		require_once('../../modules/'.$moduleName.'/'.$moduleName.'.php');
		
		try
		{
			$importModule = new $moduleName();
			$importModule->connect($url,$loginws,$apikey);
			
			if (!method_exists($importModule, $getMethod))
				die('{"hasError" : true, "error" : ["not_exist"], "datas" : []}');
			else
			{
				$return = call_user_func_array(array($importModule, $getMethod), array($limit, $nbr_import));
				$shopImporter = new shopImporter();
				$shopImporter->genericImport($className, $return, (bool)$save);
			}
			die('{"hasError" : false, "error" : []}');
		} catch (Exception $e)
		{
			die('{"hasError" : true, "error" : ['.json_encode($e->getMessage()).'], "datas" : []}');	
		}
	}
}

if (Tools::isSubmit('truncatTable'))
{	
	$shopImporter = new shopImporter();
	if ($shopImporter->truncateTable($className))
		die('{"hasError" : false, "error" : []}');
	else
		die('{"hasError" : true, "error" : ["'.$className.'"]}');

}

if (Tools::isSubmit('alterTable'))
{	
	$shopImporter = new shopImporter();
	if ($shopImporter->alterTable($className))
		die('{"hasError" : false, "error" : []}');
	else
		die('{"hasError" : true, "error" : ["'.$className.'"]}');

}

if (Tools::isSubmit('displaySpecificOptions'))
{	
	if (file_exists('../../modules/'.$moduleName.'/'.$moduleName.'.php'))
	{
		require_once('../../modules/'.$moduleName.'/'.$moduleName.'.php');
		$importModule = new $moduleName();
		$importModule->server = $server;
		$importModule->user = $user;
		$importModule->passwd = $password;
		$importModule->database = $database;
		$importModule->prefix = $prefix;
		if ($link = @mysql_connect(Tools::getValue('server'), Tools::getValue('user'), Tools::getValue('password')))
		{
			if (!@mysql_select_db(Tools::getValue('database'), $link))
				die(Tools::displayError('The database selection cannot be made.'));
			elseif (method_exists($importModule, 'displaySpecificOptions'))
				die($importModule->displaySpecificOptions());
			else
				die('not_exist');
		}
		else
			die(Tools::displayError('Link to database cannot be established.'));
	}
}
elseif (Tools::isSubmit('displaySpecificOptionsWsdl'))
{
	if (file_exists('../../modules/'.$moduleName.'/'.$moduleName.'.php'))
	{
		require_once('../../modules/'.$moduleName.'/'.$moduleName.'.php');
		$importModule = new $moduleName();

		try
		{
			if (method_exists($importModule, 'displaySpecificOptions'))
				die($importModule->displaySpecificOptions());
			else
				die('not_exist');
		} catch (Exception $e)
		{
			die('{"hasError" : true, "error" : ['.json_encode($e->getMessage()).'], "datas" : []}');	
		}
	}
}
if (Tools::isSubmit('connexionWs'))
{
	if (file_exists('../../modules/'.$moduleName.'/'.$moduleName.'.php'))
	{
		require_once('../../modules/'.$moduleName.'/'.$moduleName.'.php');
		try
		{

			$importModule = new $moduleName();
			$importModule->connect($url,$loginws,$apikey);
			die('{"hasError" : false, "error" : []}');
		} catch (Exception $e)
		{
			die('{"hasError" : true, "error" : ['.json_encode($e->getMessage()).'], "datas" : []}');	
		}
	}
}

if (Tools::isSubmit('validateSpecificOptions'))
{
	if (file_exists('../../modules/'.$moduleName.'/'.$moduleName.'.php'))
	{
		require_once('../../modules/'.$moduleName.'/'.$moduleName.'.php');
		$importModule = new $moduleName();
		if (!method_exists($importModule, 'validateSpecificOptions'))
			die('{"hasError" : true, "error" : ["not_exist"]}');
		else
			die($importModule->validateSpecificOptions());
	}
}	
if (Tools::isSubmit('displayConfigConnector'))
{
	if (file_exists('../../modules/'.$moduleName.'/'.$moduleName.'.php'))
	{
		require_once('../../modules/'.$moduleName.'/'.$moduleName.'.php');
		$importModule = new $moduleName();
		if (!method_exists($importModule, 'displayConfigConnector'))
			die('{"hasError" : true, "error" : ["not_exist"]}');
		else
			die($importModule->displayConfigConnector());
	}
}
?>