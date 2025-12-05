<?php

declare(strict_types=1);

namespace YakimaFinds\Infrastructure\Repositories\Claims;

use YakimaFinds\Domain\Claims\Buyer;
use YakimaFinds\Domain\Claims\BuyerRepositoryInterface;
use YakimaFinds\Infrastructure\Database\AbstractRepository;
use YakimaFinds\Infrastructure\Database\ConnectionInterface;

class BuyerRepository extends AbstractRepository implements BuyerRepositoryInterface
{
    protected function getTableName(): string
    {
        return 'yfc_buyers';
    }

    protected function getEntityClass(): string
    {
        return Buyer::class;
    }

    public function findById(int $id): ?Buyer
    {
        $result = parent::findById($id);
        return $result instanceof Buyer ? $result : null;
    }

    public function findByEmail(string $email): ?Buyer
    {
        $result = $this->findOneBy(['email' => $email]);
        return $result instanceof Buyer ? $result : null;
    }

    public function findByPhone(string $phone): ?Buyer
    {
        // Normalize phone number for lookup
        $normalizedPhone = preg_replace('/[^0-9]/', '', $phone);

        $sql = "SELECT * FROM {$this->getTableName()}
                WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone, '-', ''), '(', ''), ')', ''), ' ', '') = :phone
                LIMIT 1";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['phone' => $normalizedPhone]);

        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$data) {
            return null;
        }

        return Buyer::fromArray($data);
    }

    public function findByAuthToken(string $token): ?Buyer
    {
        $sql = "SELECT * FROM {$this->getTableName()}
                WHERE verification_code = :token AND verified = 1
                LIMIT 1";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['token' => $token]);

        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$data) {
            return null;
        }

        return Buyer::fromArray($data);
    }

    public function save(Buyer $buyer): Buyer
    {
        $result = parent::save($buyer);
        return $result instanceof Buyer ? $result : $buyer;
    }

    public function delete(int $id): void
    {
        $sql = "DELETE FROM {$this->getTableName()} WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['id' => $id]);
    }

    public function findExpiredTokens(int $hoursOld = 24): array
    {
        $sql = "SELECT * FROM {$this->getTableName()}
                WHERE verified = 0
                AND created_at < DATE_SUB(NOW(), INTERVAL :hours HOUR)";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':hours', $hoursOld, \PDO::PARAM_INT);
        $stmt->execute();

        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Buyer::fromArray($data);
        }

        return $results;
    }

    public function cleanupExpired(int $daysOld = 30): int
    {
        // Delete buyers who never verified and haven't been active in X days
        $sql = "DELETE FROM {$this->getTableName()}
                WHERE verified = 0
                AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
                AND id NOT IN (SELECT DISTINCT buyer_id FROM yfc_offers)";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':days', $daysOld, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function countByAuthMethod(string $method): int
    {
        $field = $method === 'phone' ? 'phone' : 'email';

        $sql = "SELECT COUNT(*) FROM {$this->getTableName()}
                WHERE $field IS NOT NULL AND $field != ''";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }
}
