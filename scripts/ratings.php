<?php
$i = 0;

/*
New  Distribution  Votes  Rank  Title
      51......3.       6   3.8  "!Next?" (1994)

*/
include('../conn.php');

$voteA = array();
$rankA = array();

$handle = @fopen("ratings.list", "r");
if ($handle) {
  while (($buffer = fgets($handle, 4096)) !== false) {
    if($i >= 290844){
      $votes = trim(substr($buffer,18,7));
      $rank = trim(substr($buffer,26,5));
      $title = trim(substr($buffer,31));
      $vindex = floor($votes/1000);
      if(isset($voteA[$vindex])) {
        $voteA[$vindex]++;
      } else {
        $voteA[$vindex] = 1;
      }
      $rindex = floor($rank);
      if(isset($rankA[$rindex])) {
        $rankA[$rindex]++;
      } else {
        $rankA[$rindex] = 1;
      }
      
      $four = substr($title, -4);
      if($votes > 100 && $rindex >= 2 && $four != "(VG)" && $four != " (V)" && $four != "(TV)"){
        while(!in_array(substr($title,-1),array("0", "1", "2", "3", "4", "5", "6", "7", "8", "9")) && strlen($title) > 0){
          $title = substr($title, 0, -1);
        }
        
        $year = substr($title, -4);

        $title = utf8_encode(trim(substr($title, 0, -5)));
        $sql = "INSERT INTO elokuvat (imdbID, title, year, imdbRating) VALUES ('x".$i."', :title, :year, '".$rank."')";
        $q = $db->prepare($sql);
        $q->bindParam(':title', $title, PDO::PARAM_STR);
        $q->bindParam(':year', $year, PDO::PARAM_INT);
        $q->execute();
      }
    }
    $i++;
  }
  ksort($voteA);
  ksort($rankA);
  if (!feof($handle)) {
    echo "Stopped\n";
  }
  fclose($handle);
}

