<?php

require __DIR__ . '/../mysqly.php';
mysqly::auth('test', 'test', 'test');



mysqly::exec('DROP TABLE IF EXISTS bulk');
mysqly::exec('CREATE TABLE bulk(id INT AUTO_INCREMENT PRIMARY KEY, val TEXT) ENGINE = INNODB');



$max = 5000;
$insert = [];
for ( $i = 0; $i < $max; $i++ ) {
  $insert[] = ['val' => md5(mt_rand(1, time()))];
}
$start = microtime(1);
foreach ( $insert as $row ) {
  mysqly::insert('bulk', $row);
}

echo "Inserted {$max} rows in ";
echo $delta = microtime(1) - $start;
echo ' seconds, ' . round($max/$delta, 2) . ' rows per second';
echo "\n";



for ( $k = 1; $k < 10; $k++ ) {
  $max = 100000;
  $insert = [];
  for ( $i = 0; $i < $max; $i++ ) {
    $insert[] = ['val' => md5(mt_rand(1, time()))];
  }

  echo round(strlen(json_encode($insert))/1024/1024, 2) . 'MB' . "\n";
  
  $start = microtime(1);
  mysqly::multi_insert('bulk', $insert);
  echo "Inserted {$max} rows in bulk in ";
  echo $delta = microtime(1) - $start;
  echo ' seconds, ' . round($max/$delta, 2) . ' rows per second';
}
echo "\n";