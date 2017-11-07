# BAG 
*An e-commerce cart system for Laravel 5.5*
[![Laravel](https://img.shields.io/badge/laravel-5.5-orange.svg)](http://laravel.com)
[![Source](https://img.shields.io/badge/source-S_McDonald-blue.svg)](https://github.com/s-mcdonald/bag)

Bag is a simple and Shopping Cart solution for Laravel.
It utilizes a simple and common api that allows for the use of multiple carts/lists.

## Example usage

```php

// Add items to Bag
$item1 = Bag::add('a1', 'Shoes',  1, 25.00);
$item2 = Bag::add('a2', 'Poster', 1,  5.00, ['size' => 'XL']);

// Update Item in Bag
Bag::update($item1->getRowId(), 6);


// Remove Item from Bag
Bag::remove($item2->getRowId());


// List Bag Contents
foreach(Bag::content() as $item)
{
    $item->getName();
}


```


## Installation

Install the package through [Composer](http://getcomposer.org/). 

Run the Composer require command from the Terminal:

    composer require s-mcdonald/bag
    
I will be adding AutoDiscovery code soon but for now just add the usual

    SamMcDonald\Bag\BagServiceProvider::class

And optionally the `aliases`:

    'Bag' => SamMcDonald\Bag\Facades\Bag::class,


## Documentation

Below is a comprehensive list of commands exposed by LaravelBag.

#### RowID

Its important to note that once an Item is added to the Bag, the Bag generates a RowID for the item added.
You need the rowid to perform various operations, so if you need to get the rowid you can get it by either the returned value of `add()` or by iterating over `content()`.

```php

$row = Bag::add('xyz', 'Poster', 5.00);
$row->getRowId();


foreach(Bag::content() as $item)
{
    $item->getRowId()
}

```


### Selecting a Bag

Bag allows you to use multiple lists/Bags. This must be enabled in the config settings.



#### Bag::select()

To select a user defined Bag or List;

```php

// Pass in the list name in the select method.

Bag::select('wishlist');
Bag::select('list-name');

```



#### Bag::default()

Use `Bag::default()` to select the default Bag. The session will remember which bag you have activated on a page re-load. Once you change into a bag it will remain active in your session.


```php

// Alias for select(). This does not take any attributes.
Bag::default()

// Use select without attributes.
Bag::select();

```

### Adding to the Bag


#### Bag::add()

You can `add()` items in the Bag in various ways. The quickest way is the Simple method.

##### Simple Method 

To use the Simple Method, just pass in the values you want to use. The min required values are code, name and price.
Qty and Attributes will default to `1` and `null` respectivly.

```php

Bag::add(string productCode, string productName, number price)
Bag::add(string productCode, string productName, number price, number qty)
Bag::add(string productCode, string productName, number price, number qty, array attributes)

```

Example 

```php

Bag::add('xyz', 'Poster', 5.00);
Bag::add('xyz', 'Poster', 5.00, 5);
Bag::add('xyz', 'Poster', 5.00, 5, ['size' => 'XL']);


```


##### Array Method 

Optionally, you can pass an array to the `add()` method. The array must be an associative array with the following keys.

```php

Bag::add(array product)
Bag::add(array product, array attributes)

```

| Key                     | Short         | Long           | Required      |
| ----------------------- | ------------- |----------------|---------------|
| id                      | `id`          | `code`         | *Yes*         |
| name                    | `name`        | `description`  | *Yes*         |
| price                   | `price`       | `price`        | *Yes*         |
| quantity                | `qty`         | `quantity`     | No            |
| attributes              | `options`     | `attributes`   | No            |


Example:

```php
$product = [
    'code'          =>'xyz', 
    'name'          =>'Poster 2', 
    'qty'           => 1, 
    'price'         => 5.00
];

$product = [
    'id'            =>'xyz', 
    'description'   =>'Poster 2', 
    'quantity'      => 1, 
    'price'         => 5.00
];

```

```php

// basic
Bag::add(['code'=>'xyz', 'name'=>'Poster 2', 'qty' => 1, 'price' => 5.00]);

// Pass a second array for attributes
Bag::add(['code'=>'abc', 'name'=>'Poster 1'], ['size'=>'xs']);

// attributes contained in the first array
$product = [
    'code'=>'xyz', 
    'name'=>'Poster 2', 
    'qty' => 1, 
    'price' => 5.00,
    'attributes' => [
        'size' => 'xs',
        'color' => 'green'
    ]
];

Bag::add($product);

```

##### Sellable Method 

The Bag package contains a Sellable Interface that you can add to your product Model. This allows you to pass in your model
to add to the Bag.



```php

// Implement the Sellable Interface
class Shoes extends Model implements Sellable
{
    ...
}


$shoes = Shoes::where('id', 102)->firstOrfail();

// Default to Qty 1, price is set in the model
Bag::add($shoes);

// Set the Qty
Bag::add($shoes, 2);


// Set the Qty and Attributes
Bag::add($shoes, 2, ['size' => '10']);



// Passing Qty as null is possible but will result in Qty being set to 1
Bag::add($shoes, 2, ['size' => '10']);


```

### Updating Content

#### Bag::update()

To update a row in the Bag you first need to obtain the RowID. This is different to the Product Code.
There are 3 ways to update the Qty value of a BagItem (all which require the rowid).

##### Update Qty

Change Qty to 7

```php

$rowid = 'c6de02f9729d1628c0fb2d7d6cad8f2a';

// First
Bag::get($rowid)->setQty(7); 

// Second
Bag::update($rowid, 7);

// Third
Bag::update($rowid, ['qty' => 7]); 

```

##### Update Price

There are 2 ways to update the price.
Change Unit Price to 5.50

```php

$rowid = 'c6de02f9729d1628c0fb2d7d6cad8f2a';


// First
Bag::get($rowid)->setPrice(5.50); 

// Second
Bag::update($rowid, 5.50, 'price');

// Third
Bag::update($rowid, ['price' => 5.50]); 
```

##### Update Qty & Price

If you want to update the price and Qty together, simply pass an array in the second parameter.

```php
Bag::update($rowid, [
        'qty' => 7,
        'price' => 5.50,
    ]
); 
```



### Deleting Content


#### Bag::remove()

Remove an item from the *current* Bag.

```php

$rowid = 'c6de02f9729d1628c0fb2d7d6cad8f2a';

bool Bag::remove($rowid); 

```



#### Bag::clear()

Clears the current active Bag 

```php


Bag::clear();

// Clear a specific Bag (does not change the active Bag)
Bag::clear('wishlist');

```

#### Bag::clearAll()

Clears All Bags 

```php

Bag::clearAll();


```



### Retrieving Content

#### Bag::get()

Get an item from the Bag

```php

$rowid = 'c6de02f9729d1628c0fb2d7d6cad8f2a';

// Gets item by price
$item = Bag::get($rowid);

//Now you can get item info
$item->getPrice();
$item->getName();
$item->getQty();
$item->getRowId();


```


#### Bag::content()

Get all items from the Bag as a Laravel collection.
Because of this you can call other Collection functions and chain them as required.

```php

Bag::content();
Bag::content()->last();
Bag::content()->first();
Bag::content()->random();

```


#### Bag::total()

Gets the total value of the Cart

```php

Bag::total();

```



#### Bag::rows()

Returns the Number of row items.

```php

Bag::rows();

```

#### Bag::count()

Returns the Total QTY of items

```php

Bag::count();


```





### Callback Methods

Bag has 2 shortcut functions that are essentially a link to the Collection object

so Bag::filter() is the same as Bag::content()->filter(....)
so Bag::each() is the same as Bag::content()->each(....)

#### Bag::filter()
#### Bag::each()


## Exceptions


| Exception                    |                                                                              |
| ---------------------------- | ---------------------------------------------------------------------------------- |
| *BagException* |  |



## Events


## Example

