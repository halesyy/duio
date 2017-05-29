<?php
  class Counts
    {
      static $type_indicator;
      public static function SetIndicator($set)
        {
          self::$type_indicator = $set;
        }
      public static function count($string, $ind = false)
        {
          if ($ind === false) $ind = self::$type_indicator;
          $return = [];
            foreach ($ind as $tocount)
            $return[$tocount] = count(explode($tocount, $string))-1;;
          $pieces = explode(' ',$string);
            foreach ($pieces as $index => $exploded)
            $return['word_'.$index] = $exploded;
          return $return;
        }
    }
