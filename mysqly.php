<?php

class mysqly {
  private static $db;
  private static $auth = [];
  
  private static function exec($sql, $bind = []) {
    if ( !self::$db ) {
      if ( !self::$auth ) {
        self::$auth = @include '/var/lib/mysqly/.auth.php';
      }
      self::$db = new PDO('mysql:host=' . self::$auth['host'] . ';dbname=' . self::$auth['db'], self::$auth['user'], self::$auth['pwd']);
    }
    
    $statement = self::$db->prepare($sql);
    $statement->execute($bind);
    
    if ( (int)$statement->errorInfo()[0] ) {
      throw new Exception($statement->errorInfo()[2]);
    }
    
    return $statement;
  }
  
  
  /**
   * Connect to mysql server
   */
  public static function auth($user, $pwd, $db, $host = 'localhost') {
    self::$auth = ['user' => $user, 'pwd' => $pwd, 'db' => $db, 'host' => $host];
  }
  
  
  /**
   * Fetch array of rows based on SQL or parameters
   */
  public static function fetch($sql_or_table, $bind_or_where = []) {
    if ( strpos($sql_or_table, ' ') || (strpos($sql_or_table, 'SELECT ') === 0) ) {
      $sql = $sql_or_table;
      $bind = $bind_or_where;
    }
    else {
      $sql = "SELECT * FROM {$sql_or_table}";
      $order = '';
      
      if ( $bind_or_where ) {
        if ( is_array($bind_or_where) ) {
          foreach ( $bind_or_where as $k => $v ) {
            if ( $k == 'order_by' ) {
              $order = ' ORDER BY ' . $v;
              continue;
            }
            
            $where[] = "`{$k}` = :{$k}";
            $bind[":{$k}"] = $v;
          }
          
          if ( $where ) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
          }
        }
        else {
          $sql .= ' WHERE id = :id';
          $bind[":id"] = $bind_or_where;
        }
      }
      
      $sql .= $order;
    }
    
    $statement = self::exec($sql, $bind);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    $list = [];
    foreach ( $rows as $row ) $list[] = $row;
    
    return $list;
  }
  
  /**
   * Fetch array of values (single column list) based on SQL or parameters
   */
  public static function array($sql_or_table, $bind_or_where = []) {
    $rows = self::fetch($sql_or_table, $bind_or_where);
    foreach ( $rows as $row ) $list[] = array_shift($row);
    return $list;
  }
  
  /**
   * Fetch 2-column rows and trasform that to associaltive array: [1st column => 2nd column]
   */
  public static function key_vals($sql_or_table, $bind_or_where = []) {
    $rows = self::fetch($sql_or_table, $bind_or_where);
    foreach ( $rows as $row ) $list[array_shift($row)] = array_shift($row);
    return $list;
  }
  
  /**
   * Insert data to table
   */
  public static function insert($table, $data, $ignore = false) {
    foreach ( $data as $name => $value ) {
      $values[] = "`{$name}` = :{$name}";
      $bind[":{$name}"] = $value;
    }
    
    $values = implode(', ', $values);
    $sql = 'INSERT ' . ($ignore ? ' IGNORE ' : '') . "INTO {$table} SET {$values}";
    self::exec($sql, $bind);
    return self::$db->lastInsertId();
  }
  
  /**
   * Insert data to table, update date on duplicate keys
   */
  public static function insert_update($table, $data) {
    foreach ( $data as $name => $value ) {
      $values[] = "`{$name}` = :{$name}";
      $bind[":{$name}"] = $value;
    }
    
    $values = implode(', ', $values);
    $sql = 'INSERT ' . ($ignore ? ' IGNORE ' : '') . "INTO {$table} SET {$values} ON DUPLICATE KEY UPDATE {$values}";
    self::exec($sql, $bind);
  }
  
  /**
   * Update data in table
   */
  public static function update($table, $where, $data) {
    foreach ( $data as $name => $value ) {
      $values[] = "`{$name}` = :{$name}";
      $bind[":{$name}"] = $value;
    }
    
    foreach ( $where as $k => $v ) {
      $query[] = "`{$k}` = :{$k}";
      $bind[":{$k}"] = $v;
    }
    
    $values = implode(', ', $values);
    $where = $query ? ' WHERE ' . implode(' AND ', $query) : '';
    $sql = "UPDATE {$table} SET {$values} {$where}";
    $statement = self::exec($sql, $bind);
    return self::$db->lastInsertId();
  }
  
  /**
   * Remove data from table
   */
  public static function remove($table, $where) {
    if ( is_array($where) ) {
      foreach ( $bind_or_where as $k => $v ) {
        $query[] = "`{$k}` = :{$k}";
        $bind[":{$k}"] = $v;
      }
    }
    else {
      $query[] = 'id = :id';
      $bind[':id'] = $where;
    }
    
    $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $query);
    self::exec($sql, $bind);
  }
}
