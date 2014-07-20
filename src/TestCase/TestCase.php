<?php

namespace PrestaShop\TestCase;

use \PrestaShop\ShopManager;
use \PrestaShop\Shop;

abstract class TestCase extends \PHPUnit_Framework_TestCase implements \PrestaShop\Ptest\TestClass\Basic
{
	private static $shops = [];
	private static $shop_managers = [];

	public static function setUpBeforeClass()
	{
		\PrestaShop\SeleniumManager::ensureSeleniumIsRunning();
		$class = get_called_class();
		$manager = ShopManager::getInstance();
		self::$shops[$class] = $manager->getShop();
		self::$shop_managers[$class] = $manager;
	}

	public static function beforeAll()
	{

	}

	public static function tearDownAfterClass()
	{
		static::getShopManager()->cleanUp(static::getShop());
	}

	public static function getShop()
	{
		$class = get_called_class();
		return self::$shops[$class];
	}

	public static function getShopManager()
	{
		$class = get_called_class();
		return self::$shop_managers[$class];
	}

	public function setUp()
	{
		$this->shop = static::getShop();
		
		static::getShop()->getBrowser()->clearCookies();
		// TODO: Save state of shop
	}

	public function tearDown()
	{
		// TODO: restore state of shop
	}
}
