<?php

namespace SamMcDonald\Bag;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Session\SessionManager;
use Illuminate\Contracts\Events\Dispatcher;

use SamMcDonald\Bag\Contracts\Sellable;
use SamMcDonald\Bag\Components\AbstractBag;
use SamMcDonald\Bag\Exceptions\BagException;


class Bag extends AbstractBag
{

    /**
     * Gets a array of Bags.
     * 
     * @return array
     */
    public function all()
    {
        return array_values($this->bag_list->toArray());
    }


    /**
     * Returns the current Bag
     * 
     * @return string [description]
     */
    public function current()
    {
        return $this->bag_list[$this->bag];
    }


    /**
     * Switch to the Default Bag.
     * 
     * @return self
     */
    public function default()
    {
        return $this->select(self::DEFAULT_BAG_NAME);
    }

    /**
     * Select a specific Bag.
     *
     * If $bag is null OR Multiple Bags not allowed,
     *     then the default Bag must be used.
     *
     * Otherwise the Bag to use will be $bag 
     *     prefixed with self::BAG_PREFIX
     *
     * Add the Bag into the Bag Index if not already.
     * 
     * Load the Bag contents
     * 
     * @param  string $name Desired bag
     * @return self
     */
    public function select(string $bag = null)
    {
        // Determine the bag to use
        if( 
            ($bag == null) ||
            (!$this->multiple_bags) ||
            ($bag == self::DEFAULT_BAG_NAME)) 
        {
                $this->bag = self::DEFAULT_CART_FQN;
        }
        else
            $this->bag = self::BAG_PREFIX.$bag;

        // Initialize the Bag if not set.
        if(!isset($this->bags[$this->bag])) {
            $this->bags[$this->bag] = new Collection;
        }

        // Check to see if we have indexed the Bag
        // if not, index and save the bag list
        if(!$this->bag_list->has($this->bag)) {
            $this->bag_list->put($this->bag, $bag);
            $this->save();
        }

        // Load the Bag contents
        $this->loadBag();

        return $this;
    }





    /**
     * Adds an Item to the Bag.
     * 
     * add(BagItem)
     * add(Sellable)
     * add(Sellable, qty)
     * add(Sellable, qty, attributes)
     * add(Sellable, null, attributes)
     * add(array)
     * add(array, qty)
     * add(array, qty, attributes)
     * add(array, null, attributes)
     * add(code, name, price)
     * add(code, name, qty, price, attributes)
     * 
     * @param mixed $param      [description]
     * @param mixed $param2     [description]
     * @param mixed $param3     [description]
     * @param mixed $param4     [description]
     * @param array  $attributes [description]
     */
    public function add($param, $param2 = null, $param3 = null, $param4 = null, array $attributes = [])
    {
        $item = BagItem::make($param, $param2, $param3, $param4, $attributes);

        $this->put($item->getRowId(), $item);
        
        $this->saveBag(self::EVT_ITEM_ADDED, $item);

        return $item;
    }



    /**
     * Updates the qty of a specific Item.
     *
     * Update use the second param as numeric for qty or array for price & qty.
     * 
     * @param  string $rowid [description]
     * @param  number $qty   [description]
     * @return BagItem
     */
    public function update($rowid, $data, $field = 'qty')
    {
        if($this->hasNone($rowid)) {
            return false;
        }

        $item = $this->get($rowid);

        if(is_array($data))
        {
            if(isset($data['price']))
                $item->setPrice($data['price']);

            if(isset($data['qty']))
                $item->setQty($data['qty']);
        }
        elseif($field === 'qty')
        {
            if ($data <= 0)
                $this->remove($item->getRowId());
            else
                $item->setQty($data);
        }
        elseif($field === 'price')
        {
            $item->setPrice($data);
        }

        $this->saveBag(self::EVT_ITEM_UPDATED, $item);

        return $item;
    }


