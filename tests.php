<?php

require __DIR__ . '/../testy/testy.php';
require __DIR__ . '/mysqly.php';

class tests extends testy {
  protected static function prepare() {
    mysqly::auth('root', '', 'test');
  }
  
  public static function test_now() {
    $now = mysqly::fetch('SELECT NOW() now')[0]['now'];
    self::assert(true,
                 preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}/', $now) == 1,
                 'Checking current timestamp');
  }
  
  public static function test_insert() {
    $pre_count = (int)mysqly::fetch('SELECT count(*) t FROM test')[0]['t'];
    $new_id = mysqly::insert('test', ['age' => 27, 'name' => 'Test']);
    $post_count = (int)mysqly::fetch('SELECT count(*) t FROM test')[0]['t'];
    self::assert($pre_count + 1,
                 $post_count,
                 'Checking simple insertion');
                 
    self::assert(true,
                 $new_id > 0,
                 'Checking return ID');
  }
  
  public static function test_insert_update() {
    $new_id = mysqly::insert('test', ['age' => 27, 'name' => 'Test']);
    mysqly::insert_update('test', [
      'id' => $new_id,
      'age' => 28
    ]);
    
    $row = mysqly::fetch('test', ['id' => $new_id])[0];
    self::assert(28,
                 (int)$row['age'],
                 'Checking updated column value');
  }
  
  public static function test_update() {
    $new_id = mysqly::insert('test', ['age' => 27, 'name' => 'Test']);
    
    mysqly::update('test', $new_id, [ 'age' => 28 ]);
    $row = mysqly::fetch('test', ['id' => $new_id])[0];
    self::assert(28,
                 (int)$row['age'],
                 'Checking updated column value, ID update');
                 
    mysqly::update('test', ['age' => 28], [ 'name' => 'Don' ]);
    $row = mysqly::fetch('test', ['id' => $new_id])[0];
    self::assert('Don',
                 $row['name'],
                 'Checking updated column value, parametric update');
  }
  
  public static function test_in() {
    mysqly::remove('test', ['age' => [10, 11, 12]]);
    mysqly::insert('test', ['age' => 10, 'name' => 'Test']);
    mysqly::insert('test', ['age' => 11, 'name' => 'Test']);
    mysqly::insert('test', ['age' => 12, 'name' => 'Test']);
    
    $rows = mysqly::fetch('test', ['age' => [10, 11, 12]]);
    self::assert(3,
                 count($rows),
                 'Checking rows selected with IN clause');
  }
}

tests::run();