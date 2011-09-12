<?php

include __DIR__ . '/v.php'; // debug print

define ('GAME_MEMORY_LIMIT', 2000*1000*1000);
define ('GAME_TIME_LIMIT', 3);
define ('GAME_INPUT_FILE',  'answer.txt');
define ('GAME_OUTPUT_FILE', 'output.txt');

class GameOverException extends Exception{}
class GameSkipException extends Exception{}

/**
 * パズル
 */
class Puzzle
{
    /**
     * パズル番号
     *
     * @var int
     */
    public $num;

    /**
     * 幅
     *
     * @var int
     */
    public $w;

    /**
     * 高さ
     *
     * @var int
     */
    public $h;

    /**
     * ボード文字列
     *
     * @var string
     */
    public $board = '';

    /**
     * 目標文字列
     *
     * @var string
     */
    public $goalBoard = '';

    /**
     * Last index
     *
     * @var int
     */
    public $lastIndex;

    /**
     * マンハッタン距離コストマップ
     *
     * @var array
     */
    public $costMap = array();

    /**
     * リバースモード?
     *
     * @var bool
     */
    public $isReverse = false;


    /**
     * 移動マップ
     *
     * @var array
     */
    public $directionMap = array();

    /**
     * @param int    $num   パズル番号
     * @param int    $w　　　幅
     * @param int    $h     高さ
     * @param string $board ボード
     */
    public function __construct($num, $w, $h, $board, $isReverse = false)
    {
        $this->num = $num;
        $this->w = $w;
        $this->h = $h;
        $this->board = $board;
        $this->lastIndex = strlen($board) - 1;
        $this->goalBoard = $this->getGoalBoard($board);
        if ($isReverse === true) {
            $this->isReverse = true;
            $board = $this->board;
            $goalBoard = $this->goalBoard;
            $this->board = $goalBoard;
            $this->goalBoard = $board;
        }
        $this->costMap = $this->getCostMap($w, $h, $this->board);
        $this->directionMap = $this->getDirectionMap($this->board, $w, $h);
    }

    /**
     * 反対向きのボードを取得
     *
     * @param string $board
     *
     * @return string
     */
    public function getReverseBoard($board)
    {
        $reverseBoard = $board;
        $max = strlen($board);
        for ($i = 0; $i < $max ; $i++) {
            $reverseBoard[$i] = $board[$max - $i - 1];
        }
        $reverseTable = array('R' => 'L', 'L' => 'R', 'U' => 'D', 'D' => 'U');
        var_dump($reverseBoard);
        $reverseBoard = str_split($reverseBoard);
        $result = array_map(function ($var) use ($reverseTable){
            return $reverseTable[$var];
        }, $reverseBoard);
        $result = implode('', $result);
        return $result;
    }

    /**
     * 評価関数マンハッタン距離移動コストマップの取得
     *
     * @param int $w
     * @param int $h
     * @param string $board
     */
    private function getCostMap($w, $h, $board)
    {
        $goal = $this->goalBoard;
        $len = strlen($board);
        $scoringBoard = array();
        $keys = str_replace('=', '', $board);
        for ($i = 0; $i < $len; $i++) {
            // 現在の位置
            $sourceX = $i % $w;
            $sourceY = (int)($i / $w);
            // ここに入るべきピース
            $goalChar = $goal[$i];
            if ($goalChar === '=') {
                continue;
            }
            // ここに入るべきピースのpos
            $goalPos = strpos($goal, $goalChar);
            // 入るべきピースに対してそれぞれの距離を格納
            $keyLen = strlen($keys);
            $moveCost = function($fromX, $fromY, $toX, $toY) use ($board, $w, $h) {
                $wallCost = 0;
                // 移動中に壁なら上下(左右コスト追加
                if ($fromY === $toY) {
                    for ($i = $fromX + 1; $i < $toX; $i++) {
                        if ($board[$i] === '=') {
                            $wallCost += 2;
                            break;
                        }
                    }
                }
                if ($fromX === $toX) {
                    for ($i = $fromY + $w; $i < $toY; $i += $w) {
                        if ($board[$i] === '=') {
                            $wallCost += 2;
                            break;
                        }
                    }
                }
                $distance = abs($fromX - $toX) + abs($fromY - $toY);
                $cost = $distance + $wallCost;
                return $cost;
            };
            for ($j = 0; $j < $keyLen; $j++) {
                $key = $keys[$j];
                $keyPos = strpos($goal, $key);
                $keyX = $keyPos % $w;
                $keyY = (int)($keyPos / $w);
                $cost = $moveCost($sourceX, $sourceY, $keyX, $keyY);
                $scoringBoard[$i][$key] = $cost;
            }
        }
        return $scoringBoard;
    }

