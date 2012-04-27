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

$translationCron = new TranslationCron();
$translationCron->setDetailsXmlUrl('http://update.joomla.org/details/');
$translationCron->setSavePaths('', 'details/');
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
     *
     * @var string
     * @access private
     */
    private $savePathDetails = '';

    /**
     * @var string
     * @access private
     */
    private $detailsXmlUrl = '';



    /**
     * Initialise some varibales and check files and folders
     *
     * @access public
     */
    public function __construct()
    {
        $this->absolutePath = dirname(__FILE__);
        $this->detailsPath = $this->absolutePath . self::DS . 'details';
        $this->draftsPath = $this->absolutePath . self::DS . 'drafts';

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
        $this->savePathDetails = $details;
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
        $this->createXmls();
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
        if (!is_file($this->draftsPath . self::DS . 'translationlist.xml'))
        {
            $this->error = 'The "translationlist.xml" cannot be found in "' . $this->draftsPath . '" folder!';
            $this->__destruct();
        }
        elseif (!is_file($this->draftsPath . self::DS . 'xx-XX_details.xml'))
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
        require_once 'libraries/user_config.php';
        require_once 'libraries/gforgeconnector.php';

        $config = new Config();
        $client = new GForgeConnector($config->site, $config->soap_options);
        $client->login($config->username, $config->password);

        $project = $client->getProject('jtranslation1_6');
        $packages = $client->getFrsPackages($project->project_id);

        $translationlist_xml = simplexml_load_file('drafts/translationlist.xml');
        foreach ($packages as $package)
        {
            $name_explode = explode('_', $package->package_name);
            $lang_tag = array_pop($name_explode);
            $name = implode(' ', $name_explode);

            $details_xml = simplexml_load_file('drafts/xx-XX_details.xml');
            if ($package->is_public === true && $package->status_id === 1 && $package->require_login === false) // && $package->package_name === 'German_de-DE'
            {
                $releases = $client->getFrsReleases($package->frs_package_id);

                $biggest_jversion = '';
                //$biggest_tiny_version = '';
                foreach ($releases as $release)
                {
                    $files = $client->getFilesystems('frsrelease', $release->frs_release_id);

                    foreach ($files as $file)
                    {
                        if ($file->deleted === false && substr($file->file_name, -3) === 'zip')
                        {
                            if (preg_match('/^' . $lang_tag . '_joomla_lang_full_[0-9]{1,2}.[0-9]{1,2}.[0-9]{1,2}v[0-9]{1,2}.zip/', $file->file_name) > 0)
                            {
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

                            /*
                            if (preg_match('/^' . $lang_tag . '_TinyMCE_[0-9]{1,2}.[0-9]{1,2}v[0-9]{1,2}.zip/', $file->file_name) > 0)
                            {
                                $file_explode = explode('_', $file->file_name);

                                $version_with_v = substr(array_pop($file_explode), 0, -4);
                                $version = str_replace('v', '.', $version_with_v);

                                $target_version_explode = explode('v', $version_with_v);
                                $target_version = substr($target_version_explode[0], 0, 3);

                                if (version_compare($target_version, '1.7', '>='))
                                {

                                    if (empty($biggest_tiny_version))
                                    {
                                        $biggest_tiny_version = $version;
                                    }
                                    else
                                    {
                                        if (version_compare($biggest_tiny_version, $version, '<'))
                                        {
                                            $biggest_tiny_version = $version;
                                        }
                                    }

                                    $updates = $details_xml->addChild('update');
                                    $updates->addChild('name', $name . ' TinyMCE');
                                    $updates->addChild('description', $name . ' Translation of TinyMCE for Joomla!');
                                    $updates->addChild('element', 'file_tinymce_' . $lang_tag);
                                    $updates->addChild('type', 'file');
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
                            */
                        }
                    }

                    if (!empty($details_xml))
                    {
                        $details_dom = new DOMDocument('1.0');
                        $details_dom->preserveWhiteSpace = false;
                        $details_dom->formatOutput = true;
                        $details_dom->loadXML($details_xml->asXML());

                        // Save XML to file
                        $details_dom->save($this->savePathDetails . $lang_tag . '_details.xml');
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

                /*
                if (!empty($biggest_tiny_version))
                {
                    $extension = $translationlist_xml->addChild('extension');
                    $extension->addAttribute('name', $name . ' TinyMCE');
                    $extension->addAttribute('element', 'file_tinymce_' . $lang_tag);
                    $extension->addAttribute('type', 'file');

                    $extension->addAttribute('version', $biggest_tiny_version);
                    $extension->addAttribute('detailsurl', $this->detailsXmlUrl . $lang_tag . '_details.xml');
                }
                */
            }
        }
        $translationlist_dom = new DOMDocument('1.0');
        $translationlist_dom->preserveWhiteSpace = false;
        $translationlist_dom->formatOutput = true;
        $translationlist_dom->loadXML($translationlist_xml->asXML());

        // Save XML to file
        $translationlist_dom->save($this->savePathTranslationlist . 'translationlist.xml');

        $client->logout();
    }
}

?>