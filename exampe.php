<?php

require "mysqly.php";
mysqly::auth('test', 'test', 'test');

print_r( mysqly::fetch("SELECT NOW()") );