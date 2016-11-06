<?php
/**
* read tvheadend and parse the program data based on movie and imdb setting
*/
class tvheadendreader{
  
  var $db = null;
  var $tvheadend = null;
  
  var $channels = array(
      "YLE TV1" => array("24bf889cf39f6906c566e4548d8e7956"),
      "YLE TV2" => array("0320fe1faa5cd12c2f7c641b9d0f3b64"),
      "AVA" => array("ade975fab1cdc1b0e56bf0f87430fd2d"),
      "FOX" => array("bb0e3d1a0657dc7f23f729ae7dad02c2"),
      "Frii" => array("3a96080bc226f0660cf4726bf3e00a12"),
      "Hero" => array("09a07a661597d242907edb55e4084d09"),
      "Jim" => array("e9742f4fbf624df943a4e7d746ce9796"),
      "Kutonen" => array("9a6d5444d7742db5cfda7baecdcf3421"),
      "Liv" => array("f4117106bbac64555c797a174744670a"),
      "MTV3" => array("43df06ed4e676a390e6bc0f99e75ed12"),
      "Nelonen" => array("0f2dec046db3e95b648a7abd44a5e11c"),
      "Sub" => array("95dae29515c5b9b142918b090cef78b7"),
      "TV5" => array("35b1a9622b8ab2ad480e6868096d4287"),
      "YLE FEM" => array("067c601b06d0edbb2b943f793e6a6ec3"),
      "YLE TEEMA" => array("ef028c46382de56dcdd81c84f98b35c3"),
    );

  var $movie_length = 90; // minutes
  var $imdb_rating = 6.9;
  
  public function tvheadendreader($db, $tvheadend){
    $this->db = $db;
    $this->tvheadend = $tvheadend;
  }
  
  public function readChannels(){
    $goodMovies = array();

    foreach($this->channels AS $chan => $cid){
      $url = $this->tvheadend."/api/epg/events/grid";
      $myvars = "dir=ASC&limit=400&sort=start&start=0&channel=".$cid[0];

      $ch = curl_init( $url );
      curl_setopt( $ch, CURLOPT_POST, 1);
      curl_setopt( $ch, CURLOPT_POSTFIELDS, $myvars);
      curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
      curl_setopt( $ch, CURLOPT_HEADER, 0);
      curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);

      $c = curl_exec( $ch );
      $movies = $this->parseChannelData($c);
      $goodMovies = array_merge($goodMovies, $this->goodMovies($movies));
      curl_close($ch);
    }
    return $goodMovies;
  }
  
  /**
  * parse channel data and pick out the movies
  */
  private function parseChannelData($c){
    $movies = array();
    $programs = json_decode($c);
    foreach($programs->entries AS $p) {
      if ($p->stop - $p->start > ($this->movie_length * 60) && isset($p->title)){ // movie duration must be over x seconds
        if(isset($p->genre) && in_array(16, $p->genre)) {
          array_push($movies, $p);
        } else if(substr($p->title,0,7) == "Elokuva"){ // Elokuva
          array_push($movies, $p);
        }
      }
    }
    // \b(19|20)\d{2}\b
    //print_r($movies);
    return $movies;
  }
  
  
  /**
  * find out about the good movies
  */
  private function goodMovies($movies){
    $goodMovies = array();
    if(!empty($movies)){
      foreach($movies AS $i => $movie){
        $rating = 0;
        $match = null;
        if(isset($movie->subtitle)){
          preg_match('/((19|20)\d{2})/', $movie->subtitle, $match);
        } else {
          $movie->subtitle = "";
        }

        if(isset($match[1])){
          $year = $match[1];
          // print $year;
          $rating = $this->findMovieRating($movie->title, $movie->subtitle, $year);
        } else {
          $rating = $this->findMovieRating($movie->title, $movie->subtitle);
        }
        if($rating > $this->imdb_rating){
          $movie->imdb = $rating;
          array_push($goodMovies, $movie);
        } else {
        }
      }
    }
    return $goodMovies;
  }
  
  /**
  * set the recording
  */
  public function recordMovie($event_id){
    $url = $this->tvheadend."/api/dvr/entry/create_by_event";
    $myvars = "config_uuid=&event_id=".$event_id;
    
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_POST, 1);
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $myvars);
    curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt( $ch, CURLOPT_HEADER, 0);
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);

    $c = curl_exec( $ch );    
    curl_close($ch);
  }
  
  /**
  *
  */
  private function findMovieRating($title, $subtitle, $year = null){
    if(mb_substr($title, 0, 8) == "Elokuva:"){
      $title = mb_substr($title, 8);
    }
    $title = mb_substr($title, 0, 30)."%"; // 30 chars should be enough
    $h = $this->findMovie($title,$year);
    if($h > 0){ return $h; }
    $title = mb_substr($title, 0, 20)."%"; // 20 chars should be enough
    $h = $this->findMovie($title, $year);
    if($h > 0){ return $h; }
    // not found, try with subtitle
    if(!empty($subtitle)){
      if(mb_substr($subtitle, 0, 1) == "("){
        $subtitle = mb_substr($subtitle, 1);
      }
      $comma_pos = mb_strpos($subtitle, ",");
      $slash_pos = mb_strpos($subtitle, "/");
      $par_pos = mb_strpos($subtitle, ")");

      if($comma_pos !== false){
        $h = $this->findMovie(mb_substr($subtitle,0,$comma_pos), $year);
        if($h > 0){ return $h; }
      }
      if($slash_pos !== false){
        $h = $this->findMovie(mb_substr($subtitle,0,$slash_pos), $year);
        if($h > 0){ return $h; }
      }
      if($par_pos !== false){
        $h = $this->findMovie(mb_substr($subtitle,0,$par_pos), $year);
        if($h > 0){ return $h; }
      }
    }
    return 0;
  }
  
  private function findMovie($title, $year){
    $title = trim($title);
    // print $title.$year."\n";
    if(!empty($year)){
      $sql = "SELECT imdbRating FROM elokuvat WHERE year = :year AND title LIKE :title ";
    } else {
      $sql = "SELECT imdbRating FROM elokuvat WHERE title LIKE :title ";
    }
    $q = $this->db->prepare($sql);
    if(!empty($year)){
      $q->bindParam(':year', $year, PDO::PARAM_STR);
    }
    $q->bindParam(':title', $title, PDO::PARAM_STR);
    $q->execute();
    if ($r = $q->fetch(PDO::FETCH_ASSOC)) {
      return $r["imdbRating"];
    }
  }

}

