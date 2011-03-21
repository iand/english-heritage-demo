<?php
require_once "apikey.php"; // Sets the $api_key variable with my API key
require_once "/var/www/lib/potassium/potassium.php";


// If you want to run this yourself, don't forget to subscribe to the following APIs:
// http://beta.kasabi.com/api/sparql-endpoint-english-heritage
// http://beta.kasabi.com/api/sparql-endpoint-ordnance-survey-linked-data

$kasabi = new Potassium($api_key);

$sparql_boroughs = 'select * where {?uri a <http://data.ordnancesurvey.co.uk/ontology/admingeo/Borough>; <http://www.w3.org/2004/02/skos/core#altLabel> ?label } order by ?label';

$borough_list = $kasabi->get('sparql-endpoint-ordnance-survey-linked-data', array('query'=>$sparql_boroughs));

$borough = isset($_GET['borough']) ? $_GET['borough'] : '';
if ($borough) {
  $sparql = "select * where {?uri <http://data.ordnancesurvey.co.uk/ontology/spatialrelations/within> <" . $borough . "> ; <http://data.ordnancesurvey.co.uk/ontology/geometry/extent> ?e ; <http://www.w3.org/2000/01/rdf-schema#label> ?label . optional { ?s <http://open.vocab.org/terms/listingGrade> ?g }  ?e <http://data.ordnancesurvey.co.uk/ontology/geometry/asGeoJSON> ?data}";
  $raw_areas = $kasabi->get('sparql-endpoint-english-heritage', array('query'=>$sparql));
  $areas = array();
  if ($raw_areas) {  
    for ($i = 0; $i < count($raw_areas); $i++) {
      
      $label = $raw_areas[$i]['label'];
      if (!preg_match("/^Geometry/", $label)) {
        $areas[] = $raw_areas[$i];
        if (!$label) {
          $label = 'Unnamed feature';
        }
        if (isset($bindings[$i]['g']['value'])) {
          $label .= ' (grade ' . $bindings[$i]['g']['value'] . ')';
        }
        $areas[count($areas)-1]['label'] = $label;
      }
    }


  }

}



?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" 
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="EN">
	<head>
    <title>Browse English Heritage Sites</title>

    <!-- Framework CSS -->
    <link rel="stylesheet" href="blueprint/screen.css" type="text/css" media="screen, projection">
    <link rel="stylesheet" href="blueprint/print.css" type="text/css" media="print">

    <!--[if lt IE 8]><link rel="stylesheet" href="blueprint/ie.css" type="text/css" media="screen, projection"><![endif]-->

    <!-- Import fancy-type plugin for the sample page. -->
    <link rel="stylesheet" href="blueprint/plugins/fancy-type/screen.css" type="text/css" media="screen, projection">
  
	<style>
		#map {
			width: 460px;
			height: 400px;
			border: 1px solid black;
		}	
	</style>
	<script src='http://openlayers.org/api/OpenLayers.js'></script>
    <script type="text/javascript">
        var lon = 5;
        var lat = 40;
        var zoom = 5;
        var map, layer;

        var areas = <?php echo json_encode($areas); ?>

        function init(){
         
            map = new OpenLayers.Map('map');
            map.addLayer(new OpenLayers.Layer.OSM());
      
            var vectors = new OpenLayers.Layer.Vector("Vector Layer");
            map.addLayer(vectors);

            var select = new OpenLayers.Control.SelectFeature(vectors, { hover: true, onSelect: info, onUnselect: unselect });
            map.addControl(select);
            select.activate();
            
            var bounds;
            for (var ai = 0; ai < areas.length; ai++) {
              var fmt = new OpenLayers.Format.GeoJSON({
                  'internalProjection': map.baseLayer.projection,
                  'externalProjection': new OpenLayers.Projection('EPSG:4326')
              });

              var features = fmt.read(areas[ai]['data']);
              if(features) {
                
                  if(features.constructor != Array) {
                      features = [features];
                  }
                  for(var i=0; i<features.length; ++i) {
                    features[i].attributes['label'] = areas[ai]['label'];
                    features[i].attributes['index'] = ai;
                    
                    
                    if (!areas[ai]['bounds']) {
                        areas[ai]['bounds'] = features[i].geometry.getBounds();
                    } else {
                        areas[ai]['bounds'].extend(features[i].geometry.getBounds());
                    }

                    if (!bounds) {
                        bounds = features[i].geometry.getBounds();
                    } else {
                        bounds.extend(features[i].geometry.getBounds());
                    }

                  }
                  
                  
                  vectors.addFeatures(features);

              }
            }
            map.zoomToExtent(bounds);
            
        }
        function info(feature) {
          for (var i = 0; i < areas.length; i++) {
            document.getElementById('feature' + i).style.backgroundColor = '#FFFFFF';
          }
          var index = feature.attributes.index;
          document.getElementById('feature' + index).style.backgroundColor = '#FFCCCC';
        }

        function unselect(feature) {
          var index = feature.attributes.index;
          document.getElementById('feature' + index).style.backgroundColor = '#FFFFFF';
        }

        function mapzoom(index) {
          map.zoomToExtent(areas[index]['bounds']);
        }
        
        
    </script>
  </head>
  <body onload="init()">
      <h1 id="title">Browse English Heritage Sites</h1>
      <form action="" method="get">
        <label for="borough">Borough:</label>
        <select id="borough" name="borough"><option>(select borough)</option>
          <?php 
            for ($i = 0; $i < count($borough_list); $i++) {
              echo '<option value="' . htmlspecialchars($borough_list[$i]['uri']) . '"';
              if ($borough == $borough_list[$i]['uri']) {
                echo " selected";
              }
              echo '>' . htmlspecialchars($borough_list[$i]['label']) . '</option>'. "\n";
            }
          ?>
        </select>
        <input type="submit" value="Display sites">
      
      </form>

    <p>This demo allows you to browse English Heritage sites by English borough</p>
    <div class="span-12">
    <div id="map"></div>
    <hr class="space">
    <script
     type="text/javascript"
     src="http://api.kasabi.com/dataset/english-heritage/attribution">
</script>
    <hr class="space">
    <div>Source code available from <a href="http://github.com/iand/english-heritage-demo">GitHub</a></div>
    </div>
    <ul class="span-12 last">
          <?php 
            for ($i = 0; $i < count($areas); $i++) {
              $label = $areas[$i]['label'];
              if (!$label) {
                $label = 'Unnamed feature';
              }
              echo '<li><a href="#" id="feature' . $i. '" + onclick="mapzoom(' . $i. '); return 0;">' . htmlspecialchars($label) . '</a> [<a href="' . htmlspecialchars($areas[$i]['uri']) . '">data</a>]</li>';
            }
          ?>
    </ul>

  </body>

</html>
