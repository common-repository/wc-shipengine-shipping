<?php

/*********************************************************************
 *  PROGRAM          FlexRC                                          *
 *  PROPERTY         3-7170 Ash Cres                                 *
 *  OF               Vancouver BC   V6P 3K7                          *
 *  				 Voice 604 800-7879                              *
 *                                                                   *
 *  Any usage / copying / extension or modification without          *
 *  prior authorization is prohibited                                *
 *********************************************************************/

declare(strict_types=1);

namespace OneTeamSoftware\Logger;

interface LoggerInterface
{
	/**
	 * log a message for a given level
	 *
	 * @param string $level
	 * @param string $file
	 * @param int $line
	 * @param string $message
	 * @param mixed ...$args
	 * @return void
	 */
	public function log(string $level, string $file, int $line, string $message, ...$args): void;

	/**
	 * log a message for with debug level
	 *
	 * @param string $file
	 * @param int $line
	 * @param string $message
	 * @param mixed ...$args
	 * @return void
	 */
	public function debug(string $file, int $line, string $message, ...$args): void;

	/**
	 * log a message for with info level
	 *
	 * @param string $file
	 * @param int $line
	 * @param string $message
	 * @param mixed ...$args
	 * @return void
	 */
	public function info(string $file, int $line, string $message, ...$args): void;

	/**
	 * log a message for with notice level
	 *
	 * @param string $file
	 * @param int $line
	 * @param string $message
	 * @param mixed ...$args
	 * @return void
	 */
	public function notice(string $file, int $line, string $message, ...$args): void;

	/**
	 * log a message for with warning level
	 *
	 * @param string $file
	 * @param int $line
	 * @param string $message
	 * @param mixed ...$args
	 * @return void
	 */
	public function warning(string $file, int $line, string $message, ...$args): void;

	/**
	 * log a message for with error level
	 *
	 * @param string $file
	 * @param int $line
	 * @param string $message
	 * @param mixed ...$args
	 * @return void
	 */
	public function error(string $file, int $line, string $message, ...$args): void;

	/**
	 * log a message for with critical level
	 *
	 * @param string $file
	 * @param int $line
	 * @param string $message
	 * @param mixed ...$args
	 * @return void
	 */
	public function critical(string $file, int $line, string $message, ...$args): void;

	/**
	 * log a message for with alert level
	 *
	 * @param string $file
	 * @param int $line
	 * @param string $message
	 * @param mixed ...$args
	 * @return void
	 */
	public function alert(string $file, int $line, string $message, ...$args): void;

	/**
	 * log a message for with emergency level
	 *
	 * @param string $file
	 * @param int $line
	 * @param string $message
	 * @param mixed ...$args
	 * @return void
	 */
	public function emergency(string $file, int $line, string $message, ...$args): void;
}