    /**
     * ゴール時のボードを取得
     *
     * @param string $board
     *
     * @return string
     */
    private function getGoalBoard($board)
    {
        $sortBoard = str_replace('=', '', $board);
        $sortBoard = str_replace('0', '[', $sortBoard);// '[' is after 'Z'

        $array = str_split($sortBoard);
        natcasesort($array);
        $len = strlen($board);
        $result = '';
        for ($i = 0; $i < $len; $i++) {
            $result .= ($board[$i] === '=') ? '=' : array_shift($array);
        }
        $result = str_replace('[', '0', $result);
        return $result;
    }

    /**
     * 移動用マップの取得
     *
     * @param string $board
     * @param int $w
     * @param int $h
     *
     * @return array {array[$index][$direction] = $bool}
     */
    private function getDirectionMap($board, $w, $h)
    {
        $lfBoard = '';
        for ($i = 0; $i < $h; $i++) {
            // パズルを一次元配列で扱うために各行に改行'='を付加
            $lfBoard .= substr($board, $w * $i, $w) . '=';
        }
        $directions = array('D' => $w + 1,
                            'R' => 1,
                            'L' => -1,
                            'U' => -1 * ($w + 1)
        );
        // 改行付きボードから"上下左右どの方向に移動できるかmap"を作成
        // 一次元なのに左右にはみ出さないのは改行コードを=にすることで実現
        $func = function ($index) use ($lfBoard, $directions) {
            $j = 0;
            $array = array();
            foreach ($directions as $directionKey => $offset) {
                $isAvailable = (isset($lfBoard[$index + $offset]) && $lfBoard[$index + $offset] !== '=');
                $array[$directionKey] = $isAvailable  ? true : false;
            }
            return $array;
        };
        $cnt = $offset = 0;
        for($i = 0; $i < $h; $i++) {
            for($j = 0; $j < $w ; $j++) {
                $map[$cnt++] = $func($offset++);
            }
            $offset++;
        }
        return $map;
    }
}

/**
 * ゲームの機能と状態
 */
class Game
{
    /**
     * パズル
     *
     * @var Puzzle
     */
    public $puzzle;

    /**
     * "0"の場所
     *
     * @var int
     */
    public $zero;

    /**
     * 移動方向ヒストリー
     *
     * @var string
     */
    public $directionKeys = '';

    /**
     * 最後に移動した方向
     *
     * @var string
     */
    private $lastDirectionKey = null;

    /**
     * ボード配列
     *
     * @var SplFixedArray
     */
    private $board;


    /**
     * コンストラクタ
     *
     * @param Puzzle $puzzle
     */
    public function __construct(Puzzle $puzzle)
    {
        $this->board = SplFixedArray::fromArray(str_split($puzzle->board));
        $this->puzzle = $puzzle;
        $this->zero = strpos($puzzle->board, '0');
    }

    public function __clone()
    {
        $this->board = clone $this->board;
    }

    /**
     * "0"の移動
     *
     * @param int $directionKey 'R' | 'L' |'U' | 'D' |
     * @param int $offset        offset
     *
     * @return void
     */
    public function goNext($directionKey, $offset)
    {
        $targetIndex = $this->zero + $offset;
        $targetChar = $this->board[$targetIndex];
        $this->board[$this->zero] = $targetChar;
        $this->board[$targetIndex] = '0';
        $this->zero = $targetIndex;
        $this->directionKeys .= $directionKey;
        $this->lastDirectionKey = $directionKey;
    }

