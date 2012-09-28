# Address Location Field

* Version: 1.1.1
* Author: John Porter
* Build Date: 2012-09-28
* Requirements: Symphony 2.3

## Installation

1. Upload the 'addressgeolocationfield' folder in this archive to your Symphony 'extensions' folder
2. Enable it by selecting the "Field: Address GeoLocation", choose Enable from the with-selected menu, then click Apply
3. The field will be available in the list when creating a Section


## Configuration

When adding this field to a section, the following options are available to you:

* **Address Field's Labels** can be changed to whatever you prefer

The field works in both Main Content and Sidebar columns, collapsing to a smaller viewport if required in the sidebar.

## Usage

When creating a new entry, add the address details into the fields provided. Once added, click on 'Locate on map' to find a latitude and longitude, and displaying the marker on the map.

The marker can be dragged around the map to more precisely locate your latitude and longitude, which will be stored in the fields.

To start again with a new address, click on the 'Clear Address' button.

## Data Source Filtering

Address Fields's filtering syntax *hopefully* compliments Symphony's built-in syntax. There are two types of queries: 1) Queries on sub-fields and 2) radius queries.

Sub-field queries:

	in SUBFIELD of VALUE

* `SUBFIELD` corresponds to the label of the sub-field on the field. For instance, if you changed the Postal Code to 'Zip code' this would be `zip-code`.
* `VALUE` is the value you are matching.
 
Examples:

	in city of springfield
	in postal-code of {$postal}
	in state of {$state}
  
Radius queries:

	within DISTANCE UNIT of ORIGIN

* `DISTANCE` is an number
* `UNIT` is the distance unit: `km`, `mile` or `miles`
* `ORIGIN` is the longitude and latitude, separated by a comma ','
 
Examples:

	within 5 miles of -93.2971954;37.2083092
	within 100 km of {$coords}
	within {$distance} of {$longitude},{$latitude}

Data Source XML Result
----------------------

	<address longitude="-93.2971954" latitude="37.2083092">
		<street handle="600-w-college">600 W College</street>
		<city handle="springfield">Springfield</city>
		<state handle="missouri">Missouri</state>
		<zip-code handle="65806">65806</zip-code>
		<country handle="united-states-of-america">United States of America</country>
	</address>

The first two attributes are the latitude/longitude of the marker on the map. The `<map>` element contains any information you need to rebuild the Google Map on the frontend of your website: its zoom level, and centre-point.

If you are filtering using the Address GeoLocation Field using a "within" filter then you will see an additional `<distance>` element:

	<location latitude="51.6614" longitude="-0.40042">
		<distance from="51.6245572,-0.4674079" distance="3.8" unit="miles" />
	</location>

The `from` attribute is the latitude/longitude resolved from the DS filter (the origin), the `unit` shows either "km" or "miles" depending on what you use in your filter, and `distance` is the distance between your map marker and the origin.