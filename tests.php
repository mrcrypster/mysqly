<?php

require __DIR__ . '/../testy/testy.php';
require __DIR__ . '/mysqly.php';

class tests extends testy {
  protected static function prepare() {
    mysqly::auth('root', '', 'test');
    mysqly::fetch('TRUNCATE test');
  }
  
  public static function test_now() {
    $now = mysqly::fetch('SELECT NOW() now')[0]['now'];
    self::assert(true,
                 preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}/', $now) == 1,
                 'Checking current timestamp');
  }
  
  public static function test_fetch() {
    $new_id1 = mysqly::insert('test', ['age' => 29, 'name' => 'Test Fetch 1']);
    $new_id2 = mysqly::insert('test', ['age' => 29, 'name' => 'Test Fetch 2']);
    
    $row = mysqly::fetch('test', $new_id1)[0];
    self::assert(true,
                 $row['age'] == 29 && $row['name'] == 'Test Fetch 1',
                 'Checking ID fetch');
                 
    $rows = mysqly::fetch('test', ['age' => 29]);
    self::assert(true,
                 count($rows) == 2 && $rows[0]['age'] == 29,
                 'Checking parametric fetch');
                 
    $rows = mysqly::fetch('test', ['age' => 29, 'order_by' => 'id DESC']);
    self::assert(true,
                 count($rows) == 2 && $rows[0]['name'] == 'Test Fetch 2',
                 'Checking ordering');
                 
    $rows = mysqly::fetch('SELECT * FROM test WHERE age >= :age', ['age' => 29]);
    self::assert(true,
                 count($rows) == 2 && $rows[0]['age'] == 29,
                 'Checking SQL query');
  }
  
  public static function test_random() {
    mysqly::insert('test', ['age' => 31, 'name' => 'name 1']);
    mysqly::insert('test', ['age' => 31, 'name' => 'name 2']);
    
    $totals = [];
    for ( $i = 0; $i < 50; $i++ ) {
      $row = mysqly::random('test', ['age' => 31]);
      $totals[$row['name']]++;
    }
    
    self::assert(true,
                 $totals['name 1'] > 0 && $totals['name 2'] > 0,
                 'Checking randomizing queries');
  }
  
  public static function test_magic() {
    $new_id = mysqly::insert('test', ['age' => 30, 'name' => 'Some1']);
    $age = mysqly::test_age($new_id);
    self::assert(30,
                 (int)$age,
                 'Checking magic column fetch, ID');
    
    $age = 0;
    $age = mysqly::test_age(['id' => $new_id]);             
    self::assert(30,
                 (int)$age,
                 'Checking magic column fetch, parametric');
                 
    $row = mysqly::test_($new_id);
    self::assert(30,
                 (int)$row['age'],
                 'Checking magic column fetch, *');
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
  
  public static function test_remove() {
    $new_id = mysqly::insert('test', ['age' => 277, 'name' => 'Test Remove']);
    $row = mysqly::fetch('test', ['id' => $new_id])[0];
    mysqly::remove('test', $new_id);
    $removed_row = mysqly::fetch('test', ['id' => $new_id]);
    
    self::assert(true,
                 $row && !$removed_row,
                 'Checking row removal');
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
                 'Checking parametric IN');
                 
                 
    $rows = mysqly::fetch('SELECT * FROM test WHERE age IN (:ages)', ['ages' => [10, 11, 12]]);
    self::assert(3,
                 count($rows),
                 'Checking SQL IN binding');
  }
}

tests::run();