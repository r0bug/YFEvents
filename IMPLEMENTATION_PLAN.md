# YFEvents Implementation Plan

**Version**: 1.0
**Created**: December 2024
**Status**: Ready for Approval

---

## Decision Point: YFClaim vs Webcat

### System Comparison

| Feature | YFClaim (PHP) | Webcat (Node.js) |
|---------|---------------|------------------|
| **Purpose** | Estate sale platform | Consignment mall catalog |
| **Workflow** | Timed sales with offers | Direct listing/sale |
| **Offer System** | Yes (bid/accept/reject) | No (fixed price) |
| **Sale Timeline** | Preview → Claim → Pickup | Always available |
| **Buyer Auth** | Phone/SMS verification | Account required |
| **QR Code Access** | Yes | No |
| **Tech Stack** | PHP 8.2 (matches YFEvents) | Node.js/React |
| **Integration** | Native to YFEvents | Separate system |
| **Status** | 60% complete | 100% deployed |

### Use Case Analysis

**YFClaim is better for:**
- Estate sale companies
- Timed auction-style sales
- Seller control over which offers to accept
- QR code access at physical locations
- Integration with YFEvents calendar

**Webcat is better for:**
- Consignment mall back-room items
- Always-available inventory
- Simple direct sales
- Standalone operation

### Recommendation

**Keep both but clarify purpose:**

1. **YFClaim** → Estate sale platform (complete it)
   - Timed sales with offer system
   - Integrate with YFEvents calendar (estate sales show as events)
   - Deploy at: `yfevents.yakimafinds.com/claims`

2. **Webcat** → Consignment catalog (already deployed)
   - Ongoing inventory management
   - Direct sales model
   - Keep at: `webcat.yakimafinds.com`

**If you only want ONE system**, I'd recommend:
- **Complete YFClaim** if estate sales are the primary use case
- **Use Webcat** if simple classifieds is the primary use case

---

## Implementation Plan

### Phase 0: Infrastructure Setup
**Duration**: 1 day

| Task | Description | Files |
|------|-------------|-------|
| DNS Setup | Point yfevents.yakimafinds.com to server | DNS records |
| SSL Certificate | Obtain Let's Encrypt cert for new domain | certbot |
| Apache Config | Create vhost for yfevents subdomain | apache config |
| Update Spec | Fix URL references in specification | CONSOLIDATED_SPECIFICATION.md |

**Deliverable**: yfevents.yakimafinds.com serving YFEvents

---

### Phase 1: Core System Cleanup
**Duration**: 2-3 days

#### 1.1 Consolidate Codebase

| Task | Action | Reason |
|------|--------|--------|
| Remove duplicate modules | Delete `modules/yfclaim/src/Models/` | Use Domain entities instead |
| Clean legacy files | Archive `www/html/*.php` that duplicate refactor | Reduce confusion |
| Fix namespace issues | Standardize on `YakimaFinds\` | Currently mixed |
| Update autoloader | Regenerate composer autoload | Clean PSR-4 loading |

#### 1.2 Repository Implementations

**Create these files:**
```
src/Infrastructure/Repositories/Claims/
├── SaleRepository.php
├── ItemRepository.php
├── OfferRepository.php
├── BuyerRepository.php
└── SellerRepository.php
```

Each repository implements its interface from `src/Domain/Claims/`.

#### 1.3 Complete ClaimsController

**Create:**
```
src/Presentation/Http/Controllers/ClaimsController.php
src/Presentation/Api/Controllers/ClaimsApiController.php
```

**Routes to add (`routes/web.php`):**
```php
// Public claim routes
$router->get('/claims', ClaimsController::class, 'listSales');
$router->get('/claims/{id}', ClaimsController::class, 'viewSale');
$router->get('/claims/{id}/items', ClaimsController::class, 'viewItems');
$router->post('/claims/verify', ClaimsController::class, 'verifyBuyer');
$router->post('/claims/offer', ClaimsController::class, 'submitOffer');

// Seller routes
$router->get('/seller/dashboard', ClaimsController::class, 'sellerDashboard');
$router->get('/seller/sales', ClaimsController::class, 'sellerSales');
$router->post('/seller/sales/create', ClaimsController::class, 'createSale');
```

---

### Phase 2: Unified User System
**Duration**: 1-2 days

#### 2.1 Database Migration

```sql
-- Add role flags to users table
ALTER TABLE users
ADD COLUMN is_seller BOOLEAN DEFAULT FALSE,
ADD COLUMN is_business_owner BOOLEAN DEFAULT FALSE,
ADD COLUMN is_yf_vendor BOOLEAN DEFAULT FALSE,
ADD COLUMN is_yf_staff BOOLEAN DEFAULT FALSE,
ADD COLUMN seller_id INT NULL,
ADD COLUMN shop_id INT NULL;

-- Migrate existing sellers to unified users
INSERT INTO users (username, email, password_hash, name, is_seller, seller_id)
SELECT
    LOWER(REPLACE(company_name, ' ', '_')),
    email,
    password_hash,
    contact_name,
    TRUE,
    id
