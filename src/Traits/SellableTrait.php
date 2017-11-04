<?php

namespace SamMcDonald\Bag\Traits;

use Illuminate\Support\Facades\Config;

trait SellableTrait
{
    public function getCode()
    {
        if($field = config('bag.product-fields.code'))
        {
            return $this->$field;
        }

        return method_exists($this, 'getKey') ? $this->getKey() : $this->id;
    }

    public function getName()
    {
        if($field = config('bag.product-fields.name'))
        {
            return $this->$field;
        }

        if(property_exists($this, 'name')) return $this->name;
    }

    public function getPrice()
    {
        if($field = config('bag.product-fields.price'))
        {
            return $this->$field;
        }

        if(property_exists($this, 'price')) return $this->price;
    }
}