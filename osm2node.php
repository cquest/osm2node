<?php
/*********************************************************************************
Un petit wrapper � osmconvert http://wiki.openstreetmap.org/wiki/Osmconvert.

On l'appel par http://x/osm2node?[Syntaxe XAPI]
On l'appel avec la syntaxe de XAPI, il fait lui m�me un appel XAPI
et renvoi tout objet osm simplifi� en noeud (un noeud reste un noeud, mais way et relation 
sont converti en un noeud positionn� au centre approximatif).

On peut aussi appel� le script avec "http://x/osm2nodegps?[Syntaxe XAPI]" et il fourni une conversion 
de osm (noeud) en waypoint gpx

sly sylvain@letuffe.org 28/11/2012
**********************************************************************************/
$config['xapi_url']="http://api.openstreetmap.fr/xapi-without-meta?";
$config['osmconvert_path']="../osmconvert/osmconvert";

function osmnodes2gpx($osm_xml)
{
  function c($txt)
  {
    return htmlspecialchars($txt,ENT_COMPAT,'UTF-8');
  }
  $osm = simplexml_load_string($osm_xml);
  if (isset($osm->node))
  {
    $gpx_wpts="";
    foreach ( $osm->node as $node )
    {
      $gpx_wpts.="\t<wpt lat=\"$node[lat]\" lon=\"$node[lon]\">\n";
      if (isset($node[timestamp]))
	$gpx_tags="\t\t<time>$node[timestamp]</time>\n";
      else
        $gpx_tags="";
      $gpx_tags_extension="";
      if (isset($node->tag))
      {
	$gpx_tags_extension="\t\t\t<extensions>\n";
	foreach ( $node->tag as $tag )
	{
	  $tag['k']=c($tag['k']);
	  $tag['v']=c($tag['v']);
	  
	  if ($tag['k']=="name")
	    $gpx_tags.="\t\t<name>$tag[v]</name>\n";
	  else if ($tag['k']=="ele")
	    $gpx_tags.="\t\t<ele>$tag[v]</ele>\n";
	  else
	    $gpx_tags_extension.="\t\t\t\t<tag k=\"$tag[k]\" v=\"$tag[v]\"/>\n";
	}
	$gpx_tags_extension.="\t\t\t</extensions>\n";
      }
      $gpx_wpts.="$gpx_tags$gpx_tags_extension\t</wpt>\n";
    }
  } 
    
    $gpx_en_tete='<?xml version="1.0" encoding="utf-8"?>'."\n".'<gpx creator="osm2nodesgpx" version="1.0" xmlns="http://www.topografix.com/GPX/1/0" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.topografix.com/GPX/1/0 http://www.topografix.com/GPX/1/0/gpx.xsd">'."\n";
    $gpx_end="</gpx>";
  
  return $gpx_en_tete.$gpx_wpts.$gpx_end;
}

// On pr�pare l'appel � osmconvert
$descriptorspec = array(
   0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
   1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
);

$process = proc_open($config['osmconvert_path']." - --drop-relations --all-to-nodes --out-osm", $descriptorspec, $pipes);

// On appel xapi avec exactement la m�me syntaxe qu'on nous a demand�
$xapi_p=fopen($config['xapi_url'].$_SERVER['QUERY_STRING'],"r");
$osm="";
while (!feof($xapi_p)) 
{
  // Au f�r et � mesure on nourri, en flux, osmconvert
  $osm = fread($xapi_p, 8192);
  fwrite($pipes[0], $osm);
}

fclose($pipes[0]);

// on r�cup�re le fichier osm r�sultant en retirant les noeuds (pas super proprement) qui n'ont pas de tags
// FIXME c'est pas parfait car osmconvert aurait pu avoir une option pour �a
// Donc on se retrouve avec des noeuds qui pourrait avoir un created_by / source ou autre tag sans rapport avec
// la requ�te xapi initiale car ils �taient le composant d'un way qui lui a �t� converti en noeud
$osm_node_only="";
while (!feof($pipes[1])) 
{
  $line=fgets($pipes[1]);
  if (!preg_match("/<node.*\/>$/",$line))
    $osm_node_only.=$line;
}
  fclose($pipes[1]);

// Choix du format renvoyer osm ou gpx
if ( preg_match("/^\/osm2nodegpx/",$_SERVER['REQUEST_URI'])) // On veut des noeuds au format gpx
{
  $nom_fichier="osm2nodegpx.gpx";
  $content_type="application/gpx";
  $xml=osmnodes2gpx($osm_node_only);
}
else // et par d�faut, osm
{
  $xml=$osm_node_only;
  $nom_fichier="osm2node.osm";
  $content_type="text/xml";
}
//header("Content-disposition: attachment; filename=$nom_fichier");
//header("Content-Type: $content_type; charset=utf-8"); // rajout du charset
//header("Content-Transfer-Encoding: binary");
print($xml);

?>