<?php

namespace SamMcDonald\Bag;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Session\SessionManager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Contracts\Events\Dispatcher;

use SamMcDonald\Bag\Contracts\Sellable;
use SamMcDonald\Bag\Exceptions\BagException;


class Bag
{


    /**
     * Session manager
     * 
     * @var [type]
     */
    private $session;



    /**
     * Event Dispatcher
     * 
     * @var [type]
     */
    private $events;



    /**
     * The Current Bag
     * 
     * @var [type]
     */
    private $trolley;



    /**
     * Content store for our Bag items
     * 
     * @var [type]
     */
    private $content;



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
     * The Default Bag Name
     */
    private const DEFAULT_CART_NAME = 'default';



    /**
     * FQN for the Default Bag
     */
    private const DEFAULT_CART_FQN  = 'Bags:Bag:default';



    /**
     * Bag prefix for custom Bags
     */
    private const BAG_PREFIX        = 'Bags:Bag:';



    /**
     * Session name for Bag Index
     */
    private const BAG_LIST_KEY   = 'Bags:Bag';


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
        $this->trolley = self::DEFAULT_CART_FQN;
        $this->events   = $events;
        $this->session  = $session;
        $this->content  = null;       
        $this->bag_list = $this->fetch_trollies();
        $this->multiple_bags = config('bag.multi-bags') ?: false;
        $this->initialiseBag();
    }


    /**
     * Gets all Bags.
     * 
     * @return [type] [description]
     */
    public function all()
    {
        return $this->bag_list;
    }


    /**
     * Switch to the Default Bag.
     * 
     * @return [type] [description]
     */
    public function default()
    {
        return $this->select(self::DEFAULT_CART_NAME);
    }

    /**
     * Select a specific Bag.
     * 
     * @param  [type] $name [description]
     * @return [type]       [description]
     */
    public function select(string $name = null)
    {
        if( 
            ($name == null) ||
            (!$this->multiple_bags) ||
            ($name == self::DEFAULT_CART_NAME)) {
            $this->trolley = self::DEFAULT_CART_FQN;
        }
        else
            $this->trolley = self::BAG_PREFIX.$name;

        if(!isset($this->content[$this->trolley])) {
            $this->content[$this->trolley] = null;
        }

        if(!$this->bag_list->has($this->trolley)) {
            $this->bag_list->put($this->trolley,$name);
            $this->save();
        }

        // bag is now selected, lets initialize it
        $this->initialiseBag();

        return $this;
    }



    /**
     * Returns current trolly.
     * 
     * @return string
     */
    public function view()
    {
        return $this->trolley;
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
     * @param [type] $param      [description]
     * @param [type] $param2     [description]
     * @param [type] $param3     [description]
     * @param [type] $param4     [description]
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
     * @param  [type] $rowId [description]
     * @param  [type] $qty   [description]
     * @return [type]        [description]
     */
    public function update($rowId, $qty)
    {
        if($this->hasNone($rowId)) {
            return false;
        }

        $item = $this->get($rowId);

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
     * @param  string $rowId [description]
     * @return [type]        [description]
     */
    public function remove(string $rowId = '') : bool
    {
        if($item = $this->get($rowId)) {
            $this->content[$this->trolley]->pull($rowId);           
            $this->saveBag(self::EVT_ITEM_REMOVED, $item);
            return true;
        }

        return false;
    }


    /**
     * Gets an Item from the Bag
     * 
     * @param  [type] $rowId [description]
     * @return [type]        [description]
     */
    public function get($rowId)
    {
        if ( ! $this->content[$this->trolley]->has($rowId))
            return false;

        return $this->content[$this->trolley]->get($rowId);
    }


    /**
     * Places a item in the Bag
     * 
     * @param  [type] $row  [description]
     * @param  [type] $item [description]
     * @return [type]       [description]
     */
    protected function put($row,$item)
    {
        return $this->content[$this->trolley]->put($row, $item);
    }


    /**
     * Confirms if the Bag contains a specific RowID.
     * 
     * @param  [type]  $rowId [description]
     * @return boolean        [description]
     */
    public function has($rowId)
    {
        return $this->content[$this->trolley]->has($rowId);
    }


    /**
     * Confirms if the Bag doesnt contain a specific row.
     * 
     * @param  [type]  $rowId [description]
     * @return boolean        [description]
     */
    public function hasNone($rowId)
    {
        return !($this->has($rowId));
    }


    /**
     * Clears the current Bag.
     * 
     * @return [type] [description]
     */
    public function clear()
    {
        $this->session->remove($this->trolley);

        $this->content[$this->trolley] = new Collection;

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
        if($this->content[$this->trolley] === null)
        {
            $this->content[$this->trolley] = $this->session->has($this->trolley)
                ? $this->session->get($this->trolley)
                : new Collection;

            $this->saveBag(self::EVT_CART_INIT);
        }

        return $this->content[$this->trolley];
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
    public function subtotal($rowId)
    {
        if($row = $this->get($rowId))
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
    private function initialiseBag()
    {
        if(!isset($this->content[$this->trolley]))
        {
            $this->content[$this->trolley] = $this->session->has($this->trolley)
                ? $this->session->get($this->trolley)
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
        $this->session->put($this->trolley, $this->content[$this->trolley]);

        $this->session->put(self::BAG_LIST_KEY, $this->bag_list);

        $this->session->save();

        $this->events->fire($event_name, $payload);
    }

    /**
     * Fetch the list of Bags we have stored
     *
     * This gets the index of bags, a collection of Bag 
     * names and not the Bags themselves.
     *
     * 
     * @return Collection Laravel Collection
     */
    private function fetch_trollies()
    {
        if($this->session->has(self::BAG_LIST_KEY)) {
            return $this->session->get(self::BAG_LIST_KEY);
        }

        $this->bag_list = new Collection();
        $this->bag_list->put($this->trolley, self::DEFAULT_CART_NAME);
        $this->save();

        return $this->bag_list;
    }
}
