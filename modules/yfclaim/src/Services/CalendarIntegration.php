<?php
/**
 * Calendar Integration Service
 * Syncs estate sales with the main YFEvents calendar
 */

namespace YFEvents\Modules\YFClaim\Services;

use PDO;

class CalendarIntegration
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Sync a sale to the events calendar
     * Creates or updates an event based on the sale
     */
    public function syncSaleToCalendar(int $saleId): ?int
    {
        // Get sale with seller info
        $stmt = $this->pdo->prepare("
            SELECT s.*, sel.company_name, sel.phone, sel.email
            FROM yfc_sales s
            JOIN yfc_sellers sel ON s.seller_id = sel.id
            WHERE s.id = ?
        ");
        $stmt->execute([$saleId]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sale) {
            return null;
        }

        // Only sync active sales
        if ($sale['status'] !== 'active') {
            // If sale has an event but is no longer active, mark event as rejected
            if ($sale['event_id']) {
                $this->updateEventStatus($sale['event_id'], 'rejected');
            }
            return $sale['event_id'];
        }

        // Build event data
        $eventData = [
            'title' => "Estate Sale: " . $sale['title'],
            'description' => $this->buildDescription($sale),
            'start_datetime' => $sale['claim_start'] ?? $sale['start_date'] . ' 09:00:00',
            'end_datetime' => $sale['claim_end'] ?? $sale['end_date'] . ' 17:00:00',
            'location' => $sale['company_name'],
            'address' => $this->buildAddress($sale),
            'latitude' => $sale['latitude'],
            'longitude' => $sale['longitude'],
            'contact_info' => json_encode([
                'phone' => $sale['phone'],
                'email' => $sale['email']
            ]),
            'external_url' => '/claims/sale.php?id=' . $sale['id'],
            'status' => 'approved',
            'featured' => $sale['featured'] ? 1 : 0
        ];

        if ($sale['event_id']) {
            // Update existing event
            $this->updateEvent($sale['event_id'], $eventData);
            return $sale['event_id'];
        } else {
            // Create new event
            $eventId = $this->createEvent($eventData);

            // Link event to sale
            $stmt = $this->pdo->prepare("UPDATE yfc_sales SET event_id = ? WHERE id = ?");
            $stmt->execute([$eventId, $saleId]);

            return $eventId;
        }
    }

    /**
     * Create a new event
     */
    private function createEvent(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO events (
                title, description, start_datetime, end_datetime,
                location, address, latitude, longitude,
                contact_info, external_url, status, featured,
                created_at
            ) VALUES (
                :title, :description, :start_datetime, :end_datetime,
                :location, :address, :latitude, :longitude,
                :contact_info, :external_url, :status, :featured,
                NOW()
            )
        ");

        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update an existing event
     */
    private function updateEvent(int $eventId, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE events SET
                title = :title,
                description = :description,
                start_datetime = :start_datetime,
                end_datetime = :end_datetime,
                location = :location,
                address = :address,
                latitude = :latitude,
                longitude = :longitude,
                contact_info = :contact_info,
                external_url = :external_url,
                status = :status,
                featured = :featured,
                updated_at = NOW()
            WHERE id = :id
        ");

        $data['id'] = $eventId;
        $stmt->execute($data);
    }

    /**
     * Update event status only
     */
    private function updateEventStatus(int $eventId, string $status): void
    {
        $stmt = $this->pdo->prepare("UPDATE events SET status = ? WHERE id = ?");
        $stmt->execute([$status, $eventId]);
    }

    /**
     * Build event description from sale data
     */
    private function buildDescription(array $sale): string
    {
        $desc = $sale['description'] ?? '';
        $desc .= "\n\n";
        $desc .= "🏷️ Estate Sale by " . $sale['company_name'] . "\n\n";

        if ($sale['claim_start'] && $sale['claim_end']) {
            $desc .= "📅 Claim Period: " . date('M j, g:i A', strtotime($sale['claim_start']));
            $desc .= " - " . date('M j, g:i A', strtotime($sale['claim_end'])) . "\n";
        }

        if ($sale['pickup_start'] && $sale['pickup_end']) {
            $desc .= "🚚 Pickup: " . date('M j, g:i A', strtotime($sale['pickup_start']));
            $desc .= " - " . date('M j, g:i A', strtotime($sale['pickup_end'])) . "\n";
        }

        $desc .= "\n🔗 Browse items and make offers at: /claims/sale.php?id=" . $sale['id'];

        return trim($desc);
    }

    /**
     * Build full address string
     */
    private function buildAddress(array $sale): string
    {
        $parts = array_filter([
            $sale['address'],
            $sale['city'],
            $sale['state'],
            $sale['zip']
        ]);
        return implode(', ', $parts);
    }

    /**
     * Sync all active sales to calendar
     */
    public function syncAllActiveSales(): array
    {
        $stmt = $this->pdo->query("SELECT id FROM yfc_sales WHERE status = 'active'");
        $sales = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $results = [];
        foreach ($sales as $saleId) {
            $eventId = $this->syncSaleToCalendar($saleId);
            $results[$saleId] = $eventId;
        }

        return $results;
    }

    /**
     * Remove event when sale is deleted
     */
    public function removeSaleEvent(int $saleId): void
    {
        $stmt = $this->pdo->prepare("SELECT event_id FROM yfc_sales WHERE id = ?");
        $stmt->execute([$saleId]);
        $eventId = $stmt->fetchColumn();

        if ($eventId) {
            $stmt = $this->pdo->prepare("DELETE FROM events WHERE id = ?");
            $stmt->execute([$eventId]);
        }
    }
}
