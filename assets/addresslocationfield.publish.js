(function($){
	var field = null;
	var map = null;
	var marker = {};
	var lat = null;
	var lng = null;
	var geocoder = 	new google.maps.Geocoder();
	var helpers = {
        /**
         * Disable or enable a form element (mainly buttons)
         * @param {DOMElement} $el The element to disable/enable
         * @param {boolean} disable If set to true, then the $el will become disabled, false (or not passed in) enables it
         */
		toggleFieldState: function ($el, disable) {
			if ($el && $el.length) {
				if (disable) {
					$el.attr('disabled', 'disabled');
				} else {
					$el.removeAttr('disabled');
				}
			}
		}
	};

	function addresslocationField(){
		map =  new google.maps.Map($('div.field-addresslocation div.map')[0], {
			center: new google.maps.LatLng(0,0),
			zoom: 1,
			MapTypeId: google.maps.MapTypeId.ROADMAP
		});
		
		field = $('div.field-addresslocation');
		lat = field.find('label.latitude input');
		lng = field.find('label.longitude input');
		
		if(lat.val() && lng.val()){
			var latlng = new google.maps.LatLng(lat.val(), lng.val());
			map.setCenter(latlng);
			map.setZoom(16);
			SetMarker(latlng);
			helpers.toggleFieldState(field.find('label.locate input[name="locate"]'), true);
		}
		else{
			helpers.toggleFieldState(field.find('label.locate input[name="clear"]'), true);
		}
		
		field.find('label.locate input[name="clear"]').click(function(ev){
			
			ev.preventDefault();
			
			var fields = field.find('label.street input, label.city input, label.region input, label.postal-code input, label.country input, label.latitude input, label.longitude input');
			
			fields.val('');
			
			marker.setMap(null);
			map.setCenter(new google.maps.LatLng(0,0));
			map.setZoom(1);
			
			field.find('label.locate input[name="locate"]').removeAttr('disabled');
			
		});
		
		if(field.find('div.address').hasClass('sidebar')){
			
			var a = $('<a class="mapswitch" href="#">[-] Hide Map</a>').appendTo('label.locate')
			
			field.delegate('label.locate a.mapswitch', 'click', function(ev){
				console.log('clicked');
				ev.preventDefault();
				var map = field.find('div.map');
				if(map.hasClass('open')){
					map.slideUp().removeClass('open').addClass('closed');
					$(this).text('[+] Show Map');
				}
				else if(map.hasClass('closed')){
					map.slideDown().removeClass('closed').addClass('open');
					$(this).text('[+] Hide Map');
				}
			});
		}
		

		field.find('label.locate input[name="locate"]').click(function(ev){
			
			//Reassign field to stop mime warning/error
			var field = $('div.field-addresslocation');
			var button = $(this);
			
			var button_value = button.val();
			button.val('Geocoding...').attr('disabled', 'disabled');
			button.parent('label').find('i').remove();
			
			ev.preventDefault();
			
			var street = field.find('label.street input').val(),
				city = field.find('label.city input').val(),
				region = field.find('label.region input').val(),
				postalcode = field.find('label.postal-code input').val(),
				country = field.find('label.country input').val();
				
			var address = '';
			if(street) address += street;
			if(city) address += ', ' + city;
			if(region) address += ', ' + region;
			if(postalcode) address += ', ' + postalcode;
			if(country) address += ', ' + country;

			GeocodeAddress(address, function(result){
				button.val(button_value);
				SetMarker(result.geometry.location);
			}, function(){
				button.val(button_value).removeAttr('disabled');
				button.parent('label').append('<i>Address not found</i>')
			});
		});

		field.on('focus', 'input[type=text]', function(ev){
			var $btn = field.find('label.locate input[name="locate"]')
			if ($btn.attr('disabled')) {
				helpers.toggleFieldState($btn);
			}

		});
	}
	function GeocodeAddress(address, success, fail){
		geocoder.geocode({"address":address}, function(results, status){
			console.log(status, results);
			if(status == google.maps.GeocoderStatus.OK){
				success(results[0]);
			}else{
				fail();
			}
		});
	}
	
	function SetLatLng(latlng){
		field = $('div.field-addresslocation');
		lat = field.find('label.latitude input');
		lng = field.find('label.longitude input');
		lat.val(latlng.lat().toFixed(7));
		lng.val(latlng.lng().toFixed(7));
	}
	
	function SetMarker(latlng){
		if($.isEmptyObject(marker)){
			marker = new google.maps.Marker({
				"clickable": false,
				"draggable": true,
				"position": latlng,
				"animation": google.maps.Animation.DROP,
				"map": map
			})
		}
		else{
			marker.setPosition(latlng);
			marker.setMap(map);
		}

		map.setZoom(16);
		map.setCenter(marker.getPosition());
		SetLatLng(latlng);
		
		google.maps.event.addListener(marker, "dragend", function(){
			SetLatLng(marker.getPosition());
			map.setCenter(marker.getPosition());
		});
	}
	
	$(document).ready(function(){
		addresslocationField();
	});
})(jQuery);
