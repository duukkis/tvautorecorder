<?php
$i = 0;
/*

CREATE TABLE IF NOT EXISTS `elokuvat` (
  `imdbId` varchar(50) CHARACTER SET utf8 NOT NULL,
  `title` varchar(255) CHARACTER SET utf8 NOT NULL,
  `year` varchar(10) CHARACTER SET utf8 NOT NULL,
  `imdbRating` double(2,1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--# THE INSERTION CAN BE DONE WITHOUT THE KEYS AND AFTER INSERTION ADD THE KEYS
-- OR WITH KEYS, sligtly slower

ALTER TABLE `elokuvat`
  ADD PRIMARY KEY (`imdbId`),
  ADD KEY `title` (`title`),
  ADD FULLTEXT KEY `title_2` (`title`);

New  Distribution  Votes  Rank  Title
      51......3.       6   3.8  "!Next?" (1994)

Parses through the imdb ratings list and picks out the movies
run this on command line php ratings.php

THIS CAN BE IMPROVED A LOT

*/
include('../conn.php');

$voteA = array();
$rankA = array();

$this_year = date("Y");

$handle = @fopen("ratings.list", "r");
if ($handle) {
  while (($buffer = fgets($handle, 4096)) !== false) {
    $votes = trim(substr($buffer,18,7));
    $rank = trim(substr($buffer,26,5));
    $title = trim(substr($buffer,31));
    $vindex = floor($votes/1000);
    $rindex = floor($rank);
    $four = substr($title, -4);

    if($votes > 100 && $rindex >= 2 && $four != "(VG)" && $four != " (V)" && $four != "(TV)"){
      // clean the movie name
      while(!in_array(substr($title,-1),array("0", "1", "2", "3", "4", "5", "6", "7", "8", "9")) && strlen($title) > 0){
        $title = substr($title, 0, -1);
      }

      $year = substr($title, -4);
      preg_match('/((19|20)\d{2})/', $title, $match);
      if(isset($match[1])){
        if($match[1] <= $this_year){
          if($year != $match[1]){
            $year = $match[1];
          }
        }
      }
      
      if($year >= 1874 && $year <= $this_year){
        $title = utf8_encode(trim(substr($title, 0, -5)));
        $title = trim($title, '"');
        $sql = "INSERT INTO elokuvat (imdbID, title, year, imdbRating) VALUES ('x".$i."', :title, :year, '".$rank."')";
        $q = $db->prepare($sql);
        $q->bindParam(':title', $title, PDO::PARAM_STR);
        $q->bindParam(':year', $year, PDO::PARAM_INT);
        $q->execute();
      }
    }
    $i++;
  }
  if (!feof($handle)) {
    echo "Stopped\n";
  }
  fclose($handle);
}

