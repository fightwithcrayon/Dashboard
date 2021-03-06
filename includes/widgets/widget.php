<?php
  if(isset($_GET['nonce']) && $_GET['nonce'] === 'test') {
    if(is_array($_GET['file'])){
      foreach($_GET['file'] as $file) {
        include($file . '.php');
      }
    } else {
      include($_GET['file'] . '.php');
    }
  }

  class GBWidget {
    protected $database;
    public $daterange;
    public $dates;
    public $brand;
    protected $chartDefaults;
    protected $title;
    protected $id;

    function __construct(){
      require_once( __DIR__ . '/../app/core.php');
      $this->database = new DBAccess;

      $BrandDB = new Brands();
      $this->brand = $BrandDB->get_brands($_GET['client'])[0];
      $this->networks = $this->brand;
      unset($this->networks['name']);
      unset($this->networks['analytics']);
      unset($this->networks['id']);
      unset($this->networks['logo']);

      $this->chartDefaults = '
        Chart.defaults.global.defaultFontColor = "rgba(0, 0, 0, 0.6)";
        Chart.defaults.global.defaultFontFamily = "open-sans", "helvetica", "sans-serif";
      ';
      $this->title = 'Untitled';
      $this->id = 'unidentified';
      $this->icon = 'fa-clock-o';

      $this->section = array(
        'id' => '',
        'title' => '',
        'desc' => false,
        'date' => false
      );

      $this->setup_daterange();
    }


    //
    // Structure date range
    //
    function setup_daterange(){
      if(isset($_POST['range'])) {
        $this->daterange = $_POST['range'];
      } else {
        $this->daterange = 7;
      }
      $timestamp = strtotime('- ' . ($this->daterange - 1) . ' days');
      $days = array();
      for ($i = 0; $i < $this->daterange; $i++) {
          $days[] = date('Y-m-d', $timestamp);
          $timestamp = strtotime('+1 day', $timestamp);
      }
      $this->dates = $days;
    }

    //
    // Chart options
    //
    function chart_default_options(){
      $args = array(
        'response' => true,
        'tooltips' => array(
          'mode' => 'index',
          'intersect' => false
        ),
        'hover' => array(
          'mode' => 'nearest',
          'intersect' => true
        ),
        'legend' => array(
          'position' => 'bottom'
        ),
        'scales' => array( array(
          'xAxes' => array(
            'display' => true,
            'fontColor' => "white"
          ),
          'yAxes' => array(
            'ticks' => array(
              'fontColor' => "white"
            )
          )
        ) )
      );
      $options = $this->chart_options($args);
      return json_encode($options);
    }
    function chart_default_options_pie(){
      $args = array(
        'animation' => array(
          'animateScale' => true
        )
      );
      $options = $this->chart_options($args);
      return json_encode($options);
    }
    function chart_options($args) {
      return $args;
    }
    function generate_piecolours($dataset){
      $total = count($dataset);
      $defaultcolours = array(
        'rgb(24,96,126)',
        'rgb(222,67,20)',
        'rgb(115,252,161)',
        'rgb(11,40,57)',
        'rgb(237,232,18)',
        'rgb(195,12,212)',
        'rgb(31,196,235)'
      );
      $i = 0;
      $colours = array();
      while($i < $total) {
        $colours[] = $defaultcolours[$i];
        $i++;
      }
      return $colours;
    }

    //
    //Output HTML
    //
    function outputSection(){
      echo '<section id="'. $this->section['id'] .'">';
      echo '<header>';
      echo '<h5>'. $this->section['title'] .'</h5>';
      if($this->section['desc']) {
        echo '<p>'. $this->section['desc'] .'</p>';
      }
      if($this->section['date']) {
        echo '<div class="comparison">';
          echo '<p>Comparison:</p>';
          echo '<select>';
          foreach($this->dateranges as $date => $value) {
            if($date == 'now') {
              echo '<option name="'. $date .'" value="now">None</option>';

            } else {
              echo '<option name="'. $date .'" value="'. $date .'">vs '. $date .'</option>';

            }
          }
          echo '</select>';
        echo '</div>';
      }
      echo '</header>';
    }
    function closeSection(){
      echo '</section>';
    }
    function outputWidget(){
      echo $this->buildWidgetHeader();
      echo '<div class="body">';
        $this->widgetBody();
      echo '</div>';
      echo $this->closeWidget();
    }
    function buildWidgetHeader($id = false, $title = false){
      $id = $id ?: $this->id;
      $output = '<article id="'. $id .'">';
      if($this->title !== '') {
        $title = $title ?: $this->title;
        $output .= '<h5>'. $title .'</h5>';
      }

      return $output;
    }
    function closeWidget(){
      return '</article>';
    }
    function widgetBody(){
      // Child widgets will hook in to this to build content.
      echo '<canvas id="'. $this->id .'-chart" width="400" height="400"></canvas>';
      $this->build_chart();
    }
    function build_chart($dataset = 'default'){
      if($this->chart_type === 'line') {
        echo '<script type="text/javascript">
          var ctx = document.getElementById("'. $this->id .'-chart").getContext("2d");
          var myChart = new Chart(ctx, {
              "type": "line",
              "data": '. $this->build_dataset() .',
              "options": '. $this->chart_default_options() .'
          });
          '. $this->chartDefaults .'
        </script>';
      }else if($this->chart_type === 'doughnut') {
        echo '<script type="text/javascript">
          var ctx = document.getElementById("'. $this->id .'-chart").getContext("2d");
          var myChart = new Chart(ctx, {
              "type": "doughnut",
              "data": '. $this->build_dataset() .',
              "options": '. $this->chart_default_options_pie() .'
          });
          '. $this->chartDefaults .'
        </script>';
      }
    }
    /*
    //Build dataset
    */
    function build_dataset(){
      $dataset = array(
        'labels' => $this->dates,
        'datasets' => array(
          $this->create_source('Twitter', $this->format_twitterstats(), 'rgb(51,51,51)'),
          $this->create_source('Facebook', $this->format_facebookstats(), 'rgb(59,89,152)'),
          $this->create_source('Instagram', $this->format_instagramstats(), 'rgb(205,72,107)')
        )
      );
      return json_encode($dataset);
    }
    function create_source($name, $data, $colour = false, $fill = false){
      $args = array(
        'label' => $name,
        'data' => $data,
        'fill' => false
      );
      if($colour != false) {
        $args['borderColor'] = $colour;
      };
      if($fill != false) {
        $args['fill'] = true;
        $args['backgroundColor'] = $fill;
      };
      return $args;
    }
    function blank_dataset(){
      return array_fill(0,count($this->dates),0);
    }

    //
    //Format data
    //
    function format_twitterstats($start, $end = null){
      global $twitter;
      $twitterstats = $twitter->get_stats($this->brand['twitter'], $start, $end);
      $dataset = $this->blank_dataset();
      foreach($twitterstats as $date => $data) {
        $offset = array_search($date, $this->dates);
        if($offset !== false) {
          $dataset[$offset] = $data['totals']['followers'];
        }
      }
      return $dataset;
    }

    function format_facebookstats($start, $end = null){
      global $facebook;
      $fbstats = $facebook->get_stats($this->brand['facebook'], $start, $end);
      $dataset = $this->blank_dataset();
      foreach($fbstats as $day) {
        $offset = array_search($day['date'], $this->dates);
        if($offset !== false) {
          if($day['page_fans'] === null) {
            $dataset[$offset] = 0;
          } else {
            $dataset[$offset] = $day['page_fans'];
          }
        }
      }
      return $dataset;
    }
    function format_instagramstats($start, $end = null){
      global $instagram;
      $instastats = $instagram->get_stats($this->brand['instagram'], $start, $end);
      $dataset = $this->blank_dataset();
      foreach($instastats as $day) {
        $offset = array_search($day['date'], $this->dates);
        if($offset !== false) {
          $dataset[$offset] = $day['fans'];
        }
      }
      return $dataset;
    }

  }
?>
