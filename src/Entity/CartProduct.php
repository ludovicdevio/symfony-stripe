<?php

namespace App\Entity;

use Talleu\RedisOm\Om\Mapping as RedisOm;

#[RedisOm\Entity]
class CartProduct
{
    #[RedisOm\Id]
    #[RedisOm\Property]
    public ?string $id = null;

    #[RedisOm\Property]
    public ?int $quantity = null;

    public function __construct(?string $id = null, ?int $quantity = null)
    {
        $this->id = $id;
        $this->quantity = $quantity;
    }
}
