<?php

declare(strict_types=1);

namespace YakimaFinds\Presentation\Api\Controllers;

use YakimaFinds\Application\Services\ClaimService;
use YakimaFinds\Application\Services\ClaimAuthService;
use YakimaFinds\Infrastructure\Container\ContainerInterface;
use YakimaFinds\Infrastructure\Config\ConfigInterface;
use Exception;

/**
 * Claims API controller for RESTful endpoints
 */
class ClaimsApiController
{
    private ContainerInterface $container;
    private ConfigInterface $config;
    private ClaimService $claimService;
    private ClaimAuthService $authService;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        $this->container = $container;
        $this->config = $config;
        $this->claimService = $container->resolve(ClaimService::class);
        $this->authService = $container->resolve(ClaimAuthService::class);
    }

    /**
     * GET /api/claims/sales - List active sales
     */
    public function getSales(): void
    {
        try {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));

            $sales = $this->claimService->getActiveSales($page, $limit);

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'sales' => array_map([$this, 'formatSale'], $sales->getItems()),
                    'meta' => [
                        'total' => $sales->getTotal(),
                        'page' => $sales->getPage(),
                        'per_page' => $sales->getPerPage(),
                        'total_pages' => ceil($sales->getTotal() / $sales->getPerPage())
                    ]
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load sales: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/claims/sales/{id} - Get single sale
     */
    public function getSale(int $id): void
    {
        try {
            $sale = $this->claimService->getSaleDetails($id);

            if (!$sale) {
                $this->errorResponse('Sale not found', 404);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'data' => $this->formatSale($sale, true)
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load sale: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/claims/sales/{id}/items - Get items for a sale
     */
    public function getSaleItems(int $saleId): void
    {
        try {
            $items = $this->claimService->getSaleItemsWithOffers($saleId);

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'items' => array_map([$this, 'formatItem'], $items),
                    'count' => count($items)
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load items: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/claims/verify - Start buyer verification
     */
    public function verifyBuyer(): void
    {
        try {
            $input = $this->getJsonInput();

            if (empty($input['phone']) && empty($input['email'])) {
                $this->errorResponse('Phone or email is required', 400);
                return;
            }

            $result = $this->authService->initiateVerification(
                $input['phone'] ?? null,
                $input['email'] ?? null,
                $input['name'] ?? null
            );

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'buyer_id' => $result['buyer_id'],
                    'verification_method' => $result['method']
                ],
                'message' => 'Verification code sent'
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Verification failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/claims/confirm - Confirm verification code
     */
    public function confirmVerification(): void
    {
        try {
            $input = $this->getJsonInput();

            if (empty($input['buyer_id']) || empty($input['code'])) {
                $this->errorResponse('Buyer ID and code are required', 400);
                return;
            }

            $result = $this->authService->confirmVerification(
                (int)$input['buyer_id'],
                $input['code']
            );

            if (!$result['success']) {
                $this->errorResponse($result['message'] ?? 'Invalid verification code', 400);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'verified' => true,
                    'auth_token' => $result['token'],
                    'buyer' => $result['buyer']
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Verification failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/claims/offers - Submit an offer
     */
    public function submitOffer(): void
    {
        try {
            $input = $this->getJsonInput();

            if (empty($input['item_id']) || empty($input['buyer_id']) || !isset($input['amount'])) {
                $this->errorResponse('Item ID, buyer ID, and amount are required', 400);
                return;
            }

            // Verify authentication
            $authToken = $input['auth_token'] ?? $this->getBearerToken();
            if (!$authToken || !$this->authService->validateToken($authToken, (int)$input['buyer_id'])) {
                $this->errorResponse('Authentication required', 401);
                return;
            }

            $offer = $this->claimService->submitOffer(
                (int)$input['item_id'],
                (int)$input['buyer_id'],
                (float)$input['amount'],
                $input['message'] ?? null
            );

            $this->jsonResponse([
                'success' => true,
                'data' => $this->formatOffer($offer),
                'message' => 'Offer submitted successfully'
            ], 201);

        } catch (Exception $e) {
            $this->errorResponse('Failed to submit offer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/claims/offers/mine - Get buyer's offers
     */
    public function getMyOffers(): void
    {
        try {
            $buyerId = $_GET['buyer_id'] ?? null;
            $authToken = $_GET['auth_token'] ?? $this->getBearerToken();

            if (!$buyerId || !$authToken) {
                $this->errorResponse('Authentication required', 401);
                return;
            }

            if (!$this->authService->validateToken($authToken, (int)$buyerId)) {
                $this->errorResponse('Invalid authentication', 401);
                return;
            }

            $offers = $this->claimService->getBuyerOffers((int)$buyerId);

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'offers' => array_map([$this, 'formatOffer'], $offers),
                    'count' => count($offers)
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load offers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/claims/sales/map - Get sales for map display
     */
    public function getSalesForMap(): void
    {
        try {
            $latitude = isset($_GET['latitude']) ? (float)$_GET['latitude'] : null;
            $longitude = isset($_GET['longitude']) ? (float)$_GET['longitude'] : null;
            $radius = (float)($_GET['radius'] ?? 25);

            if ($latitude && $longitude) {
                $sales = $this->claimService->getSalesNearLocation($latitude, $longitude, $radius);
            } else {
                $result = $this->claimService->getActiveSales(1, 100);
                $sales = $result->getItems();
            }

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'sales' => array_map(function ($sale) {
                        return [
                            'id' => $sale->getId(),
                            'title' => $sale->getTitle(),
                            'address' => $sale->getAddress(),
                            'latitude' => $sale->getLatitude(),
                            'longitude' => $sale->getLongitude(),
                            'phase' => $this->getSalePhase($sale),
                        ];
                    }, $sales),
                    'count' => count($sales)
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load map sales: ' . $e->getMessage(), 500);
        }
    }

    // Helper methods

    private function formatSale($sale, bool $detailed = false): array
    {
        $data = [
            'id' => $sale->getId(),
            'title' => $sale->getTitle(),
            'description' => $sale->getDescription(),
            'address' => $sale->getAddress(),
            'city' => $sale->getCity(),
            'state' => $sale->getState(),
            'latitude' => $sale->getLatitude(),
            'longitude' => $sale->getLongitude(),
            'preview_start' => $sale->getPreviewStart()?->format('c'),
            'preview_end' => $sale->getPreviewEnd()?->format('c'),
            'claim_start' => $sale->getClaimStart()?->format('c'),
            'claim_end' => $sale->getClaimEnd()?->format('c'),
            'phase' => $this->getSalePhase($sale),
            'status' => $sale->getStatus(),
            'featured' => $sale->isFeatured(),
        ];

        if ($detailed) {
            $data['zip'] = $sale->getZip();
            $data['pickup_start'] = $sale->getPickupStart()?->format('c');
            $data['pickup_end'] = $sale->getPickupEnd()?->format('c');
            $data['pickup_instructions'] = $sale->getPickupInstructions();
            $data['stats'] = $sale->getStats();
        }

        return $data;
    }

    private function formatItem($item): array
    {
        return [
            'id' => $item->getId(),
            'item_number' => $item->getItemNumber(),
            'title' => $item->getTitle(),
            'description' => $item->getDescription(),
            'category_id' => $item->getCategoryId(),
            'starting_price' => $item->getStartingPrice(),
            'current_high_offer' => $item->getCurrentHighOffer(),
            'status' => $item->getStatus(),
            'images' => $item->getImages(),
        ];
    }

    private function formatOffer($offer): array
    {
        return [
            'id' => $offer->getId(),
            'item_id' => $offer->getItemId(),
            'amount' => $offer->getAmount(),
            'status' => $offer->getStatus(),
            'message' => $offer->getMessage(),
            'seller_notes' => $offer->getSellerNotes(),
            'created_at' => $offer->getCreatedAt()?->format('c'),
            'updated_at' => $offer->getUpdatedAt()?->format('c'),
        ];
    }

    private function getSalePhase($sale): string
    {
        $now = new \DateTime();

        if ($sale->getPreviewStart() && $now < $sale->getPreviewStart()) {
            return 'upcoming';
        }
        if ($sale->getPreviewEnd() && $now <= $sale->getPreviewEnd()) {
            return 'preview';
        }
        if ($sale->getClaimEnd() && $now <= $sale->getClaimEnd()) {
            return 'claim';
        }
        if ($sale->getPickupEnd() && $now <= $sale->getPickupEnd()) {
            return 'pickup';
        }

        return 'completed';
    }

    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }

    private function getBearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function errorResponse(string $message, int $statusCode = 400): void
    {
        $this->jsonResponse([
            'success' => false,
            'error' => $message
        ], $statusCode);
    }
}
