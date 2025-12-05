<?php
/**
 * Notification Service
 * Handles notifications for buyers and sellers
 */

namespace YFEvents\Modules\YFClaim\Services;

use PDO;

class NotificationService
{
    private PDO $pdo;
    private ?string $siteUrl;
    private EmailService $emailService;
    private SmsService $smsService;

    public function __construct(PDO $pdo, ?string $siteUrl = null)
    {
        $this->pdo = $pdo;
        $this->siteUrl = $siteUrl ?? 'https://yfevents.yakimafinds.com';
        $this->emailService = new EmailService();
        $this->smsService = new SmsService();
    }

    /**
     * Notify seller about new offer
     */
    public function notifySellerOfOffer(int $offerId): void
    {
        $offer = $this->getOfferDetails($offerId);
        if (!$offer) return;

        $this->createNotification(
            'seller',
            $offer['seller_id'],
            'offer_received',
            "New offer: \${$offer['offer_amount']} on {$offer['item_title']}",
            "{$offer['buyer_name']} made an offer of \${$offer['offer_amount']} on \"{$offer['item_title']}\" in {$offer['sale_title']}.",
            "/claims/admin/offers.php?sale_id={$offer['sale_id']}"
        );

        // Send email if enabled
        if ($this->shouldSendEmail('seller', $offer['seller_id'], 'offers')) {
            $this->sendEmail(
                $offer['seller_email'],
                "New Offer Received - {$offer['item_title']}",
                $this->buildOfferReceivedEmail($offer)
            );
        }

        // Send SMS if seller has phone
        if (!empty($offer['seller_phone'])) {
            $this->smsService->sendOfferNotification(
                $offer['seller_phone'],
                $offer['item_title'],
                (float)$offer['offer_amount']
            );
        }
    }

    /**
     * Notify buyer when offer is accepted
     */
    public function notifyBuyerOfferAccepted(int $offerId): void
    {
        $offer = $this->getOfferDetails($offerId);
        if (!$offer) return;

        $this->createNotification(
            'buyer',
            $offer['buyer_id'],
            'offer_accepted',
            "Offer accepted! {$offer['item_title']}",
            "Your offer of \${$offer['offer_amount']} on \"{$offer['item_title']}\" has been accepted! Please pick up during the scheduled pickup time.",
            "/claims/my-offers.php"
        );

        if ($this->shouldSendEmail('buyer', $offer['buyer_id'], 'offers')) {
            $this->sendEmail(
                $offer['buyer_contact'],
                "Your Offer Was Accepted! - {$offer['item_title']}",
                $this->buildOfferAcceptedEmail($offer)
            );
        }

        // Send SMS if buyer has phone
        if (!empty($offer['buyer_phone'])) {
            $this->smsService->sendOfferAcceptedNotification(
                $offer['buyer_phone'],
                $offer['item_title']
            );
        }
    }

    /**
     * Notify buyer when offer is rejected
     */
    public function notifyBuyerOfferRejected(int $offerId, ?string $reason = null): void
    {
        $offer = $this->getOfferDetails($offerId);
        if (!$offer) return;

        $message = "Your offer of \${$offer['offer_amount']} on \"{$offer['item_title']}\" was not accepted.";
        if ($reason) {
            $message .= " Reason: {$reason}";
        }

        $this->createNotification(
            'buyer',
            $offer['buyer_id'],
            'offer_rejected',
            "Offer not accepted - {$offer['item_title']}",
            $message,
            "/claims/sale.php?id={$offer['sale_id']}"
        );

        if ($this->shouldSendEmail('buyer', $offer['buyer_id'], 'offers')) {
            $this->sendEmail(
                $offer['buyer_contact'],
                "Offer Update - {$offer['item_title']}",
                $this->buildOfferRejectedEmail($offer, $reason)
            );
        }
    }

    /**
     * Notify buyer when outbid
     */
    public function notifyBuyerOutbid(int $buyerId, int $itemId, float $newHighBid): void
    {
        $item = $this->getItemDetails($itemId);
        if (!$item) return;

        $buyer = $this->getBuyerDetails($buyerId);
        if (!$buyer) return;

        $this->createNotification(
            'buyer',
            $buyerId,
            'offer_outbid',
            "You've been outbid on {$item['title']}",
            "Someone has placed a higher offer of \${$newHighBid} on \"{$item['title']}\". Place a new offer to stay in the running!",
            "/claims/item.php?id={$itemId}"
        );

        if ($this->shouldSendEmail('buyer', $buyerId, 'offers')) {
            $this->sendEmail(
                $buyer['contact'],
                "You've Been Outbid - {$item['title']}",
                $this->buildOutbidEmail($item, $buyer, $newHighBid)
            );
        }
    }

