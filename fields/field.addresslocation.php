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

				$ch = new Gateway;
				$ch->init();
				$ch->setopt('URL', 'http://maps.googleapis.com/maps/api/geocode/json?address='.urlencode($address).'&sensor=false');
				$response = json_decode($ch->exec());

				$coordinates = $response->results[0]->geometry->location;

				if ($coordinates && is_object($coordinates)) {
					$cache->write($cache_id, $coordinates->lat . ', ' . $coordinates->lng, $this->_geocode_cache_expire); // cache lifetime in minutes
				}

			}
			// fill data from the cache
			else {
				$coordinates = $cachedData['data'];
			}
			// coordinates is an array, split and return
			if ($coordinates && is_object($coordinates)) {
				return $coordinates->lat . ', ' . $coordinates->lng;
			}
			// return comma delimeted string
			elseif ($coordinates) {
				return "$coordinates";
			}
		}

		public function mustBeUnique()
		{
			return true;
		}

		public function canFilter()
		{
			return true;
		}

		function displaySettingsPanel(&$wrapper, $errors=NULL)
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
				'street' => $data['street'],
				'city' => $data['city'],
				'region' => $data['region'],
				'postal_code' => $data['postal_code'],
				'country' => $data['country'],
			);
			if($data['latitude'] == '' || $data['longitude'] == ''){
				$coordinates = explode(',',$this->__geocodeAddress(implode(',', $result)));
				$result['latitude'] = trim($coordinates[0]);
				$result['longitude'] = trim($coordinates[1]);
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

		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL)
		{
			if (Administration::instance()->Page) {
				Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/addresslocationfield/assets/addresslocationfield.publish.css', 'screen', 78);
				Administration::instance()->Page->addScriptToHead('http://maps.google.com/maps/api/js?sensor=false', 79);
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
				  INDEX `country_handle` (`country_handle`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
			);
		}

		public function appendFormattedElement(&$wrapper, $data, $encode = false)
		{
			$field = new XMLElement($this->get('element_name'), null, array(
				'latitude' => $data['latitude'],
				'longitude' => $data['longitude']
			));
			$wrapper->appendChild($field);

			foreach (array('street', 'city', 'region', 'postal_code', 'country') as $name)
			{
				if ($encode === TRUE){
					$data[$name] = General::sanitize($data[$name]);
				}
				$element = new XMLElement(Lang::createHandle($this->get("{$name}_label")), $data[$name]);
				$element->setAttribute('handle', Lang::createHandle($data[$name]));
				$field->appendChild($element);
			}

			if (count($this->_filter_origin['latitude']) > 0) {
				$distance = new XMLElement('distance');
				$distance->setAttribute('from', $this->_filter_origin['latitude'] . ',' . $this->_filter_origin['longitude']);
				$distance->setAttribute('distance', $this->_driver->geoDistance($this->_filter_origin['latitude'], $this->_filter_origin['longitude'], $data['latitude'], $data['longitude'], $this->_filter_origin['unit']));
				$distance->setAttribute('unit', ($this->_filter_origin['unit'] == 'k') ? 'km' : 'miles');
				$field->appendChild($distance);
			}

		}

		public function prepareTableValue($data, XMLElement $link = null)
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
				$column = $columns_to_labels[$filters[1]];
				$value = $filters[2];

				$where .= " AND (
					t{$this->get('id')}_{$this->_key}.{$column} = '{$value}'
					OR t{$this->get('id')}_{$this->_key}.{$column}_handle = '{$value}'
				)";
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
					$geocode = $this->__geocodeAddress($origin);
					if ($geocode) $geocode = explode(',', $geocode);
					$lat = trim($geocode[0]);
					$lng = trim($geocode[1]);
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