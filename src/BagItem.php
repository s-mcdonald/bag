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
        $this->price    = BagItemUtil::Currency($price);
        $this->$rowid    = BagItemUtil::RowID($code, $attributes);
        $this->attributes  = new BagItemAttributes($attributes);        
    }

    /**
     * make(BagItem)
     * make(Sellable)
     * make(Sellable, qty)
     * make(Sellable, qty, attributes)
     * make(Sellable, null, attributes)
     * make(array)
     * make(array, qty)
     * make(array, qty, attributes)
     * make(array, null, attributes)
     * make(code, name, price)
     * make(code, name, qty, price, attributes)
     *
     * 
     * @param  [type] $param   [description]
     * @param  [type] $param2  [description]
     * @param  [type] $param3     [description]
     * @param  [type] $price   [description]
     * @param  array  $attributes [description]
     * @return [type]          [description]
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
            $attributes = array_get($param, 'attributes', false );
            $item = new static(($param['code'] ?: $param['id']), $param['name'] ?: $param['description'], $param['price'], $attributes ?: $param3 ?: []);
            $item->setQty($param['qty'] ?: $param2 ?: self::MIN_QTY);
        } 
        else 
        {
            $item = new static($param, $param2, $param4, $attributes); 
            $item->setQty($param3 ?: self::MIN_QTY);
        }

        return $item;
    }



    /**
     * gets the row ID
     * 
     * @return [type] [description]
     */
    public function getRowId()
    {
        return $this->$rowid;
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


    /**
     * toArray()
     * 
     * @return [type] [description]
     */
    public function toArray()
    {
        return [
            'rowid'    => $this->$rowid,
            'code'     => $this->code,
            'name'     => $this->name,
            'qty'      => $this->qty,
            'price'    => $this->price,
            'attributes'  => $this->attributes->toArray(),
            'subtotal' => $this->subtotal
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
