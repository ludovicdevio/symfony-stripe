<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

class SessionService
{
    public const CART_ID_KEY = 'cartId';

    public function __construct(private RequestStack $requestStack)
    {
    }

    public function setCartId(string $cartId): void
    {
        $this->requestStack->getSession()->set(self::CART_ID_KEY, $cartId);
    }

    public function getCartId(): string
    {
        $cartId = $this->requestStack->getSession()->get(self::CART_ID_KEY);
        if (null === $cartId) {
            $cartId = uniqid('cart_');
            $this->setCartId($cartId);
        }

        return $cartId;
    }
}
