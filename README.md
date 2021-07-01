Micro PHP mysql lib (~ 150 lines of code) with powerful yet simple fetch & update. Features:
- fetch/insert/update/delete based on associative arrays
- native SQL queries
- parametric queries and values binding for security
- PDO-based, no extra dependencies
- near-zero overhead because of static class in a single file

# Installation
Download latest version of library file:
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
  'user' => 'crypster',
  'pwd' => '23049ujasdW_',
  'db' => 'crypto'
];
```

# Fetch data
## Fetch rows by SQL query
```php
$users = mysqly::fetch('SELECT * FROM users');
```

## Parameters binding
```php
$users = mysqly::fetch('SELECT * FROM users WHERE age = :age', [ ':age' => $_GET['age'] ]);
```

## Fetch rows from table with filters
```php
$users = mysqly::fetch('users', ['age' => '45']);
# The same as "SELECT * FROM users WHERE age = 45"
```

## Fetch rows from table by ID
```php
$user = mysqly::fetch('users', 45)[0]; # ! you'll have to select only first row from results
# The same as "SELECT * FROM users WHERE id = 45"
```
