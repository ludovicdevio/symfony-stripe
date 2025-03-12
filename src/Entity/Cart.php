<?php

namespace App\Entity;
use Talleu\RedisOm\Om\Mapping as RedisOm;

#[RedisOm\Entity]
Class Cart
{
    #[RedisOm\Id]
    #[RedisOm\Property]
    public ?string $id;

    #[RedisOm\Property]
    public array $products = [];
}
