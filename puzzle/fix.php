<?php
$result = file('screen.log');
$target = array();
$target = array_pad($target, 5000, "");
foreach ($result as $line) {
    if (strpos($line, 'Compl')) {
        $pattern = "/#([0-9]+).+Completed:([A-Z]+)/";
        preg_match($pattern, $line, $match);
        $target[(int)$match[1] - 1] = $match[2];
    }
}
$target = implode("\n", $target);
file_put_contents('fixed.txt', $target);