    /**
     * Create notification record
     */
    private function createNotification(
        string $userType,
        int $userId,
        string $type,
        string $title,
        string $message,
        string $link
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO yfc_notifications (user_type, user_id, type, title, message, link)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userType, $userId, $type, $title, $message, $link]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Get unread notifications for user
     */
    public function getUnread(string $userType, int $userId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM yfc_notifications
            WHERE user_type = ? AND user_id = ? AND read_at IS NULL
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userType, $userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark notification as read
     */
    public function markRead(int $notificationId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE yfc_notifications SET read_at = NOW() WHERE id = ?
        ");
        $stmt->execute([$notificationId]);
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllRead(string $userType, int $userId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE yfc_notifications SET read_at = NOW()
            WHERE user_type = ? AND user_id = ? AND read_at IS NULL
        ");
        $stmt->execute([$userType, $userId]);
    }

    /**
     * Check if email should be sent based on preferences
     */
    private function shouldSendEmail(string $userType, int $userId, string $type): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM yfc_notification_preferences
            WHERE user_type = ? AND user_id = ?
        ");
        $stmt->execute([$userType, $userId]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prefs) {
            return true; // Default to sending
        }

        switch ($type) {
            case 'offers':
                return (bool)$prefs['email_offers'];
            case 'updates':
                return (bool)$prefs['email_updates'];
            default:
                return true;
        }
    }

    /**
     * Send email using EmailService
     */
    private function sendEmail(string $to, string $subject, string $htmlBody): bool
    {
        return $this->emailService->send($to, $subject, $htmlBody);
    }

    /**
     * Get offer details for notifications
     */
    private function getOfferDetails(int $offerId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                o.*,
                i.title as item_title,
                i.sale_id,
                s.title as sale_title,
                s.seller_id,
                sel.email as seller_email,
                sel.phone as seller_phone,
                sel.company_name as seller_name,
                b.name as buyer_name,
                b.phone as buyer_phone,
                COALESCE(b.email, b.phone) as buyer_contact
            FROM yfc_offers o
            JOIN yfc_items i ON o.item_id = i.id
            JOIN yfc_sales s ON i.sale_id = s.id
            JOIN yfc_sellers sel ON s.seller_id = sel.id
            JOIN yfc_buyers b ON o.buyer_id = b.id
            WHERE o.id = ?
        ");
        $stmt->execute([$offerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get item details
     */
    private function getItemDetails(int $itemId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, s.title as sale_title, s.seller_id
            FROM yfc_items i
            JOIN yfc_sales s ON i.sale_id = s.id
            WHERE i.id = ?
        ");
        $stmt->execute([$itemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get buyer details
     */
    private function getBuyerDetails(int $buyerId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT *, COALESCE(email, phone) as contact FROM yfc_buyers WHERE id = ?");
        $stmt->execute([$buyerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Build email HTML for offer received
     */
    private function buildOfferReceivedEmail(array $offer): string
    {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: #2ecc71; color: white; padding: 20px; text-align: center;'>
                <h1>New Offer Received!</h1>
            </div>
            <div style='padding: 20px;'>
                <h2>💰 \${$offer['offer_amount']}</h2>
                <p><strong>Item:</strong> {$offer['item_title']}</p>
                <p><strong>Sale:</strong> {$offer['sale_title']}</p>
                <p><strong>Buyer:</strong> {$offer['buyer_name']}</p>
                <p><strong>Contact:</strong> {$offer['buyer_contact']}</p>
                <div style='margin-top: 20px;'>
                    <a href='{$this->siteUrl}/claims/admin/offers.php?sale_id={$offer['sale_id']}'
                       style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                        View & Respond
                    </a>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Build email HTML for offer accepted
     */
    private function buildOfferAcceptedEmail(array $offer): string
    {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: #2ecc71; color: white; padding: 20px; text-align: center;'>
                <h1>🎉 Your Offer Was Accepted!</h1>
            </div>
            <div style='padding: 20px;'>
                <p>Congratulations! Your offer has been accepted.</p>
                <h2>{$offer['item_title']}</h2>
                <p><strong>Your Offer:</strong> \${$offer['offer_amount']}</p>
                <p><strong>Sale:</strong> {$offer['sale_title']}</p>
                <p style='margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;'>
                    <strong>Next Steps:</strong><br>
                    Please pick up your item during the scheduled pickup window.
                    Check the sale details for pickup times and location.
                </p>
                <div style='margin-top: 20px;'>
                    <a href='{$this->siteUrl}/claims/my-offers.php'
                       style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                        View My Offers
                    </a>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Build email HTML for offer rejected
     */
    private function buildOfferRejectedEmail(array $offer, ?string $reason): string
    {
        $reasonText = $reason ? "<p><strong>Reason:</strong> {$reason}</p>" : '';

        return "
        <html>
        <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: #e74c3c; color: white; padding: 20px; text-align: center;'>
                <h1>Offer Update</h1>
            </div>
            <div style='padding: 20px;'>
                <p>We're sorry, but your offer was not accepted.</p>
                <h2>{$offer['item_title']}</h2>
                <p><strong>Your Offer:</strong> \${$offer['offer_amount']}</p>
                {$reasonText}
                <p style='margin-top: 20px;'>
                    Don't worry! There are plenty of other great items available.
                </p>
                <div style='margin-top: 20px;'>
                    <a href='{$this->siteUrl}/claims/sale.php?id={$offer['sale_id']}'
                       style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                        Browse More Items
                    </a>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Build email HTML for outbid notification
     */
    private function buildOutbidEmail(array $item, array $buyer, float $newHighBid): string
    {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: #f39c12; color: white; padding: 20px; text-align: center;'>
                <h1>⚠️ You've Been Outbid!</h1>
            </div>
            <div style='padding: 20px;'>
                <h2>{$item['title']}</h2>
                <p><strong>New High Offer:</strong> \${$newHighBid}</p>
                <p>Someone has placed a higher offer. Place a new offer to stay in the running!</p>
                <div style='margin-top: 20px;'>
                    <a href='{$this->siteUrl}/claims/item.php?id={$item['id']}'
                       style='background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                        Place New Offer
                    </a>
                </div>
            </div>
        </body>
        </html>";
    }
}
