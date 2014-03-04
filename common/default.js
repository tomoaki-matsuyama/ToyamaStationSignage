function chgmap(filename) {
	var initPos = new google.maps.LatLng(35.127152, 138.910627);
	var myOptions = {
		noClear : true,
		center : initPos,
		zoom : 5,
		mapTypeId : google.maps.MapTypeId.ROADMAP
	};
	var map_canvas = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
    var kmlUrl = "kmls/"+filename;
    var kmlLayer = new google.maps.KmlLayer(kmlUrl);
    kmlLayer.setMap(map_canvas);
}
