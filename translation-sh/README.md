# Language XML Stream v1.0

These scripts are used to extract the language pack xml files from the Joomla! download site and make them statically publicly available.

# Okay, Lets get started... ˘Ô≈ôﺣ

Should you like to contribute any improvements either in code or conduct, just open an issue as the first step, and beginning of the conversation. ツ

## Setup the Builder

Clone this repository
```bash
$ git clone https://github.com/joomla/update.joomla.org.git
$ cd update.joomla.org/translation-sh/
```

## Run the Builder

Make sure that the following files are executable.
```bash
$ sudo chmod +x run.sh
```

Start the Building process
```bash
$ ./run.sh
```

# Help Menu
```txt
Usage: ./run.sh [OPTION...]

You are able to change a few default behaviours in the XML Static File extractor
  ------ Passing no command options will fallback on the defaults -------

	Options ᒡ◯ᵔ◯ᒢ
	======================================================
   --endpoint=<url>
    set the endpoint of the xml retrieval

    example: ./run.sh --endpoint=https://downloads.joomla.org/index.php?option=com_languagepack&view=export&format=xml
    ======================================================
   --version-key=<word>
    set the version key used in URL

    example: ./run.sh --version-key=cms_version
    ======================================================
   --language-key=<word>
    set the language key used in URL

    example: ./run.sh --language-key=language_code
    ======================================================
   --mapper=<path>
    set all the mapper details with a file
    the repo/translation-sh/conf/mapper.tmp has more details of the format

    example: ./run.sh --conf=/home/username/.config/xml-stream-mapper.conf

    defaults:
        - repo/translation-sh/conf/.mapper
    ======================================================
   --push
    push changes to github (only if there are changes)
        - must be able to push (ssh authentication needed)

    example: ./run.sh --push
    ======================================================
   --target-folder=<path>
    set folder where we place the XML static files

    example: ./run.sh --target-folder=/home/username/public_html/language/

    defaults:
        - /home/username/public_html/language/
    ======================================================
   --conf=<path>
    set all the config properties with a file

    example: ./run.sh --conf=/home/username/.config/xml-stream.conf

    defaults:
        - repo/translation-sh/conf/.config
    ======================================================
   --dry
    To show all defaults, and not update repo

    example: ./run.sh --dry
    ======================================================
   -q|--quiet
    Quiet mode that prevent all messages from showing progress

    example: ./run.sh -q
    example: ./run.sh --quiet
    ======================================================
   -h|--help
    display this help menu

    example: ./run.sh -h
    example: ./run.sh --help
    ======================================================
            Joomla XML Stream v1.0
    ======================================================
```

# Setup Cron Job

To run this in a crontab
```bash
$ crontab -e
```
Then add the following line, update the time as needed
```bash
10 5 * * MON /home/username/translation-sh/run.sh >> /home/username/translation-sh/stream.log 2>&1
```

### Free Software
```txt
Copyright (C) 2019. All Rights Reserved
GNU/GPL Version 2 or later - http://www.gnu.org/licenses/gpl-2.0.html
```

