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

namespace OneTeamSoftware\WC\Logger;

class LoggerInstance
{
	/**
	 * @var array
	 */
	private static $instances = [];

	/**
	 * returns instance of a logger for a given id
	 *
	 * @param string $id
	 * @return Logger
	 */
	public static function getInstance(string $id): Logger
	{
		if (empty(self::$instances[$id])) {
			self::$instances[$id] = new Logger($id);
		}

		return self::$instances[$id];
	}
}
