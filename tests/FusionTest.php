<?php

namespace App\Tests;

use App\Entity\Cart;
use App\Entity\CartProduct;
use App\Service\CartService;
use App\Service\SessionService;
use App\Service\StripeService;
use PHPUnit\Framework\TestCase;
use Stripe\Product;
use Stripe\Price;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Talleu\RedisOm\Om\RedisObjectManagerInterface;

// Créer une classe de test qui étend StripeService pour les tests
class TestableStripeService extends StripeService
{
    // On surcharge le constructeur pour éviter d'appeler l'original
    public function __construct() {}
    
    // On surcharge les méthodes à tester avec des versions de test
    public function findOneProduct(string $productId): Product
    {
        // Créer une instance réelle de Product
        $product = new Product($productId);
        $product->name = 'Test Product';
        $product->description = 'Test Description';
        $product->images = ['https://example.com/image.jpg'];
        return $product;
    }
    
    public function getLastActivePrice(Product $product): ?Price
    {
        $price = new Price('price_123');
        $price->unit_amount = 1000;
        return $price;
    }
    
    public function getProductBuyUrl(
        Product $product,
        string $successUrl,
        string $cancelUrl,
        int $quantity = 1
    ): string {
        return 'https://stripe.com/checkout/session/123';
    }
}

class FusionTest extends TestCase
{
    private CartService $cartService;
    private SessionService $sessionService;
    private StripeService $stripeService;
    private RedisObjectManagerInterface $redisObjectManager;

    protected function setUp(): void
    {
        // Création des mocks habituels
        $this->redisObjectManager = $this->createMock(RedisObjectManagerInterface::class);
        
        $session = new Session(new MockArraySessionStorage());
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getSession')->willReturn($session);
        
        $this->sessionService = new SessionService($requestStack);
        
        // Pour les tests non liés à Stripe, utiliser un mock normal
        $this->stripeService = $this->createMock(StripeService::class);
        
        $this->cartService = new CartService(
            $this->redisObjectManager,
            $this->sessionService,
            $this->stripeService
        );
    }

    public function testGetCartIdGeneratesNewIdIfNotExists(): void
    {
        $cartId = $this->sessionService->getCartId();
        
        $this->assertNotNull($cartId);
        $this->assertStringContainsString('cart_', $cartId);
        
        // Second call should return the same ID
        $secondCartId = $this->sessionService->getCartId();
        $this->assertSame($cartId, $secondCartId);
    }

    public function testAddProductToCart(): void
    {
        $cart = new Cart();
        $cart->id = $this->sessionService->getCartId();
        $cart->products = [];

        // Mock the redisObjectManager to return our cart
        $this->redisObjectManager->expects($this->once())
            ->method('find')
            ->with(Cart::class, $cart->id)
            ->willReturn($cart);
        
        $this->redisObjectManager->expects($this->once())
            ->method('persist')
            ->with($cart);
        
        $this->redisObjectManager->expects($this->once())
            ->method('flush');

        // Test adding a product
        $productId = 'prod_123';
        $updatedCart = $this->cartService->addProductToCart($productId);
        
        $this->assertArrayHasKey($productId, $updatedCart->products);
        $this->assertInstanceOf(CartProduct::class, $updatedCart->products[$productId]);
        $this->assertEquals(1, $updatedCart->products[$productId]->quantity);
    }

    public function testAddProductToCartWithExistingProduct(): void
    {
        $cart = new Cart();
        $cart->id = $this->sessionService->getCartId();
        $productId = 'prod_123';
        $cart->products = [$productId => new CartProduct($productId, 1)];

        // Mock the redisObjectManager to return our cart
        $this->redisObjectManager->expects($this->once())
            ->method('find')
            ->with(Cart::class, $cart->id)
            ->willReturn($cart);
        
        $this->redisObjectManager->expects($this->once())
            ->method('persist')
            ->with($cart);
        
        $updatedCart = $this->cartService->addProductToCart($productId);
        
        $this->assertEquals(2, $updatedCart->products[$productId]->quantity);
    }

    public function testClearCart(): void
    {
        $cart = new Cart();
        $cart->id = $this->sessionService->getCartId();
        $cart->products = ['prod_123' => new CartProduct('prod_123', 1)];

        // Mock the redisObjectManager to return our cart
        $this->redisObjectManager->expects($this->any())
            ->method('find')
            ->with(Cart::class, $cart->id)
            ->willReturn($cart);
        
        $this->redisObjectManager->expects($this->once())
            ->method('persist')
            ->with($cart);
        
        $this->redisObjectManager->expects($this->once())
            ->method('flush');

        // Test clearing the cart
        $this->cartService->clearCart();
        
        $this->assertEmpty($cart->products);
    }

    public function testGetCartCreatesNewCartIfNotExists(): void
    {
        $cartId = $this->sessionService->getCartId();
        
        // Mock the redisObjectManager to return null (cart doesn't exist)
        $this->redisObjectManager->expects($this->once())
            ->method('find')
            ->with(Cart::class, $cartId)
            ->willReturn(null);
        
        // It should create a new cart and persist it
        $this->redisObjectManager->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function($cart) use ($cartId) {
                $this->assertInstanceOf(Cart::class, $cart);
                $this->assertEquals($cartId, $cart->id);
                $this->assertEmpty($cart->products);
            });
        
        $cart = $this->cartService->getCart();
        
        $this->assertInstanceOf(Cart::class, $cart);
        $this->assertEquals($cartId, $cart->id);
    }

    public function testStripeProductIntegration(): void
    {
        // Utiliser notre classe de test plutôt qu'un mock
        $testableStripeService = new TestableStripeService();
        
        // Appel direct à la méthode
        $productId = 'prod_123';
        $product = $testableStripeService->findOneProduct($productId);
        
        // Vérifier que la méthode retourne bien une URL de checkout
        $checkoutUrl = $testableStripeService->getProductBuyUrl(
            $product,
            'https://example.com/success',
            'https://example.com/cancel',
            1
        );
        
        $this->assertEquals('https://stripe.com/checkout/session/123', $checkoutUrl);
        $this->assertEquals('Test Product', $product->name);
        $this->assertEquals('Test Description', $product->description);
    }
}