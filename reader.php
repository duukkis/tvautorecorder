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
  var $imdb_rating = 7.4;
  var $tvheadend_movie_genre = 16;
  
  var $start = null;
  var $span = 86400; // 24 h
  
  public function tvheadendreader($db, $tvheadend){
    $this->db = $db;
    $this->tvheadend = $tvheadend;
    $tomorrow = date("Y-m-d", strtotime("+1 day"));
    $this->start = strtotime($tomorrow." 00:00:00");
    $this->end = $this->start + $this->span;
  }
  
  /**
  * parse the channels and return good movies
  * @return movies with rating > imdb_rating
  */
  public function parseChannels(){
    $goodMovies = array();

    foreach($this->channels AS $chan => $cid){
      $url = $this->tvheadend."/api/epg/events/grid";
      $myvars = "dir=ASC&limit=400&sort=start&start=0&channel=".$cid[0];
      $c = $this->postRequest($url, $myvars);
      
      $movies = $this->parseChannelData($c);
      $goodMovies = array_merge($goodMovies, $this->goodMovies($movies));
      
    }
    return $goodMovies;
  }
  
  /**
  * make a request with curl
  * @param string $url to make the post on
  * @param string $vars variables on a list
  * @return string - the response
  */
  private function postRequest($url, $vars){
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_POST, 1);
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $vars);
    curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt( $ch, CURLOPT_HEADER, 0);
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);
    $c = curl_exec( $ch );
    curl_close($ch);
    return $c;
  }
  
  /**
  * parse channel data and pick out the movies
  * @param string - json formatted string
  * @return array
  */
  private function parseChannelData($c){
    $movies = array();
    $programs = json_decode($c);
    if(isset($programs->entries)){
      foreach($programs->entries AS $p) {
        // get the movies starting within span (default 24h)
        if($p->start > $this->start && $this->start <= $this->end){
          // movie duration must be over x seconds and title be set
          if ($p->stop - $p->start > ($this->movie_length * 60) && isset($p->title)){
            // movie genre
            if(isset($p->genre) && in_array($this->tvheadend_movie_genre, $p->genre)) {
              array_push($movies, $p);
            // sometimes movies are only prepended with text Elokuva
            } else if(substr($p->title,0,7) == "Elokuva"){
              array_push($movies, $p);
            }
          }
        }
      }
    }
    return $movies;
  }
  
  
  /**
  * filter out the good movies from all movies
  * @param array - movie array
  * @return array - movies that have rating > $this->imdb_rating
  */
  private function goodMovies($movies){
    $goodMovies = array();
    if(!empty($movies)){
      foreach($movies AS $i => $movie){
        $rating = 0;
        $match = null;
        // find the year from subtitle
        if(isset($movie->subtitle)){
          preg_match('/((19|20)\d{2})/', $movie->subtitle, $match);
        } else {
          $movie->subtitle = "";
        }

        if(isset($match[1])){
          $year = $match[1];
          // search with year
          $rating = $this->searchMovieRating($movie->title, $movie->subtitle, $year);
        } else {
          // search without year
          $rating = $this->searchMovieRating($movie->title, $movie->subtitle);
        }
        if($rating > $this->imdb_rating){
          $movie->imdb = $rating;
          array_push($goodMovies, $movie);
        }
      }
    }
    return $goodMovies;
  }
  
  /**
  * set the recording on tvheadend
  * @param int event_id - movie event to set the recording onto
  * @return void
  */
  public function recordMovie($event_id){
    $url = $this->tvheadend."/api/dvr/entry/create_by_event";
    $myvars = "config_uuid=&event_id=".$event_id;
    $c = $this->postRequest($url, $myvars);
  }
  
  /**
  * search a raring based on title, subtitle (longer description) and year
  * @param string $title - title to search on
  * @param string $title - subtitle to search on
  * @param string $year - year to search on
  * @return int imdb rating found on db, 0 if not found
  */
  private function searchMovieRating($title, $subtitle, $year = null){
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
  
  /**
  * find a single movie based on title / slash year
  * @param string $title - title to search on
  * @param string $year - year to search on
  * @return int imdb rating found on db, 0 if not found
  */
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

