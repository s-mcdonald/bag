<?php

return [



    /*
    |--------------------------------------------------------------------------
    | Multiple Bags & List
    |--------------------------------------------------------------------------
    |
    |
    */

    'multi-bags' => true,

    /*
    |--------------------------------------------------------------------------
    | Default number format
    |--------------------------------------------------------------------------
    |
    | This defaults will be used for the formated numbers if you don't
    | set them in the method call.
    |
    */
    'number-format' => [

        'precision'   => 2,

        'decimal'     => '.',

        'thousands'   => ','

    ],



    /*
    |--------------------------------------------------------------------------
    | Purchasable Fields
    |--------------------------------------------------------------------------
    |
    | Field names in your Product model
    | 
    |
    */
    'product-fields' => [

        'code'      => 'code',

        'name'      => 'name',

        'price'     => 'price'

    ],
];