    /**
     * 移動可能なマスを見つける
     *
     * @return array
     */
    public function find($directions)
    {
        $array = array();
        foreach ($directions as $directionKey => $offset) {
            $isReverse = isset($this->lastDirectionKey) && ($directions[$this->lastDirectionKey] * (-1) === $offset)  ? true : false;
            if (!$isReverse && $this->puzzle->directionMap[$this->zero][$directionKey]) {
                $array[] = $directionKey;
            }
        }
        return $array;
    }

    /**
     * 評価関数
     *
     * @param int $offset
     *
     * @return int
     */
    public function __invoke($delta = 0)
    {
        $board = clone $this->board;
        if ($delta !== 0) {
            $board[$this->zero] = $board[$this->zero + $delta];
            $board[$this->zero + $delta] = '0';
        }
        $cost = $mapCost = $this->getMapCost($board);
        $cost += $this->getCountCost();
        //$cost += $this->getMapEdgeCost($board);
        $score = 500 - $cost;
        return array($mapCost, $score);
    }

    /**
     * マンハッタン距離移動コストの取得
     *
     * @param SplFixedArray $board
     *
     * @return int
     */
    public function getMapCost(SplFixedArray $board = null)
    {
        if (is_null($board)) {
            $board = $this->board;
            $penalty = 0;
        } else {
            $penalty = 1;
        }
        $costMap = $this->puzzle->costMap;
        $cost = 0;
        $len = $board->count();
        for($i = 0; $i < $len ; $i++) {
            if ($board[$i] !== '=') {
                $cost += ($this->puzzle->costMap[$i][$board[$i]] !== 0 ) ?
                $this->puzzle->costMap[$i][$board[$i]] + $penalty : // 正解のマスでなければ+2
                0;
            }
        }
        return $cost;
    }

    /**
     * 移動回数をコストとして取得
     *
     * @return int
     */
    private function getCountCost()
    {
        $score = (int)(strlen($this->directionKeys) / 2);
        return $score;
    }

    /**
     * "ボード端のあと一つだけ入ってない"困難コストの取得
     *
     * @param SplFixedArray $board
     *
     * @return int
     */
    public function getMapEdgeCost(SplFixedArray $board)
    {
        $costMap = $this->puzzle->costMap;
        $cost = 0;
        $w = $this->puzzle->w;
        $h = $this->puzzle->h;
        $edges = array(array(0, $this->puzzle->w - 1, 1, $w), // top
        //                        array($w * ($h - 1) + 1, $w * $h - 1, 1, $w), // bottom
        array(0, $w * ($h - 1), $w, $h), // left
        array($w, $w * $h - 1, $w, $h) //right
        );
        foreach ($edges as $k => $edge) {
            $isNotCorrect = 0;
            for($i = $edge[0]; $i <= $edge[1] ; $i += $edge[2]) {
                if ($this->board[$i] === '=') {
                    continue;
                }
                if ($this->board[$i] !== '0' && $this->puzzle->costMap[$i][$this->board[$i]] !== 0 ) {
                    $isNotCorrect++;
                }
            }
            if ($isNotCorrect === 1) {
                $cost += 1;
            }
        }
        return $cost;
    }

    /**
     * 完了?
     *
     * @return boolean
     */
    public function isCompleted()
    {
        $boardString = implode('', $this->board->toArray());
        //         return ($this->zero === $this->puzzle->lastIndex) && ($boardString === $this->puzzle->goalBoard) ;
        return ($boardString === $this->puzzle->goalBoard) ;
    }

    /**
     * パズル盤面とゲーム状態を文字列で表現
     *
     * @return string
     */
    public function __toString()
    {
        $string = "\n";
        $offset = 0;
        for ($y = 0; $y < $this->puzzle->h; $y++) {
            for ($x = 0; $x < $this->puzzle->w; $x++) {
                $string .= $this->board[$offset++];
            }
            $string .= "\n";
        }
        $string .= 'directions: ' . $this->directionKeys . "\n";
        $string .= 'original: ' . $this->puzzle->board . "\n";
        $string .= 'score: ' .  $this->getMapCost() . "\n";
        return $string;
    }

    /**
     * ゲーム状態ハッシュIDの取得
     *
     * @return string
     */
    public function getBoardHash()
    {
        return md5(serialize($this->board));
    }
}

/**
 * ゲームプレイ
 *
 */
class Play
{
    /**
     * 移動カウンタ
     *
     * @var int
     */
    private $counter = 0;

