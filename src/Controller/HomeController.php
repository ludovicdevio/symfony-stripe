<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\SessionService;
use App\Service\StripeService;
use App\Service\CartService;
use App\Entity\Cart;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly SessionService $sessionService,
        private readonly StripeService $stripeService,
        private readonly CartService $cartService
        )
    {
    }


    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $cartId = $this->sessionService->getCartId();
        $products = $this->stripeService->getActiveProducts();

        return $this->render('home/index.html.twig', [
            'cartId' => $cartId,
            'products' => $products
        ]);
    }

    #[Route('/products/{id}/buy', name: 'app_buy_product')]
    public function buyProduct(string $id): Response
    {
        $product = $this->stripeService->findOneProduct($id);


        return $this->redirect($this->stripeService->getProductBuyUrl($product));

    }

    #[Route('/products/{id}/add-to-cart', name: 'app_add_to_cart')]
    public function addToCart(string $id): Response
    {
       $cart =  $this->cartService->addProductToCart($id);


        return $this->redirectToRoute('app_home');

    }

    #[Route('/products/buy-cart', name: 'app_buy_cart')]
    public function buyCart(): Response
    {
       $cart =  $this->cartService->getCart();


        return $this->redirect($this->stripeService->getCartBuyUrl($cart));

    }
}
