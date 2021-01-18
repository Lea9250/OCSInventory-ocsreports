<?php
/*
 * Copyright 2005-2020 OCSInventory-NG/OCSInventory-ocsreports contributors.
 * See the Contributors file for more details about them.
 *
 * This file is part of OCSInventory-NG/OCSInventory-ocsreports.
 *
 * OCSInventory-NG/OCSInventory-ocsreports is free software: you can redistribute
 * it and/or modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation, either version 2 of the License,
 * or (at your option) any later version.
 *
 * OCSInventory-NG/OCSInventory-ocsreports is distributed in the hope that it
 * will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OCSInventory-NG/OCSInventory-ocsreports. if not, write to the
 * Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

 /**
  * Class for PackageBuilder
  */
class PackageBuilder
{
	private $packageBuilderForm;
	private $packageBuilderParseXml;
	private $downloadConfig = [];
	
	/**
	 * Method __construct
	 *
	 * @param $packageBuilderForm $packageBuilderForm [explicite description]
	 * @param $packageBuilderParseXml $packageBuilderParseXml [explicite description]
	 *
	 * @return void
	 */
	function __construct($packageBuilderForm, $packageBuilderParseXml) {
		$this->packageBuilderForm = $packageBuilderForm;
		$this->packageBuilderParseXml = $packageBuilderParseXml;

		$this->downloadConfig = look_config_default_values([
			'DOWNLOAD_PACK_DIR' => 'DOWNLOAD_PACK_DIR',
			'DOWNLOAD_ACTIVATE_FRAG' => 'DOWNLOAD_ACTIVATE_FRAG',
			'DOWNLOAD_RATIO_FRAG' => 'DOWNLOAD_RATIO_FRAG',
		]);
	}
	
	/**
	 * Method buildPackage
	 *
	 * @param $post $post [explicite description]
	 * @param $file $file [explicite description]
	 *
	 * @return void
	 */
	public function buildPackage($post, $file) {
		global $l;
		
		$timestamp = time();
		$digest = md5_file($file["additionalfiles"]["tmp_name"]);

		if(isset($this->downloadConfig['tvalue']['DOWNLOAD_PACK_DIR'])) {
			$downloadPath = $this->downloadConfig['tvalue']['DOWNLOAD_PACK_DIR'].'/download/'.$timestamp;
		} else {
			$downloadPath = VARLIB_DIR . '/download/'.$timestamp;
		}

		if(file_exists($file["additionalfiles"]["tmp_name"])) {
			// Create folder if not exists
			if (!file_exists($downloadPath)) {
                mkdir($downloadPath);
			}
			// Create package archive
			$details = $this->fragmentPackage($file["additionalfiles"]["tmp_name"], $downloadPath, $timestamp);
		}

		// Get Xml option info
		$xmlDetails = $this->packageBuilderParseXml->parseOptions($post['FORMTYPE']);
		// Replace dynamic value from xml
		$xmlDetails = $this->replaceXmlValue($post, $xmlDetails);
		// Generate info xml
		$info = $this->writePackageInfo($xmlDetails, $timestamp, $details['frag'], $digest, $post['pathfile']);
		// Create info file
		$handinfo = fopen($downloadPath."/info", "w+");
        fwrite($handinfo, $info);
		fclose($handinfo);
		
		//delete all package with the same id
		mysql2_query_secure("DELETE FROM download_available WHERE FILEID='%s'", $_SESSION['OCS']["writeServer"], $timestamp);
		
        //insert new package
        $req = "INSERT INTO download_available(FILEID, NAME, PRIORITY, FRAGMENTS, SIZE, OSNAME, COMMENT) 
				VALUES ('%s','%s','%s','%s','%s','%s','%s')";
        $arg = array(
			$timestamp, 
			$post['NAME'], 
			$xmlDetails->packagedefinition->PRI, 
			$details['frag'],
			$details['size'], 
			$xmlDetails->refos, 
			$post['DESCRIPTION'], 
			$sql_details['id_wk']
		);
			
        mysql2_query_secure($req, $_SESSION['OCS']["writeServer"], $arg);

		addLog($l->g(512), $l->g(617) . " " . $timestamp);
		//info message
		msg_success($l->g(437) . " " . $downloadPath);
		//delete cache for activation
		unset($_SESSION['OCS']['DATA_CACHE']['LIST_PACK']);
		unset($_SESSION['OCS']['NUM_ROW']['LIST_PACK']);

	}
	
	/**
	 * Method replaceXmlValue
	 *
	 * @param $post $post [explicite description]
	 * @param $xmlDetails $xmlDetails [explicite description]
	 *
	 * @return void
	 */
	private function replaceXmlValue($post, $xmlDetails) {
		foreach($xmlDetails->packagedefinition as $packagedefinition) {
			foreach ($packagedefinition as $key => $value) {
				if(strpos($value, ":") !== false) {
					$xmlDetails->packagedefinition->$key = $post[ltrim($value, ':')];	
				}
			}	
		}

		return $xmlDetails;
	}
	
