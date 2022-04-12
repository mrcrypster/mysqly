<?php

require __DIR__ . '/../mysqly.php';
mysqly::auth('test', 'test', 'test');


$max = 1000;
$threads = 25;


if ( !$engine = $argv[1] ) {
  mysqly::exec('DROP TABLE IF EXISTS my_isam');
  mysqly::exec('CREATE TABLE my_isam(id INT AUTO_INCREMENT PRIMARY KEY, val TEXT) ENGINE = MYISAM');
  mysqly::exec('DROP TABLE IF EXISTS inno_db');
  mysqly::exec('CREATE TABLE inno_db(id INT AUTO_INCREMENT PRIMARY KEY, val TEXT) ENGINE = INNODB');
  
  foreach ( ['my_isam', 'inno_db'] as $engine ) {
    @unlink('/tmp/engine.stats');
    echo "Testing {$engine} engine \n";
    
    for ( $i = 0; $i < $threads; $i++ ) {
      exec('php ' . __FILE__ . ' ' . $engine . ' >> /tmp/engine.stats &');
    }
    
    $start = microtime(1);
    
    while ( true ) {
      $count = exec('ps aux | grep [i]nnodb_vs_myisam | wc -l');
      if ( $count <= 1 ) {
        break;
      }
      
      usleep(50);
    }
  
    echo 'It took ';
    echo microtime(1) - $start;
    echo 's to execute ' . ($max * $threads) . ' inserts and ';
    echo ($max * $threads) . ' updates' . "\n";
  }
}
else {
  $start = microtime(1);
  for ( $i = 0; $i < $max; $i++ ) {
    mysqly::insert($engine, ['val' => md5(mt_rand(1, time()))]);
    
    for ( $j = 0; $j < 5; $j++ ) {
      mysqly::update($engine, ['id' => mt_rand(1, $max)], ['val' => md5(mt_rand(1, time()))]);
    }
  }
  
  echo $delta = microtime(1) - $start;
  echo "\n";
}