<?php

	Class extension_addresslocationfield extends Extension {

		public function getSubscribedDelegates()
		{
			return array(
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'addCustomPreferenceFieldsets'
				)
			);
		}

		public function addCustomPreferenceFieldsets($context) {
			$currentSettings = Symphony::Configuration()->get('addresslocationfield');
			$settingsCtn = new XMLElement('fieldset');
			$settingsCtn->addClass('settings');
			$legend = new XMLElement('legend', 'Address Location Field');
			$settingsCtn->appendChild($legend);
			$label = new XMLElement('label', 'API Key');
			$label->appendChild(Widget::Input('settings[addresslocationfield][api_key]', $currentSettings['api_key'], 'text'));
			$settingsCtn->appendChild($label);
			$context['wrapper']->appendChild($settingsCtn);
		}

		public function uninstall()
		{
			Symphony::Database()->query("DROP TABLE `tbl_fields_addresslocation`");
		}

		public function update($previousVersion = false){
			$addresslocation_entry_tables = Symphony::Database()->fetchCol("field_id", "SELECT `field_id` FROM `tbl_fields_addresslocation`");

			if(version_compare($previousVersion, '1.2.1', '<')){
				if(is_array($addresslocation_entry_tables) && !empty($addresslocation_entry_tables))
				{
					foreach($addresslocation_entry_tables as $field)
					{
						Symphony::Database()->query(sprintf(
							"ALTER TABLE `tbl_entries_data_%d` ADD `result_data` blob",
							$field
						));
					}
				}
			}

			if(version_compare($previousVersion, '1.2.2', '<')){
				if(is_array($addresslocation_entry_tables) && !empty($addresslocation_entry_tables))
				{
					foreach($addresslocation_entry_tables as $field)
					{
						Symphony::Database()->query(sprintf(
							"ALTER TABLE `tbl_entries_data_%d` ADD `neighborhood` VARCHAR(255), ADD `neighborhood_handle` VARCHAR(255)",
							$field
						));
					}
				}
			}

			if(version_compare($previousVersion, '1.2.3', '<')){
				if(is_array($addresslocation_entry_tables) && !empty($addresslocation_entry_tables))
				{
					foreach($addresslocation_entry_tables as $field)
					{
						Symphony::Database()->query(sprintf(
							"ALTER TABLE `tbl_entries_data_%d` MODIFY COLUMN `result_data` blob",
							$field
						));
					}
				}
			}

			return true;
		}

		public function install()
		{
			return Symphony::Database()->query("CREATE TABLE `tbl_fields_addresslocation` (
				`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
				`field_id` INT(11) UNSIGNED NOT NULL,
				`street_label` VARCHAR(80) NOT NULL,
				`city_label` VARCHAR(80) NOT NULL,
				`region_label` VARCHAR(80) NOT NULL,
				`postal_code_label` VARCHAR(80) NOT NULL,
				`country_label` VARCHAR(80) NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `field_id` (`field_id`)
			) TYPE=MyISAM");
		}

		/*
			Modified from:
			http://www.kevinbradwick.co.uk/developer/php/free-to-script-to-calculate-the-radius-of-a-coordinate-using-latitude-and-longitude
		*/
		public function geoRadius($lat, $lng, $rad, $kilometers = false)
		{
			$radius = ($kilometers) ? ($rad * 0.621371192) : $rad;

			(float)$dpmLAT = 1 / 69.1703234283616;

			// Latitude calculation
			(float)$usrRLAT = $dpmLAT * $radius;
			(float)$latMIN = $lat - $usrRLAT;
			(float)$latMAX = $lat + $usrRLAT;

			// Longitude calculation
			(float)$mpdLON = 69.1703234283616 * cos($lat * (pi/180));
			(float)$dpmLON = 1 / $mpdLON; // degrees per mile longintude
			$usrRLON = $dpmLON * $radius;

			$lonMIN = $lng - $usrRLON;
			$lonMAX = $lng + $usrRLON;

			return array("lonMIN" => $lonMIN, "lonMAX" => $lonMAX, "latMIN" => $latMIN, "latMAX" => $latMAX);
		}

		/*
		Calculate distance between two lat/long pairs
		*/
		public function geoDistance($lat1, $lon1, $lat2, $lon2, $unit)
		{
			$theta = $lon1 - $lon2;
			$dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
			$dist = acos($dist);
			$dist = rad2deg($dist);
			$miles = $dist * 60 * 1.1515;

			$unit = strtolower($unit);

			$distance = 0;

			if ($unit == "k") {
				$distance = ($miles * 1.609344);
			} else if ($unit == "n") {
				$distance = ($miles * 0.8684);
			} else {
				$distance = $miles;
			}

			return round($distance, 1);

		}

	}

?>