    /**
     * タスクマネージャー（キュー）
     *
     * @var Task
     */
    private $task;

    /**
     * ゲーム状態
     *
     * @var Game
     */
    private $game;

    /**
     * 移動オフセット array('R'=>$offset, 'L'=>...)
     *
     * @var array
     */
    private $direction;

    /**
     * ボード状態ハッシュリポジトリ
     *
     * @var array
     */
    private $flag = array();

    /**
     * 状態向上中フラグ
     *
     * @var bool
     */
    private $isBestMapCostUpdated = false;

    /**
     * コンストラクタ
     *
     * @param Game    $game
     * @param Task    $task
     * @param Closure $skipStrategy
     */
    public function __construct(Game $game, Task $task)
    {
        $this->game = $game;
        $this->task = $task;
            $this->time = microtime(true);
        $this->extraTime = 0;
        $this->input = file_exists(GAME_INPUT_FILE) ? file(GAME_INPUT_FILE): array();
        $this->bestMapCost = $game->getMapCost();
        $this->direction = array('D' => $this->game->puzzle->w,
                                 'R' => 1,
                                 'L' => -1,
                                 'U' => -1 * ($this->game->puzzle->w)
        );
        if ($task instanceof StackTask) {
            $task->searchDepth = ((($this->game->zero + $this->game->puzzle->lastIndex) % 2 === 0)) ? 1 : 2;
        }
        echo $task->searchMethod . '(' . get_class($this->task) . ')';
    }

    /**
     * 解答を取得
     *
     * @return string
     * @throws GameOverException
     * @throws GameSkipException
     */
    public function getAnswer(Closure $skipStrategy)
    {
        $skipStrategy($this->game->puzzle->num, $this->input, $this->game->puzzle->w, $this->game->puzzle->h); // skip ?
        $game = clone $this->game;
        while (true) {
            $this->setTask($this->task, $game);
            $this->counter++;
            try {
                // タスクの取り出し
                list($directionKey, $game) = $this->task->get();
            } catch (Exception $e) {
                if ($this->task instanceof StackTask === false) {
                    throw new GameOverException('no game task.');
                }
                continue;
            }
            // 0を動かしゲームを進める
            $game->goNext($directionKey, $this->direction[$directionKey]);
            if ($game->isCompleted()) {
                $answer = ($this->game->puzzle->isReverse === false) ? $game->directionKeys : $this->game->puzzle->getReverseBoard($game->directionKeys);
                return $answer;
            }
            $this->checkGiveUp($game); // time up ? memory over ?
        }
    }

    /**
     * Give up
     *
     * @param Game $game
     * @throws GameOverException
     *
     * @return void
     * @throws GameOverException
     */
    private function checkGiveUp(Game $game)
    {
        static $checkCounter = 0;
        static $isFirstCheck = true;
        static $plusTime = 5;

        // check give up
        if ($checkCounter++ !== 1000) {
            return;
        }
        $checkCounter = 0;
        if (memory_get_usage(true) > GAME_MEMORY_LIMIT) {
            throw new GameOverException('Out of memory');
        }
        if (microtime(true) -  $this->time  - $this->extraTime < GAME_TIME_LIMIT) {
            return;
        }
        // time up
        if ($isFirstCheck === true) {
            if ($this->bestMapCost > 10)      {
                throw new GameOverException('Time Up...');
            }
            $isFirstCheck = false;
        }
        if ($this->isBestMapCostUpdated === false) {
            throw new GameOverException('Extended time up...');
        }
        $this->isBestMapCostUpdated = false;
        $this->extraTime += $plusTime;
        //         $plusTime++;
        echo '.+time';
    }

    /**
     * タスクのセット
     *
     * @param Task $task
     * @param Game $game
     *
     * @return void
     */
    private function setTask(Task $task, Game $game)
    {
        $id = $game->getBoardHash();
        if (isset($this->flag[$id])) {
            return;
        }
        $this->flag[$id] = true;
        $directions = $game->find($this->direction);
        if ($this->task->adapter instanceof SplPriorityQueue) {
            foreach ($directions as $directionKey) {
                list($mapCost, $priority) = $game($this->direction[$directionKey]);
                $this->task->setPriority($priority);
                $task->set(array($directionKey, clone $game));
                if ($mapCost < $this->bestMapCost) {
                    $this->bestMapCost = $mapCost;
                    $this->isBestMapCostUpdated = true;
                    echo '.' . $this->bestMapCost;
                }
            }
        } else {
            foreach ($directions as $directionKey) {
                $task->set(array($directionKey, clone $game));
            }
        }
    }

