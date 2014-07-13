<?php

namespace PrestaShop\ShopCapability;

class DatabaseManagement extends ShopCapability
{
	public function getPDO()
	{
		static $pdo;

		$shop = $this->getShop();

		try {
			if (!$pdo)
				$pdo = new \PDO('mysql:host='.$shop->getMysqlHost().';port='.$shop->getMysqlPort().';dbname='.$shop->getMysqlDatabase(),
					$shop->getMysqlUser(),
					$shop->getMysqlPass()
				);
		} catch (\Exception $e) {
			$pdo = null;
		}

		return $pdo;
	}

	/**
	* Drop the database if it exists
	* @return true if the database existed, false otherwise
	*/
	public function dropDatabaseIfExists()
	{
		$h = $this->getPDO();
		if ($h) {
			$sql = 'DROP DATABASE `'.$this->getShop()->getMysqlDatabase().'`';
			$res = $h->exec($sql);
			return $res;
		}
		return false;
	}

	public function buildMysqlCommand($command, array $arguments)
	{
		$command = $command
		.' -u'.escapeshellcmd($this->getShop()->getMysqlUser())
		.' -p'.escapeshellcmd($this->getShop()->getMysqlPass())
		.' -h'.escapeshellcmd($this->getShop()->getMysqlHost())
		.' -P'.escapeshellcmd($this->getShop()->getMysqlPort())
		.implode('', array_map(function($arg){return ' '.escapeshellcmd($arg);}, $arguments))
		.' 2>/dev/null'; // quickfix for warning about using password on command line

		return $command;
	}

	public function duplicateDatabaseTo($new_database_name)
	{
		$old_database_name = $this->getShop()->getMysqlDatabase();

		$commands = [
			$this->buildMysqlCommand('mysqladmin', ['create', $new_database_name]),
			$this->buildMysqlCommand('mysqldump', [$old_database_name])
			.' | '.$this->buildMysqlCommand('mysql', [$new_database_name])
		];	

		foreach ($commands as $command)
		{
			exec($command);
		}
	}
}