    /**
     * Removes an Item from the bag
     * 
     * @param  string $rowid [description]
     * @return bool        [description]
     */
    public function remove(string $rowid = '') : bool
    {
        if($item = $this->get($rowid)) {
            $this->bags[$this->bag]->pull($rowid);           
            $this->saveBag(self::EVT_ITEM_REMOVED, $item);
            return true;
        }

        return false;
    }


    /**
     * Gets an Item from the Bag
     * 
     * @param  [type] $rowid [description]
     * @return [type]        [description]
     */
    public function get($rowid)
    {
        if ( ! $this->bags[$this->bag]->has($rowid))
            return false;

        return $this->bags[$this->bag]->get($rowid);
    }


    /**
     * Places a item in the Bag
     * 
     * @param  [type] $row  [description]
     * @param  [type] $item [description]
     * @return [type]       [description]
     */
    protected function put($row, $item)
    {
        return $this->bags[$this->bag]->put($row, $item);
    }


    /**
     * Confirms if the Bag contains a specific RowID.
     * 
     * @param  [type]  $rowid [description]
     * @return boolean        [description]
     */
    public function has($rowid)
    {
        return $this->bags[$this->bag]->has($rowid);
    }


    /**
     * Confirms if the Bag doesnt contain a specific row.
     * 
     * @param  [type]  $rowid [description]
     * @return boolean        [description]
     */
    public function hasNone($rowid)
    {
        return !($this->has($rowid));
    }


    /**
     * Clears the current Bag.
     * 
     * @return [type] [description]
     */
    public function clear()
    {
        $this->session->remove($this->bag);

        $this->bags[$this->bag] = new Collection;

        $this->saveBag(self::EVT_CART_CLEARED);

        return $this;
    }

    /**
     * Clears all the avilable Bags.
     * 
     * @return void
     */
    public function clearAll()
    {
        foreach($this->bag_list as $bag)
            static::select($bag)->clear();

        $this->bag_list = new Collection;

        $this->bag_list->put(self::DEFAULT_CART_FQN, self::DEFAULT_BAG_NAME);               
    }

    /**
     * Gets the contents of the Bag.
     * 
     * @return Collection
     */
    public function content()
    {
        if($this->bags[$this->bag] === null)
        {
            $this->bags[$this->bag] = $this->session->has($this->bag)
                ? $this->session->get($this->bag)
                : new Collection;

            $this->saveBag(self::EVT_CART_INIT);
        }

        return $this->bags[$this->bag];
    }


    /**
     * Bag::count();
     * 
     * Get the number of items in the bag.
     * Not the number of Rows. But the sum of Qty
     *
     * @return int|float
     */
    public function count()
    {
        $content = $this->content();

        return $content->sum('qty');
    }


    /**
     * returns Number of LineItems
     * 
     * @return int 
     */
    public function rows()
    {
        $content = $this->content();

        return $content->count();
    }


    /**
     * Returns the Subtotal for a specific row.
     * 
     * Bag::subtotal('abcdefg');
     * 
     * @return  Numeric
     */
    public function subtotal($rowid)
    {
        if($row = $this->get($rowid))
        {
            return $row->getTotal();
        }

        return 0;
    }


    /**
     * Gets the Total value of the Bag.
     *
     * Bag::total();
     * 
     * @return Numeric Value of the Bag.
     */
    public function total()
    {
        $content = $this->content();

        $total = $content->reduce(function ($total, BagItem $item) {
            return $total + ($item->getTotal());
        }, 0);

        return BagItemUtil::Currency($total);
    }

    /**
     * Bag::filter(function($item){
     *   return $item->getCode() == 'abcd'
     * });
     * 
     * @param  Closure $func [description]
     * @return [type]          [description]
     */
    public function filter(Closure $func)
    {
        return $this->content()->filter($func);
    }


    /**
     * Bag::each(function($item, $key){
     *   $item->getCode()
     * });
     * 
     * @param  Closure $func [description]
     * @return [type]        [description]
     */
    public function each(Closure $func)
    {
        return $this->content()->each($func);
    }


    /**
     * Save function for direct access from Facade
     * 
     */
    public function save()
    {
        $this->saveBag(self::EVT_CART_SAVED, null);
    }


}
