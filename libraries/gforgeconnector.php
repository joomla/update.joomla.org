<?php


class DateTimeProxy {
	function now() {
		return date('Y-m-d H:i:s');
	}
}

class GForgeConnector {
	var $client = null;
	var $sessionhash = null;
	var $username = '';
	
	private $_error = '';
	
	function __construct($site, $soap_options=Array()) {
		$this->client = new SoapClient($site .'/xmlcompatibility/soap5/?wsdl', $soap_options);
		//$this->client = new SoapClient(dirname(__FILE__).'/joomlacode.wsdl', $soap_options);
		if(!$this->client) die("GForge Constructor failed\n");
	}

	/**
	 * Dummy JObject support
	 */
	function setError($error) {
		$this->_error = $error;
	}
	
	function login($username, $password) {
		$this->username = $username; // cache the username locally, we use it later
		try {
			$sessionhash = $this->client->login($username,$password);
			$this->sessionhash = $sessionhash;
			return true;
		} catch(SoapFault $e) {
			echo 'Login Failed: '. $e->faultstring . "\n";
			$this->_error = $e->faultstring;
			return false;
		}	
	}
	
	function getUser() {
		try {
			$user = $this->client->getUserByUnixName($this->sessionhash, $this->username);
			return $user;
		} catch(SoapFault $e) {
			echo 'Failed to get user: '. $e->faultstring;
			$this->_error = $e->faultstring;
			return false;
		}
	}
		
	
	function getUserProjects() {
		try {
			$projects = $this->client->getUserProjects($this->sessionhash, $this->username);
			return $projects;
		} catch (SoapFault $e) {
			echo 'Operation failed: '. $e->faultstring;
			$this->_error = $e->faultstring;
			return false;
		}
	}
	
	function getProject($unix_name) {
		try {
			$project = $this->client->getProjectByUnixName($this->sessionhash, $unix_name);
			return $project;
		} catch(SoapFault $e) {
			echo 'Failed to get project: '. $e->faultstring;
			$this->_error = $e->faultstring;
			return false;
		}
	}

	function getFrsPackages($project_id) {
		try {
			return $this->client->getFrsPackages($this->sessionhash, $project_id);
		} catch(SoapFault $e) {
			echo 'Failed to get FRS packages: '. $e->faultstring;
			$this->_error = $e->faultstring;
			return false;
		}
	}
	
	function addFrsPackage($project_id, $package_name, $status_id=1, $is_public=0, $require_login=0) {
		try {
			return $this->client->addFrsPackage($this->sessionhash, $project_id, $package_name, $status_id, $is_public, $require_login);
		} catch(SoapFault $e) {
			echo 'Failed to add FRS package: '. $e->faultstring;
			$this->_error = $e->faultstring;
			return false;
		}
	}

	function getFrsReleases($frs_package_id, $released_by=-1) {
		try {
			return $this->client->getFrsReleases($this->sessionhash, $frs_package_id, $released_by);
		} catch(SoapFault $e) {
			echo 'Failed to get releases: '. $e->faultstring;
			$this->_error = $e->faultstring;
			return false;
		}
	}
	
	function getFrsRelease($frs_release_id) {
		try {
			return $this->client->getFrsRelease($this->sessionhash, $frs_release_id);
		} catch(SoapFault $e) {
			echo 'Failed to get release: '. $e->faultstring;
			$this->_error = $e->faultstring;
			return false;
		}
	}

	function addFrsRelease($frs_package_id, $release_name, $release_notes, $changes, 
		$status_id=1, $preformatted=0, $release_date=null, $is_released=0) {
		if(!$release_date) $release_date = DateTimeProxy::now();
		try {
			return $this->client->addFrsRelease($this->sessionhash, $frs_package_id, $release_name, $release_notes, $changes, $status_id, $preformatted, $release_date, $is_released);
		} catch(SoapFault $e) {
			echo 'Failed to add release: '. $e->faultstring;
			$this->_error = $e->faultstring;
			return false;
		}
	}
	
	function addFilesystem($section, $ref_id, $file_name, $file_type, &$data) {
		try {
			return $this->client->addFilesystem($this->sessionhash, $section, $ref_id, $file_name, $file_type, $data);
		} catch(SoapFault $e) {
			echo 'Failed to add filesystem entry: '. $e->faultstring;
			$this->_error = $e->faultstring;
			return false;
		}	
	}
	
	function getFilesystem($filesystem_id) {
		try {
			return $this->client->getFilesystem($this->sessionhash, $filesystem_id);
		} catch(SoapFault $e) {
			echo 'Failed to get filesystem entry: '. $e->faultstring;
			$this->_error = $e->faultstring;
			return false;
		}
	}

	function getFilesystemData($filesystem_id) {
		try {
			return $this->client->getFilesystemData($this->sessionhash, $filesystem_id);
		} catch(SoapFault $e) {
			echo 'Failed to get filesystem data: '. $e->faultstring;
			$this->_error = $e->faultstring;
			return false;
		}
	}
	
	function getFilesystems($section, $ref_id) {
		try {
			return $this->client->getFilesystems($this->sessionhash, $section, $ref_id);
		} catch(SoapFault $e) {
			echo 'Failed to get filesystems entry: '. $e->faultstring;
			$this->_error = $e->faultstring;
			return false;
		}
	}

	function logout() {
		try {
			$this->client->logout($this->sessionhash);
		} catch (SoapFault $e) {
			echo ('Logout Failed: '. $e->faultstring);
			$this->_error = $e->faultstring;
			return false;
		}
	}

	function getError()
	{
		return $this->_error;
	}
}
