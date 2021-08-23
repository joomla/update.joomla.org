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

require_once __DIR__ . '/libraries/user_config.php';
require_once __DIR__ . '/libraries/gforgeconnector.php';

echo date('Y-m-d H:m:s') . ": Starting Translation Cron Job.\n";
$translationCron = new TranslationCron($argv);
$translationCron->runCron();

final class TranslationCron
{
	/**
	 * @var string
	 */
	private $error = '';

	/**
	 * @var string
	 */
	private $absolutePath = '';

	/**
	 * @var string
	 */
	private $detailsPath = '';

	/**
	 * @var string
	 */
	private $draftsPath = '';

	/**
	 * @var string
	 */
	private $savePathTranslationlist = '';

	/**
	 * @var string
	 */
	private $detailsXmlUrl = '';

	/**
	 * @var array
	 */
	private $detailFileNames = array();

	/**
	 * Whether we have a CLI argument -v for verbose
	 *
	 * @var boolean
	 */
	private $verbose = false;

	/**
	 * The list of J4 language packs to mark them as comptible
	 *
	 * @var array
	 */
	private $j4LanguagePack = array(
		'ar-AA',
		'eu-ES',
		'ca-ES',
		'zh-CN',
		'zh-TW',
		'hr-HR',
		'da-DK',
		'nl-NL',
		'en-AU',
		'en-US',
		'eo-XX',
		'nl-BE',
		'fr-FR',
		'ka-GE',
		'de-DE',
		'de-AT',
		'de-LI',
		'de-LU',
		'de-CH',
		'el-GR',
		'hu-HU',
		'id-ID',
		'it-IT',
		'ja-JP',
		'km-KH',
		'lv-LV',
		'nb-NO',
		'fa-IR',
		'pl-PL',
		'pt-PT',
		'ro-RO',
		'ru-RU',
		'sk-SK',
		'sl-SI',
		'es-ES',
		'sw-KE',
		'sv-SE',
		'ta-IN',
		'th-TH',
		'uk-UA',
		'cy-GB',
	);

	/**
	 * Configuration with different values for different Joomla versions
	 * Selected in the CLI argument
	 *
	 * @var VersionConfig
	 */
	public $versionConfig = null;

	/**
	 * Initialise some variables and check files and folders
	 */
	public function __construct($argv)
	{
		$this->absolutePath = __DIR__;
		$this->webRoot      = dirname(__DIR__) . '/public_html';
		$this->draftsPath   = $this->absolutePath . '/drafts';

		// Set $verbose
		require_once $this->absolutePath . '/libraries/' . $argv[1];
		$this->versionConfig = new VersionConfig();
		$this->setDetailsXmlUrl($this->versionConfig->updateFolder);
		$this->setSavePaths($this->absolutePath, $this->absolutePath . '/' . $this->versionConfig->detailsFolder);

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
	 * @param string $translationlist The translationlist xml save path on disk.
	 * @param string $details The details.xml save path on disk.
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
	 */
	public function setDetailsXmlUrl($url)
	{
		$this->detailsXmlUrl = $url;
	}

	/**
	 * This is the main method to run the cron.
	 */
	public function runCron()
	{
		// Remove all old files before starting
		$this->deleteXMLFiles();
		$this->createXmls();

		// Get list of detail files
		$files = array_keys($this->detailFileNames);

		$this->moveFiles();
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
		if (!is_file($this->draftsPath . '/translationlist.xml'))
		{
			$this->error = 'The "translationlist.xml" cannot be found in "' . $this->draftsPath . '" folder!';
			$this->__destruct();
		}
		elseif (!is_file($this->draftsPath . '/xx-XX_details.xml'))
		{
			$this->error = 'The "xx-XX_details.xml" cannot be found in "' . $this->draftsPath . '" folder!';
			$this->__destruct();
		}
	}

	/**
	 * Create the XMLs and write them to the disk.
	 */
	private function createXmls()
	{
		$config = new Config();
		$client = new GForgeConnector($config->site, $config->soap_options);
		$client->login($config->username, $config->password);

		$project = $client->getProject($this->versionConfig->project);
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
								if (isset($this->versionConfig->targetPlatformSuffix))
								{
									$target_version = substr($target_version, 0, 2) . $this->versionConfig->targetPlatformSuffix;
								}

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

									// Set packages for 4.0
									if ($this->versionConfig->detailsFolder === 'details3' && in_array($lang_tag, $this->j4LanguagePack))
									{
										$target_version = '[34].' . $this->versionConfig->targetPlatformSuffix;
									}

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
		$fileName = $this->savePathTranslationlist . '/' . $this->versionConfig->xmlFile;
		$translationlist_dom->save($fileName);

		$client->logout();
	}

	/**
	 * Delete the old files
	 */
	private function deleteXMLFiles()
	{
		// Delete all details files
		foreach(glob($this->detailsPath . '/*.xml') as $v)
		{
			@unlink($v);
		}

		// Delete translation list file
		if (file_exists($this->savePathTranslationlist . '/' . $this->versionConfig->xmlFile))
		{
			@unlink($this->savePathTranslationlist . '/' . $this->versionConfig->xmlFile);
		}
	}

	/**
	 * Move files to the web root
	 *
	 * @return void
	 */
	private function moveFiles()
	{
		$fileCount = 0;

		// Move the translationlist
		$src = $this->savePathTranslationlist . '/' . $this->versionConfig->xmlFile;
		$dest = str_replace($this->savePathTranslationlist, $this->webRoot . '/language', $src);

		if (@rename($src, $dest))
		{
			if ($this->verbose)
			{
				echo "Moving of $dest was successful.\n";
			}

			$fileCount++;
		}

		// Get list of detail files
		$files = array_keys($this->detailFileNames);

		foreach ($files as $file)
		{
			$src  = $file;
			$dest = str_replace($this->absolutePath, $this->webRoot . '/language', $file);

			if (@rename($src, $dest))
			{
				if ($this->verbose)
				{
					echo "Moving of $dest was successful.\n";
				}

				$fileCount++;
			}
		}

		if ($this->verbose)
		{
			echo "Finished moving files\n";
		}

		echo date('Y-m-d H:m:s') . ": $fileCount files copied to update server.\n";
	}
}
