<?php

namespace ClientEventBundle\Helper;

class DataHelper
{
    public static function arrayImplode($glue, $separator, $array) {
        if (!is_array($array)) {
            return $array;
        }

        $string = [];

        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $val = implode(',', $val);
            }

            $string[] = "{$key}{$glue}{$val} \n";
        }

        return implode($separator, $string);
    }
}
