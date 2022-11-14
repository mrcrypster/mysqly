<?php

require __DIR__ . '/../../testy/testy.php';
require __DIR__ . '/../mysqly.php';

class db1 extends mysqly {}

class tests extends testy {
  protected static function prepare() {
    mysqly::auth('test', 'test', 'test');
    mysqly::exec('TRUNCATE test');
  }
  
  public static function test_exec() {
    mysqly::insert('test', ['age' => 20, 'name' => 'name 1']);
    mysqly::exec('UPDATE test set age = 10');
    
    self::assert(1,
                 mysqly::count('test', ['age' => 10]),
                 'Checking exec');
  }
  
  public static function test_error() {
    try {
      mysqly::exec('SELECT * FROM unknown');
    }
    catch ( PDOException $e ) {
      $message = $e->getMessage();
    }
    
    self::assert(true,
                 strpos($message, 'not found') !== false,
                 'Checking error handling');
  }
  
  public static function test_multiple_connections() {
    
    $now = db1::now();
    self::assert(true,
                 preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}/', $now) == 1,
                 'Checking multiple DB connections');
  }
  
  public static function test_now() {
    $now = mysqly::now();
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
      if ( !isset($totals[$row['name']]) ) {
        $totals[$row['name']] = 0;
      }

      $totals[$row['name']]++;
    }
    
    self::assert(true,
                 $totals['name 1'] > 0 && $totals['name 2'] > 0,
                 'Checking randomizing queries');
  }
  
  public static function test_count() {
    mysqly::insert('test', ['age' => 71, 'name' => 'name 1']);
    mysqly::insert('test', ['age' => 71, 'name' => 'name 2']);
    
    
    self::assert(2,
                 mysqly::count('test', ['age' => 71]),
                 'Checking count parametric queries');
                 
    self::assert(2,
                 mysqly::count('SELECT count(*) FROM test WHERE age = 71'),
                 'Checking count SQL queries');
  }
  
  public static function test_aggregations() {
    mysqly::remove('test', ['name' => 'names_agg']);
    mysqly::insert('test', ['age' => 100, 'name' => 'names_agg']);
    mysqly::insert('test', ['age' => 101, 'name' => 'names_agg']);
    mysqly::insert('test', ['age' => 101, 'name' => 'names_agg']);
    mysqly::insert('test', ['age' => 102, 'name' => 'names_agg']);
    
    
    self::assert((float)101,
                 (float)mysqly::avg_age('test', ['name' => 'names_agg']),
                 'Checking average aggregation');
                 
    self::assert((float)102,
                 (float)mysqly::max_age('test', ['name' => 'names_agg']),
                 'Checking max aggregation');
                 
    self::assert((float)100,
                 (float)mysqly::min_age('test', ['name' => 'names_agg']),
                 'Checking min aggregation');
  }
  
  public static function test_increments() {
    mysqly::remove('test', ['name' => 'name_inc']);
    mysqly::insert('test', ['age' => 200, 'name' => 'names_inc']);
    mysqly::increment('age', 'test', ['name' => 'names_inc']);

    
    self::assert((int)201,
                 (int)mysqly::test_age(['name' => 'names_inc']),
                 'Checking increment');
  }
  
  public static function test_decrements() {
    mysqly::remove('test', ['name' => 'name_dec']);
    mysqly::insert('test', ['age' => 200, 'name' => 'name_dec']);
    mysqly::decrement('age', 'test', ['name' => 'name_dec']);

    
    self::assert((int)199,
                 (int)mysqly::test_age(['name' => 'name_dec']),
                 'Checking decrement');
  }
  
  public static function test_toggle() {
    mysqly::remove('test', ['name' => 'name_toggle']);
    mysqly::insert('test', ['age' => 100, 'name' => 'name_toggle']);
    mysqly::toggle('test', ['name' => 'name_toggle'], 'age', 100, 200);

    self::assert((int)200,
                 (int)mysqly::test_age(['name' => 'name_toggle']),
                 'Checking value toggle, first step');
                 
    mysqly::toggle('test', ['name' => 'name_toggle'], 'age', 100, 200);
    
    self::assert((int)100,
                 (int)mysqly::test_age(['name' => 'name_toggle']),
                 'Checking value toggle, second step');
  }
  
  public static function test_transactions() {
    mysqly::remove('test', ['name' => 'transaction']);
    
    mysqly::transaction(function() {
      mysqly::insert('test', ['name' => 'transaction']);
      return false;
    });
    
    self::assert((int)0,
                 (int)mysqly::count('test', ['name' => 'transaction']),
                 'Checking rollback');
                 
    mysqly::transaction(function() {
      mysqly::insert('test', ['name' => 'transaction']);
      return true;
    });
    
    self::assert((int)1,
                 (int)mysqly::count('test', ['name' => 'transaction']),
                 'Checking commit');
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
                 
    $rows = mysqly::test(['age' => 30]);
    self::assert(true,
                 ($rows[0]['age'] == 30) && (count($rows) == 1),
                 'Checking magic table list fetch, parametric');
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
  
  public static function test_multi_insert() {
    $pre_count = (int)mysqly::fetch('SELECT count(*) t FROM test')[0]['t'];
    mysqly::multi_insert('test', [
      ['age' => 27, 'name' => 'Test1'],
      ['age' => 27, 'name' => 'Test2'],
      ['age' => 27, 'name' => 'Test3']
    ]);
    $post_count = (int)mysqly::fetch('SELECT count(*) t FROM test')[0]['t'];
    self::assert($pre_count + 3,
                 $post_count,
                 'Checking multiple rows insert');
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

  public static function test_json_insert() {
    mysqly::exec('DROP TABLE IF EXISTS json_test');
    mysqly::exec('CREATE TABLE json_test(id bigint, data_json JSON)');
    mysqly::insert('json_test', [
      'id' => 1, 'data_json' => ['name' => 'Denys', 'age' => 37]
    ]);
    $rows = mysqly::fetch('json_test');
    self::assert('Denys', $rows[0]['data_json']['name'], 'JSON encoded/decoded');
  }
  
  public static function test_json() {
    mysqly::remove('test', ['id' => 300]);
    mysqly::insert('test', ['id' => 300, 'name' => json_encode(['json' => 'ok'])]);
    
    $rows = mysqly::fetch('SELECT name name_json FROM test WHERE id = 300');
    self::assert('ok',
                 $rows[0]['name_json']['json'],
                 'Checking automatic JSON deconversion');
    
    
    mysqly::update('test', ['id' => 300], ['name.json' => 'updated']);
    $rows = mysqly::fetch('SELECT name name_json FROM test WHERE id = 300');
    self::assert('updated',
                 $rows[0]['name_json']['json'],
                 'Checking automatic JSON attributes update');
  }
  
  public static function test_export() {
    @unlink('/tmp/test.csv');
    mysqly::export_csv('/tmp/test.csv', 'test', [], 'id, name');
    $f = fopen('/tmp/test.csv', 'r');
    while ( $row = fgetcsv($f) ) {
      $csv[] = $row;
    }

    self::assert(true,
                 count($csv) > 1 && count($csv[0]) > 1,
                 'Checking CSV export');
                 
                 
    @unlink('/tmp/test.tsv');
    mysqly::export_tsv('/tmp/test.tsv', 'test');
    $f = fopen('/tmp/test.tsv', 'r');
    while ( $row = fgetcsv($f, null, "\t") ) {
      $tsv[] = $row;
    }
    
    self::assert(true,
                 count($tsv) > 1 && count($tsv[0]) > 1,
                 'Checking TSV export');
  }
  
  public static function test_key_value_storage() {
    mysqly::set('test', 'hi');
    self::assert('hi',
                 mysqly::get('test'),
                 'Checking key_value storage insert');
                 
    mysqly::set('test', 'ok');
    self::assert('ok',
                 mysqly::get('test'),
                 'Checking key_value storage update');
                 
    mysqly::set('test', 'ok1', '2');
    self::assert('ok1',
                 mysqly::get('test', '2'),
                 'Checking key_value storage spaces');
    self::assert('ok',
                 mysqly::get('test'),
                 'Checking key_value storage spaces');

    mysqly::unset('test');
    self::assert(NULL,
                 mysqly::get('test'),
                 'Checking key_value storage delete');
  }
  
  public static function test_cache() {
    mysqly::exec('DROP TABLE IF EXISTS _cache');
    
    mysqly::uncache('a1');
    
    $calls = 0;
    $gen = function() use ( &$calls) { $calls++; return 25; };
    
    mysqly::cache('a1', $gen);
    $value = mysqly::cache('a1', $gen);
    
    self::assert(25,
                 $value,
                 'Checking cached value');
    
    self::assert(25,
                 mysqly::cache('a1', function() { return 26; }),
                 'Checking caching engine');
    
    self::assert(1,
                 $calls,
                 'Checking caching engine');
    
    mysqly::uncache('a1');
    self::assert(null,
                 mysqly::cache('a1', function() { return null; }),
                 'Checking cache removal');
  }
  
  public static function test_queue() {
    mysqly::exec('DROP TABLE IF EXISTS _queue');
    
    $job = mysqly::read('sample');
    self::assert(null,
                 $job,
                 'Checking queue, absent jobs');
    
    mysqly::write('sample', ['some' => 'data']);
    $job = mysqly::read('sample');
    self::assert('data',
                 $job['some'],
                 'Checking queue job');
    
    $job = mysqly::read('sample');
    self::assert(null,
                 $job,
                 'Checking queue, absent jobs');
                 
                 
    mysqly::write('sample', '1');
    mysqly::write('sample', '2');
    
    try { mysqly::on('sample', function($j) { throw new Exception($j); }); }
    catch ( Exception $e ) {
      self::assert('1',
                 $e->getMessage(),
                 'Checking queue jobs order and subscription');
    }
    
    try { mysqly::on('sample', function($j) { throw new Exception($j); }); }
    catch ( Exception $e ) {
      self::assert('2',
                 $e->getMessage(),
                 'Checking queue jobs order and subscription');
    }
  }
  
  public static function test_auto_create() {
    mysqly::exec('DROP TABLE IF EXISTS test1');
    
    try {
      mysqly::insert('test1', ['id' => 25]);
    }
    catch ( PDOException $e ) {
      self::assert(true,
                 strpos($e->getMessage(), "doesn't exist") !== false,
                 'Checking table not being created');
    }
    
    mysqly::auto_create(true);
    mysqly::insert('test1', ['id' => 25, 'name' => 'Donald']);
    $row = mysqly::test1_(25);
    self::assert('Donald',
                 $row['name'],
                 'Checking table auto create');
                 
    mysqly::update('test1', ['id' => 25], ['age' => 97]);
    $row = mysqly::test1_(25);
    
    self::assert('97',
                 $row['age'],
                 'Checking table auto alter');
  
  
    mysqly::exec('DROP TABLE IF EXISTS test2');
    mysqly::insert_update('test2', ['id' => 1]);
    mysqly::insert_update('test2', ['id' => 1, 'name' => 'Joe']);
    $row = mysqly::test2_(1);
    self::assert(1,
                 intval($row['id']),
                 'Checking table insert_update create/alter');
                 
    self::assert('Joe',
                 $row['name'],
                 'Checking table insert_update create/alter');
    
  }
}

tests::run();