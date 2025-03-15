<?php

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartProduct;
use Talleu\RedisOm\Om\RedisObjectManagerInterface;

class CartService
{
    public function __construct(
        private readonly RedisObjectManagerInterface $redisObjectManager,
        private readonly SessionService $sessionService,
        private readonly StripeService $stripeService,
    ) {
    }

    public function addProductToCart(string $productId): Cart
    {
        $cart = $this->getCart();
        $cartProduct = new CartProduct($productId, 1);

        if (isset($cart->products[$productId])) {
            ++$cart->products[$productId]->quantity;
        } else {
            $cart->products[$productId] = $cartProduct;
        }

        $this->redisObjectManager->persist($cart);
        $this->redisObjectManager->flush();

        return $cart;
    }

    public function getCart(): Cart
    {
        $cartId = $this->sessionService->getCartId();
        $cart = $this->redisObjectManager->find(Cart::class, $cartId);

        if (!$cart) {
            $cart = new Cart();
            $cart->id = $cartId;
            $this->redisObjectManager->persist($cart);
            $this->redisObjectManager->flush();
        }

        return $cart;
    }

    public function removeProductFromCart(string $productId): Cart
    {
        $cart = $this->getCart();

        // S'assurer que products est bien un array
        if (!is_array($cart->products)) {
            $cart->products = [];
        }

        if (isset($cart->products[$productId])) {
            // Copier le tableau pour s'assurer que Redis détecte le changement
            $updatedProducts = $cart->products;
            unset($updatedProducts[$productId]);
            $cart->products = $updatedProducts;

            // Persister les changements
            $this->redisObjectManager->persist($cart);
            $this->redisObjectManager->flush();
        }

        return $cart;
    }

    public function decreaseProductQuantity(string $productId): Cart
    {
        $cart = $this->getCart();

        if (isset($cart->products[$productId])) {
            if ($cart->products[$productId]->quantity > 1) {
                --$cart->products[$productId]->quantity;
            } else {
                unset($cart->products[$productId]);
            }

            $this->redisObjectManager->persist($cart);
            $this->redisObjectManager->flush();
        }

        return $cart;
    }

    public function clearCart(): void
    {
        $cart = $this->getCart();

        // Réinitialiser en créant un nouveau tableau vide
        $cart->products = [];

        // Persister explicitement
        $this->redisObjectManager->persist($cart);
        $this->redisObjectManager->flush();

        // Vérification optionnelle que le panier est bien vidé
        $refreshedCart = $this->getCart();
        if (!empty($refreshedCart->products)) {
            // Tentative alternative de nettoyage si l'approche standard échoue
            $this->redisObjectManager->remove($cart);
            $this->redisObjectManager->flush();

            // Créer un nouveau panier vide
            $newCart = new Cart();
            $newCart->id = $this->sessionService->getCartId();
            $newCart->products = [];
            $this->redisObjectManager->persist($newCart);
            $this->redisObjectManager->flush();
        }
    }

    public function getCartWithProductDetails(Cart $cart): array
    {
        $result = [
            'products' => [],
            'total' => 0,
        ];

        foreach ($cart->products as $cartProduct) {
            try {
                $product = $this->stripeService->findOneProduct($cartProduct->id);
                $price = $this->stripeService->getLastActivePrice($product);

                $productWithDetails = [
                    'id' => $cartProduct->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'image' => $product->images[0] ?? null,
                    'quantity' => $cartProduct->quantity,
                    'unitPrice' => $price ? $price->unit_amount / 100 : 0,
                    'totalPrice' => $price ? ($price->unit_amount * $cartProduct->quantity) / 100 : 0,
                ];

                $result['products'][] = $productWithDetails;
                $result['total'] += $productWithDetails['totalPrice'];
            } catch (\Exception $e) {
                // Produit non trouvé ou autre erreur
                continue;
            }
        }

        return $result;
    }
}
