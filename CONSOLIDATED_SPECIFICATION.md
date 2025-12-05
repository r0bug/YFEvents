# YFEvents Platform - Consolidated Specification

**Version**: 2.0
**Last Updated**: December 2024
**Status**: Review Draft

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Platform Vision & Goals](#2-platform-vision--goals)
3. [System Architecture](#3-system-architecture)
4. [Core Modules](#4-core-modules)
5. [Database Schema](#5-database-schema)
6. [API Specification](#6-api-specification)
7. [User Roles & Permissions](#7-user-roles--permissions)
8. [User Workflows](#8-user-workflows)
9. [Technical Requirements](#9-technical-requirements)
10. [Security Requirements](#10-security-requirements)
11. [Deployment Architecture](#11-deployment-architecture)

---

## 1. Executive Summary

### 1.1 Purpose

YFEvents is a strategic platform suite for Yakima Finds designed to:
- **Drive traffic** to yakimafinds.com through free public services
- **Grow market share** in the local events and business directory space
- **Facilitate sales** through the estate sale claim platform
- **Build community** through internal communication tools

### 1.2 Platform Components

| Module | Purpose | Target Users | Status |
|--------|---------|--------------|--------|
| **YFEvents Calendar** | Aggregate local events from multiple sources | General Public | Production |
| **Business Directory** | Thrifting/antiquing focused shop directory | General Public | Production |
| **YFClaim** | Estate sale platform with offer system | Sellers, Buyers | 60% Complete |
| **Webcat Integration** | Shared user system with webcat.yakimafinds.com | YF Staff, Vendors | Planned |
| **YFCommunication** | Internal messaging for staff/vendors | YF Staff, Vendors | Production |
| **Admin Panel** | Content management and moderation | Administrators | Production |

### 1.3 Production URLs

| System | URL | Purpose |
|--------|-----|---------|
| **YFEvents** | `https://yfevents.yakimafinds.com/` | Events, Shops, Claims, Communication |
| **Webcat** | `https://webcat.yakimafinds.com/` | Consignment catalog (shared users) |

---

## 2. Platform Vision & Goals

### 2.1 Strategic Objectives

1. **Traffic Generation**: Free event calendar drives visitors to main YF site
2. **Local Authority**: Become the go-to source for Yakima Valley events
3. **Business Relationships**: Directory creates partnerships with local shops
4. **Revenue Stream**: YFClaim estate sale platform generates commissions
5. **Community Building**: Communication hub strengthens vendor relationships

### 2.2 Success Metrics

| Metric | Target | Measurement |
|--------|--------|-------------|
| Monthly unique visitors | 10,000+ | Analytics |
| Events listed per month | 200+ | Database count |
| Business directory listings | 50+ active | Database count |
| Estate sales per month | 5+ | YFClaim data |
| Scraper success rate | >95% | Automated monitoring |
| Page load time | <2 seconds | Performance monitoring |

### 2.3 User Value Propositions

**For General Public:**
- Single source for all local events
- Interactive map showing today's activities
- Business directory for trip planning
- Free event submission

**For Business Owners:**
- Free business listing
- Event integration with their business
- Customer reach expansion

**For Estate Sale Companies:**
- Digital platform for item listing
- Automated offer management
- QR code access for buyers
- Real-time notifications

**For YF Staff/Vendors:**
- Private communication channels
- Announcements and updates
- Mobile-friendly messaging

---

## 3. System Architecture

### 3.1 Technology Stack

| Component | Technology | Version |
|-----------|------------|---------|
| Backend | PHP | 8.2+ |
| Database | MySQL | 8.0+ |
| Web Server | Apache | 2.4+ |
| Frontend | Vanilla JavaScript | ES6+ |
| Maps | Google Maps API | v3 |
| CSS Framework | Bootstrap | 5.x |
| Package Manager | Composer | 2.x |

### 3.2 Architectural Pattern

**Domain-Driven Design (DDD) with Clean Architecture**

```
┌─────────────────────────────────────────────────────────────┐
│                    PRESENTATION LAYER                        │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐ │
│  │ Web Controllers │  │ API Controllers │  │   Views     │ │
│  └─────────────────┘  └─────────────────┘  └─────────────┘ │
└─────────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────────┐
│                    APPLICATION LAYER                         │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐ │
│  │    Services     │  │   Validators    │  │    DTOs     │ │
│  └─────────────────┘  └─────────────────┘  └─────────────┘ │
└─────────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────────┐
│                      DOMAIN LAYER                            │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐ │
│  │    Entities     │  │ Value Objects   │  │  Interfaces │ │
│  └─────────────────┘  └─────────────────┘  └─────────────┘ │
└─────────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────────┐
│                   INFRASTRUCTURE LAYER                       │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐ │
│  │  Repositories   │  │    Database     │  │  External   │ │
│  │                 │  │   Connection    │  │  Services   │ │
│  └─────────────────┘  └─────────────────┘  └─────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

### 3.3 Directory Structure

```
YFEvents/
├── src/                          # Modern architecture source
│   ├── Domain/                   # Business logic & entities
│   │   ├── Events/
│   │   ├── Shops/
│   │   ├── Claims/
│   │   ├── Users/
│   │   ├── Communication/
│   │   └── Common/
│   ├── Application/              # Use cases & services
│   │   ├── Services/
│   │   └── Validators/
│   ├── Infrastructure/           # Technical implementations
│   │   ├── Repositories/
│   │   ├── Database/
│   │   ├── Container/
│   │   └── Http/
│   └── Presentation/             # HTTP interface
│       ├── Http/Controllers/
│       └── Api/Controllers/
├── public/                       # Web entry point
│   └── index.php
├── routes/                       # Route definitions
│   ├── web.php
│   ├── api.php
│   └── api/communication.php
├── config/                       # Configuration files
├── database/                     # SQL schemas
├── www/html/                     # Legacy public files
└── tests/                        # Test files
```

### 3.4 Namespace Structure

```php
// Primary namespace
YakimaFinds\Domain\*
YakimaFinds\Application\*
YakimaFinds\Infrastructure\*
YakimaFinds\Presentation\*

// Autoloading (PSR-4)
"YakimaFinds\\": "src/"
"YFEvents\\": "src/"
```

---

## 4. Core Modules

### 4.1 YFEvents Calendar Module

#### Purpose
Aggregate and display local events from multiple sources with map integration.

#### Features
- **Calendar Views**: Month, week, day, list views
- **Map View**: Interactive Google Maps with event pins
- **Event Search**: Filter by date, category, location
- **Event Detail**: Full event information with directions
- **Event Submission**: Public form for community events
- **Source Attribution**: Link back to original event source

#### Event Sources
| Source Type | Method | Configuration |
|-------------|--------|---------------|
| iCal feeds | Automated scraping | URL, refresh interval |
| HTML pages | CSS selector scraping | Selectors, patterns |
| JSON APIs | API integration | Endpoints, auth |
| Facebook | Email parsing | IMAP configuration |
| Manual entry | Admin/public forms | Approval workflow |

#### Event Lifecycle
```
┌─────────┐    ┌─────────┐    ┌──────────┐    ┌───────────┐
│ Scraped │───>│ Pending │───>│ Approved │───>│ Published │
└─────────┘    └─────────┘    └──────────┘    └───────────┘
                    │              │
                    v              v
               ┌─────────┐    ┌──────────┐
               │Rejected │    │ Featured │
               └─────────┘    └──────────┘
```

### 4.2 Business Directory Module

#### Purpose
Comprehensive local business directory focused on thrifting, antiquing, and local shops.

#### Features
- **Shop Listings**: Full business profiles with photos
- **Map Integration**: Location-based discovery
- **Operating Hours**: Real-time open/closed status
- **Categories**: Hierarchical business categorization
- **Verification**: Business owner verification system
- **Claiming**: Business owners can claim listings

#### Shop Profile Fields
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | string | Yes | Business name |
| description | text | No | Business description |
| address | text | Yes | Full street address |
| latitude/longitude | decimal | Auto | Geocoded coordinates |
| phone | string | No | Contact phone |
| email | string | No | Contact email |
| website | url | No | Business website |
| operating_hours | json | No | Weekly schedule |
| category_id | int | Yes | Primary category |
| amenities | json | No | Available amenities |
| payment_methods | json | No | Accepted payments |

### 4.3 YFClaim Estate Sale Module

#### Purpose
Digital platform for estate sale companies to list items and accept offers from buyers.

#### Key Concepts

**Sale**: A single estate sale event with defined timeline
- Preview Period: Buyers can view items, no offers
- Claim Period: Buyers can submit offers
- Pickup Period: Winning buyers collect items

**Item**: Individual item for sale
- Photos, description, starting price
- Category assignment
- Status tracking (available, claimed, sold)

**Offer**: Buyer bid on an item
- Amount, message, timestamp
- Status (pending, accepted, rejected, outbid)

**Buyer**: Temporary authenticated user
- Phone/email verification
- No permanent account required
- Can track their offers

**Seller**: Estate sale company
- Permanent account
- Manages multiple sales
- Receives real-time notifications

#### Sale Timeline
```
┌──────────────┬───────────────┬───────────────┬──────────────┐
│   PREVIEW    │    CLAIMING   │   SELECTION   │    PICKUP    │
│  (View Only) │ (Make Offers) │(Accept/Reject)│(Collect Items)│
└──────────────┴───────────────┴───────────────┴──────────────┘
     Day 1-2        Day 3-5         Day 6          Day 7
```

#### Offer Workflow
```
┌────────────┐    ┌─────────────┐    ┌──────────────┐
│   Buyer    │───>│Submit Offer │───>│   Pending    │
└────────────┘    └─────────────┘    └──────────────┘
                                            │
                    ┌───────────────────────┼───────────────────────┐
                    v                       v                       v
             ┌──────────┐           ┌──────────────┐         ┌──────────┐
             │ Accepted │           │   Rejected   │         │  Outbid  │
             └──────────┘           └──────────────┘         └──────────┘
                    │
                    v
             ┌──────────────┐
             │ Item Claimed │
             └──────────────┘
```

#### Access Methods
- **QR Code**: Physical signs at sale location
- **Access Code**: 8-character alphanumeric
- **Direct Link**: Shareable URL

### 4.4 YFCommunication Module

#### Purpose
Internal communication platform for YF staff, vendors, and associates.

#### Features
- **Channels**: Public and private chat rooms
- **Direct Messages**: Person-to-person messaging
- **Announcements**: Broadcast messages
- **Notifications**: Email and in-app alerts
- **File Attachments**: Document and image sharing
- **Mobile PWA**: Install-to-home-screen support

#### Channel Types
| Type | Visibility | Join Method | Use Case |
|------|------------|-------------|----------|
| public | All users | Open join | General discussion |
| private | Invited only | Invitation | Team channels |
| vendor | Vendors only | Role-based | Vendor updates |
| announcement | Read-only | Automatic | Official notices |
| event | Event-specific | Event link | Event coordination |

#### Picks Feature
Location-based sharing for estate sales and yard sales:
- Post location with address and date
- Map view with all picks
- Integrated with communication channels

### 4.5 Admin Module

#### Purpose
Centralized management interface for all platform components.

#### Admin Sections
| Section | Functions |
|---------|-----------|
| Dashboard | Statistics, recent activity, alerts |
| Events | Approve/reject, edit, bulk actions |
| Shops | Manage listings, verify businesses |
| Scrapers | Configure sources, test, view logs |
| Users | Manage accounts, roles, permissions |
| Claims | Oversee estate sales, resolve issues |
| Communication | Moderate channels, announcements |
| Settings | System configuration |

---

## 5. Database Schema

### 5.1 Core Tables

#### events
```sql
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    start_datetime TIMESTAMP NOT NULL,
    end_datetime TIMESTAMP NULL,
    location VARCHAR(255),
    address TEXT,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    contact_info JSON,
    external_url VARCHAR(500),
    source_id INT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    scraped_at TIMESTAMP NULL,
    external_event_id VARCHAR(255)
);
```

#### calendar_sources
```sql
CREATE TABLE calendar_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    url VARCHAR(500) NOT NULL,
    scrape_type ENUM('ical', 'html', 'json', 'eventbrite', 'facebook') NOT NULL,
    scrape_config JSON,
    last_scraped TIMESTAMP NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### local_shops
```sql
CREATE TABLE local_shops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    address TEXT NOT NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    phone VARCHAR(50),
    email VARCHAR(255),
    website VARCHAR(500),
    category_id INT NULL,
    operating_hours JSON,
    payment_methods JSON,
    amenities JSON,
    featured BOOLEAN DEFAULT FALSE,
    verified BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'pending', 'inactive') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 5.2 YFClaim Tables

#### yfc_sellers
```sql
CREATE TABLE yfc_sellers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(50),
    password_hash VARCHAR(255) NOT NULL,
    address TEXT,
    website VARCHAR(500),
    logo_url VARCHAR(500),
    status ENUM('pending', 'active', 'suspended') DEFAULT 'pending',
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### yfc_sales
```sql
CREATE TABLE yfc_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    address TEXT NOT NULL,
    city VARCHAR(100),
    state VARCHAR(50),
    zip VARCHAR(20),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    preview_start DATETIME NOT NULL,
    preview_end DATETIME NOT NULL,
    claim_start DATETIME NOT NULL,
    claim_end DATETIME NOT NULL,
    pickup_start DATETIME NOT NULL,
    pickup_end DATETIME NOT NULL,
    pickup_instructions TEXT,
    qr_code VARCHAR(50) UNIQUE,
    access_code VARCHAR(20) UNIQUE,
    status ENUM('draft', 'active', 'paused', 'completed', 'cancelled') DEFAULT 'draft',
    featured BOOLEAN DEFAULT FALSE,
    settings JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES yfc_sellers(id)
);
```

#### yfc_items
```sql
CREATE TABLE yfc_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    item_number VARCHAR(50),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category_id INT,
    starting_price DECIMAL(10, 2) DEFAULT 0,
    current_high_offer DECIMAL(10, 2) DEFAULT 0,
    winning_offer_id INT NULL,
    status ENUM('available', 'claimed', 'sold', 'withdrawn') DEFAULT 'available',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES yfc_sales(id)
);
```

#### yfc_item_images
```sql
CREATE TABLE yfc_item_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    thumbnail_url VARCHAR(500),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES yfc_items(id) ON DELETE CASCADE
);
```

#### yfc_buyers
```sql
CREATE TABLE yfc_buyers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(50),
    email VARCHAR(255),
    name VARCHAR(255),
    verification_code VARCHAR(10),
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### yfc_offers
```sql
CREATE TABLE yfc_offers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    buyer_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    message TEXT,
    status ENUM('pending', 'accepted', 'rejected', 'outbid', 'withdrawn') DEFAULT 'pending',
    seller_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES yfc_items(id),
    FOREIGN KEY (buyer_id) REFERENCES yfc_buyers(id)
);
```

#### yfc_categories
```sql
CREATE TABLE yfc_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    parent_id INT NULL,
    icon VARCHAR(50),
    sort_order INT DEFAULT 0,
    FOREIGN KEY (parent_id) REFERENCES yfc_categories(id)
);
```

### 5.3 Communication Tables

#### communication_channels
```sql
CREATE TABLE communication_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    type ENUM('public', 'private', 'event', 'vendor', 'announcement') DEFAULT 'public',
    created_by_user_id INT,
    settings JSON,
    participant_count INT DEFAULT 0,
    last_activity_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### communication_messages
```sql
CREATE TABLE communication_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    content_type ENUM('text', 'system', 'announcement') DEFAULT 'text',
    parent_message_id INT NULL,
    is_pinned BOOLEAN DEFAULT FALSE,
    is_edited BOOLEAN DEFAULT FALSE,
    edited_at TIMESTAMP NULL,
    -- Location fields for Picks feature
    location_name VARCHAR(255),
    location_address TEXT,
    location_latitude DECIMAL(10, 8),
    location_longitude DECIMAL(11, 8),
    event_date DATE,
    event_start_time TIME,
    event_end_time TIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (channel_id) REFERENCES communication_channels(id)
);
```

#### communication_participants
```sql
CREATE TABLE communication_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('member', 'admin') DEFAULT 'member',
    last_read_message_id INT,
    notification_preference ENUM('all', 'mentions', 'none') DEFAULT 'all',
    email_digest ENUM('none', 'daily', 'weekly') DEFAULT 'none',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_participant (channel_id, user_id)
);
```

### 5.4 User/Auth Tables

#### users (Unified)
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    phone VARCHAR(50),
    avatar_url VARCHAR(500),
    -- Role flags (user can have multiple roles)
    is_admin BOOLEAN DEFAULT FALSE,
    is_moderator BOOLEAN DEFAULT FALSE,
    is_seller BOOLEAN DEFAULT FALSE,
    is_business_owner BOOLEAN DEFAULT FALSE,
    is_yf_vendor BOOLEAN DEFAULT FALSE,
    is_yf_staff BOOLEAN DEFAULT FALSE,
    is_yf_associate BOOLEAN DEFAULT FALSE,
    -- Linked IDs
    seller_id INT NULL,           -- Links to yfc_sellers
    shop_id INT NULL,             -- Links to local_shops
    -- Status
    status ENUM('active', 'suspended', 'pending') DEFAULT 'pending',
    email_verified BOOLEAN DEFAULT FALSE,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 6. API Specification

### 6.1 Public Event API

#### GET /api/events
List events with filtering.

**Parameters:**
| Name | Type | Description |
|------|------|-------------|
| start_date | date | Filter by start date |
| end_date | date | Filter by end date |
| category | int | Filter by category ID |
| featured | bool | Only featured events |
| lat | float | Latitude for proximity search |
| lng | float | Longitude for proximity search |
| radius | int | Radius in miles (default 25) |
| limit | int | Results per page (default 20) |
| offset | int | Pagination offset |

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Farmers Market",
      "description": "Weekly farmers market...",
      "start_datetime": "2024-12-15T09:00:00",
      "end_datetime": "2024-12-15T14:00:00",
      "location": "Downtown Yakima",
      "latitude": 46.6021,
      "longitude": -120.5059,
      "source": "Visit Yakima",
      "featured": true
    }
  ],
  "meta": {
    "total": 45,
    "page": 1,
    "per_page": 20
  }
}
```

#### GET /api/events/{id}
Get single event details.

#### POST /api/events
Submit new event (public submission).

### 6.2 Public Shop API

#### GET /api/shops
List shops with filtering.

#### GET /api/shops/{id}
Get shop details.

#### GET /api/shops/map
Get shops for map display.

#### GET /api/shops/nearby
Get shops near location.

### 6.3 YFClaim API

#### GET /api/claims/sales
List active sales.

#### GET /api/claims/sales/{id}
Get sale details with items.

#### GET /api/claims/sales/{id}/items
Get items for a sale.

#### POST /api/claims/offers
Submit an offer.

**Request:**
```json
{
  "item_id": 123,
  "buyer_phone": "509-555-1234",
  "amount": 25.00,
  "message": "I'd love this item!"
}
```

#### GET /api/claims/offers/mine
Get buyer's offers (requires verification).

### 6.4 Communication API

#### GET /api/communication/channels
List user's channels.

#### GET /api/communication/channels/{id}/messages
Get channel messages.

#### POST /api/communication/channels/{id}/messages
Post message to channel.

#### GET /api/communication/notifications
Get user notifications.

### 6.5 Admin API

All admin endpoints require authentication.

#### Events
- GET /admin/events - List all events
- POST /admin/events/{id}/approve - Approve event
- POST /admin/events/{id}/reject - Reject event
- POST /admin/events/bulk-approve - Bulk approve

#### Shops
- GET /admin/shops - List all shops
- POST /admin/shops/{id}/verify - Verify shop
- POST /admin/shops/{id}/feature - Feature shop

#### Dashboard
- GET /admin/dashboard/statistics - System statistics
- GET /admin/dashboard/health - System health check

---

## 7. User Roles & Permissions

### 7.1 Role Definitions

| Role | Description | Access Level |
|------|-------------|--------------|
| **Public** | Anonymous visitor | View public content |
| **Registered** | Basic account | Submit events, save favorites |
| **Business Owner** | Owns a shop listing | Manage own shop profile |
| **Claim Buyer** | Estate sale participant | Make offers, track purchases |
| **Claim Seller** | Estate sale company | Manage sales, accept offers |
| **YF Vendor** | Yakima Finds vendor | Access communication hub |
| **YF Associate** | YF business partner | Limited internal access |
| **YF Staff** | YF employee | Full communication access |
| **Moderator** | Content moderator | Approve/reject content |
| **Admin** | Full administrator | All system access |

### 7.2 Permission Matrix

| Action | Public | Registered | Business | Seller | Moderator | Admin |
|--------|--------|------------|----------|--------|-----------|-------|
| View events | Yes | Yes | Yes | Yes | Yes | Yes |
| Submit event | No | Yes | Yes | Yes | Yes | Yes |
| View shops | Yes | Yes | Yes | Yes | Yes | Yes |
| Claim shop | No | Yes | Yes | Yes | Yes | Yes |
| Make offer | Yes* | Yes | Yes | Yes | Yes | Yes |
| Create sale | No | No | No | Yes | No | Yes |
| Approve events | No | No | No | No | Yes | Yes |
| Manage users | No | No | No | No | No | Yes |
| System settings | No | No | No | No | No | Yes |

*Public offers require phone/email verification

---

## 8. User Workflows

### 8.1 Event Discovery Flow

```
┌─────────────┐
│  Homepage   │
└──────┬──────┘
       │
       v
┌─────────────────────────────────────────┐
│          Choose View Mode               │
├─────────────┬─────────────┬─────────────┤
│  Calendar   │    List     │    Map      │
└──────┬──────┴──────┬──────┴──────┬──────┘
       │             │             │
       └─────────────┼─────────────┘
                     v
              ┌─────────────┐
              │ Filter/Search│
              └──────┬──────┘
                     v
              ┌─────────────┐
              │Event Detail │
              └──────┬──────┘
                     │
       ┌─────────────┼─────────────┐
       v             v             v
┌───────────┐ ┌───────────┐ ┌───────────┐
│Add to Cal │ │ Directions│ │   Share   │
└───────────┘ └───────────┘ └───────────┘
```

### 8.2 Estate Sale Buyer Flow

```
┌─────────────┐
│ Find Sale   │ (QR Code, Link, or Browse)
└──────┬──────┘
       v
┌─────────────┐
│ View Items  │ (Preview Period)
└──────┬──────┘
       v
┌─────────────┐
│Verify Phone │ (SMS Code)
└──────┬──────┘
       v
┌─────────────┐
│ Make Offer  │ (Claim Period)
└──────┬──────┘
       v
┌─────────────┐
│Wait for     │
│Notification │
└──────┬──────┘
       │
       ├──────────────┐
       v              v
┌───────────┐  ┌───────────┐
│  Won!     │  │  Outbid   │
│ Pickup    │  │ Try Again │
└───────────┘  └───────────┘
```

### 8.3 Estate Sale Seller Flow

```
┌─────────────┐
│  Register   │ (Company Account)
└──────┬──────┘
       v
┌─────────────┐
│ Create Sale │ (Dates, Location)
└──────┬──────┘
       v
┌─────────────┐
│ Add Items   │ (Photos, Prices)
└──────┬──────┘
       v
┌─────────────┐
│ Activate    │ (Generate QR/Code)
└──────┬──────┘
       v
┌─────────────┐
│Monitor Offers│ (Real-time)
└──────┬──────┘
       v
┌─────────────┐
│Accept/Reject│ (End of Claim)
└──────┬──────┘
       v
┌─────────────┐
│ Coordinate  │
│   Pickup    │
└─────────────┘
```

### 8.4 Business Claiming Flow

```
┌─────────────┐
│ Find Shop   │ (Search Directory)
└──────┬──────┘
       v
┌─────────────┐
│ Click Claim │
└──────┬──────┘
       v
┌─────────────┐
│Register/Login│
└──────┬──────┘
       v
┌─────────────┐
│Submit Proof │ (Phone/Document)
└──────┬──────┘
       v
┌─────────────┐
│Admin Review │
└──────┬──────┘
       │
       ├──────────────┐
       v              v
┌───────────┐  ┌───────────┐
│ Approved  │  │ Rejected  │
│ Manage    │  │ Appeal    │
└───────────┘  └───────────┘
```

---

## 9. Technical Requirements

### 9.1 Server Requirements

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| PHP Version | 8.1 | 8.2+ |
| MySQL Version | 5.7 | 8.0+ |
| Memory | 512MB | 2GB+ |
| Storage | 10GB | 50GB+ |
| SSL | Required | Required |

### 9.2 PHP Extensions Required

- PDO + PDO_MySQL
- cURL
- JSON
- XML + DOM
- OpenSSL
- MBString
- GD (for image processing)
- ZIP

### 9.3 External Services

| Service | Purpose | Required |
|---------|---------|----------|
| Google Maps API | Maps, geocoding | Yes |
| SMTP Server | Email sending | Yes |
| SMS Gateway | Phone verification | For YFClaim |
| Segmind API | AI scraping | Optional |
| Firecrawl | Advanced scraping | Optional |

### 9.4 Performance Targets

| Metric | Target |
|--------|--------|
| Page load time | < 2 seconds |
| API response time | < 500ms |
| Time to first byte | < 200ms |
| Scraper success rate | > 95% |
| Uptime | 99.9% |

---

## 10. Security Requirements

### 10.1 Authentication

- Password hashing: bcrypt with cost 12+
- Session management: Secure, HTTP-only cookies
- CSRF protection: Token-based
- Rate limiting: Login attempts limited

### 10.2 Data Protection

- All passwords hashed, never stored plain
- API keys in environment variables only
- SQL injection prevention via prepared statements
- XSS prevention via output encoding
- Input validation on all user data

### 10.3 API Security

- HTTPS required for all endpoints
- API rate limiting per IP/user
- Authentication tokens for protected endpoints
- CORS configuration for frontend domains

### 10.4 File Upload Security

- Allowed extensions whitelist
- File type verification (magic bytes)
- Size limits enforced
- Uploaded files outside webroot
- Virus scanning (optional)

---

## 11. Deployment Architecture

### 11.1 Server Configuration

```
Production Server (backoffice.yakimafinds.com)
├── Apache 2.4
│   ├── DocumentRoot: /home/robug/YFEvents/www/html
│   ├── SSL: Let's Encrypt
│   └── mod_rewrite enabled
├── PHP 8.2 (FPM)
│   └── OPcache enabled
├── MySQL 8.0
│   └── Database: yakima_finds
└── Cron Jobs
    └── Event scraping: Daily at 2 AM
```

### 11.2 URL Structure

| URL Pattern | Handler |
|-------------|---------|
| / | Landing page |
| /calendar.php | Event calendar |
| /shops.php | Business directory |
| /api/* | REST API endpoints |
| /admin/* | Admin interface |
| /communication/* | Communication hub |
| /refactor/* | Modern architecture (testing) |

### 11.3 Deployment Process

1. **Staging**: Test changes at /refactor/ subdirectory
2. **Backup**: Database and files before deployment
3. **Deploy**: Pull from Git, run migrations
4. **Verify**: Test critical paths
5. **Monitor**: Check error logs

### 11.4 Backup Strategy

| Data | Frequency | Retention |
|------|-----------|-----------|
| Database | Daily | 30 days |
| Uploaded files | Daily | 30 days |
| Configuration | On change | Indefinite |
| Full system | Weekly | 4 weeks |

---

## Appendix A: Glossary

| Term | Definition |
|------|------------|
| **Claim** | The act of making an offer on an estate sale item |
| **Claim Period** | Time window when offers can be submitted |
| **Featured** | Highlighted/promoted content |
| **Geocoding** | Converting address to lat/lng coordinates |
| **Pick** | User-shared location for sales/events |
| **Preview Period** | Time when items can be viewed but not claimed |
| **Scraping** | Automated collection of event data from websites |
| **Source** | External website/feed that events are scraped from |
| **Verification** | Process of confirming identity/ownership |

---

## Appendix B: Status Enumerations

### Event Status
- `pending` - Awaiting approval
- `approved` - Visible to public
- `rejected` - Not approved for display

### Shop Status
- `active` - Listed and visible
- `pending` - Awaiting verification
- `inactive` - Hidden from directory

### Sale Status
- `draft` - Being created
- `active` - Live and accepting offers
- `paused` - Temporarily stopped
- `completed` - Sale finished
- `cancelled` - Sale cancelled

### Offer Status
- `pending` - Awaiting seller decision
- `accepted` - Offer won
- `rejected` - Offer declined
- `outbid` - Higher offer accepted
- `withdrawn` - Buyer cancelled offer

### User Status
- `active` - Normal access
- `suspended` - Temporarily blocked
- `pending` - Email not verified

---

## Appendix C: Configuration Keys

### Environment Variables
```
DB_HOST=localhost
DB_NAME=yakima_finds
DB_USER=yfevents
DB_PASS=********

GOOGLE_MAPS_API_KEY=********
SMTP_HOST=smtp.example.com
SMTP_USER=********
SMTP_PASS=********

APP_ENV=production
APP_DEBUG=false
APP_URL=https://backoffice.yakimafinds.com
```

---

*End of Specification*
