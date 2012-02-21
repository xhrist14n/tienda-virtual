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
*  @version  Release: $Revision: 12447 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

$filePrefix = 'PREFIX_';
$engineType = 'ENGINE_TYPE';

if (function_exists('date_default_timezone_set'))
	date_default_timezone_set('Europe/Paris');

// if _PS_ROOT_DIR_ is defined, use it instead of "guessing" the module dir.
if (defined('_PS_ROOT_DIR_') AND !defined('_PS_MODULE_DIR_'))
	define('_PS_MODULE_DIR_', _PS_ROOT_DIR_.'/modules/');
else
	if (!defined('_PS_MODULE_DIR_'))
		define('_PS_MODULE_DIR_', realpath(INSTALL_PATH.'/../').'/modules/');

if(!defined('_PS_INSTALLER_PHP_UPGRADE_DIR_'))
	define('_PS_INSTALLER_PHP_UPGRADE_DIR_',  INSTALL_PATH.DIRECTORY_SEPARATOR.'php/');
// deactivate non-native module is outside the upgrade process, 
// so we have to add it manually
require_once(_PS_INSTALLER_PHP_UPGRADE_DIR_.'deactivate_custom_modules.php');

//old version detection
global $oldversion, $logger;
$oldversion = false;
if (file_exists(SETTINGS_FILE) AND file_exists(DEFINES_FILE))
{
	include_once(SETTINGS_FILE);
	include_once(DEFINES_FILE);
	$oldversion = _PS_VERSION_;
}
else
{
	$logger->logError('The config/settings.inc.php file was not found.');
	die('<action result="fail" error="30" />'."\n");
}

if (!file_exists(DEFINES_FILE))
{
	$logger->logError('The config/settings.inc.php file was not found.');
	die('<action result="fail" error="37" />'."\n");
}
include_once(SETTINGS_FILE);

if (!defined('_THEMES_DIR_'))
	define('_THEMES_DIR_', __PS_BASE_URI__.'themes/');
if (!defined('_PS_IMG_'))
	define('_PS_IMG_', __PS_BASE_URI__.'img/');
if (!defined('_PS_JS_DIR_'))
	define('_PS_JS_DIR_', __PS_BASE_URI__.'js/');
if (!defined('_PS_CSS_DIR_'))
	define('_PS_CSS_DIR_', __PS_BASE_URI__.'css/');
include_once(DEFINES_FILE);

$oldversion = _PS_VERSION_;

$versionCompare =  version_compare(INSTALL_VERSION, $oldversion);

if ($versionCompare == '-1')
{
	$logger->logError('This installer is too old.');
	die('<action result="fail" error="27" />'."\n");
}
elseif ($versionCompare == 0)
{
	$logger->logError(sprintf('You already have the %s version.',INSTALL_VERSION));
	die('<action result="fail" error="28" />'."\n");
}
elseif ($versionCompare === false)
{
	$logger->logError('There is no older version. Did you delete or rename the config/settings.inc.php file?');
	die('<action result="fail" error="29" />'."\n");
}

//check DB access
include_once(INSTALL_PATH.'/classes/ToolsInstall.php');
$resultDB = ToolsInstall::checkDB(_DB_SERVER_, _DB_USER_, _DB_PASSWD_, _DB_NAME_, false);
if ($resultDB !== true)
{
	$logger->logError('Invalid database configuration.');
	die("<action result='fail' error='".$resultDB."'/>\n");
}

//custom sql file creation
$upgradeFiles = array();
if ($handle = opendir(INSTALL_PATH.'/sql/upgrade'))
{
	while (false !== ($file = readdir($handle)))
		if ($file != '.' AND $file != '..')
			$upgradeFiles[] = str_replace(".sql", "", $file);
	closedir($handle);
}
if (empty($upgradeFiles))
{
	$logger->logError('Can\'t find the sql upgrade files. Please verify that the /install/sql/upgrade folder is not empty)');
	die('<action result="fail" error="31" />'."\n");
}
natcasesort($upgradeFiles);
$neededUpgradeFiles = array();

// fix : complete version number if there is not all 4 numbers
// for example replace 1.4.3 by 1.4.3.0
// consequences : file 1.4.3.0.sql will be skipped if oldversion = 1.4.3
// @since 1.4.4.0
$arrayVersion = preg_split('#\.#', $oldversion);
$versionNumbers = sizeof($arrayVersion);

if ($versionNumbers != 4)
	$arrayVersion = array_pad($arrayVersion, 4, '0');

$oldversion = implode('.', $arrayVersion);
// end of fix

