(function($) {
    $(document).ready(function() {
      // Get user's location on page load
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
          var lat = position.coords.latitude;
          var lng = position.coords.longitude;
  
          // Send location data to server
          $.post(locationSharing.ajaxurl, {
            action: 'location_sharing_update_location',
            lat: lat,
            lng: lng
          });
        });
      }
    });
  })(jQuery);
  