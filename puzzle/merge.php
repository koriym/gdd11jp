<?php
$dirs = array('R' => 0, 'U' => 0, 'L' => 0, 'D' => 0);
$cnt = $answerCnt = $outputCnt = 0;
$answer = file('answer.txt');
$output = file('output.txt');
for ($i = 0; $i < 5000; $i++) {
    $answerVal = isset($answer[$i]) ? $answer[$i] : "\n";
    $outputVal = isset($output[$i]) ? $output[$i] : "\n";
    if ($answerVal == "\n" && $outputVal == "\n"){
        $newVal = "\n";
    } elseif ($answerVal != "\n" && ($outputVal == "\n" || $answer != "\n" && (strlen($answerVal) <= strlen($outputVal))) && strlen($answerVal) <= 300) {
        $newVal = $answerVal;
        $answerCnt++;
    } elseif (strlen($outputVal) <= 300) {
        $newVal = $outputVal;
        $outputCnt++;
    } else {
        $newVal = "\n";
    }
    //echo "$i=$newVal";
    foreach ($dirs as $dir => &$val) {
        $val += substr_count($newVal, $dir);
    }
    // 72187 81749 72303 81778
    if ($dirs['L'] <= 72187 && $dirs['R'] <= 81749 && $dirs['U'] <= 72303 && $dirs['D'] <= 81778) {
        $merged[$i] = $newVal;
    } else {
        $merged[$i] = "\n";
        echo "skip $i:";
    }
}
echo "merged.txt created.\n";
echo "answer:" . ($answerCnt) . " output:" . ($outputCnt) . " merged: " . count($merged) . "\n";
$mergedFile = implode('', $merged);
file_put_contents('merged.txt', $mergedFile);
var_dump($dirs);
