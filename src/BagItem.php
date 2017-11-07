<?php

namespace SamMcDonald\Bag;

use Illuminate\Support\Facades\Config;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;

use SamMcDonald\Bag\Contracts\Sellable;
use SamMcDonald\Bag\Exceptions\BagException;


class BagItem implements Arrayable, Jsonable
{
    /**
     * The rowID of the bag item.
     * Not the product id. This value
     * is auto-generated.
     *
     * @var string
     */
    protected $rowid;


    /**
     * Product Code or ID
     * 
     * @var [type]
     */
    protected $code;


    /**
     * Qty
     * 
     * @var [type]
     */
    protected $qty;


    /**
     * Product Name
     * 
     * @var [type]
     */
    protected $name;



    /**
     * Product price
     * 
     * @var [type]
     */
    protected $price;



    /**
     * Product attributes
     * height
     * Width
     *  etc..
     *      
     * @var array
     */
    protected $attributes;



    /**
     * Min ty of items
     */
    private const MIN_QTY = 1;



    /**
     * Construct()
     * 
     * @param [type] $code       [description]
     * @param [type] $name       [description]
     * @param [type] $price      [description]
     * @param array  $attributes [description]
     */
    public function __construct($code, $name, $price = null, array $attributes = [])
    {
        if(empty($code)) 
            throw new BagException('Invalid ID.');

        if(empty($name)) 
            throw new BagException('Invalid name.');

        if(strlen($price) < 0 || ! is_numeric($price)) 
            throw new BagException('Invalid price.');

        $this->code     = $code;
        $this->name     = $name;
        $this->rowid    = BagItemUtil::RowID($code, $attributes);
        $this->attributes  = new BagItemAttributes($attributes);

        $this->setPrice($price);
        $this->setQty(1);
    }

    /**
     * make(BagItem)
     * make(Sellable)
     * make(Sellable, qty)
     * make(Sellable, qty, attributes)
     * make(Sellable, null, attributes)
     * make(array)
     * make(array, attributes)
     * make(code, name, price)
     * make(code, name, price, qty, attributes)
     *
     * 
     * @param  [type] $param        [description]
     * @param  [type] $param2       [description]
     * @param  [type] $param3       [description]
     * @param  [type] $price        [description]
     * @param  array  $attributes   [description]
     * @return [type]               [description]
     */
    public static function make($param, $param2 = null, $param3 = null, $param4 = null, array $attributes = [])
    {
        if ($param instanceof Sellable) 
        {
            $item = new static($param->getCode(), $param->getName(), $param->getPrice(), $param3 ?: []);
            $item->setQty($param2 ?: self::MIN_QTY);
        }
        elseif ($param instanceof self) 
        {
            $item = $param;
        } 
        elseif (is_array($param)) 
        {
            $array = static::preparray($param);
            $attributes = (is_array($param2)) ? $param2 : [];
            $item = static::makeByArray($array, $attributes);
        } 
        else 
        {
            $item = new static($param, $param2, $param3, $attributes ); 
            $item->setQty($param4 ?: self::MIN_QTY);
        }

        return $item;
    }

    private static function makeByArray(array $array, array $attributes = [])
    {
        $attributes = array_get($array, 'attributes', $attributes );
        $item = new static(($array['code'] ?: $array['id']), $array['name'] ?: $array['description'], $array['price'], $attributes);
        $item->setQty($array['qty'] ?: $array['quantity'] ?: self::MIN_QTY);
        return $item;
    }

    /**
     * Prepares the array
     * 
     * @param  array  $array [description]
     * @return [type]        [description]
     */
    private static function preparray(array $array)
    {
        if(!isset($array['id'])) $array['id'] = false;
        if(!isset($array['code'])) $array['code'] = false;
        if(!isset($array['name'])) $array['name'] = false;
        if(!isset($array['qty'])) $array['qty'] = false;
        if(!isset($array['quantity'])) $array['quantity'] = false;

        

        //if using the options shorthand switch it up for attributes
        if(isset($array['options'])) $array['attributes'] = $array['options'];
        return $array;
    }



    /**
     * gets the row ID
     * 
     * @return [type] [description]
     */
    public function getRowId()
    {
        return $this->rowid;
    }


    /**
     * Get the product code
     * 
     * @return [type] [description]
     */
    public function getCode()
    {
        return $this->code;
    }



    /**
     * Get the product name/title
     * 
     * @return [type] [description]
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * Get the Qty
     * 
     * @return [type] [description]
     */
    public function getQty()
    {
        return $this->qty;
    }

    /**
     * Get the price
     * 
     * @return [type] [description]
     */
    public function getPrice()
    {
        return BagItemUtil::Currency($this->price);
    }
    
    /**
     * Gets the line total
     * 
     * @return [type] [description]
     */
    public function getTotal()
    {
        return BagItemUtil::Currency(($this->qty * $this->price));
    }


    /**
     * Set the Qty
     * 
     * @param [type] $qty [description]
     */
    public function setQty($qty)
    {
        if(empty($qty) || ! is_numeric($qty))
            throw new BagException('Invalid quantity.');

        return $this->qty = $qty;
    }


    public function setPrice($price)
    {
        if(empty($price) || ! is_numeric($price))
            throw new BagException('Invalid price.');

        return $this->price = BagItemUtil::Currency($price);
    }


    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * toArray()
     * 
     * @return [type] [description]
     */
    public function toArray()
    {
        return [
            'rowid'    => $this->rowid,
            'code'     => $this->code,
            'name'     => $this->name,
            'qty'      => $this->qty,
            'price'    => $this->price,
            'attributes'  => $this->attributes->toArray(),
            'subtotal' => $this->getTotal()
        ];
    }


    /**
     * toJson()
     * 
     * @param  integer $attributes [description]
     * @return [type]              [description]
     */
    public function toJson($attributes = 0)
    {
        return json_encode($this->toArray(), $attributes);
    }

    /**
     * Format to currency
     * 
     * @param  [type] $value [description]
     * @return [type]        [description]
     */
    private function formatCurrency($value)
    {
        $decimal = config('bag.number-format.precision')   ?: 2;
        $comma   = config('bag.number-format.thousands')   ?: ',';
        $point   = config('bag.number-format.decimal')     ?: '.';
        
        return number_format($value, $decimal, $point, $comma);
    }
}
