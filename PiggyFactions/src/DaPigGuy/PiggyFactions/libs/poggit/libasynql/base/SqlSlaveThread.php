<?php

/*
 * libasynql
 *
 * Copyright (C) 2018 SOFe
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace DaPigGuy\PiggyFactions\libs\poggit\libasynql\base;

use InvalidArgumentException;
use pmmp\thread\Thread as NativeThread;
use pocketmine\Server;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\thread\Thread;
use DaPigGuy\PiggyFactions\libs\poggit\libasynql\libasynql;
use DaPigGuy\PiggyFactions\libs\poggit\libasynql\SqlError;
use DaPigGuy\PiggyFactions\libs\poggit\libasynql\SqlResult;
use DaPigGuy\PiggyFactions\libs\poggit\libasynql\SqlThread;

abstract class SqlSlaveThread extends Thread implements SqlThread{
	private SleeperHandlerEntry $sleeperEntry;

	private static $nextSlaveNumber = 0;

	protected $slaveNumber;
	protected $bufferSend;
	protected $bufferRecv;
	protected $connCreated = false;
	protected $connError;
	protected $busy = false;

	protected function __construct(SleeperHandlerEntry $entry, QuerySendQueue $bufferSend = null, QueryRecvQueue $bufferRecv = null){
		$this->sleeperEntry = $entry;

		$this->slaveNumber = self::$nextSlaveNumber++;
		$this->bufferSend = $bufferSend ?? new QuerySendQueue();
		$this->bufferRecv = $bufferRecv ?? new QueryRecvQueue();

		if(!libasynql::isPackaged()){
			/** @noinspection PhpUndefinedMethodInspection */
			/** @noinspection NullPointerExceptionInspection */
			/** @var ClassLoader $cl */
			$cl = Server::getInstance()->getPluginManager()->getPlugin("DEVirion")->getVirionClassLoader();
			$this->setClassLoaders([Server::getInstance()->getLoader(), $cl]);
		}
		$this->start(NativeThread::INHERIT_INI);
	}

	protected function onRun() : void{
		$error = $this->createConn($resource);
		$this->connCreated = true;
		$this->connError = $error;

		$notifier = $this->sleeperEntry->createNotifier();

		if($error !== null){
			return;
		}

		while(true){
			$row = $this->bufferSend->fetchQuery();
			if(!is_string($row)){
				break;
			}
			$this->busy = true;
			[$queryId, $modes, $queries, $params] = unserialize($row, ["allowed_classes" => true]);

			try{
				$results = [];
				foreach($queries as $index => $query){
					$results[] = $this->executeQuery($resource, $modes[$index], $query, $params[$index]);
				}
				$this->bufferRecv->publishResult($queryId, $results);
			}catch(SqlError $error){
				$this->bufferRecv->publishError($queryId, $error);
			}

			$notifier->wakeupSleeper();
			$this->busy = false;
		}
		$this->close($resource);
	}

	/**
	 * @return bool
	 */
	public function isBusy() : bool{
		return $this->busy;
	}

	public function stopRunning() : void{
		$this->bufferSend->invalidate();

		parent::quit();
	}

	public function quit() : void{
		$this->stopRunning();
		parent::quit();
	}

	public function addQuery(int $queryId, array $modes, array $queries, array $params) : void{
		$this->bufferSend->scheduleQuery($queryId, $modes, $queries, $params);
	}

	public function readResults(array &$callbacks, ?int $expectedResults) : void{
		if($expectedResults === null){
			$resultsList = $this->bufferRecv->fetchAllResults();
		}else{
			$resultsList = $this->bufferRecv->waitForResults($expectedResults);
		}
		foreach($resultsList as [$queryId, $results]){
			if(!isset($callbacks[$queryId])){
				throw new InvalidArgumentException("Missing handler for query #$queryId");
			}

			$callbacks[$queryId]($results);
			unset($callbacks[$queryId]);
		}
	}

	public function connCreated() : bool{
		return $this->connCreated;
	}

	public function hasConnError() : bool{
		return $this->connError !== null;
	}

	public function getConnError() : ?string{
		return $this->connError;
	}

	protected abstract function createConn(&$resource) : ?string;

	/**
	 * @param mixed   $resource
	 * @param int     $mode
	 * @param string  $query
	 * @param mixed[] $params
	 *
	 * @return SqlResult
	 * @throws SqlError
	 */
	protected abstract function executeQuery($resource, int $mode, string $query, array $params) : SqlResult;


	protected abstract function close(&$resource) : void;
}
