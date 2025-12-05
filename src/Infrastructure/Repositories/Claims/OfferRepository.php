<?php

declare(strict_types=1);

namespace YakimaFinds\Infrastructure\Repositories\Claims;

use YakimaFinds\Domain\Claims\Offer;
use YakimaFinds\Domain\Claims\OfferRepositoryInterface;
use YakimaFinds\Infrastructure\Database\AbstractRepository;
use YakimaFinds\Infrastructure\Database\ConnectionInterface;

class OfferRepository extends AbstractRepository implements OfferRepositoryInterface
{
    protected function getTableName(): string
    {
        return 'yfc_offers';
    }

    protected function getEntityClass(): string
    {
        return Offer::class;
    }

    public function findById(int $id): ?Offer
    {
        $result = parent::findById($id);
        return $result instanceof Offer ? $result : null;
    }

    public function findByItemId(int $itemId, ?string $status = null): array
    {
        $criteria = ['item_id' => $itemId];
        if ($status !== null) {
            $criteria['status'] = $status;
        }
        return $this->findBy($criteria, ['amount' => 'DESC', 'created_at' => 'ASC']);
    }

    public function findByBuyerId(int $buyerId, ?string $status = null): array
    {
        $criteria = ['buyer_id' => $buyerId];
        if ($status !== null) {
            $criteria['status'] = $status;
        }
        return $this->findBy($criteria, ['created_at' => 'DESC']);
    }

    public function findBySaleId(int $saleId, ?string $status = null): array
    {
        $sql = "SELECT o.* FROM {$this->getTableName()} o
                JOIN yfc_items i ON o.item_id = i.id
                WHERE i.sale_id = :sale_id";
        $params = ['sale_id' => $saleId];

        if ($status !== null) {
            $sql .= " AND o.status = :status";
            $params['status'] = $status;
        }

        $sql .= " ORDER BY o.created_at DESC";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Offer::fromArray($data);
        }

        return $results;
    }

    public function findWinningOffersBySale(int $saleId): array
    {
        $sql = "SELECT o.* FROM {$this->getTableName()} o
                JOIN yfc_items i ON o.item_id = i.id
                WHERE i.sale_id = :sale_id AND o.status = 'accepted'
                ORDER BY o.updated_at DESC";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['sale_id' => $saleId]);

        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Offer::fromArray($data);
        }

        return $results;
    }

    public function findTopOffersForItem(int $itemId, int $limit = 5): array
    {
        $sql = "SELECT * FROM {$this->getTableName()}
                WHERE item_id = :item_id AND status = 'pending'
                ORDER BY amount DESC
                LIMIT :limit";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':item_id', $itemId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Offer::fromArray($data);
        }

        return $results;
    }

    public function save(Offer $offer): Offer
    {
        $result = parent::save($offer);
        return $result instanceof Offer ? $result : $offer;
    }

    public function delete(int $id): void
    {
        $sql = "DELETE FROM {$this->getTableName()} WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['id' => $id]);
    }

    public function deleteByItem(int $itemId): void
    {
        $sql = "DELETE FROM {$this->getTableName()} WHERE item_id = :item_id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['item_id' => $itemId]);
    }

    public function countByItem(int $itemId, ?string $status = null): int
    {
        $criteria = ['item_id' => $itemId];
        if ($status !== null) {
            $criteria['status'] = $status;
        }
        return $this->count($criteria);
    }

    public function countByBuyer(int $buyerId, ?string $status = null): int
    {
        $criteria = ['buyer_id' => $buyerId];
        if ($status !== null) {
            $criteria['status'] = $status;
        }
        return $this->count($criteria);
    }

    public function getSaleStatistics(int $saleId): array
    {
        $sql = "SELECT
                    COUNT(o.id) as total_offers,
                    COUNT(DISTINCT o.buyer_id) as unique_buyers,
                    SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) as pending_offers,
                    SUM(CASE WHEN o.status = 'accepted' THEN 1 ELSE 0 END) as accepted_offers,
                    SUM(CASE WHEN o.status = 'rejected' THEN 1 ELSE 0 END) as rejected_offers,
                    COALESCE(SUM(CASE WHEN o.status = 'accepted' THEN o.amount ELSE 0 END), 0) as total_sales,
                    COALESCE(AVG(o.amount), 0) as average_offer,
                    COALESCE(MAX(o.amount), 0) as highest_offer
                FROM {$this->getTableName()} o
                JOIN yfc_items i ON o.item_id = i.id
                WHERE i.sale_id = :sale_id";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['sale_id' => $saleId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    public function buyerHasOfferOnItem(int $buyerId, int $itemId): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->getTableName()}
                WHERE buyer_id = :buyer_id AND item_id = :item_id
                AND status NOT IN ('withdrawn', 'rejected')";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['buyer_id' => $buyerId, 'item_id' => $itemId]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function getHighestOfferForItem(int $itemId): ?Offer
    {
        $sql = "SELECT * FROM {$this->getTableName()}
                WHERE item_id = :item_id AND status = 'pending'
                ORDER BY amount DESC
                LIMIT 1";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['item_id' => $itemId]);

        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$data) {
            return null;
        }

        return Offer::fromArray($data);
    }
}
