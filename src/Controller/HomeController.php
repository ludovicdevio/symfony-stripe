<?php

namespace App\Controller;

use App\Service\CartService;
use App\Service\SessionService;
use App\Service\StripeService;
use Stripe\Exception\ApiErrorException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly SessionService $sessionService,
        private readonly StripeService $stripeService,
        private readonly CartService $cartService,
    ) {
    }

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $cartId = $this->sessionService->getCartId();

        try {
            $products = $this->stripeService->getActiveProducts();
            $cart = $this->cartService->getCart();

            return $this->render('home/index.html.twig', [
                'cartId' => $cartId,
                'products' => $products,
                'cart' => $cart,
            ]);
        } catch (ApiErrorException $e) {
            $this->addFlash('error', 'Impossible de récupérer les produits. Veuillez réessayer plus tard.');

            return $this->render('home/index.html.twig', [
                'cartId' => $cartId,
                'products' => [],
                'cart' => null,
            ]);
        }
    }

    #[Route('/products/{id}/buy', name: 'app_buy_product')]
    public function buyProduct(string $id): Response
    {
        try {
            $product = $this->stripeService->findOneProduct($id);

            return $this->redirect($this->stripeService->getProductBuyUrl(
                $product,
                $this->generateUrl('app_payment_success', [], 0),
                $this->generateUrl('app_payment_cancel', [], 0),
                1 // quantité maintenant en dernier
            ));
        } catch (ApiErrorException $e) {
            $this->addFlash('error', 'Le produit que vous essayez d\'acheter n\'existe pas ou est indisponible.');

            return $this->redirectToRoute('app_home');
        }
    }

    #[Route('/products/{id}/add-to-cart', name: 'app_add_to_cart')]
    public function addToCart(string $id): Response
    {
        try {
            $product = $this->stripeService->findOneProduct($id);
            $this->cartService->addProductToCart($id);
            $this->addFlash('success', sprintf('"%s" a été ajouté à votre panier', $product->name));
        } catch (ApiErrorException $e) {
            $this->addFlash('error', 'Impossible d\'ajouter ce produit au panier.');
        }

        return $this->redirectToRoute('app_home');
    }

    #[Route('/products/buy-cart', name: 'app_buy_cart')]
    public function buyCart(): Response
    {
        $cart = $this->cartService->getCart();

        if (empty($cart->products)) {
            $this->addFlash('warning', 'Votre panier est vide.');

            return $this->redirectToRoute('app_home');
        }

        try {
            $checkoutUrl = $this->stripeService->getCartBuyUrl(
                $cart,
                $this->generateUrl('app_payment_success', [], 0),
                $this->generateUrl('app_payment_cancel', [], 0)
            );

            return $this->redirect($checkoutUrl);
        } catch (ApiErrorException $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de la préparation du paiement.');

            return $this->redirectToRoute('app_home');
        }
    }

    #[Route('/products/view-cart', name: 'app_view_cart')]
    public function viewCart(): Response
    {
        $cart = $this->cartService->getCart();
        $cartWithDetails = $this->cartService->getCartWithProductDetails($cart);

        return $this->render('home/cart.html.twig', [
            'cart' => $cartWithDetails,
        ]);
    }

    #[Route('/products/{id}/remove-from-cart', name: 'app_remove_from_cart')]
    public function removeFromCart(string $id): Response
    {
        try {
            $cart = $this->cartService->getCart();

            // Vérifier si le produit existe dans le panier
            if (isset($cart->products[$id])) {
                // Supprimer le produit
                $productName = $this->stripeService->findOneProduct($id)->name;
                $this->cartService->removeProductFromCart($id);
                $this->addFlash('success', sprintf('"%s" a été retiré de votre panier', $productName));
            } else {
                $this->addFlash('warning', 'Ce produit n\'est pas dans votre panier');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Impossible de retirer ce produit du panier');
        }

        return $this->redirectToRoute('app_view_cart');
    }

    #[Route('/products/clear-cart', name: 'app_clear_cart')]
    public function clearCart(): Response
    {
        try {
            $cart = $this->cartService->getCart();

            if (!empty($cart->products)) {
                $this->cartService->clearCart();
                $this->addFlash('success', 'Votre panier a été vidé');
            } else {
                $this->addFlash('info', 'Votre panier est déjà vide');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Impossible de vider le panier');
        }

        return $this->redirectToRoute('app_home');
    }

    #[Route('/payment/success', name: 'app_payment_success')]
    public function paymentSuccess(): Response
    {
        $this->cartService->clearCart();
        $this->addFlash('success', 'Votre paiement a été traité avec succès !');

        return $this->redirectToRoute('app_home');
    }

    #[Route('/payment/cancel', name: 'app_payment_cancel')]
    public function paymentCancel(): Response
    {
        $this->addFlash('warning', 'Le paiement a été annulé.');

        return $this->redirectToRoute('app_view_cart');
    }
}
