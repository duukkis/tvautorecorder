<?php

include('conn.php');
include('reader.php');


$tv = new tvheadendreader($db, $tvheadend);

$goodMovies = $tv->readChannels();

print '<pre>';
// print_r($goodMovies);

// set the recording
if(isset($goodMovies[0]->eventId)){
  print $goodMovies[0]->eventId."\n";
  $tv->recordMovie($goodMovies[0]->eventId);
}
