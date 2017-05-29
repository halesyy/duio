<?php
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $d = __DIR__; require_once $d."\\classes.other\\PSM.php";//loading psm&getting dir var.
                require_once $d."\\classes.other\\Counts.php";
  Counts::SetIndicator(['<<', ' ', ':', ';', ',', '{', '}', '.']);

  class Duio {
    /*
    | --------------------------------------------------------------------------
    | This is the main DUIO class. I recommend that before you try importing
    | Duio into your project you make sure you've done the following:
    | --------------------------------------------------------------------------
    | 1. Got the "classes.other" folder in the same position it came in, like
    |    /Duio.php -> folder(classes.other) -> folder files.
    |    IF you don't have the classes.other folder in the same area, go ahead
    |    and go to the GitHub page and dowload it. :)
    | 2. Looked around at the `private.duio` and `public.duio` file and understand
    |    they're the parsed files. It's a basic description language that anyone
    |    can understand that helps you know what you need to supply to it from
    |    the PHP backend.
    | 3. You've supplied your MySQL database sign-in data to the class's
    |    public variables.
    | --------------------------------------------------------------------------
    */

    // ********************************************************************************

        // All database connection variables.
        public $host = 'localhost';
        public $dbnm = 'test';
        public $user = 'root';
        public $pass = 'password';
        public $jobs;
        // The handler for connecting to the database.
        private $dbHandler;
        // 'public' or 'private', changed from the __get magic method.
        private $currentlyUsing = 'public';
        // Filenames for each of the private and public API's.
        public $privateApiFile  = 'private.duio';
        public $publicApiFile   = 'public.duio';
        // The shorthand method, loaded in from the __construct'or method.
        public $shorthandMethods;
        // Change to true on areas to get them in the opt.
        public $debug = [
          'sortedMethodTree' => true
        ];
        public $tokenErrorMessage = 'Token mismatch, please alert an admin of the website.';
        // Safety true enforces the private API to only be accessed through
        // token's supplied from forms and POST requests.
        public $safety = false;

    // ********************************************************************************

    public function __construct(PSM $databaseHandler)
      {
        $databaseHandler->connect( "{$this->host} {$this->dbnm} {$this->user} {$this->pass}" );
        $this->dbHandler = $databaseHandler;
        // Methods that can be quickly called from the parser to
        // do designated jobs.
        require_once __DIR__."\\classes.other\\ExtraMethods.duio.php";
        $this->jobs = [
          'commentRemoval' => function($data) {
            $commentTriggers = ['#', '//'];
            $data = preg_replace([
              '/^(#)(.*?)$/m',
              '/^(\/\/)(.*?)$/m'
            ], [
              '',
              ''
            ], $data);
            return $data;
          }
        ];
      }

    /*
    | Helping direct the calls to the public/private API
    | using the best magic method, __get!
    | When $duio->private->x; - Area = private.
    | When $duio->public->x;  - Area = public.
    */
    public function __get($area)
      {
        if ($area == 'private' && $this->safety) die('<code>->private</code> called, please use the <code>->token(. . .)</code> accessor to safely get into the private API.');
        else if ($area == 'private' && !$this->safety) $this->currentlyUsing = 'private';
        else if ($area == 'public') $this->currentlyUsing = 'public';
        return $this;
      }

    /*
    | Extra auth for the API to use when connecting to
    | the private sector. Token generated from backend
    | as a token in session then forms use them.
    | >token=false, generate input.
    | >token=str,   compare reutrn true/false.
    */
    public function token($token = false)
      {
        if (is_array($token)) return $this->tokenPost($token);
        if ($token === false) {
          // Generating a new token to put into a form.
          echo "<input type='hidden' name='tkn' value='{$this->generateToken()}' />";
        } else {
          // Comparing token.
          if (isset($_SESSION['tkn'])) {
            if ($token == $_SESSION['tkn']) {
              $this->currentlyUsing = 'private';
              return $this;
            }
          } else die( json_encode(['error' => $this->tokenErrorMessage]) );
        }
      }
    public function tokenPost($postVal) //shorthand Token function but takes in POST var instead and sifts
      {
        //retrieving the auth token.
        if (isset($postVal['tkn'], $_SESSION['tkn'])) $token = $_POST['tkn'];
        else die( json_encode(['error' => $this->tokenErrorMessage]) );

        //comparing.
        if ($token == $_SESSION['tkn']) {
          $this->currentlyUsing = 'private';
          return $this;
        } else die( json_encode(['error' => $this->tokenErrorMessage.' Tokens not match.']) );
      }
    public function generateToken()
      {
        $con = $set = '';
        $token = &$con;
        $set = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890;[]./,';
        for ($i = 0; $i <= 60; $i++) $con .= $set[rand(0, strlen($set)-1)];
        $_SESSION['tkn'] = $token;
        return $token;
      }










    /*
    | Performing basic tasks for manipulating data and seperating
    | complex tasks from easy, quick ones. Such as comment removal.
    */
    public function jobs($data)
      {
        $shm  = $this->jobs;
        $data = $shm['commentRemoval']($data);//comments.
        return $data;
      }



    /*
    | The "mixer" for calling methods in the file that's
    | wanted to be used, be it public or private.
    | Beginning of data being manipulated.
    */
    public function mix($tocall, $data = [])
      {
        $using = ($this->currentlyUsing) ?  $this->currentlyUsing   : die('No accessor for specific API, please call similarly: <code>$duio->private->...()</code>. Current <b>using</b>: <i>'.$this->currentlyUsing.'</i>');
        $file  = ($using == 'private')   ?  $this->privateApiFile   : $this->publicApiFile;
        // Data maninpuation.
        foreach ($data as $index => $val) {
          if (strtolower(explode(':',$val)[0]) == 'post' && count(explode(':',$val)) == 2) {
            //post data manip.
            $split = explode(':',$val);
            if (isset($_POST[$split[1]])) $data[$index] = $_POST[$split[1]];
          }
        }
        // Filedata manipulation.
        $fileData = file_get_contents( __DIR__."\\".$file );
        $fileData = $this->jobs($fileData);
        $fileData = implode("\n", array_map('trim',
          explode("\n", $fileData)
        ));
        // Returns the file sorted into a method array.
        $sortedMethods = $this->sortMethodsInFile($fileData);
        /*d*/if ($this->debug['sortedMethodTree']) $this->display($sortedMethods);
        if (isset($sortedMethods[$tocall])) {
          $r = $this->createStatementAndBinds($sortedMethods[$tocall], $data);
          if ($r[0] == 'RETURN_DATA') {
            return $r[1];
          } else {
            $this->DUIO_RUN_QUERY($r[0], $r[1]);
          }
        }
      }



    /*
    | Sorts methods in the file into a tree value
    | for easy access to methods.
    */
    public function sortMethodsInFile($fileData)
      {
        $sorted = ['unknown' => ['methodData' => []]]; $inMethod = false; $currentMethod = 'unknown';
        foreach (explode("\n", $fileData) as $index => $line): if ($line == '') continue;
          $lineCounts = Counts::count($line);
          if ($lineCounts[':'] == 1 || $lineCounts['{'] == 1) {
            //new method for iterator, initializing metadata.
            $name = trim( str_replace([':', '{'], '', $line) );//$methodName.
            $currentMethod = $name;
            $sorted[$currentMethod] = ['methodData' => []];
            $inMethod = true;
          } else if ($lineCounts[';'] == 1 || $lineCounts['}'] == 1) {
            $inMethod = false;
            $currentMethod = 'unknown';
          } else if ($lineCounts['.'] == 2) {
            // one-line definer.
            $split = array_map('trim', explode('..', $line));//0=>name,1=>instruction
            $sorted[$split[0]] = ['methodData' => [ $split[1] ]];
          } else {
            array_push($sorted[$currentMethod]['methodData'], trim($line));
          }
        endforeach;
        unset($sorted['unknown']);//remove the initial val in array.
        return $sorted;
      }



    public function createStatementAndBinds($methodData, $bindingData = [])
      {
        //given [methodData=>...,parms=>asd,dsa]
        //extracting the params.

        $constructedStatement = '';
        foreach ($methodData['methodData'] as $statementLine):
            $statementLine = preg_replace(
              '/^(.*)( )(\(.*\))$/m',
              '$1::::$3',
              $statementLine
            );
            $statementParts = array_map('trim', explode('::::', $statementLine));
            if (count($statementParts) != 2) die('Creating statement in DUIO, one method call too many managers for one line');

            // Managing the data to either contcat to the statement or return data
            // outwright.
            $statementContructed = $this->statementConstructor($statementParts);
            if (is_array($statementContructed)) {

                if ($statementContructed[0] == 'HAS_ROWS') {
                  //have to use the statement given to return TRUE/FALSE for there
                  //being rows.
                  $statement = $statementContructed[1];
                  return ['RETURN_DATA', $this->dbHandler->rows($statement, $bindingData)];
                }

            } else $constructedStatement .= $statementContructed;
        endforeach;
        $binding = array_values($bindingData);
        return [$constructedStatement, $binding];
      }



    /*
    | Takes in the split arry of |methodtobecalled|parmstring|
    | it's then up to the shorthand method to sort the parm string
    | in ways it's required to.
    | We don't do any guessing for the method since they're all
    | unique in their own ways.
    */
    public function statementConstructor($pieces)
      {
        //0=method, 1=parms.
        if (isset($this->shorthandMethods[ strtolower($pieces[0]) ])):
          return $this->shorthandMethods[ strtolower($pieces[0]) ] ($pieces[1]);
        else:
          die("Please create method for <b>{$pieces[0]}</b>, Duio couldn't build your method statement");
        endif;
      }



    /*
    | The sorting method for parm strings given from
    | method files. Will sort them into a polymorphic
    | array containing one side of columns and the other
    | containing variables in the case they need to be
    | used.
    */
    public function parmSifter($parmString)
      {
        $parms = ltrim(rtrim($parmString, ')'), '(');
        $parms = array_map('trim', explode(',', $parms));
          foreach ($parms as $index => $parm)
            if (Counts::count($parm)[':'] != 1)
            $parms[$index] = "{$parm}:.{$parm}";
        $parms = $this->verticalSplit(':', $parms);
        //return as 0=>tablenames,1=>tabledata
        return $parms;
      }



    // Same as the sifter but removes the () from each side.
    public function liteParmSifter($parmString)
      {
        return ltrim(rtrim($parmString, ')'), '(');
      }



    // Splits an array vertically into two arrays.
    public function verticalSplit($by, $arr)
      {
        $f = $s = [];
        foreach ($arr as $index => $val):
          array_push($f, explode($by, $val)[0]);
          array_push($s, explode($by, $val)[1]);
        endforeach;
        return [$f, $s];
      }



    // Shorthand method for mutliple methods that use [0][0]
    // as their indicator for tables
    public function tableExtractor($parmArr)
      {
        $table = $parmArr[0][0];
        return $table;
      }



    // Build the array from $n onward. For parm methods only.
    public function offsetFrom($n, $parmArr)
      {
          for ($i = 0; $i < $n; $i++) {
            unset($parmArr[0][$i]);
            unset($parmArr[1][$i]);
          }
        return $parmArr;
      }



    // Shorthand array displayer.
    public function display($arr) { echo "<pre>", print_r($arr) ,"</pre>"; }



    /*
    | "THE FUNCTION"
    | Get's called for dedicated function calling from other methods
    | - done after the data has all been through it's parser and
    | gotten the methods and everything's AOK.
    */
    public function DUIO_RUN_QUERY($statement, $binding)
      {
        $this->dbHandler->cquery($statement, $binding);
      }
  }

  $duio = new Duio(new PSM);

  if (isset($_POST['xd'])):
    $r = $duio->token($_POST)->mix('login', [
      'post:username', 'post:password'
    ]);
    echo ($r)? "You're logged in!": "You're false!";
  endif;
?>

  <form method="post">
    <?=$duio->token()?>
    <input type="text" name="username" />
    <input type="password" name="password" />
    <input type="submit" name="xd" />
  </form>





<?php
  ///