foreach ($upgradeFiles AS $version)
{

	if (version_compare($version, $oldversion) == 1 AND version_compare(INSTALL_VERSION, $version) != -1)
		$neededUpgradeFiles[] = $version;
}

if (empty($neededUpgradeFiles))
{
	$logger->logError('No upgrade is possible.');
	die('<action result="fail" error="32" />'."\n");
}


//refresh conf file
require_once(INSTALL_PATH.'/classes/AddConfToFile.php');
$oldLevel = error_reporting(E_ALL);
$mysqlEngine = (defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'MyISAM');
$datas = array(
	array('_DB_SERVER_', _DB_SERVER_),
	array('_DB_TYPE_', _DB_TYPE_),
	array('_DB_NAME_', _DB_NAME_),
	array('_DB_USER_', _DB_USER_),
	array('_DB_PASSWD_', _DB_PASSWD_),
	array('_DB_PREFIX_', _DB_PREFIX_),
	array('_MYSQL_ENGINE_', $mysqlEngine),
	array('_PS_CACHING_SYSTEM_', (defined('_PS_CACHING_SYSTEM_') AND _PS_CACHING_SYSTEM_ != 'MemCached') ? _PS_CACHING_SYSTEM_ : 'MCached'),
	array('_PS_CACHE_ENABLED_', defined('_PS_CACHE_ENABLED_') ? _PS_CACHE_ENABLED_ : '0'),
	array('_MEDIA_SERVER_1_', defined('_MEDIA_SERVER_1_') ? _MEDIA_SERVER_1_ : ''),
	array('_MEDIA_SERVER_2_', defined('_MEDIA_SERVER_2_') ? _MEDIA_SERVER_2_ : ''),
	array('_MEDIA_SERVER_3_', defined('_MEDIA_SERVER_3_') ? _MEDIA_SERVER_3_ : ''),
	array('__PS_BASE_URI__', __PS_BASE_URI__),
	array('_THEME_NAME_', _THEME_NAME_),
	array('_COOKIE_KEY_', _COOKIE_KEY_),
	array('_COOKIE_IV_', _COOKIE_IV_),
	array('_PS_CREATION_DATE_', defined("_PS_CREATION_DATE_") ? _PS_CREATION_DATE_ : date('Y-m-d')),
	array('_PS_VERSION_', INSTALL_VERSION)
);
if (defined('_RIJNDAEL_KEY_'))
	$datas[] = array('_RIJNDAEL_KEY_', _RIJNDAEL_KEY_);
if (defined('_RIJNDAEL_IV_'))
	$datas[] = array('_RIJNDAEL_IV_', _RIJNDAEL_IV_);
if (!defined('_PS_CACHE_ENABLED_'))
	define('_PS_CACHE_ENABLED_', '0');
if (!defined('_MYSQL_ENGINE_'))
	define('_MYSQL_ENGINE_', 'MyISAM');

$sqlContent = '';
if (isset($_GET['customModule']) AND $_GET['customModule'] == 'desactivate')
	deactivate_custom_modules();

foreach($neededUpgradeFiles AS $version)
{
	$file = INSTALL_PATH.'/sql/upgrade/'.$version.'.sql';
	if (!file_exists($file))
	{
		$logger->logError('Error while loading sql upgrade file.');
		die('<action result="fail" error="33" />'."\n");
	}
	if (!$sqlContent .= file_get_contents($file))
	{
		$logger->logError('Error while loading sql upgrade file.');
		die('<action result="fail" error="33" />'."\n");
	}
	$sqlContent .= "\n";
}

$sqlContent = str_replace(array($filePrefix, $engineType), array(_DB_PREFIX_, $mysqlEngine), $sqlContent);
$sqlContent = preg_split("/;\s*[\r\n]+/",$sqlContent);

error_reporting($oldLevel);
$confFile = new AddConfToFile(SETTINGS_FILE, 'w');
if ($confFile->error)
{
	$logger->logError($confFile->error);
	die('<action result="fail" error="'.$confFile->error.'" />'."\n");
}

foreach ($datas AS $data){
	$confFile->writeInFile($data[0], $data[1]);
}

if ($confFile->error != false)
{
	$logger->logError($confFile->error);
	die('<action result="fail" error="'.$confFile->error.'" />'."\n");
}


//sql file execution
global $requests, $warningExist;
$requests = '';
$warningExist = false;

Configuration::loadConfiguration();