FROM yfc_sellers;
```

#### 2.2 Update Auth System

- Modify login to check `users` table
- Update session to include role flags
- Update middleware to check permissions

---

### Phase 3: Frontend Implementation
**Duration**: 2-3 days

#### 3.1 Public Claim Views

**Create templates:**
```
www/html/templates/claims/
├── list.php          # Browse active sales
├── sale.php          # Single sale detail
├── items.php         # Items grid with photos
├── item-detail.php   # Single item with offers
└── buyer-offers.php  # Buyer's offer tracking
```

#### 3.2 Seller Dashboard

**Create templates:**
```
www/html/templates/seller/
├── dashboard.php     # Seller home
├── sales.php         # Manage sales
├── create-sale.php   # New sale form
├── items.php         # Manage items
└── offers.php        # Review/accept offers
```

#### 3.3 Admin Claims Section

**Create templates:**
```
www/html/admin/claims/
├── index.php         # Claims overview
├── sales.php         # All sales management
├── sellers.php       # Seller management
└── reports.php       # Sales reports
```

---

### Phase 4: Integration & Testing
**Duration**: 1-2 days

#### 4.1 Calendar Integration

- Estate sales appear as events on calendar
- "View Sale" link from event to claim page
- Auto-create event when sale is activated

#### 4.2 Notification System

```sql
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('offer_received', 'offer_accepted', 'offer_rejected', 'sale_starting'),
    title VARCHAR(255),
    message TEXT,
    link VARCHAR(500),
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### 4.3 Testing Checklist

- [ ] Create seller account
- [ ] Create sale with items
- [ ] Upload item photos
- [ ] Activate sale (QR code generated)
- [ ] Buyer views sale via QR/link
- [ ] Buyer makes offer
- [ ] Seller receives notification
- [ ] Seller accepts/rejects offer
- [ ] Buyer receives notification
- [ ] Sale completes

---

### Phase 5: Deployment
**Duration**: 1 day

#### 5.1 Pre-Deployment

- [ ] Run security check script
- [ ] Backup current database
- [ ] Backup current files
- [ ] Test on staging

#### 5.2 Deployment Steps

```bash
# 1. Pull latest code
cd /home/robug/YFEvents
git pull origin refactor/v2-complete-rebuild

# 2. Run database migrations
mysql -u yfevents -p yakima_finds < database/migrations/unified_users.sql
mysql -u yfevents -p yakima_finds < database/migrations/notifications.sql

# 3. Update composer
composer dump-autoload

# 4. Clear caches
rm -rf cache/*

# 5. Update Apache config
sudo cp /home/robug/yfevents-apache.conf /etc/apache2/sites-available/
sudo a2ensite yfevents
sudo systemctl reload apache2

# 6. Verify
curl -I https://yfevents.yakimafinds.com/
```

#### 5.3 Post-Deployment

- [ ] Verify all routes working
- [ ] Test login/authentication
- [ ] Create test sale
- [ ] Monitor error logs

---

## File Inventory: What to Keep vs Remove

### Keep (Good Reusable Code)

| Path | Description |
|------|-------------|
| `src/Domain/*` | All domain entities and interfaces |
| `src/Application/Services/*` | All application services |
| `src/Infrastructure/Container/` | DI container |
| `src/Infrastructure/Database/` | Database abstraction |
| `src/Infrastructure/Http/` | Router |
| `src/Infrastructure/Repositories/Event*` | Working repos |
| `src/Infrastructure/Repositories/Shop*` | Working repos |
| `src/Infrastructure/Repositories/Communication/*` | Working repos |
| `src/Presentation/*` | All controllers |
| `routes/*` | Route definitions |
| `public/index.php` | Modern entry point |
| `www/html/communication/` | Communication hub |
| `www/html/admin/` | Admin interface |

### Remove or Archive

| Path | Reason |
|------|--------|
| `modules/yfclaim/src/Models/` | Duplicate of Domain entities |
| `www/html/yfclaim*.php` | Legacy, use modern controllers |
| `www/html/refactor_backup_*` | Old backup |
| Duplicate `.env` files | Consolidate to one |

### Needs Completion

| Path | Work Needed |
|------|-------------|
| `src/Infrastructure/Repositories/Claims/` | Create all 5 repos |
| `src/Presentation/Http/Controllers/ClaimsController.php` | Create |
| `src/Presentation/Api/Controllers/ClaimsApiController.php` | Create |
| `www/html/templates/claims/` | Create views |
| `www/html/templates/seller/` | Create views |

---

## Timeline Summary

| Phase | Duration | Dependency |
|-------|----------|------------|
| Phase 0: Infrastructure | 1 day | None |
| Phase 1: Core Cleanup | 2-3 days | Phase 0 |
| Phase 2: Unified Users | 1-2 days | Phase 1 |
| Phase 3: Frontend | 2-3 days | Phase 1, 2 |
| Phase 4: Integration | 1-2 days | Phase 3 |
| Phase 5: Deployment | 1 day | Phase 4 |

**Total: 8-12 days of development**

---

## Questions for You

Before proceeding, please confirm:

1. **YFClaim vs Webcat**:
   - Keep both (recommended)?
   - Complete YFClaim only?
   - Use Webcat only?

2. **Domain**: Confirm `yfevents.yakimafinds.com` is correct?

3. **Priority**: Start with Phase 0 (infrastructure) or Phase 1 (code cleanup)?

4. **Testing approach**: Create test data as we go, or wait until Phase 4?

---

*Ready to begin when you approve this plan.*
