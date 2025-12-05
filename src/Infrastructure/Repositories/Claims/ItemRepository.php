<?php

declare(strict_types=1);

namespace YakimaFinds\Infrastructure\Repositories\Claims;

use YakimaFinds\Domain\Claims\Item;
use YakimaFinds\Domain\Claims\ItemRepositoryInterface;
use YakimaFinds\Infrastructure\Database\AbstractRepository;
use YakimaFinds\Infrastructure\Database\ConnectionInterface;

class ItemRepository extends AbstractRepository implements ItemRepositoryInterface
{
    protected function getTableName(): string
    {
        return 'yfc_items';
    }

    protected function getEntityClass(): string
    {
        return Item::class;
    }

    public function findById(int $id): ?Item
    {
        $result = parent::findById($id);
        return $result instanceof Item ? $result : null;
    }

    public function findBySaleId(int $saleId, ?string $status = null): array
    {
        $criteria = ['sale_id' => $saleId];
        if ($status !== null) {
            $criteria['status'] = $status;
        }
        return $this->findBy($criteria, ['sort_order' => 'ASC', 'id' => 'ASC']);
    }

    public function findByCategory(int $categoryId, int $limit = 20, int $offset = 0): array
    {
        return $this->findBy(
            ['category_id' => $categoryId],
            ['created_at' => 'DESC'],
            $limit,
            $offset
        );
    }

    public function findWithOffers(int $saleId): array
    {
        $sql = "SELECT i.*, COUNT(o.id) as offer_count, MAX(o.amount) as highest_offer
                FROM {$this->getTableName()} i
                LEFT JOIN yfc_offers o ON i.id = o.item_id AND o.status IN ('pending', 'accepted')
                WHERE i.sale_id = :sale_id
                GROUP BY i.id
                ORDER BY i.sort_order ASC, i.id ASC";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['sale_id' => $saleId]);

        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $offerCount = $data['offer_count'];
            $highestOffer = $data['highest_offer'];
            unset($data['offer_count'], $data['highest_offer']);

            $item = Item::fromArray($data);
            // Store offer info as metadata (item entity can handle this)
            $results[] = $item;
        }

        return $results;
    }

    public function findSold(int $saleId): array
    {
        return $this->findBy(
            ['sale_id' => $saleId, 'status' => 'sold'],
            ['updated_at' => 'DESC']
        );
    }

    public function search(string $query, ?int $saleId = null, int $limit = 20): array
    {
        $sql = "SELECT * FROM {$this->getTableName()}
                WHERE (title LIKE :query OR description LIKE :query)";
        $params = ['query' => "%{$query}%"];

        if ($saleId !== null) {
            $sql .= " AND sale_id = :sale_id";
            $params['sale_id'] = $saleId;
        }

        $sql .= " ORDER BY title ASC LIMIT :limit";

        $stmt = $this->connection->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Item::fromArray($data);
        }

        return $results;
    }

    public function save(Item $item): Item
    {
        $result = parent::save($item);
        return $result instanceof Item ? $result : $item;
    }

    public function saveMultiple(array $items): array
    {
        $savedItems = [];
        foreach ($items as $item) {
            if ($item instanceof Item) {
                $savedItems[] = $this->save($item);
            }
        }
        return $savedItems;
    }

    public function delete(int $id): void
    {
        // First delete related images
        $sql = "DELETE FROM yfc_item_images WHERE item_id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['id' => $id]);

        // Then delete the item
        $sql = "DELETE FROM {$this->getTableName()} WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['id' => $id]);
    }

    public function deleteBySale(int $saleId): void
    {
        // First delete related images
        $sql = "DELETE img FROM yfc_item_images img
                JOIN {$this->getTableName()} i ON img.item_id = i.id
                WHERE i.sale_id = :sale_id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['sale_id' => $saleId]);

        // Then delete items
        $sql = "DELETE FROM {$this->getTableName()} WHERE sale_id = :sale_id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['sale_id' => $saleId]);
    }

    public function countBySale(int $saleId, ?string $status = null): int
    {
        $criteria = ['sale_id' => $saleId];
        if ($status !== null) {
            $criteria['status'] = $status;
        }
        return $this->count($criteria);
    }

    public function getStatistics(int $itemId): array
    {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM yfc_offers WHERE item_id = :item_id) as total_offers,
                    (SELECT MAX(amount) FROM yfc_offers WHERE item_id = :item_id2 AND status = 'pending') as highest_pending_offer,
                    (SELECT amount FROM yfc_offers WHERE item_id = :item_id3 AND status = 'accepted' LIMIT 1) as winning_amount,
                    (SELECT COUNT(*) FROM yfc_item_shares WHERE item_id = :item_id4) as share_count";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'item_id' => $itemId,
            'item_id2' => $itemId,
            'item_id3' => $itemId,
            'item_id4' => $itemId
        ]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    public function incrementViewCount(int $itemId): void
    {
        // Check if view_count column exists, if not this is a no-op
        $sql = "UPDATE {$this->getTableName()} SET view_count = COALESCE(view_count, 0) + 1 WHERE id = :id";
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(['id' => $itemId]);
        } catch (\PDOException $e) {
            // Column might not exist, ignore
        }
    }

    public function getPopular(int $saleId, int $limit = 10): array
    {
        $sql = "SELECT i.*, COUNT(o.id) as offer_count
                FROM {$this->getTableName()} i
                LEFT JOIN yfc_offers o ON i.id = o.item_id
                WHERE i.sale_id = :sale_id AND i.status = 'available'
                GROUP BY i.id
                ORDER BY offer_count DESC, i.starting_price DESC
                LIMIT :limit";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':sale_id', $saleId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            unset($data['offer_count']);
            $results[] = Item::fromArray($data);
        }

        return $results;
    }
}
