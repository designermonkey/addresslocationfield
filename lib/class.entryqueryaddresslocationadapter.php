<?php

/**
 * @package toolkit
 */
/**
 * Specialized EntryQueryFieldAdapter that facilitate creation of queries filtering/sorting data from
 * an textarea Field.
 * @see FieldAddressLocation
 * @since Symphony 3.0.0
 */
class EntryQueryAddressLocationAdapter extends EntryQueryFieldAdapter
{
    public function isFilterIn($filter, $columns)
    {
        return preg_match("/^in:? ?($columns) of (.+)$/", $filter);
    }

    public function createFilterIn($filter, $columns, $columns_to_labels)
    {
        $field_id = General::intval($this->field->get('id'));
        $filter = $this->field->cleanValue($filter);
        $matches = [];
        preg_match("/^in:? ?($columns) of (.+)$/", $filter, $matches);

        $column = $columns_to_labels[$matches[1]];
        $value = $matches[2];

        $conditions = [];
        $conditions[] = [$this->formatColumn($column, $field_id) => $value];
        $conditions[] = [$this->formatColumn($column . '_handle', $field_id) => $value];

        if (count($conditions) < 2) {
            return $conditions;
        }
        return ['or' => $conditions];
    }

    public function isFilterWithin($filter)
    {
        return preg_match('/^within:? ?([0-9]+)\s?(km|mile|miles) of (.+)$/', $filter);
    }

    public function createFilterWithin($filter)
    {
        $field_id = General::intval($this->field->get('id'));
        $filter = $this->field->cleanValue($filter);
        $matches = [];
        preg_match('/^within:? ?([0-9]+)\s?(km|mile|miles) of (.+)$/', $filter, $matches);

        $radius = trim($matches[1]);
        $unit = strtolower(trim($matches[2]));
        $origin = trim($matches[3]);

        $lat = null;
        $lng = null;

        // is a lat/long pair
        if (preg_match('/^(-?[.0-9]+),?\s?(-?[.0-9]+)$/', $origin, $latlng)) {
            $lat = $latlng[1];
            $lng = $latlng[2];
        }
        // otherwise the origin needs geocoding
        else {
            $geocoded_result = $this->field->geocodeAddress($origin);
            $coordinates = $geocoded_result->geometry->location;

            if ($geocoded_result) {
                $lat = $coordinates->lat;
                $lng = $coordinates->lng;
            }
        }

        // if we don't have a decent set of coordinates, we can't query
        if (is_null($lat) || is_null($lng)) return true;

        $this->field->filter_origin['latitude'] = $lat;
        $this->field->filter_origin['longitude'] = $lng;
        $this->field->filter_origin['unit'] = $unit[0];

        // build the bounds within the query should look
        $radius = $this->field->driver->geoRadius($lat, $lng, $radius, ($unit[0] == 'k'));

        $conditions = [];
        $conditions[] = [$this->formatColumn('latitude', $field_id) => ['between' => [$radius['latMIN'], $radius['latMAX']]]];
        $conditions[] = [$this->formatColumn('longitude', $field_id) => ['between' => [$radius['lonMIN'], $radius['lonMAX']]]];

        if (count($conditions) < 2) {
            return $conditions;
        }
        return ['or' => $conditions];
    }

    /**
     * @see EntryQueryFieldAdapter::filterSingle()
     *
     * @param EntryQuery $query
     * @param string $filter
     * @return array
     */
    protected function filterSingle(EntryQuery $query, $filter)
    {
        General::ensureType([
            'filter' => ['var' => $filter, 'type' => 'string'],
        ]);

        $columns_to_labels = array();

        foreach (array('street', 'city', 'region', 'postal_code', 'country') as $name)
        {
            $columns_to_labels[Lang::createHandle($this->field->get("{$name}_label"))] = $name;
        }

        $columns = implode('|', array_keys($columns_to_labels));
        $this->_key++;

        if ($this->isFilterIn($filter, $columns)) {
            return $this->createFilterIn($filter, $columns, $columns_to_labels);
        } elseif ($this->isFilterWithin($filter)) {
            return $this->createFilterWithin($filter);
        }
    }
}
