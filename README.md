Micro PHP mysql lib (~ 200 lines of code) with ultra powerful CRUD for faster than ever development:
- parametric fetch/insert/update/delete (based on associative arrays): `fetch('table', ['col' => 10])`
- native SQL queries support
- values binding for security
- PDO-based, no extra dependencies
- near-zero overhead because of static class in a single file
- builtin `IN` support: `fetch('table', ['id' => [1, 2, 3]])`
- magic methods for even faster development:
  - get column value from a table by id
  - get single row from a table by id
  - get count/avg/sum/max/min from table by parametric filter (implementing in #6)

# Installation
Just download latest version of lib:
```bash
wget https://raw.githubusercontent.com/mrcrypster/mysqly/main/mysqly.php
```

# Usage
Include:
```php
require 'mysqly.php';
```

Authenticate:
```php
mysqly::auth('user', 'pwd', 'db', 'localhost');
```

Fetch something:
```php
$rows = mysqly::fetch('SELECT NOW()');
print_r( $rows );
```

# Better authentication
To make authentication more secure, you can create auth file `/var/lib/mysqly/.auth.php` with auth data:
```php
<?php return [
  'user' => 'user',
  'pwd' => 'pwd',
  'db' => 'db'
];
```

# Fetch data
### Parametric fetch
```php
$users = mysqly::fetch('users', [ 'age' => 45 ]);
# SELECT * FROM users WHERE age = 45
```

### Fetch rows from table by ID
```php
$user = mysqly::fetch('users', 45)[0]; # ! you'll have to select only first row from results
# SELECT * FROM users WHERE id = 45
```

### Parametric sorting
```php
$users = mysqly::fetch('users', [ 'age' => 45, 'order_by' => 'id DESC' ]);
# SELECT * FROM users WHERE age = 45 ORDER BY id DESC
```

### Secure parametric `IN` support
```php
$users = mysqly::fetch('users', [ 'age' => [45, 46, 47] ]);
# SELECT * FROM users WHERE age IN (45, 46, 47)
```

### Fetch using standard SQL
```php
$users = mysqly::fetch('SELECT * FROM users');
```

### Binding
```php
$users = mysqly::fetch('SELECT * FROM users WHERE age = :age', [ ':age' => $_GET['age'] ]);
```

### Secure `IN` binding
```php
$users = mysqly::fetch('SELECT * FROM users WHERE age IN (:ages)', [ 'ages' => [45, 46, 47] ]);
# SELECT * FROM users WHERE age IN (45, 46, 47)
```

### Fetch single column list (one-dimensional array)
```php
$ids = mysqly::array('SELECT id FROM users');
```

### Fetch key-value pairs (one-dimensional associative array)
```php
$ages = mysqly::key_vals('SELECT id, age FROM users');
# example resulting array: [ 1 => 45, 2 => 46 ... ]
```

### Fetch random row based on parametric query
```php
$row = mysqly::random('users', ['age' => 25]);
# SELECT * FROM users WHERE age = 25 ORDER BY RAND() LIMIT 1
```


# Magic fetch
Set of magic methods (not directly defined, but dynamically handled) allows quick access in the following cases:
### Select single column value from a table by id
```php
$name = mysqly::users_name(45);
# SELECT name FROM users WHERE id = 45
```

### Select single column value from a table by custom parameters
```php
$name = mysqly::users_name(['col' => 'val']);
# SELECT name FROM users WHERE col = 'val' LIMIT 1
```

### Select whole row (all columns) from table by id
```php
$user = mysqly::users_(45);
# SELECT * FROM users WHERE id = 45 LIMIT 1
```

### Select list of rows from table by parameters
```php
$users = mysqly::users(['age' => 35]);
# SELECT * FROM users WHERE age = 35
```


# Insert data
### Insert single row as associative array
```php
mysqly::insert('users', ['age' => 46, 'gender' => 'x']);
```

### Insert with ignore (if duplicate primary/unique key)
```php
mysqly::insert('users', ['age' => 46, 'gender' => 'x'], true);
```

### Insert update (update data if duplicate primary/unique key)
```php
mysqly::insert_update('users', ['age' => 46, 'gender' => 'x']);
```

# Update data
To update gender column to "x" for all users with age = 45:
```php
mysqly::update('users', ['age' => 45], ['gender' => 'x']);
#               ▲        ▲              ▲
#               table    filter         update
```

# Remove data
To remove all rows with age = 46:
```php
mysqly::remove('users', ['age' => 46]);
```