    /**
     * プレイ状態
     *
     * @return string
     */
    public function __toString()
    {
        $msg = array();
        $msg[] = 'best:' . $this->bestMapCost;
        $msg[] = 'sec:' . (int)(microtime(true) - $this->time);
        $msg[] = 'move:' . $this->counter;
        $msg[] = 'pattern:' . count($this->flag);
        $msg[] = 'task:' . $this->task->adapter->count();
        $msg[] = 'speed:' . (int)($this->counter/(microtime(true) - $this->time)) . '/sec';
        return implode(' ', $msg);
    }

}

/**
 * タスクインターフェイス
 *
 */
interface Task {
    public function __construct();
    public function set(array $task);
    public function get();
}

/**
 * タスク抽象クラス
 *
 */
abstract class AbstractTask
{
    /**
     * ジョブキュー用双方向リンクリスト
     *
     * @var SplDoublyLinkedList
     */
    public $adapter;

    /**
     * 探索する深さ
     *
     * @var int
     */
    public $searchDepth = PHP_INT_MAX;

    /**
     * アダプタ取得
     *
     * @param string $directionKeys
     *
     * @return SplDoublyLinkedList
     */
    public function getAdapter($directionKeys)
    {
        return $this->adapter;
    }
}

/**
 * BFS(幅検索)
 *
 */
class QueTask extends AbstractTask implements Task
{
    public $searchMethod = 'BFS';

    public function __construct(){
        $this->adapter = new SplQueue();
    }

    public function set(array $task)
    {
        $this->adapter->enqueue($task);
    }

    public function get()
    {
        return $this->adapter->dequeue();
    }
}

/**
 * IDDFS (反復深化深さ優先探索)
 *
 */
class StackTask extends AbstractTask implements Task
{
    public $searchMethod = 'IDDFS';

    private $deeperAdapter;

    public function __construct(){
        $this->adapter = new SplStack();
        $this->deeperAdapter = new SplStack();
    }

    public function getAdapter($directionKeys)
    {
        $adapter = (strlen($directionKeys) <= $this->searchDepth)  ? $this->adapter : $this->deeperAdapter;
        return $adapter;
    }

    public function set(array $job)
    {
        $game = $job[1];
        $adapter = (strlen($game->directionKeys) <= $this->searchDepth)  ? $this->adapter : $this->deeperAdapter;
        $adapter->push($job);
    }

    public function get()
    {
        try {
            return $this->adapter->pop();
        } catch (RuntimeException $e){
            // タスクがなくなったので深さ+2
            $this->searchDepth += 2;
            $this->adapter = $this->deeperAdapter;
            $this->deeperAdapter = new SplStack();
            echo ".{$this->searchDepth}(" . $this->adapter->count() . ')';
            return $this->adapter->pop();
        }

    }
}
/**
 * ヒューリスティック探索
 *
 */
class PriorityTask extends AbstractTask implements Task
{
    public $searchMethod = 'A*';
    public $adapter;
    private $priority;

    public function __construct(){
        $this->adapter = new SplPriorityQueue();
    }

    public function setPriority($priority)
    {
        $this->priority = $priority;
    }

    public function set(array $task)
    {
        $this->adapter->insert($task, $this->priority);
    }

    public function get()
    {
        return $this->adapter->extract();
    }
}

/**
 * パズルレポジトリ
 */
class PuzzuleRepository
{
    /**
     * ゲーム数
     *
     * @var int
     */
    public $n;

    /**
     * LX, RX, UX, DX
     *
     * @var ArrayObject
     */
    public $moveCount;

    /**
     * 問題ファイル
     *
     * @var array
     */
    private $file = array();

