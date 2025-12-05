<?php

declare(strict_types=1);

namespace YakimaFinds\Infrastructure\Repositories\Claims;

use YakimaFinds\Domain\Claims\Sale;
use YakimaFinds\Domain\Claims\SaleRepositoryInterface;
use YakimaFinds\Infrastructure\Database\AbstractRepository;
use YakimaFinds\Infrastructure\Database\ConnectionInterface;

class SaleRepository extends AbstractRepository implements SaleRepositoryInterface
{
    protected function getTableName(): string
    {
        return 'yfc_sales';
    }

    protected function getEntityClass(): string
    {
        return Sale::class;
    }

    public function findById(int $id): ?Sale
    {
        $result = parent::findById($id);
        return $result instanceof Sale ? $result : null;
    }

    public function findByAccessCode(string $accessCode): ?Sale
    {
        $result = $this->findOneBy(['access_code' => $accessCode]);
        return $result instanceof Sale ? $result : null;
    }

    public function findBySellerId(int $sellerId, ?string $status = null): array
    {
        $criteria = ['seller_id' => $sellerId];
        if ($status !== null) {
            $criteria['status'] = $status;
        }
        return $this->findBy($criteria, ['created_at' => 'DESC']);
    }

    public function findActive(int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->getTableName()}
                WHERE status = 'active'
                ORDER BY preview_start DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Sale::fromArray($data);
        }

        return $results;
    }

    public function findByPhase(string $phase, int $limit = 20, int $offset = 0): array
    {
        $now = date('Y-m-d H:i:s');

        switch ($phase) {
            case 'preview':
                $sql = "SELECT * FROM {$this->getTableName()}
                        WHERE status = 'active'
                        AND preview_start <= :now AND preview_end >= :now
                        ORDER BY preview_start ASC
                        LIMIT :limit OFFSET :offset";
                break;
            case 'claim':
                $sql = "SELECT * FROM {$this->getTableName()}
                        WHERE status = 'active'
                        AND claim_start <= :now AND claim_end >= :now
                        ORDER BY claim_end ASC
                        LIMIT :limit OFFSET :offset";
                break;
            case 'pickup':
                $sql = "SELECT * FROM {$this->getTableName()}
                        WHERE status = 'active'
                        AND pickup_start <= :now AND pickup_end >= :now
                        ORDER BY pickup_end ASC
                        LIMIT :limit OFFSET :offset";
                break;
            default:
                return [];
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':now', $now);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Sale::fromArray($data);
        }

        return $results;
    }

    public function findNearLocation(float $latitude, float $longitude, float $radiusMiles = 25): array
    {
        $sql = "SELECT *,
                (3959 * acos(cos(radians(:lat)) * cos(radians(latitude)) *
                cos(radians(longitude) - radians(:lng)) +
                sin(radians(:lat2)) * sin(radians(latitude)))) AS distance
                FROM {$this->getTableName()}
                WHERE latitude IS NOT NULL AND longitude IS NOT NULL
                AND status = 'active'
                HAVING distance <= :radius
                ORDER BY distance ASC";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'lat' => $latitude,
            'lng' => $longitude,
            'lat2' => $latitude,
            'radius' => $radiusMiles
        ]);

        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            unset($data['distance']);
            $results[] = Sale::fromArray($data);
        }

        return $results;
    }

    public function findUpcoming(int $days = 7): array
    {
        $sql = "SELECT * FROM {$this->getTableName()}
                WHERE status IN ('active', 'draft')
                AND preview_start > NOW()
                AND preview_start <= DATE_ADD(NOW(), INTERVAL :days DAY)
                ORDER BY preview_start ASC";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
        $stmt->execute();

        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Sale::fromArray($data);
        }

        return $results;
    }

    public function save(Sale $sale): Sale
    {
        $result = parent::save($sale);
        return $result instanceof Sale ? $result : $sale;
    }

    public function delete(int $id): void
    {
        $sql = "DELETE FROM {$this->getTableName()} WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['id' => $id]);
    }

    public function countBySeller(int $sellerId, ?string $status = null): int
    {
        $criteria = ['seller_id' => $sellerId];
        if ($status !== null) {
            $criteria['status'] = $status;
        }
        return $this->count($criteria);
    }

    public function getStatistics(int $saleId): array
    {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM yfc_items WHERE sale_id = :sale_id) as total_items,
                    (SELECT COUNT(*) FROM yfc_items WHERE sale_id = :sale_id2 AND status = 'sold') as sold_items,
                    (SELECT COUNT(*) FROM yfc_items WHERE sale_id = :sale_id3 AND status = 'claimed') as claimed_items,
                    (SELECT COUNT(*) FROM yfc_offers o
                     JOIN yfc_items i ON o.item_id = i.id
                     WHERE i.sale_id = :sale_id4) as total_offers,
                    (SELECT COALESCE(SUM(o.amount), 0) FROM yfc_offers o
                     JOIN yfc_items i ON o.item_id = i.id
                     WHERE i.sale_id = :sale_id5 AND o.status = 'accepted') as total_sales_amount";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'sale_id' => $saleId,
            'sale_id2' => $saleId,
            'sale_id3' => $saleId,
            'sale_id4' => $saleId,
            'sale_id5' => $saleId
        ]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    public function updateStatistics(int $saleId): void
    {
        // Statistics are calculated on-the-fly, no update needed
        // This method exists for cache invalidation if we add caching later
    }

    public function generateUniqueAccessCode(): string
    {
        do {
            $code = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
            $existing = $this->findByAccessCode($code);
        } while ($existing !== null);

        return $code;
    }
}
