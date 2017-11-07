<?php

namespace SamMcDonald\Bag\Components;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Session\SessionManager;
use Illuminate\Contracts\Events\Dispatcher;

use SamMcDonald\Bag\Contracts\Sellable;
use SamMcDonald\Bag\Exceptions\BagException;


abstract class AbstractBag
{



    /**
     * The Current or Selected Bag
     * 
     * @var Collection
     */
    protected $bag;



    /**
     * Array of Collection (Bag Content)
     * 
     * @var array
     */
    protected $bags;



    /**
     * Session manager
     * 
     * @var SessionManager
     */
    protected $session;



    /**
     * Event Dispatcher
     * 
     * @var Dispatcher
     */
    protected $events;





    /**
     * Event: Bag Initialized
     */
    protected const EVT_CART_INIT     = 'Bag.Init';



    /**
     * Event: Bag Saved
     */
    protected const EVT_CART_SAVED    = 'Bag.Save';



    /**
     * Event: Bag Cleared
     */
    protected const EVT_CART_CLEARED  = 'Bag.Clear';



    /**
     * Event: Bag Item Added
     */
    protected const EVT_ITEM_ADDED    = 'Bag.Item.Added';



    /**
     * Event: Bag Item Removed
     */
    protected const EVT_ITEM_REMOVED  = 'Bag.Item.Removed';



    /**
     * Event: Bag Item Updated
     */
    protected const EVT_ITEM_UPDATED  = 'Bag.Item.Updated';




    /**
     * Session name for Bag Index
     */
    protected const BAG_LIST_KEY      = 'Bags:List';



    /**
     * Bag prefix for custom Bags
     */
    protected const BAG_PREFIX        = 'Bags:Bag:';


    /**
     * FQN for the Default Bag
     */
    protected const DEFAULT_CART_FQN  = 'Bags:Bag:default';



    protected const CURRENT_BAG       = 'Bags:Current';


    /**
     * The Default Bag Name
     */
    protected const DEFAULT_BAG_NAME = 'default';




    /**
     * Flag to indicate if we are allowed to use
     * multiple bags.
     * 
     * @var [type]
     */
    protected $multiple_bags;


    /**
     * Index of Bags
     * 
     * @var Collection
     */
    protected $bag_list;


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
    final private function initialize()
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
            $this->bag_list->put($this->bag, self::DEFAULT_BAG_NAME);
        }

        $this->multiple_bags    = config('bag.multi-bags') ?: false;
    }



    /**
     * [__toString description]
     * @return string [description]
     */
    public function __toString()
    {
        return $this->current()->toJson();
    }


    /**
     * DieDump the Bag object
     * 
     * @param  [type] $dumps [description]
     * @return [type]        [description]
     */
    final public function dd(...$dumps)
    {
        if($dumps === null)
            dd($this);

        dd($dumps,$this);
    }



    /**
     * Initialize the current Bag.
     * 
     * @return [type] [description]
     */
    final protected function loadBag()
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
    final protected function saveBag(string $event_name, $payload = null)
    {
        $this->session->put(self::CURRENT_BAG, $this->bag);

        $this->session->put($this->bag, $this->bags[$this->bag]);

        $this->session->put(self::BAG_LIST_KEY, $this->bag_list);

        $this->session->save();

        $this->events->fire($event_name, $payload);
    }

    /*
     |
     | abstract functions
     |
     |
     |
     |
     |
     */


    /**
     * Gets a listing of Bags.
     * 
     * @return Collection
     */
    abstract public function all();


    /**
     * Returns the current Bag
     * 
     * @return string [description]
     */
    abstract public function current();


    /**
     * Switch to the Default Bag.
     * 
     * @return self
     */
    abstract public function default();

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
    abstract public function select(string $bag = null);





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
    abstract public function add($param, 
        $param2 = null, 
        $param3 = null, 
        $param4 = null, 
        array $attributes = []);



    /**
     * Updates the qty of a specific Item.
     * 
     * @param  string $rowid [description]
     * @param  number $qty   [description]
     * @return BagItem
     */
    abstract public function update($rowid, $qty);


    /**
     * Removes an Item from the bag
     * 
     * @param  string $rowid [description]
     * @return bool        [description]
     */
    abstract public function remove(string $rowid = '') : bool;


    /**
     * Gets an Item from the Bag
     * 
     * @param  [type] $rowid [description]
     * @return [type]        [description]
     */
    abstract public function get($rowid);


    /**
     * Places a item in the Bag
     * 
     * @param  [type] $row  [description]
     * @param  [type] $item [description]
     * @return [type]       [description]
     */
    abstract protected function put($row, $item);

    /**
     * Confirms if the Bag contains a specific RowID.
     * 
     * @param  [type]  $rowid [description]
     * @return boolean        [description]
     */
    abstract public function has($rowid);


    /**
     * Confirms if the Bag doesnt contain a specific row.
     * 
     * @param  [type]  $rowid [description]
     * @return boolean        [description]
     */
    abstract public function hasNone($rowid);


    /**
     * Clears the current Bag.
     * 
     * @return [type] [description]
     */
    abstract public function clear();


    /**
     * Clears all the avilable Bags.
     * 
     * @return void
     */
    abstract public function clearAll();


    /**
     * Gets the contents of the Bag.
     * 
     * @return Collection
     */
    abstract public function content();




    /**
     * Bag::count();
     * 
     * Get the number of items in the bag.
     * Not the number of Rows. But the sum of Qty
     *
     * @return int|float
     */
    abstract public function count();

    /**
     * returns Number of LineItems
     * 
     * @return int 
     */
    abstract public function rows();


    /**
     * Returns the Subtotal for a specific row.
     * 
     * Bag::subtotal('abcdefg');
     * 
     * @return  Numeric
     */
    abstract public function subtotal($rowid);


    /**
     * Gets the Total value of the Bag.
     *
     * Bag::total();
     * 
     * @return Numeric Value of the Bag.
     */
    abstract public function total();

    /**
     * Bag::filter(function($item){
     *   return $item->getCode() == 'abcd'
     * });
     * 
     * @param  Closure $func [description]
     * @return [type]          [description]
     */
    abstract public function filter(Closure $func);

    /**
     * Bag::each(function($item, $key){
     *   $item->getCode()
     * });
     * 
     * @param  Closure $func [description]
     * @return [type]        [description]
     */
    abstract public function each(Closure $func);




    /**
     * Save function for direct access from Facade
     * 
     */
    abstract public function save();

}
