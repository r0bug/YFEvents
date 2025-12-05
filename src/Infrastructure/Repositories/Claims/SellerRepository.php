<?php

declare(strict_types=1);

namespace YakimaFinds\Infrastructure\Repositories\Claims;

use YakimaFinds\Domain\Claims\Seller;
use YakimaFinds\Domain\Claims\SellerRepositoryInterface;
use YakimaFinds\Infrastructure\Database\AbstractRepository;
use YakimaFinds\Infrastructure\Database\ConnectionInterface;

class SellerRepository extends AbstractRepository implements SellerRepositoryInterface
{
    protected function getTableName(): string
    {
        return 'yfc_sellers';
    }

    protected function getEntityClass(): string
    {
        return Seller::class;
    }

    public function findById(int $id): ?Seller
    {
        $result = parent::findById($id);
        return $result instanceof Seller ? $result : null;
    }

    public function findByUserId(int $userId): ?Seller
    {
        $result = $this->findOneBy(['user_id' => $userId]);
        return $result instanceof Seller ? $result : null;
    }

    public function findByEmail(string $email): ?Seller
    {
        $result = $this->findOneBy(['email' => $email]);
        return $result instanceof Seller ? $result : null;
    }

    public function findAll(?string $status = null): array
    {
        $criteria = [];
        if ($status !== null) {
            $criteria['status'] = $status;
        }
        return $this->findBy($criteria, ['company_name' => 'ASC']);
    }

    public function findVerified(): array
    {
        return $this->findBy(['verified' => 1, 'status' => 'active'], ['company_name' => 'ASC']);
    }

    public function save(Seller $seller): Seller
    {
        $result = parent::save($seller);
        return $result instanceof Seller ? $result : $seller;
    }

    public function delete(int $id): void
    {
        $sql = "DELETE FROM {$this->getTableName()} WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['id' => $id]);
    }

    public function countByStatus(string $status): int
    {
        return $this->count(['status' => $status]);
    }

    public function getStatistics(int $sellerId): array
    {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM yfc_sales WHERE seller_id = :seller_id) as total_sales,
                    (SELECT COUNT(*) FROM yfc_sales WHERE seller_id = :seller_id2 AND status = 'active') as active_sales,
                    (SELECT COUNT(*) FROM yfc_sales WHERE seller_id = :seller_id3 AND status = 'completed') as completed_sales,
                    (SELECT COUNT(*) FROM yfc_items i
                     JOIN yfc_sales s ON i.sale_id = s.id
                     WHERE s.seller_id = :seller_id4) as total_items,
                    (SELECT COUNT(*) FROM yfc_items i
                     JOIN yfc_sales s ON i.sale_id = s.id
                     WHERE s.seller_id = :seller_id5 AND i.status = 'sold') as sold_items,
                    (SELECT COALESCE(SUM(o.amount), 0) FROM yfc_offers o
                     JOIN yfc_items i ON o.item_id = i.id
                     JOIN yfc_sales s ON i.sale_id = s.id
                     WHERE s.seller_id = :seller_id6 AND o.status = 'accepted') as total_revenue";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'seller_id' => $sellerId,
            'seller_id2' => $sellerId,
            'seller_id3' => $sellerId,
            'seller_id4' => $sellerId,
            'seller_id5' => $sellerId,
            'seller_id6' => $sellerId
        ]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }
}
