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
			return Symphony::Database()
				->drop('tbl_fields_addresslocation')
				->ifExists()
				->execute()
				->success();
		}

		public function update($previousVersion = false){
			$addresslocation_entry_tables = Symphony::Database()
				->select(['field_id'])
				->from('tbl_fields_addresslocation')
				->execute()
				->column('field_id');

			if(version_compare($previousVersion, '1.2.1', '<')){
				if(is_array($addresslocation_entry_tables) && !empty($addresslocation_entry_tables))
				{
					foreach($addresslocation_entry_tables as $field)
					{
						Symphony::Database()
							->alter("tbl_entries_data_$field")
							->add(['result_data' => 'blob'])
							->execute()
							->success();
					}
				}
			}

			if(version_compare($previousVersion, '1.2.2', '<')){
				if(is_array($addresslocation_entry_tables) && !empty($addresslocation_entry_tables))
				{
					foreach($addresslocation_entry_tables as $field)
					{
						Symphony::Database()
							->alter("tbl_entries_data_$field")
							->add([
								'neighborhood' => 'varchar(255)',
								'neighborhood_handle' => 'varchar(255)',
							])
							->execute()
							->success();
					}
				}
			}

			if(version_compare($previousVersion, '1.2.3', '<')){
				if(is_array($addresslocation_entry_tables) && !empty($addresslocation_entry_tables))
				{
					foreach($addresslocation_entry_tables as $field)
					{
						Symphony::Dabatase()
							->alter("tbl_entries_data_$field")
							->modify(['result_data' => 'blob'])
							->execute()
							->success();
					}
				}
			}

			return true;
		}

		public function install()
		{
			Symphony::Database()
				->create('tbl_fields_addresslocation')
				->ifNotExists()
				->fields([
					'id' => [
						'type' => 'int(11)',
						'auto' => true,
					],
					'field_id' => 'int(11)',
					'street_label' => 'varchar(80)',
					'city_label' => 'varchar(80)',
					'region_label' => 'varchar(80)',
					'postal_code_label' => 'varchar(80)',
					'country_label' => 'varchar(80)',
				])
				->keys([
					'id' => 'primary',
					'field_id' => 'unique',
				])
				->execute()
				->success();
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
			(float)$mpdLON = 69.1703234283616 * cos($lat * (pi()/180));
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
