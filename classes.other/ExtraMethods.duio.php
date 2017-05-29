<?php
  $this->shorthandMethods = [
    'insert' => function($parm) {
      $parm = $this->parmSifter($parm);
      $s    = "INSERT INTO {$parm[0][0]} (";
      unset($parm[0][0], $parm[1][0]);
      $s   .= implode(',', $parm[0]).') VALUES (';
      for ($i = 0; $i < count($parm); $i++) $s .= '?,';
      $s    = rtrim($s, ',');
      $s   .= ')';
      return $s;
    },
    'order' => function($parm) {
      $parmLite = $this->liteParmSifter($parm);
      $a = ['asc', 'ascending', 'a']; $d = ['desc', 'descending', 'd'];
      if (in_array(strtolower($parmLite), $a)) return " ORDER BY id ASC";
      else if (in_array(strtolower($parmLite), $d)) return " ORDER BY id DESC";
    },
    'limit' => function($parm) {
      $parmLite = $this->liteParmSifter($parm);
      if ( Counts::count($parmLite)[','] < 2 && is_numeric(str_replace([',',' '],['',''],$parmLite)) )
      return " LIMIT $parmLite";
    },
    'true' => function($parm) {
      $parms = $this->parmSifter($parm);
      $table = $this->tableExtractor($parms);
      $parms = $this->offsetFrom(1, $parms);
      $s  = "SELECT ". implode(',', $parms[0]). " FROM {$table} WHERE ";
      //constructing the rest of the statmenet.
      $constructor = [];
      $colArr = $parms[0]; $valArr = $parms[1];
        foreach ($parms[0] as $index => $arr) array_push($constructor, "{$colArr[$index]} = ?");
      $s .= implode(' AND ', $constructor);
      // Returnnig a statement to tell the constructor
      // that there's aditional computation to be done.
      return ['HAS_ROWS', $s];
    },
    'auth' => function($parm) {



    }
  ];
