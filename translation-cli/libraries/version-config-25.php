<?php
/**
 * Version configuration file for version 2.5.x
 * @author Mark Dexter
 *
 */
class VersionConfig
{
	// Name of XML file on update site
    var $xmlFile = 'translationlist.xml';

    // Name of details folder on cron server
    var $detailsFolder = 'details';

    // Name (file path) of project on Joomlacode.org where language files can be downloaded
	var $project = 'jtranslation1_6';

	// Name of folder on update site where detail XML files are found for each language
	var $updateFolder = 'http://update.joomla.org/language/details/';
}