foreach($sqlContent as $query)
{
	$query = trim($query);
	if (!empty($query))
	{
		/* If php code have to be executed */
		if (strpos($query, '/* PHP:') !== false)
		{
			/* Parsing php code */
			$pos = strpos($query, '/* PHP:') + strlen('/* PHP:');
			$phpString = substr($query, $pos, strlen($query) - $pos - strlen(' */;'));
			$php = explode('::', $phpString);
			preg_match('/\((.*)\)/', $phpString, $pattern);
			$paramsString = trim($pattern[0], '()');
			preg_match_all('/([^,]+),? ?/', $paramsString, $parameters);
			if (isset($parameters[1]))
				$parameters = $parameters[1];
			else
				$parameters = array();
			if (is_array($parameters))
				foreach ($parameters AS &$parameter)
					$parameter = str_replace('\'', '', $parameter);

			// Call a simple function
			// note : method call from class is no longer supported
			$func_name = str_replace($pattern[0], '', $php[0]);
			if (!function_exists($func_name) 
				&& !file_exists(_PS_INSTALLER_PHP_UPGRADE_DIR_.$func_name.'.php')
			)
			{
				$logger->logError('PHP error: '.$func_name.' does not exists and '.$func_name.'.php is missing '."\r\n");
				$requests .= '	<request result="fail">
	<sqlQuery><![CDATA['.htmlentities($query).']]></sqlQuery>
	<phpMsgError><![CDATA[file is missing]]></sqlMsgError>
	<phpNumberError><![CDATA[666]]></sqlNumberError>
</request>'."\n";
			}
			else
				require_once(_PS_INSTALLER_PHP_UPGRADE_DIR_.$func_name.'.php');
			
			$phpRes = call_user_func_array($func_name, $parameters);

			if ((is_array($phpRes) AND !empty($phpRes['error'])) OR $phpRes === false )
			{
				$logger->logError('PHP error: '.$query."\r\n".(empty($phpRes['msg'])?'':' - '.$phpRes['msg']));
				$logger->logError(empty($phpRes['error'])?'':$phpRes['error']);
				$requests.= '	<request result="fail">
		<sqlQuery><![CDATA['.htmlentities($query).']]></sqlQuery>
		<phpMsgError><![CDATA['.(empty($phpRes['msg'])?'':$phpRes['msg']).']]></sqlMsgError>
		<phpNumberError><![CDATA['.(empty($phpRes['error'])?'':$phpRes['error']).']]></sqlNumberError>
	</request>'."\n";
			}
			else
				$requests .=
'	<request result="ok">
		<sqlQuery><![CDATA['.htmlentities($query).']]></sqlQuery>
	</request>'."\n";
		}
		elseif (!Db::getInstance()->Execute($query))
		{
			$logger->logError('SQL query: '."\r\n".$query);
			$logger->logError('SQL error: '."\r\n".Db::getInstance()->getMsgError());
			$warningExist = true;
			$requests .=
'	<request result="fail">
		<sqlQuery><![CDATA['.htmlentities($query).']]></sqlQuery>
		<sqlMsgError><![CDATA['.htmlentities(Db::getInstance()->getMsgError()).']]></sqlMsgError>
		<sqlNumberError><![CDATA['.htmlentities(Db::getInstance()->getNumberError()).']]></sqlNumberError>
	</request>'."\n";
		}
		else
			$requests .=
'	<request result="ok">
		<sqlQuery><![CDATA['.htmlentities($query).']]></sqlQuery>
	</request>'."\n";
	}
}
// at this point, the base is up-to-date 
// and all classes are supposed to work properly




// Settings updated, compile and cache directories must be emptied
$arrayToClean = array(
	INSTALL_PATH.'/../tools/smarty/cache/',
	INSTALL_PATH.'/../tools/smarty/compile/',
	INSTALL_PATH.'/../tools/smarty_v2/cache/',
	INSTALL_PATH.'/../tools/smarty_v2/compile/');
foreach ($arrayToClean as $dir)
	if (!file_exists($dir))
		$logger->logError('directory '.$dir." doesn't exist and can't be emptied.\r\n");
	else
		foreach (scandir($dir) as $file)
			if ($file[0] != '.' AND $file != 'index.php' AND $file != '.htaccess')
				unlink($dir.$file);

// delete cache filesystem if activated
$depth = Configuration::get('PS_CACHEFS_DIRECTORY_DEPTH');
if ($depth)
{
	CacheFS::deleteCacheDirectory();
	CacheFS::createCacheDirectories((int)$depth);
}

Configuration::updateValue('PS_HIDE_OPTIMIZATION_TIPS', 0);
Configuration::updateValue('PS_NEED_REBUILD_INDEX', 1);
Configuration::updateValue('PS_VERSION_DB', INSTALL_VERSION);
$result = $warningExist ? '<action result="fail" error="34">'."\n" : '<action result="ok" error="">'."\n";
$result .= $requests;
die($result.'</action>'."\n");

