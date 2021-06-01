#! /bin/bash

# Do some prep work
command -v wget >/dev/null 2>&1 || {
  echo >&2 "We require wget for this script to run, but it's not installed.  Aborting."
  exit 1
}

# get start time
STARTBUILD=$(date +"%s")
# use UTC+00:00 time also called zulu
STARTDATE=$(TZ=":ZULU" date +"%m/%d/%Y @ %R (UTC)")
# main project Header
HEADERTITLE="Joomla XML Stream v1.0"

# main function ˘Ô≈ôﺣ
function main() {
  # make sure we have a mapper file
  if [ ! -f $REPO_MAPPER ]; then
    showMessage "${USER^}, you must have a mapper file, see help (${0##*/:-} -h)"
    exit 1
  fi
  # make sure the directory is set
  mkdir -p "${TARGET_FOLDER}"
  # now get the mapper data
  while IFS=$'\t' read -r -a row; do
    [[ "$row" =~ ^#.*$ ]] && continue
    # set the url
    URL_MAIN_LANG="${STREAM_ENDPOINT}&${URL_VERSION_KEY}=${row[0]}"
    # set the path
    PATH_MAIN_FILE="${TARGET_FOLDER}${row[1]}.xml"
    # get the main translation file of this version
    showMessage "Downloading: ${URL_MAIN_LANG}\nStoring@: ${PATH_MAIN_FILE}"
    # do the work now
    wget -q "${URL_MAIN_LANG}" --output-document="${PATH_MAIN_FILE}"
    # now get each translation pack
    while readXML; do
      # get the language tag
      LANG=$(getElement)
      # act only if we have valid tag
      if [ -n "${LANG}" ]; then
        # set the url
        URL_LANG="${STREAM_ENDPOINT}&${URL_VERSION_KEY}=${row[0]}&${URL_LANGUAGE_KEY}=${LANG}"
        # set the path
        PATH_LANG="${TARGET_FOLDER}${row[2]}"
        # set the path
        PATH_FILE="${PATH_LANG}/${LANG}_details.xml"
        # get the language pack file
        showMessage "Downloading: ${URL_LANG}\nStoring@: ${PATH_FILE}"
        # make sure the directory is set
        mkdir -p "${PATH_LANG}"
        # do the work now
        wget -q "${URL_LANG}" --output-document="${PATH_FILE}"
      fi
    done <"${PATH_MAIN_FILE}"
  done <"$REPO_MAPPER"
  # show completion message
  completedBuildMessage
  exit 0
}

# show message
function showMessage() {
  if (("$QUIET" == 0)); then
    echo -e "${1}"
  fi
}

# completion message
function completedBuildMessage() {
  # give completion message
  if (("$QUIET" == 0)); then
    # set the build time
    ENDBUILD=$(date +"%s")
    SECONDSBUILD=$((ENDBUILD - STARTBUILD))
    # use UTC+00:00 time also called zulu
    ENDDATE=$(TZ=":ZULU" date +"%m/%d/%Y @ %R (UTC)")
    echo "${HEADERTITLE} build on ${STARTDATE} is completed in ${SECONDSBUILD} seconds!"
  fi
}

# our XML helpers
# https://stackoverflow.com/a/7052168/1429677
function readXML() {
  local IFS=\>
  read -d \< ENTITY CONTENT
  local ret=$?
  TAG_NAME=${ENTITY%% *}
  ATTRIBUTES=${ENTITY#* }
  return $ret
}
function getElement() {
  if [[ $TAG_NAME == "extension" ]]; then
    eval local $ATTRIBUTES
    echo "$element"
  else
    echo ''
  fi
}

# set any/all default config property
function setDefaults() {
  if [ -f $CONFIG_FILE ]; then
    # set all defaults
    STREAM_ENDPOINT=$(getDefault "xml.stream.endpoint" "${STREAM_ENDPOINT}")
    URL_VERSION_KEY=$(getDefault "xml.stream.url.version.key" "${URL_VERSION_KEY}")
    URL_LANGUAGE_KEY=$(getDefault "xml.stream.url.language.key" "${URL_LANGUAGE_KEY}")
    REPO_MAPPER=$(getDefault "xml.stream.repo.mapper" "${REPO_MAPPER}")
    TARGET_FOLDER=$(getDefault "xml.stream.target.folder" "${TARGET_FOLDER}")
    QUIET=$(getDefault "xml.stream.build.quiet" "$QUIET")
  fi
}

# get default properties from config file
function getDefault() {
  PROP_KEY="$1"
  PROP_VALUE=$(cat $CONFIG_FILE | grep "$PROP_KEY" | cut -d'=' -f2)
  echo "${PROP_VALUE:-$2}"
}

# help message ʕ•ᴥ•ʔ
function show_help() {
  cat <<EOF
Usage: ${0##*/:-} [OPTION...]

You are able to change a few default behaviours in the XML Stream Generator
  ------ Passing no command options will fallback on the defaults -------

	Options
	======================================================
   --endpoint=<url>
	set the endpoint of the xml retrieval

	example: ${0##*/:-} --endpoint=https://downloads.joomla.org/index.php?option=com_languagepack&view=export&format=xml
	======================================================
   --version-key=<word>
	set the version key used in URL

	example: ${0##*/:-} --version-key=cms_version
	======================================================
   --language-key=<word>
	set the language key used in URL

	example: ${0##*/:-} --language-key=language_code
	======================================================
   --mapper=<path>
	set all the mapper details with a file
	the repo/translation-sh/conf/mapper.tmp has more details of the format

	example: ${0##*/:-} --conf=/home/$USER/.config/xml-stream-mapper.conf

	defaults:
		- repo/translation-sh/conf/.mapper
	======================================================
   --push
	push changes to github (only if there are changes)
		- must be able to push (ssh authentication needed)

	example: ${0##*/:-} --push
	======================================================
   --target-folder=<path>
	set folder where we place the XML static files

	example: ${0##*/:-} --target-folder=/home/$USER/public_html/language/

	defaults:
		- /home/$USER/public_html/language/
	======================================================
   --conf=<path>
	set all the config properties with a file

	example: ${0##*/:-} --conf=/home/$USER/.config/xml-stream.conf

	defaults:
		- repo/translation-sh/conf/.config
	======================================================
   --dry
	To show all defaults, and not update repo

	example: ${0##*/:-} --dry
	======================================================
   -q|--quiet
	Quiet mode that prevent all messages from showing progress

	example: ${0##*/:-} -q
	example: ${0##*/:-} --quiet
	======================================================
   -h|--help
	display this help menu

	example: ${0##*/:-} -h
	example: ${0##*/:-} --help
	======================================================
			${HEADERTITLE}
	======================================================
EOF
}

# SET THE DEFAULTS
STREAM_ENDPOINT="https://downloads.joomla.org/index.php?option=com_languagepack&view=export&format=xml"
URL_VERSION_KEY="cms_version"
URL_LANGUAGE_KEY="language_code"
REPO_MAPPER='conf/.mapper'
TARGET_FOLDER="/home/$USER/public_html/language/"
CONFIG_FILE='conf/.config'
DRYRUN=0
QUIET=0

# check if we have options
while :; do
  case $1 in
  -h | --help)
    show_help # Display a usage synopsis.
    exit
    ;;
  -q | --quiet)
    QUIET=1
    ;;
  --dry)
    DRYRUN=1
    ;;
  --version-key) # Takes an option argument; ensure it has been specified.
    if [ "$2" ]; then
      URL_VERSION_KEY=$2
      shift
    else
      echo 'ERROR: "--version-key" requires a non-empty option argument.'
      exit 1
    fi
    ;;
  --version-key=?*)
    URL_VERSION_KEY=${1#*=} # Delete everything up to "=" and assign the remainder.
    ;;
  --version-key=) # Handle the case of an empty --version-key=
    echo 'ERROR: "--version-key" requires a non-empty option argument.'
    exit 1
    ;;
  --language-key) # Takes an option argument; ensure it has been specified.
    if [ "$2" ]; then
      URL_LANGUAGE_KEY=$2
      shift
    else
      echo 'ERROR: "--language-key" requires a non-empty option argument.'
      exit 1
    fi
    ;;
  --language-key=?*)
    URL_LANGUAGE_KEY=${1#*=} # Delete everything up to "=" and assign the remainder.
    ;;
  --language-key=) # Handle the case of an empty --language-key=
    echo 'ERROR: "--version-key" requires a non-empty option argument.'
    exit 1
    ;;
  --endpoint) # Takes an option argument; ensure it has been specified.
    if [ "$2" ]; then
      STREAM_ENDPOINT=$2
      shift
    else
      echo 'ERROR: "--endpoint" requires a non-empty option argument.'
      exit 1
    fi
    ;;
  --endpoint=?*)
    STREAM_ENDPOINT=${1#*=} # Delete everything up to "=" and assign the remainder.
    ;;
  --endpoint=) # Handle the case of an empty --endpoint=
    echo 'ERROR: "--endpoint" requires a non-empty option argument.'
    exit 1
    ;;
  --mapper) # Takes an option argument; ensure it has been specified.
    if [ "$2" ]; then
      REPO_MAPPER=$2
      shift
    else
      echo 'ERROR: "--mapper" requires a non-empty option argument.'
      exit 1
    fi
    ;;
  --mapper=?*)
    REPO_MAPPER=${1#*=} # Delete everything up to "=" and assign the remainder.
    ;;
  --mapper=) # Handle the case of an empty --mapper=
    echo 'ERROR: "--mapper" requires a non-empty option argument.'
    exit 1
    ;;
  --conf) # Takes an option argument; ensure it has been specified.
    if [ "$2" ]; then
      CONFIG_FILE=$2
      shift
    else
      echo 'ERROR: "--conf" requires a non-empty option argument.'
      exit 1
    fi
    ;;
  --conf=?*)
    CONFIG_FILE=${1#*=} # Delete everything up to "=" and assign the remainder.
    ;;
  --conf=) # Handle the case of an empty --conf=
    echo 'ERROR: "--conf" requires a non-empty option argument.'
    exit 1
    ;;
  *) # Default case: No more options, so break out of the loop.
    break ;;
  esac
  shift
done

# check if config file is set
setDefaults

# show the config values ¯\_(ツ)_/¯
if (("$DRYRUN" == 1)); then
  echo "		${HEADERTITLE}"
  echo "======================================================"
  echo "STREAM_ENDPOINT:  ${STREAM_ENDPOINT}"
  echo "URL_VERSION_KEY:  ${URL_VERSION_KEY}"
  echo "URL_LANGUAGE_KEY: ${URL_LANGUAGE_KEY}"
  echo "REPO_MAPPER:      ${REPO_MAPPER}"
  echo "CONFIG_FILE:      ${CONFIG_FILE}"
  echo "TARGET_FOLDER:    ${TARGET_FOLDER}"
  echo "QUIET:            ${QUIET}"
  echo "======================================================"
  exit
fi

# run Main ┬┴┬┴┤(･_├┬┴┬┴
main
