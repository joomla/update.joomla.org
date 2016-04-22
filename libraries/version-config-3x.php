<?php
/**
 * Version configuration file for version 3.x
 * @author Mark Dexter
 *
 */
class VersionConfig
{
	// Name of XML file on update site
	var $xmlFile = 'translationlist_3.xml';

	// Name of details folder on cron server
	var $detailsFolder = 'details3';

	// Name (file path) of project on Joomlacode.org where language files can be downloaded
	var $project = 'jtranslation3_x';

	// Name of folder on update site where detail XML files are found for each language
	var $updateFolder = 'https://update.joomla.org/language/details3/';

	// Optional range of versions for the targetplatform attribute suffix
	var $targetPlatformSuffix = '[0123456]';
}
