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

use OneTeamSoftware\Logger\AbstractLogger;

class Logger extends AbstractLogger
{
	/**
	 * @var string
	 */
	private $id;

	/**
	 * @var bool
	 */
	private $enabled;

	/**
	 * @var \WC_Logger_Interface
	 */
	private $logger;

	/**
	 * @var string
	 */
	private $traceId;

	/**
	 * constructor
	 *
	 * @param string $id
	 * @param bool $enabled
	 */
	public function __construct(string $id, bool $enabled = false)
	{
		$this->id = $id;
		$this->enabled = $enabled;
		$this->logger = null;
		$this->traceId = uniqid();
	}

	/**
	 * sets if logged should be enabled
	 *
	 * @param bool $enabled
	 * @return void
	 */
	public function setEnabled(bool $enabled): void
	{
		$this->enabled = $enabled;
	}

	/**
	 * inits loggers
	 *
	 * @return boolean
	 */
	public function initLogger(): bool
	{
		if (function_exists('wc_get_logger') && is_null($this->logger)) {
			$this->logger = wc_get_logger();
		}

		return is_object($this->logger);
	}

	/**
	 * logs a message with a given logger
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
		// do not log if not enabled
		if (false === $this->enabled) {
			return;
		}

		// assemble log message
		if (false === empty($args) && is_array($args)) {
			$message = (string)sprintf($message, ...$args);
		}

		$message = $this->formatMessage($file, $line, $message);

		// log to woocommerce logger, when installed
		if ($this->initLogger()) {
			$this->logger->log($level, $message, ['source' => $this->id, 'trace_id' => $this->traceId]); // phpcs:ignore
			return;
		}

		// fallback to generic debug file, when woocommerce is not installed
		$this->writeToDebugFile($message);
	}

	/**
	 * writes message to the generic debug file
	 *
	 * @param string $message
	 * @return void
	 */
	private function writeToDebugFile(string $message): void
	{
		$handle = @fopen(WP_CONTENT_DIR . '/debug.log', 'a');
		if (!$handle) {
			return;
		}

		fwrite($handle, date('Y-m-d\TH:i:sP ') . $message . PHP_EOL);
		fclose($handle);
	}

	/**
	 * formats message
	 *
	 * @param string $file
	 * @param int $line
	 * @param string $message
	 * @return string
	 */
	private function formatMessage(string $file, int $line, string $message): string
	{
		return sprintf('[%s:%s:%s] %s', $this->traceId, basename($file), $line, $message);
	}
}
