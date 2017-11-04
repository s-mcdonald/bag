<?php

namespace SamMcDonald\Bag\Contracts;

/**
 * Sellable
 */
interface Sellable
{
    public function getCode();


    public function getName();


    public function getPrice();
}