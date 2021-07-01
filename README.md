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

# Secure authentication
To make authentication more secure, you can create auth file `/var/lib/mysqly/.auth.php` with auth data:
```php
<?php return [
  'user' => 'crypster',
  'pwd' => '23049ujasdW_',
  'db' => 'crypto'
];
```
