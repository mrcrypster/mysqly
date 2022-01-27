[Mysqly](https://mysqly.com/) is a full-featured opensource small-overhead PHP data framework for Mysql built for fast and efficient development.

<p align="center">
  <a href="https://mysqly.com/"><img src="/mysqly.png"/></a>
</p>

# Installation
```bash
wget https://mysqly.com/mysqly.php
```

- [Official website](https://mysqly.com/)
- [Learn advanced Mysql usage with PHP](https://mysqly.com/educate)

# Usage example
```php
require 'mysqly.php'; # include library (single file)
mysqly::auth('user', 'pwd', 'db', '127.0.0.1'); # connect to Mysql server

// Dynamic methods for table names
$users = mysqly::users(['age' => 25]); # SELECT * FROM users WHERE age = 25
$user = mysqly::users_(6); # SELECT * FROM users WHERE id = 6 LIMIT 1
```

# Features overview

<ul class="contents">
  <li>Procedural style implementation</li>
  <li>Single static class in a <a href="#install">single file</a></li>
  <li>PDO based - single dependancy</li>
  <li>Lazy connection to optimize resources usage</li>
  <li><a href="#retrieve">Simplified parametric queries</a> for frequent use cases</li>
  <li><a href="#lists">Lists</a> and <a href="#key_values">key-value pairs</a> retrieval</li>
  <li><a href="#dynamic">Dynamic methods</a> for less code</li>
  <li><a href="#sql">Native SQL</a> support</li>
  <li>Secure <a href="#binding">values binding</a></li>
  <li><a href="#in_binding">"IN" array values</a> binding support</li>
  <li><a href="#json">JSON</a> retrieval and attributes manipulation</li>
  <li>Automatic <a href="#json_retrieve">JSON/string convertions</a></li>
  <li>"Server has gone away" automatic reconnection</li>
  <li><a href="#multiple_dbs">Multiple DB/server connections</a></li>
  <li><a href="#auto_creation">Automatic fields/tables creation</a> mode</li>
  <li><a href="#key_value_storage">Key-value storage</a> component</li>
  <li><a href="#job_queue">Jow queue </a> component</li>
  <li><a href="#cache_storage">Cache storage</a> with TTL support component</li>
  <li><a href="#export">CSV/TSV</a> export</li>
  <li><a href="#increments">Increment and decrement</a> column values atomically</li>
  <li><a href="#transactions">Transactions</a> support</li>
</ul>
