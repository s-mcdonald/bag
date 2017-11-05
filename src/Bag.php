<?php

namespace SamMcDonald\Bag;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Session\SessionManager;
use Illuminate\Contracts\Events\Dispatcher;

use SamMcDonald\Bag\Contracts\Sellable;
use SamMcDonald\Bag\Exceptions\BagException;


class Bag
{



    /**
     * The Current or Selected Bag
     * 
     * @var Collection
     */
    private $bag;



    /**
     * Array of Collection (Bag Content)
     * 
     * @var array
     */
    private $bags;



    /**
     * Session manager
     * 
     * @var SessionManager
     */
    private $session;



    /**
     * Event Dispatcher
     * 
     * @var Dispatcher
     */
    private $events;





    /**
     * Event: Bag Initialized
     */
    private const EVT_CART_INIT     = 'Bag.Init';



    /**
     * Event: Bag Saved
     */
    private const EVT_CART_SAVED    = 'Bag.Save';



    /**
     * Event: Bag Cleared
     */
    private const EVT_CART_CLEARED  = 'Bag.Clear';



    /**
     * Event: Bag Item Added
     */
    private const EVT_ITEM_ADDED    = 'Bag.Item.Added';



    /**
     * Event: Bag Item Removed
     */
    private const EVT_ITEM_REMOVED  = 'Bag.Item.Removed';



    /**
     * Event: Bag Item Updated
     */
    private const EVT_ITEM_UPDATED  = 'Bag.Item.Updated';




    /**
     * Session name for Bag Index
     */
    private const BAG_LIST_KEY      = 'Bags:Bag';



    /**
     * Bag prefix for custom Bags
     */
    private const BAG_PREFIX        = 'Bags:Bag:';




    /**
     * The Default Bag Name
     */
    private const DEFAULT_CART_NAME = 'default';



    /**
     * FQN for the Default Bag
     */
    private const DEFAULT_CART_FQN  = 'Bags:Bag:default';


    private const CURRENT_BAG       = 'Bags:Current';







    /**
     * Flag to indicate if we are allowed to use
     * multiple bags.
     * 
     * @var [type]
     */
    private $multiple_bags;


    /**
     * Index of Bags
     * 
     * @var Collection
     */
    private $bag_list;

    /**
     * Constructs the Bag, this is generally called from the IoC.
     * 
     * @param SessionManager $session [description]
     * @param Dispatcher     $events  [description]
     */
    public function __construct(SessionManager $session, Dispatcher $events)
    {
        $this->events           = $events;
        $this->session          = $session;

        $this->initialize();

        $this->loadBag();
    }

    /**
     * Initialize the Bag.
     * 
     * Load active bag or use default
     * 
     * @return [type] [description]
     */
    private function initialize()
    {
        $this->bags = [];  
        $this->bag  = $this->session->has(self::CURRENT_BAG)
                            ? $this->session->get(self::CURRENT_BAG)
                            : self::DEFAULT_CART_FQN;

        if($this->session->has(self::BAG_LIST_KEY)) {
            $this->bag_list = $this->session->get(self::BAG_LIST_KEY);
        }
        else {
            $this->bag_list = new Collection();
            $this->bag_list->put($this->bag, self::DEFAULT_CART_NAME);
        }

        $this->multiple_bags    = config('bag.multi-bags') ?: false;
    }




    /**
     * Gets a listing of Bags.
     * 
     * @return Collection
     */
    public function all()
    {
        return $this->bag_list;
    }


    /**
     * Returns the current Bag
     * 
     * @return string [description]
     */
    public function getActiveBag()
    {
        return $this->bag;
    }


    /**
     * Switch to the Default Bag.
     * 
     * @return self
     */
    public function default()
    {
        return $this->select(self::DEFAULT_CART_NAME);
    }

    /**
     * Select a specific Bag.
     * 
     * @param  string $name Desired bag
     * @return self
     */
    public function select(string $bag = null)
    {
        if( 
            ($bag == null) ||
            (!$this->multiple_bags) ||
            ($bag == self::DEFAULT_CART_NAME)) {
            $this->bag = self::DEFAULT_CART_FQN;
        }
        else
            $this->bag = self::BAG_PREFIX.$bag;

        if(!isset($this->bags[$this->bag])) {
            $this->bags[$this->bag] = null;
        }

        if(!$this->bag_list->has($this->bag)) {
            $this->bag_list->put($this->bag, $bag);
            $this->save();
        }

        // bag is now selected, lets initialize it
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
     * @param  string $rowid [description]
     * @param  number $qty   [description]
     * @return BagItem
     */
    public function update($rowid, $qty)
    {
        if($this->hasNone($rowid)) {
            return false;
        }

        $item = $this->get($rowid);

        if ($qty <= 0)
            $this->remove($item->getRowId());
        else {
            $item->setQty($qty);
            $this->put($item->getRowId(), $item);
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

        $this->bag_list->put(self::DEFAULT_CART_FQN, self::DEFAULT_CART_NAME);               
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
     * @param  Closure $search [description]
     * @return [type]          [description]
     */
    public function filter(Closure $search)
    {
        return $this->content()->filter($search);
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



    /*
     |
     | Private functions
     |
     |
     |
     |
     |
     */
    

    /**
     * Initialize the current Bag.
     * 
     * @return [type] [description]
     */
    private function loadBag()
    {
        $this->session->put(self::CURRENT_BAG, $this->bag);

        if(!isset($this->bags[$this->bag]))
        {
            $this->bags[$this->bag] = $this->session->has($this->bag)
                ? $this->session->get($this->bag)
                : new Collection;
        }
    }


    /**
     * Saves the current Bag to the session
     *
     * An event is fired after saving with the relevat payload.
     * 
     * @param  string $event_name [description]
     * @param  [type] $payload    [description]
     * @return [type]             [description]
     */
    private function saveBag(string $event_name, $payload = null)
    {
        $this->session->put(self::CURRENT_BAG, $this->bag);

        $this->session->put($this->bag, $this->bags[$this->bag]);

        $this->session->put(self::BAG_LIST_KEY, $this->bag_list);

        $this->session->save();

        $this->events->fire($event_name, $payload);
    }

}
