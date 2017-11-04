## Laravel BAG (e-commerce)

Yet another Shopping cart package for Laravel.

## Installation

Install the package through [Composer](http://getcomposer.org/). 

Run the Composer require command from the Terminal:

    composer require s-mcdonald/bag
    
I will be adding AutoDiscovery code soon but for now just add the usual

	SamMcDonald\Bag\BagServiceProvider::class

And optionally the `aliases`:

	'Bag' => SamMcDonald\Bag\Facades\Bag::class,


## Overview


## Usage

The Bag gives you the following methods to use:

### Bag::add()


Simple 

```php

Bag::add('xyz', 'Poster', 1, 5.00);

```

With Product Attributes 

```php

Bag::add('xyz', 'Poster', 1, 5.00, ['size' => 'XL']);

```

Pass an Array 

```php

$product = ['code'=>'xyz', 'name'=>'Poster', 'qty' => 1, 'price' => 5.00];

Bag::add($product);

  or

$product = ['code'=>'xyz', 'name'=>'Poster'];

Bag::add($product,3, 3.50);

```


Using Sellable Interface 

```php

$shoes = Shoes::where('id',5)->firstOrfail();

Bag::add($shoes, 2, 5.00, ['size' => '10']);

```



### Bag::update()



```php

$row = 'shdfksdajhfdklahfdifhkd.....';

Bag::update($row, 7); // Changes QTY to 7

```


### Cart::remove()

To remove an item for the cart, you'll again need the rowId. This rowId you simply pass to the `remove()` method and it will remove the item from the cart.

```php

$row = 'shdfksdajhfdklahfdifhkd.....';

Bag::remove($row); 

```

### Bag::get()

Get an item from the Bag

```php
$row = 'shdfksdajhfdklahfdifhkd.....';

Bag::get($row);
```


### Bag::content()

Get all items from the Bag

```php
Bag::content();
```


### Bag::select()

Select an alternate bag or wishlist

```php

Bag::select('wishlist');

```

### Bag::clear()

Clears the Bag

```php

Bag::clear();

```




### Bag::total()



```php

Bag::total();

```

### Bag::rows()

```php

Bag::rows();

```

### Bag::count()

```php

Bag::count();

```

### Bag::filter()
### Bag::each()


## Exceptions


| Exception                    |                                                                              |
| ---------------------------- | ---------------------------------------------------------------------------------- |
| *BagException* |  |



## Events


## Example

