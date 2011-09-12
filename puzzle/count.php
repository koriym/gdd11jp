<?php
$cnt = 0;
$file = file($argv[1]);
$dirs = array('R' => 0, 'U' => 0, 'L' => 0, 'D' => 0);
$count = array();
for ($i = 0; $i < count($file); $i++) {
    $len = strlen($file[$i]) - 1;
    if (isset($argv[2]) && $len > $argv[2]) {
        echo  "$len\n";
    }
    foreach ($dirs as $dir => &$val) {
        $val += substr_count($file[$i], $dir);
    }
}
var_dump($dirs);
