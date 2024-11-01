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

use Automattic\Jetpack\Constants;
use Automattic\WooCommerce\Internal\Admin\Logging\FileV2\File;
use WC_Log_Handler_File;

class LogFilePathFinder
{
	/**
	 * Returns the log file path or empty string, if the log file path have not been found.
	 *
	 * @param string $id
	 * @return string
	 */
	public function getLogFilePath(string $id): string
	{
		$filePath = $this->getFilePathWithFileV2($id);
		if (false === empty($filePath)) {
			return $filePath;
		}

		$filePath = $this->getFilePathWithWCLogHandlerFile($id);
		if (false === empty($filePath)) {
			return $filePath;
		}

		return '';
	}

	/**
	 * Get the log file path with WC_Log_Handler_File.
	 *
	 * @param string $id
	 * @return string
	 */
	private function getFilePathWithWCLogHandlerFile(string $id): string
	{
		if (false === class_exists(WC_Log_Handler_File::class)) {
			return '';
		}

		return WC_Log_Handler_File::get_log_file_path($id);
	}

	/**
	 * Get the log file path with file v2.
	 *
	 * @param string $id
	 * @return string
	 */
	private function getFilePathWithFileV2(string $id): string
	{
		if (false === class_exists(File::class)) {
			return '';
		}

		$time = time();
		$fileId = File::generate_file_id($id, null, $time);
		$hash = File::generate_hash($fileId);

		$fileName = "$fileId-$hash.log";

		return $this->getLogDirectory() . $fileName;
	}

	/**
	 * Get the log directory.
	 *
	 * @return string
	 */
	private function getLogDirectory(): string
	{
		return trailingslashit(Constants::get_constant('WC_LOG_DIR'));
	}
}