	/**
	 * Method fragmentPackage
	 *
	 * @param $fname $fname [explicite description]
	 * @param $downloadPath $downloadPath [explicite description]
	 * @param $timestamp $timestamp [explicite description]
	 *
	 * @return void
	 */
	private function fragmentPackage($fname, $downloadPath, $timestamp) {
		$size = @filesize($fname);

		// If package fragmentation disabled, frag = 1
		if($this->downloadConfig['ivalue']['DOWNLOAD_ACTIVATE_FRAG'] == 1 && $this->downloadConfig['ivalue']['DOWNLOAD_RATIO_FRAG'] != null) {
			$frag = 0;
			$sizeBis = $size / pow(1024, 2);

			while($sizeBis > 0) {
				$sizeBis = $sizeBis - intval($this->downloadConfig['ivalue']['DOWNLOAD_RATIO_FRAG']);
				$frag ++;
			}
		} else {
			$frag = 1;
		}
		
		$handle = fopen($fname, "rb");
		$read = 0;
		
		for ($i = 1; $i < $frag; $i++) {
			$contents = fread($handle, $size / $frag);
			$read += strlen($contents);
			$handfrag = fopen($downloadPath."/".$timestamp."-".$i, "w+b");
			fwrite($handfrag, $contents);
			fclose($handfrag);
		}

		$contents = fread($handle, $size - $read);
		$read += strlen($contents);
		$handfrag = fopen($downloadPath."/".$timestamp."-".$i, "w+b");
		
		fwrite($handfrag, $contents);
		fclose($handfrag);
		fclose($handle);
		
		$details['frag'] = $i;
		$details['size'] = $size;

		return $details;
	}
	
	/**
	 * Create Info package file
	 * 
	 * @param obj $xmlDetails
	 * @param int $timestamp
	 * @param int $nbFrag
	 * @param int $digest
	 * 
	 * @return string $info
	 */
	private function writePackageInfo($xmlDetails, $timestamp, $nbFrag, $digest) {

		$info  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $info .= "<DOWNLOAD ID=\"" . $timestamp . "\" " .
                 "PRI=\"" . $xmlDetails->packagedefinition->PRI . "\" " .
                 "ACT=\"" . $xmlDetails->packagedefinition->ACT . "\" " .
                 "DIGEST=\"" . $digest . "\" " .
                 "PROTO=\"" . $xmlDetails->packagedefinition->PROTO . "\" " .
                 "FRAGS=\"" . $nbFrag . "\" " .
                 "DIGEST_ALGO=\"MD5\" " .
                 "DIGEST_ENCODE=\"Hexa\" ";
        if ($xmlDetails->packagedefinition->ACT  == 'STORE') {
            $info .= "PATH=\"" . $xmlDetails->packagedefinition->PATH . "\" ";
        }
        if ($xmlDetails->packagedefinition->ACT  == 'LAUNCH') {
            $info .= "NAME=\"" . $xmlDetails->packagedefinition->NAME . "\" ";
        }
        if ($xmlDetails->packagedefinition->ACT  == 'EXECUTE') {
            $info .= "COMMAND=\"" . $xmlDetails->packagedefinition->COMMAND . "\" ";
        }

        $info .= "NOTIFY_USER=\"" . $xmlDetails->packagedefinition->NOTIFY_USER  . "\" " .
				 "NOTIFY_TEXT=\"" . $xmlDetails->packagedefinition->NOTIFY_TEXT . "\" " .
				 "NOTIFY_COUNTDOWN=\"" . $xmlDetails->packagedefinition->NOTIFY_COUNTDOWN . "\" " .
				 "NOTIFY_CAN_ABORT=\"" . $xmlDetails->packagedefinition->NOTIFY_CAN_ABORT . "\" " .
				 "NOTIFY_CAN_DELAY=\"" . $xmlDetails->packagedefinition->NOTIFY_CAN_DELAY . "\" " .
				 "NEED_DONE_ACTION=\"" . $xmlDetails->packagedefinition->NEED_DONE_ACTION . "\" " .
				 "NEED_DONE_ACTION_TEXT=\"" . $xmlDetails->packagedefinition->NEED_DONE_ACTION_TEXT . "\" " .
				 "GARDEFOU=\"rien\" />\n";
				
		return $info;
		
	}
	
	/**
	 * Method getPackageFileId
	 *
	 * @param $name $name [explicite description]
	 * @param $session $session [explicite description]
	 *
	 * @return void
	 */
	public function getPackageFileId($name , $session = null) {
		
		$sql = "SELECT fileid FROM download_available WHERE name='%s'";
		$arg = array($name);
		if($session != null) {
			$result = mysql2_query_secure($sql, $session, $arg);
		} else {
			$result = mysql2_query_secure($sql, $_SESSION['OCS']["readServer"], $arg);
		}

		if (mysqli_num_rows($result) != 0) {
			return false;
		}
	}

	
}