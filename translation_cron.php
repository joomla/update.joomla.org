#! /usr/local/bin/php
<?php

/**
 * A quick and dirty translation cron to generate Joomla! update xmls for TTs
 *
 * @copyright Copyright (c) 2011 Jan Erik Zassenhaus
 * @license GNU General Public License version 3, or later
 * @version 1.3 (2012-04-26)
 *
 *
 * This script useses a soap class from Samuel Moffatt.
 *
 * @copyright Copyright (c) 2011 Samuel Moffatt
 * @link https://svn.joomlacode.org/svn/pasamioprojects/gforge-apps/
 * @version 374
 */
// Protect from unauthorized access via the browser
(PHP_SAPI !== 'cli') ? die('Only command line!') : '';

require_once 'libraries/user_config.php';
require_once 'libraries/gforgeconnector.php';

echo date('Y-m-d H:m:s') . ": Starting Translation Cron Job.\n";
$translationCron = new TranslationCron($argv);
$translationCron->runCron();

final class TranslationCron
{
	/**
	 * Get the right directory seperator.
	 */
	const DS = DIRECTORY_SEPARATOR;

	/**
	 * @var string
	 * @access private
	 */
	private $error = '';

	/**
	 * @var string
	 * @access private
	 */
	private $absolutePath = '';

	/**
	 * @var string
	 * @access private
	 */
	private $detailsPath = '';

	/**
	 * @var string
	 * @access private
	 */
	private $draftsPath = '';

	/**
	 *
	 * @var string
	 * @access private
	 */
	private $savePathTranslationlist = '';

	/**
	 * @var string
	 * @access private
	 */
	private $detailsXmlUrl = '';

	/**
	 * @var array
	 * @access private
	 */
	private $detailFileNames = array();

	/**
	 * Whether we have a CLI argument -v for verbose
	 * @var boolean
	 * @access private
	 */
	private $verbose = false;

	/**
	 * Configuration with different values for different Joomla versions
	 * Selected in the CLI argument
	 *
	 * @var VersionConfig object
	 * @access private
	 */
	public $versionConfig = null;

	/**
	 * Initialise some varibales and check files and folders
	 *
	 * @access public
	 */
	public function __construct($argv)
	{

		$this->absolutePath = dirname(__FILE__);
		$this->draftsPath = $this->absolutePath . '/' . 'drafts';

		// Set $verbose
		require_once $this->absolutePath . '/libraries/' . $argv[1];
		$this->versionConfig = new VersionConfig();
		$this->setDetailsXmlUrl($this->versionConfig->updateFolder);
		$this->setSavePaths(dirname(__FILE__) . '/', $this->absolutePath . '/' . $this->versionConfig->detailsFolder . '/');

		$this->verbose = (isset($argv[2]) && ($argv[2] == '-v'));

		$this->checkFolders();
		$this->checkFiles();
	}



	/**
	 * If we have errors return them.
	 *
	 * @access public
	 */
	public function __destruct()
	{
		if (!empty($this->error))
		{
			exit($this->error);
		}
	}

	/**
	 * With this function the save paths can be set different.
	 * The filename will be added automaticaly! Please use a closing slash at the end!
	 *
	 * @param string $translationlist The translationlist.xml save path on disk.
	 * @param string $details The details.xml save path on disk.
	 * @access public
	 */
	public function setSavePaths($translationlist = '', $details = '')
	{
		$this->detailsPath = $details;
		$this->savePathTranslationlist = $translationlist;
	}

	/**
	 * With this function the URL to the details.xml can be changed.
	 * "xx-XX_details.xml" will be added automaticaly at the end. Please use a closing slash at the end!
	 *
	 * @param string $url The url to use for the details.xml
	 * @access public
	 */
	public function setDetailsXmlUrl($url)
	{
		$this->detailsXmlUrl = $url;
	}