    /**
     * コンストラクタ
     *
     * @param string $file
     */
    public function __construct($file)
    {
        $this->file = file($file);
        list($lx, $rx, $ux, $dx) = explode(' ', $this->file[0]);
        $n = $this->file[1];
        $array = array('L' => (int)$lx,
                       'R' => (int)$rx,
                       'U' => (int)$ux,
                       'D' => (int)$dx
        );
        $this->moveCount = new ArrayObject($array);
        $this->n = (int)$n;
    }

    /**
     * 新パズルの取得
     *
     * @param bool $isReverse
     *
     * @return Puzzle
     */
    function getNewPuzzle($isReverse = false)
    {
        static $i = 2; // puzzle start from line.2

        $this->n--;
        if (!isset($this->file[$i]) || $this->n < 0) {
            die("Completed. No more puzzle.\n");
        }
        $line = $this->file[$i++];
        list($w, $h, $board) = explode(',', $line);
        $board = rtrim($board);
        $puzzleNum = $i - 2;
        echo "#" . $puzzleNum . ' ' . date("H:i:s") . " {$w}x{$h}={$board}:";
        return new Puzzle($puzzleNum, (int)$w, (int)$h, $board, $isReverse);
    }
}

function strong1($string) {
    echo "\033[7;32m{$string}\033[0m";
}

function strong2($string) {
    echo  "\033[1;32m{$string}\033[0m";
}

/**
 * DevQuiz 2011 Slide Puzzle
 *
 * @author koriym
 */
class DevQuiz
{
    /**
     * Run
     *
     * @param Closure $taskStrategy タスク選択クロージャ
     * @param Closure $skipStrategy スキップクロージャ
     * @param bool $isReverse 反転モード？
     *
     * @return void
     */
    public static function run(Closure $taskStrategy, Closure $skipStrategy, $isReverse = false)
    {
        // init
        file_put_contents(GAME_OUTPUT_FILE, ''); //clear output file
        $puzzleRepos = new PuzzuleRepository('q.txt');
        // main
        while (true) {
            try {
                $puzzle = $puzzleRepos->getNewPuzzle($isReverse);
                $play = new Play(new Game($puzzle), $taskStrategy($puzzle));
                $answer = $play->getAnswer($skipStrategy);
                echo strong1('Completed:') . $answer . '(' . strlen($answer) . ') ' . $play;
                file_put_contents(GAME_OUTPUT_FILE, $answer, FILE_APPEND | LOCK_EX);
            } catch (GameSkipException $e) {
                echo $e->getMessage();
            } catch (GameOverException $e) {
                echo strong2('Game Over ') . $e->getMessage() . ' ' . $play;
            } catch (Exception $e) {
                echo $e->getMessage();
            }
            // LF
            echo "\n";
            file_put_contents(GAME_OUTPUT_FILE, "\n", FILE_APPEND | LOCK_EX);
        }
    }
}

/**
 * 探索方法クロージャ
 *
 * @var Task
 */
$taskStrategy = function(Puzzle $puzzle) {
    if ($puzzle->h * $puzzle->w <= 9) {
        // 幅優先探索 BFS Breadth first search,  http://goo.gl/t8lz0
        return new QueTask();
    } elseif ($puzzle->h * $puzzle->w <= 12) {
        // 反復深化深さ優先探索 IDDFS diterative deepening depth-first search http://goo.gl/YKjur
        return new StackTask();
    } else {
        // 最良優先探索 Best-first search A* http://goo.gl/xXEAd
        return new PriorityTask();
    }
};

/**
 * ゲームスキップクロージャ
 *
 * @var void
 * @throws GameSkipException
 */
$skipStrategy = function($puzzleNum, array $input, $w, $h){
    $answer = (isset($input[$puzzleNum - 1]) && $input[$puzzleNum - 1] !== "\n") ? $input[$puzzleNum - 1] : false;
    $size = strlen($answer) - 1;
    if ($answer !== false) {
        $msg = strong2("Cleared") . ':' . rtrim($input[$puzzleNum - 1]) . '(' . strlen($input[$puzzleNum - 1]) . ')';
        throw new GameSkipException($msg);
    }
    // コンティニュー用
    if ($puzzleNum < 0) {
        throw new GameSkipException(' Skipped');
    }
};

$isReverse = false; //逆探索モード
DevQuiz::run($taskStrategy, $skipStrategy, $isReverse);
