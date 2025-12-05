<?php

declare(strict_types=1);

namespace YakimaFinds\Presentation\Http\Controllers;

use YakimaFinds\Application\Services\ClaimService;
use YakimaFinds\Application\Services\ClaimAuthService;
use YakimaFinds\Infrastructure\Container\ContainerInterface;
use YakimaFinds\Infrastructure\Config\ConfigInterface;
use Exception;

/**
 * Claims controller for public estate sale functionality
 */
class ClaimsController extends BaseController
{
    private ClaimService $claimService;
    private ClaimAuthService $authService;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        $this->claimService = $container->resolve(ClaimService::class);
        $this->authService = $container->resolve(ClaimAuthService::class);
    }

    /**
     * List active sales
     */
    public function listSales(): void
    {
        try {
            $input = $this->getInput();
            $pagination = $this->getPaginationParams($input);

            $sales = $this->claimService->getActiveSales(
                $pagination['page'],
                $pagination['limit']
            );

            $formattedSales = array_map(function ($sale) {
                return [
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
                    'pickup_start' => $sale->getPickupStart()?->format('c'),
                    'pickup_end' => $sale->getPickupEnd()?->format('c'),
                    'status' => $sale->getStatus(),
                    'phase' => $this->getSalePhase($sale),
                    'featured' => $sale->isFeatured(),
                ];
            }, $sales->getItems());

            $this->successResponse([
                'sales' => $formattedSales,
                'pagination' => [
                    'total' => $sales->getTotal(),
                    'page' => $sales->getPage(),
                    'per_page' => $sales->getPerPage(),
                    'total_pages' => ceil($sales->getTotal() / $sales->getPerPage())
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load sales: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get single sale details
     */
    public function viewSale(): void
    {
        try {
            $input = $this->getInput();

            if (!isset($input['id'])) {
                $this->errorResponse('Sale ID is required');
                return;
            }

            $sale = $this->claimService->getSaleDetails((int)$input['id']);

            if (!$sale) {
                $this->errorResponse('Sale not found', 404);
                return;
            }

            $this->successResponse([
                'sale' => [
                    'id' => $sale->getId(),
                    'title' => $sale->getTitle(),
                    'description' => $sale->getDescription(),
                    'address' => $sale->getAddress(),
                    'city' => $sale->getCity(),
                    'state' => $sale->getState(),
                    'zip' => $sale->getZip(),
                    'latitude' => $sale->getLatitude(),
                    'longitude' => $sale->getLongitude(),
                    'preview_start' => $sale->getPreviewStart()?->format('c'),
                    'preview_end' => $sale->getPreviewEnd()?->format('c'),
                    'claim_start' => $sale->getClaimStart()?->format('c'),
                    'claim_end' => $sale->getClaimEnd()?->format('c'),
                    'pickup_start' => $sale->getPickupStart()?->format('c'),
                    'pickup_end' => $sale->getPickupEnd()?->format('c'),
                    'pickup_instructions' => $sale->getPickupInstructions(),
                    'status' => $sale->getStatus(),
                    'phase' => $this->getSalePhase($sale),
                    'featured' => $sale->isFeatured(),
                    'stats' => $sale->getStats(),
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load sale: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Access sale by code (QR code or direct link)
     */
    public function accessSale(): void
    {
        try {
            $input = $this->getInput();

            if (!isset($input['code'])) {
                $this->errorResponse('Access code is required');
                return;
            }

            $sale = $this->claimService->accessSaleByCode($input['code']);

            if (!$sale) {
                $this->errorResponse('Invalid or expired access code', 404);
                return;
            }

            $this->successResponse([
                'sale' => [
                    'id' => $sale->getId(),
                    'title' => $sale->getTitle(),
                    'description' => $sale->getDescription(),
                    'phase' => $this->getSalePhase($sale),
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to access sale: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get items for a sale
     */
    public function viewItems(): void
    {
        try {
            $input = $this->getInput();

            if (!isset($input['sale_id'])) {
                $this->errorResponse('Sale ID is required');
                return;
            }

            $items = $this->claimService->getSaleItemsWithOffers((int)$input['sale_id']);

            $formattedItems = array_map(function ($item) {
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
            }, $items);

            $this->successResponse([
                'items' => $formattedItems,
                'count' => count($formattedItems)
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load items: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Verify buyer (start authentication)
     */
    public function verifyBuyer(): void
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->errorResponse('POST method required', 405);
                return;
            }

            $input = $this->getInput();

            // Require either phone or email
            if (empty($input['phone']) && empty($input['email'])) {
                $this->errorResponse('Phone or email is required');
                return;
            }

            $result = $this->authService->initiateVerification(
                $input['phone'] ?? null,
                $input['email'] ?? null,
                $input['name'] ?? null
            );

            $this->successResponse([
                'buyer_id' => $result['buyer_id'],
                'verification_method' => $result['method'],
                'message' => 'Verification code sent'
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Verification failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Confirm verification code
     */
    public function confirmVerification(): void
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->errorResponse('POST method required', 405);
                return;
            }

            $input = $this->getInput();

            $missing = $this->validateRequired($input, ['buyer_id', 'code']);
            if (!empty($missing)) {
                $this->errorResponse('Missing required fields: ' . implode(', ', $missing));
                return;
            }

            $result = $this->authService->confirmVerification(
                (int)$input['buyer_id'],
                $input['code']
            );

            if (!$result['success']) {
                $this->errorResponse($result['message'] ?? 'Invalid verification code');
                return;
            }

            $this->successResponse([
                'verified' => true,
                'auth_token' => $result['token'],
                'buyer' => $result['buyer']
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Verification failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Submit an offer
     */
    public function submitOffer(): void
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->errorResponse('POST method required', 405);
                return;
            }

            $input = $this->getInput();

            $missing = $this->validateRequired($input, ['item_id', 'buyer_id', 'amount']);
            if (!empty($missing)) {
                $this->errorResponse('Missing required fields: ' . implode(', ', $missing));
                return;
            }

            // Verify buyer is authenticated
            $authToken = $input['auth_token'] ?? null;
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

            $this->successResponse([
                'offer_id' => $offer->getId(),
                'amount' => $offer->getAmount(),
                'status' => $offer->getStatus(),
                'message' => 'Offer submitted successfully'
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to submit offer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get buyer's offers
     */
    public function getBuyerOffers(): void
    {
        try {
            $input = $this->getInput();

            $missing = $this->validateRequired($input, ['buyer_id', 'auth_token']);
            if (!empty($missing)) {
                $this->errorResponse('Authentication required', 401);
                return;
            }

            if (!$this->authService->validateToken($input['auth_token'], (int)$input['buyer_id'])) {
                $this->errorResponse('Invalid authentication', 401);
                return;
            }

            $offers = $this->claimService->getBuyerOffers((int)$input['buyer_id']);

            $formattedOffers = array_map(function ($offer) {
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
            }, $offers);

            $this->successResponse([
                'offers' => $formattedOffers,
                'count' => count($formattedOffers)
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load offers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get sales for map display
     */
    public function getSalesForMap(): void
    {
        try {
            $input = $this->getInput();

            $latitude = isset($input['latitude']) ? (float)$input['latitude'] : null;
            $longitude = isset($input['longitude']) ? (float)$input['longitude'] : null;
            $radius = (float)($input['radius'] ?? 25);

            if ($latitude && $longitude) {
                $sales = $this->claimService->getSalesNearLocation($latitude, $longitude, $radius);
            } else {
                $result = $this->claimService->getActiveSales(1, 100);
                $sales = $result->getItems();
            }

            $mapSales = array_map(function ($sale) {
                return [
                    'id' => $sale->getId(),
                    'title' => $sale->getTitle(),
                    'address' => $sale->getAddress(),
                    'latitude' => $sale->getLatitude(),
                    'longitude' => $sale->getLongitude(),
                    'phase' => $this->getSalePhase($sale),
                    'preview_start' => $sale->getPreviewStart()?->format('c'),
                    'claim_end' => $sale->getClaimEnd()?->format('c'),
                ];
            }, $sales);

            $this->successResponse([
                'sales' => $mapSales,
                'count' => count($mapSales)
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load map sales: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Determine the current phase of a sale
     */
    private function getSalePhase($sale): string
    {
        $now = new \DateTime();

        if ($sale->getPreviewStart() && $sale->getPreviewEnd()) {
            if ($now >= $sale->getPreviewStart() && $now <= $sale->getPreviewEnd()) {
                return 'preview';
            }
        }

        if ($sale->getClaimStart() && $sale->getClaimEnd()) {
            if ($now >= $sale->getClaimStart() && $now <= $sale->getClaimEnd()) {
                return 'claim';
            }
        }

        if ($sale->getPickupStart() && $sale->getPickupEnd()) {
            if ($now >= $sale->getPickupStart() && $now <= $sale->getPickupEnd()) {
                return 'pickup';
            }
        }

        if ($sale->getPreviewStart() && $now < $sale->getPreviewStart()) {
            return 'upcoming';
        }

        if ($sale->getPickupEnd() && $now > $sale->getPickupEnd()) {
            return 'completed';
        }

        return 'unknown';
    }
}