	/**
	 * This is the main methode to run the cron.
	 *
	 * @access public
	 */
	public function runCron()
	{
		// Remove all old files before starting
		$this->deleteXMLFiles();
		$this->createXmls();

		// sleep command is needed to avoid errors in the ftp_chdir command. Not sure why.
		sleep(10);
		$this->ftpFiles();
	}

	private function checkFolders()
	{
		if (!is_dir($this->draftsPath))
		{
			if (!mkdir($this->draftsPath))
			{
				$this->error = 'Cannot create the "' . $this->draftsPath . '" folder!';
				$this->__destruct();
			}
		}

		if (!is_dir($this->detailsPath))
		{
			if (!mkdir($this->detailsPath))
			{
				$this->error = 'Cannot create the "' . $this->detailsPath . '" folder!';
				$this->__destruct();
			}
		}
	}

	private function checkFiles()
	{
		if (!is_file($this->draftsPath . '/' . 'translationlist.xml'))
		{
			$this->error = 'The "translationlist.xml" cannot be found in "' . $this->draftsPath . '" folder!';
			$this->__destruct();
		}
		elseif (!is_file($this->draftsPath . '/' . 'xx-XX_details.xml'))
		{
			$this->error = 'The "xx-XX_details.xml" cannot be found in "' . $this->draftsPath . '" folder!';
			$this->__destruct();
		}
	}

	/**
	 * Create the XMLs and write them to the disk.
	 *
	 * @access private
	 */
	private function createXmls()
	{
		$config = new Config();
		$client = new GForgeConnector($config->site, $config->soap_options);
		$client->login($config->username, $config->password);

		$project = $client->getProject('jtranslation1_6');
		$packages = $client->getFrsPackages($project->project_id);

		if ($packages === false)
		{
			$this->error = 'No packages found for project.';
			$this->__destruct();
		}

		$translationlist_xml = simplexml_load_file($this->draftsPath . '/translationlist.xml');
		foreach ($packages as $package)
		{
			$name_explode = explode('_', $package->package_name);
			$lang_tag = array_pop($name_explode);
			$name = implode(' ', $name_explode);

			$details_xml = simplexml_load_file($this->draftsPath . '/xx-XX_details.xml');
			if ($package->is_public === true && $package->status_id === 1 && $package->require_login === false)
			{
				$releases = $client->getFrsReleases($package->frs_package_id);

				// Check that some releases were found
				if ($releases === false)
				{
					continue;
				}

				$biggest_jversion = '';
				foreach ($releases as $release)
				{
					$files = $client->getFilesystems('frsrelease', $release->frs_release_id);

					// Check that some files were found
					if ($files === false)
					{
						continue;
					}

					foreach ($files as $file)
					{
						if ($file->deleted === false && substr($file->file_name, -3) === 'zip')
						{
							if (preg_match('/^' . $lang_tag . '_joomla_lang_full_[0-9]{1,2}.[0-9]{1,2}.[0-9]{1,2}v[0-9]{1,2}.zip/', $file->file_name) > 0)
							{
								if ($this->verbose) echo "Starting work on " . $file->file_name . "\n";
								$file_explode = explode('_', $file->file_name);

								$version_with_v = substr(array_pop($file_explode), 0, -4);
								$version = str_replace('v', '.', $version_with_v);

								$joomla_version_explode = explode('v', $version_with_v);
								$target_version = substr($joomla_version_explode[0], 0, 3);

								if (version_compare($target_version, '1.7', '>='))
								{
									if (empty($biggest_jversion))
									{
										$biggest_jversion = $version;
									}
									else
									{
										if (version_compare($biggest_jversion, $version, '<'))
										{
											$biggest_jversion = $version;
										}
									}

									$updates = $details_xml->addChild('update');
									$updates->addChild('name', $name);
									$updates->addChild('description', $name . ' Translation of Joomla!');
									$updates->addChild('element', 'pkg_' . $lang_tag);
									$updates->addChild('type', 'package');
									$updates->addChild('version', $version);

									$downloads = $updates->addChild('downloads');
									$downloadurl = $downloads->addChild('downloadurl', 'http://joomlacode.org' . $file->download_url);
									$downloadurl->addAttribute('type', 'full');
									$downloadurl->addAttribute('format', 'zip');

									$targetplatform = $updates->addChild('targetplatform');
									$targetplatform->addAttribute('name', 'joomla');
									$targetplatform->addAttribute('version', $target_version);
								}
							}
						}
					}

					if (!empty($details_xml))
					{
						$details_dom = new DOMDocument('1.0');
						$details_dom->preserveWhiteSpace = false;
						$details_dom->formatOutput = true;
						$details_dom->loadXML($details_xml->asXML());

						// Save XML to file
						$fileName = $this->detailsPath . '/' . $lang_tag . '_details.xml';
						if (!$details_dom->save($fileName))
						{
							if ($this->verbose) echo "Could not save $fileName\n";
						}
						else
						{
							$this->detailFileNames[$fileName] = true;
						}

					}
				}

				if (!empty($biggest_jversion))
				{
					$extension = $translationlist_xml->addChild('extension');
					$extension->addAttribute('name', $name);
					$extension->addAttribute('element', 'pkg_' . $lang_tag);
					$extension->addAttribute('type', 'package');

					$extension->addAttribute('version', $biggest_jversion);
					$extension->addAttribute('detailsurl', $this->detailsXmlUrl . $lang_tag . '_details.xml');
				}
			}
		}
		$translationlist_dom = new DOMDocument('1.0');
		$translationlist_dom->preserveWhiteSpace = false;
		$translationlist_dom->formatOutput = true;
		$translationlist_dom->loadXML($translationlist_xml->asXML());

		// Save XML to file
		$fileName = $this->savePathTranslationlist . 'translationlist.xml';
		$translationlist_dom->save($fileName);

		$client->logout();
	}


