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

class EchoLogger extends AbstractLogger
{
	/**
	 * prints message for a given level
	 *
	 * @param string $level
	 * @param string $file
	 * @param int $line
	 * @param string $message
	 * @param mixed ...$args
	 * @return void
	 */
	public function log(string $level, string $file, int $line, string $message, ...$args): void
	{
		echo sprintf("[%s:%s] %s\n", basename($file), $line, sprintf($message, ...$args));
	}
}
