<?php

	require_once(CORE . '/class.cacheable.php');

	Class fieldAddressLocation extends Field{

		private $_driver;
		private $_geocode_cache_expire = 60; // minutes

		// defaults used when user doesn't enter defaults when adding field to section
		private $_default_location = 'London, England';
		private $_default_coordinates = '51.58129468879224, -0.554702996875005'; // London, England

		private $_filter_origin = array();

		public function __construct()
		{
			parent::__construct();
			$this->_name = 'Address Location';
			$this->_driver = Symphony::ExtensionManager()->create('addresslocationfield');
		}

		private function __geocodeAddress($address)
		{
			$coordinates = null;

			$cache_id = md5('addresslocationfield_' . $address);
			$cache = new Cacheable(Symphony::Database());
			$cachedData = $cache->check($cache_id);

			// no data has been cached
			if(!$cachedData) {

				include_once(TOOLKIT . '/class.gateway.php');
				
				$key = Symphony::Configuration()->get('api_key','addresslocationfield');
				$ch = new Gateway;
				$ch->init();
				$ch->setopt('URL', 'https://maps.googleapis.com/maps/api/geocode/json?address='.urlencode($address) . "&key=$key");
				$response = json_decode($ch->exec());

				if (isset($response->error_message)) {
					$err = 'Google Maps Error: "' . $response->status . ' - ' . $response->error_message . '" for ' . $address . ' Geo coordinates could not be generated.';
					Symphony::Log()->pushToLog($err, E_ERROR, true);
				}

				$result = $response->results[0];
				//$coordinates = $result->geometry->location;
				//$address_components = $result->address_components;

				if ($coordinates && is_object($coordinates)) {
					$cache->write($cache_id, json_encode($result), $this->_geocode_cache_expire); // cache lifetime in minutes
				}

			}
			// fill data from the cache
			else {
				$result = json_decode($cachedData['data']);
			}
			/*// coordinates is an array, split and return
			if ($coordinates && is_object($coordinates)) {
				return $coordinates->lat . ', ' . $coordinates->lng;
			}
			// return comma delimeted string
			elseif ($coordinates) {
				return "$coordinates";
			}*/
			return $result;
		}

		public function mustBeUnique()
		{
			return true;
		}

		public function canFilter()
		{
			return true;
		}

		function displaySettingsPanel(XMLElement &$wrapper, $errors = NULL)
		{
			parent::displaySettingsPanel($wrapper, $errors);

			$this->appendGroup($wrapper, array('street' => 'Street', 'city' => 'City'));
			$this->appendGroup($wrapper, array('region' => 'Region', 'postal_code' => 'Postal Code'));

			$group = $this->appendGroup($wrapper, array('country' => 'Country'));

			$this->appendShowColumnCheckbox($group);

		}

		public function processRawFieldData($data, &$status, &$message=null, $simulate=false, $entry_id=null)
		{
			$status = self::__OK__;

			if(!is_array($data) || empty($data)) return null;

			$result = array(
				'street' => General::sanitize($data['street']),
				'city' => General::sanitize($data['city']),
				'region' => General::sanitize($data['region']),
				'postal_code' => General::sanitize($data['postal_code']),
				'country' => General::sanitize($data['country']),
			);
			$geocoded_result = $this->__geocodeAddress(implode(',', $result));

			$neighborhood = '';
			if( is_object($geocoded_result) ) {
				foreach( $geocoded_result->address_components as $key=>$val) {
					if( $val->types[0] == 'neighborhood') {
						$neighborhood = $val->long_name;
					}
				}
			}

			if($data['latitude'] == '' || $data['longitude'] == ''){
				$coordinates = $geocoded_result->geometry->location;
				$result['latitude'] = $coordinates->lat;
				$result['longitude'] = $coordinates->lng;
			}
			elseif($data['latitude'] != '' && $data['longitude'] != ''){
				$result['latitude'] = $data['latitude'];
				$result['longitude'] = $data['longitude'];
			}

			$result = array_merge($result, array(
				'entry_id' => $entry_id,
				'street_handle' => Lang::createHandle($data['street']),
				'city_handle' => Lang::createHandle($data['city']),
				'region_handle' => Lang::createHandle($data['region']),
				'postal_code_handle' => Lang::createHandle($data['postal_code']),
				'country_handle' => Lang::createHandle($data['country']),
				'neighborhood' => $neighborhood,
				'neighborhood_handle' => Lang::createHandle($neighborhood),
				'result_data' => json_encode($geocoded_result),
			));
			return $result;
		}

		function commit()
		{
			if(!parent::commit()) return false;

			$id = $this->get('id');

			if($id === false) return false;

			$fields = array(
				'field_id' => $id,
				'street_label' => $this->get('street_label'),
				'city_label' => $this->get('city_label'),
				'region_label' => $this->get('region_label'),
				'postal_code_label' => $this->get('postal_code_label'),
				'country_label' => $this->get('country_label')
			);

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
		}

		function displayPublishPanel(XMLElement &$wrapper, $data = NULL, $flagWithError = NULL, $fieldnamePrefix = NULL, $fieldnamePostfix = NULL, $entry_id = NULL)
		{
			$key = Symphony::Configuration()->get('api_key','addresslocationfield');
			if(empty($key)) {
				Administration::instance()->Page->PageAlert("You need to set a Browser API key in your config.php, more information in README", Alert::ERROR);
			}
			if (Administration::instance()->Page) {
				Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/addresslocationfield/assets/addresslocationfield.publish.css', 'screen', 78);
				Administration::instance()->Page->addScriptToHead('https://maps.google.com/maps/api/js?key=' . $key, 79);
				Administration::instance()->Page->addScriptToHead(URL . '/extensions/addresslocationfield/assets/addresslocationfield.publish.js', 80);
			}

			// input values, from data or defaults
			$coordinates = ($data['latitude'] && $data['longitude']) ? array($data['latitude'], $data['longitude']) : explode(',',$this->get('default_location_coords'));
			$class = $this->get('location');

			$label = new XMLElement('p', $this->get('label'));
			$label->setAttribute('class', 'title');
			$wrapper->appendChild($label);

			// Address Fields
			$address = new XMLElement('div');
			$address->setAttribute('class', 'address '.$class);
			$wrapper->appendChild($address);

			$label = Widget::Label($this->get('street_label'));
			$label->setAttribute('class', 'street');
			$label->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').'][street]'.$fieldnamePostfix, $data['street']));
			$address->appendChild($label);

			$label = Widget::Label($this->get('city_label'));
			$label->setAttribute('class', 'city');
			$label->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').'][city]'.$fieldnamePostfix, $data['city']));
			$address->appendChild($label);

			$label = Widget::Label($this->get('region_label'));
			$label->setAttribute('class', 'region');
			$label->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').'][region]'.$fieldnamePostfix, $data['region']));
			$address->appendChild($label);

			$label = Widget::Label($this->get('postal_code_label'));
			$label->setAttribute('class', 'postal-code');
			$label->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').'][postal_code]'.$fieldnamePostfix, $data['postal_code']));
			$address->appendChild($label);

			$label = Widget::Label($this->get('country_label'));
			$label->setAttribute('class', 'country');
			$label->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').'][country]'.$fieldnamePostfix, $data['country']));
			$address->appendChild($label);

			$label = Widget::Label('Latitude');
			$label->setAttribute('class', 'latitude');
			$label->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').'][latitude]'.$fieldnamePostfix, $coordinates[0], 'text', array('readonly' => 'readonly')));
			$address->appendChild($label);

			$label = Widget::Label('Longitude');
			$label->setAttribute('class', 'longitude');
			$label->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').'][longitude]'.$fieldnamePostfix, $coordinates[1], 'text', array('readonly' => 'readonly')));
			$address->appendChild($label);

			$label = Widget::Label();
			$label->setAttribute('class', 'locate');
			$label->appendChild(Widget::Input('locate', 'Geocode Address', 'button', array('class' => 'button')));
			$label->appendChild(Widget::Input('clear', 'Clear Address', 'button', array('class' => 'button')));
			$address->appendChild($label);

			$map = new XMLElement('div');
			$map->setAttribute('class', 'map '.$class.' open');
			$wrapper->appendChild($map);
		}

		public function createTable()
		{
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `street` varchar(255),
				  `street_handle` varchar(255),
				  `city` varchar(255),
				  `city_handle` varchar(255),
				  `region` varchar(255),
				  `region_handle` varchar(255),
				  `postal_code` varchar(255),
				  `postal_code_handle` varchar(255),
				  `country` varchar(255),
				  `country_handle` varchar(255),
				  `latitude` double default NULL,
				  `longitude` double default NULL,
				  `neighborhood` varchar(255),
				  `neighborhood_handle` varchar(255),
				  `result_data` blob NOT NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `latitude` (`latitude`),
				  KEY `longitude` (`longitude`),
				  INDEX `street` (`street`),
				  INDEX `street_handle` (`street_handle`),
				  INDEX `city` (`city`),
				  INDEX `city_handle` (`city_handle`),
				  INDEX `region` (`region`),
				  INDEX `region_handle` (`region_handle`),
				  INDEX `postal_code` (`postal_code`),
				  INDEX `postal_code_handle` (`postal_code_handle`),
				  INDEX `country` (`country`),
				  INDEX `country_handle` (`country_handle`),
				  INDEX `neighborhood` (`neighborhood`),
				  INDEX `neighborhood_handle` (`neighborhood_handle`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
			);
		}

		public function convertObjectToArray($data)
		{
			if (is_array($data) || is_object($data))
			{
				$result = array();
				foreach ($data as $key => $value)
				{
					$result[$key] = $this->convertObjectToArray($value);
				}
				return $result;
			}
			return $data;
		}

		public function buildXML($parent, $items) {
			$items = $this->convertObjectToArray($items);
			if(!is_array($items)) return;
			// Create groups
			$parent_element = new XMLElement($parent);
			$this->itemsToXML($parent_element, $items);
			return $parent_element;
		}

		public function itemsToXML($parent, $items) {
			if(!is_array($items)) return;

			foreach($items as $key => $value) {
				$index = array();
				if( is_numeric($key) ){
					$index = array(
						'index' => $key,
					);
					$key = 'item';
				}
				$item = new XMLElement($key, null, $index);

				// Nested items
				if(is_array($value)) {
					$this->itemsToXML($item, $value);
					$parent->appendChild($item);
				}

				// Other values
				else {
					$item->setValue(General::sanitize($value));
					$parent->appendChild($item);
				}
			}
		}

		public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = NULL, $entry_id = NULL)
		{
			$field = new XMLElement($this->get('element_name'), null, array(
				'latitude' => $data['latitude'],
				'longitude' => $data['longitude']
			));
			$wrapper->appendChild($field);

			foreach (array('street', 'city', 'region', 'postal_code', 'country', 'neighborhood') as $name)
			{
				if ($encode === TRUE){
					$data[$name] = General::sanitize($data[$name]);
				}
				$element_name = $this->get("{$name}_label");
				if (!($element_name)){
					$element_name = $name;
				}

				$element = new XMLElement(Lang::createHandle($element_name), $data[$name]);
				$element->setAttribute('handle', Lang::createHandle($data[$name]));
				$field->appendChild($element);
			}

			$result_data = json_decode($data['result_data']);
			if( $result_data ) {
				$result_element = $this->buildXML('result-data', $result_data);
				$field->appendChild($result_element);
			}

			// Add back Google Maps result data

			if (count($this->_filter_origin['latitude']) > 0) {
				$distance = new XMLElement('distance');
				$distance->setAttribute('from', $this->_filter_origin['latitude'] . ',' . $this->_filter_origin['longitude']);
				$distance->setAttribute('distance', $this->_driver->geoDistance($this->_filter_origin['latitude'], $this->_filter_origin['longitude'], $data['latitude'], $data['longitude'], $this->_filter_origin['unit']));
				$distance->setAttribute('unit', ($this->_filter_origin['unit'] == 'k') ? 'km' : 'miles');
				$field->appendChild($distance);
			}

		}

		public function prepareTableValue($data, XMLElement $link = NULL, $entry_id = NULL)
		{
			if (empty($data)) return;

			$string = '';
			if($data['street']) $string .= $data['street'];
			if($data['city']) $string .= ', '.$data['city'];
			if($data['region']) $string .= ', '.$data['region'];
			if($data['postal_code']) $string .= ', '.$data['postal_code'];
			if($data['country']) $string .= ', '.$data['country'];
			$string .= ' ('.$data['latitude'] . ', ' . $data['longitude'].')';

			return trim($string,", ");
		}

		function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation=false)
		{

			$columns_to_labels = array();
			$where_array = array();

			foreach (array('street', 'city', 'region', 'postal_code', 'country') as $name)
			{
				$columns_to_labels[Lang::createHandle($this->get("{$name}_label"))] = $name;
			}

			$columns = implode('|', array_keys($columns_to_labels));
			$this->_key++;

			// Symphony by default splits filters by commas. We want commas, so
			// concatenate filters back together again putting commas back in
			$data = join(',', $data);

			if(preg_match("/^in ($columns) of (.+)$/", $data, $filters)){
				$field_id = $this->get('id');

				$column = $columns_to_labels[$filters[1]];
				$value = $filters[2];

				$where .= " AND (
					t{$field_id}_{$this->_key}.{$column} = '{$value}'
					OR t{$field_id}_{$this->_key}.{$column}_handle = '{$value}'
				)";

				$joins .= " LEFT JOIN `tbl_entries_data_{$field_id}` AS `t{$field_id}_{$this->_key}` ON (`e`.`id` = `t{$field_id}_{$this->_key}`.entry_id) ";
			}
			/*
			within 20 km of 10.545, -103.1
			within 2km of 1 West Street, Burleigh Heads
			within 500 miles of England
			*/
			// is a "within" radius filter
			elseif(preg_match('/^within ([0-9]+)\s?(km|mile|miles) of (.+)$/', $data, $filters)){
				$field_id = $this->get('id');

				$radius = trim($filters[1]);
				$unit = strtolower(trim($filters[2]));
				$origin = trim($filters[3]);

				$lat = null;
				$lng = null;

				// is a lat/long pair
				if (preg_match('/^(-?[.0-9]+),\s?(-?[.0-9]+)$/', $origin, $latlng)) {
					$lat = $latlng[1];
					$lng = $latlng[2];
				}
				// otherwise the origin needs geocoding
				else {
					$geocoded_result = $this->__geocodeAddress($origin);
					$coordinates = $geocoded_result->geometry->location;

					if ($geocoded_result) {
						$lat = $coordinates->lat;
						$lng = $coordinates->lng;
					}
				}

				// if we don't have a decent set of coordinates, we can't query
				if (is_null($lat) || is_null($lng)) return true;

				$this->_filter_origin['latitude'] = $lat;
				$this->_filter_origin['longitude'] = $lng;
				$this->_filter_origin['unit'] = $unit[0];

				// build the bounds within the query should look
				$radius = $this->_driver->geoRadius($lat, $lng, $radius, ($unit[0] == 'k'));

				$where .= sprintf(
					" AND `t%d`.`latitude` BETWEEN %s AND %s AND `t%d`.`longitude` BETWEEN %s AND %s",
					$field_id, $radius['latMIN'], $radius['latMAX'],
					$field_id, $radius['lonMIN'], $radius['lonMAX']
				);

				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";

			}

			return true;

		}


		// Helper functions
		private function appendGroup(&$wrapper, $fields = array())
		{
			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');
			$wrapper->appendChild($group);

			foreach ($fields as $name => $text)
			{
				$label = Widget::Label(__("Label for $text Field"));
				$group->appendChild($label);

				$value = ($this->get("{$name}_label") ? $this->get("{$name}_label") : $text);
				$input = Widget::Input("fields[{$this->get('sortorder')}][{$name}_label]", $value);
				$label->appendChild($input);
			}

			return $group;
		}
	}

?>