	/**
	 * Delete the old files
	 *
	 * @access private
	 */
	private function deleteXMLFiles()
	{
		// Delete all details files
		foreach(glob($this->detailsPath . '/*.xml') as $v)
		{
			unlink($v);
		}
		// Delete translation list file
		unlink($this->savePathTranslationlist . 'translationlist.xml');
	}

	/**
	 * FTP the files to the update server
	 *
	 * @access private
	 */
	private function ftpFiles()
	{
		$config = new Config();
		$fileCount = 0;
		// Get list of detail files
		$files = array_keys($this->detailFileNames);

		// Connect to FTP destination

		$connectionId = ftp_connect($config->ftpSite);
		$login = ftp_login($connectionId, $config->ftpUser, $config->ftpPassword);
		if (!$connectionId || !$login)
		{
			$this->error = "FTP error: could not log in\n";
			$this->__destruct();
		}

		// Copy translationlist.xml
		if (!ftp_chdir($connectionId, '/public_html/language'))
		{
			$this->error = "FTP cannot change directory to language\n";
			$this->__destruct();
		}

		$copy = @ftp_put($connectionId, 'translationlist.xml', $this->savePathTranslationlist . 'translationlist.xml', FTP_BINARY);
		if ($copy)
		{
			if ($this->verbose) echo "Copy of translationlist.xml was successful.\n";
			$fileCount++;
		}

		// Copy detail files
		$ftpDestination = dirname($this->detailsXmlUrl);
		if (!ftp_chdir($connectionId, '/public_html/language/' . $this->versionConfig->detailsFolder))
		{
			$this->error = "FTP cannot change directory to " . $this->versionConfig->detailsFolder . "\n";
			$this->__destruct();
		}

		foreach ($files as $fromFile)
		{
			$toFile = basename($fromFile);
			$copy = @ftp_put($connectionId, $toFile, $fromFile, FTP_BINARY);
			if ($copy)
			{
				if ($this->verbose) echo "Copy of $toFile was successful.\n";
				$fileCount++;
			}
		}
		ftp_close($connectionId);
		if ($this->verbose) echo "end of file transfer\n";
		echo date('Y-m-d H:m:s') . ": $fileCount files copied to update server.\n";
	}
}
