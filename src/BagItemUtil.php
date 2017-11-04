<?php

namespace SamMcDonald\Bag;

use Illuminate\Support\Facades\Config;


class BagItemUtil
{
    public function SafeName($name)
    {
        return $name;
    }

    /**
     * BagItemUtil::RowID('abcde',[]);
     * 
     * @param [type] $code    [description]
     * @param array  $options [description]
     */
    public static function RowID($code, array $options = [])
    {
        ksort($options);

        return md5($code . serialize($options));
    }

    public static function Currency($value)
    {
        if(!is_numeric($value))
            $value = (float) $value;

        $precision  = config('bag.number-format.precision') ?: 2;
        $point      = config('bag.number-format.decimal') ?: '.';
        $comma      = config('bag.number-format.thousands') ?: ',';

        return (float) number_format($value, $precision, $point, $comma);
    }
}
