<?php

require __DIR__ . '/../mysqly.php';
mysqly::auth('test', 'test', 'test');



# Cache
mysqly::exec('DROP TABLE IF EXISTS _cache');



# Settings keys

$start = microtime(1);
$max = 10000;
for ( $i = 0; $i < $max; $i++ ) {
  mysqly::cache(md5($i), function() { return mt_rand(1000, 9999999); });
}

$delta = microtime(1) - $start;
$ops = $max / $delta;
echo "Setting {$max} cache keys: " . round($delta, 3) . "s ({$ops} ops/sec)\n";



# Getting keys

$start = time();
$max = 100000;
for ( $i = 0; $i < $max; $i++ ) {
  mysqly::cache(md5(mt_rand(0, $max / 10)));
}

$delta = microtime(1) - $start;
$ops = $max / $delta;
echo "Getting {$max} cache keys: " . round($delta, 3) . "s ({$ops} ops/sec)\n";