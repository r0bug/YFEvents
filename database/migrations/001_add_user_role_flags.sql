-- Migration: Add role flags to users table for unified user system
-- Date: 2024-12-04
-- Description: Adds role flag columns to support multiple roles per user
--              and connects users to sellers/shops for Claims and Webcat integration

-- Add role flag columns
ALTER TABLE users
ADD COLUMN is_seller BOOLEAN DEFAULT FALSE AFTER role,
ADD COLUMN is_business_owner BOOLEAN DEFAULT FALSE AFTER is_seller,
ADD COLUMN is_yf_vendor BOOLEAN DEFAULT FALSE AFTER is_business_owner,
ADD COLUMN is_yf_staff BOOLEAN DEFAULT FALSE AFTER is_yf_vendor,
ADD COLUMN is_yf_associate BOOLEAN DEFAULT FALSE AFTER is_yf_staff,
ADD COLUMN is_webcat_user BOOLEAN DEFAULT FALSE AFTER is_yf_associate;

-- Add foreign key columns for linked entities
ALTER TABLE users
ADD COLUMN seller_id INT NULL AFTER is_webcat_user,
ADD COLUMN shop_id INT NULL AFTER seller_id;

-- Add foreign key constraints (optional - depends on whether tables exist)
-- ALTER TABLE users ADD CONSTRAINT fk_users_seller FOREIGN KEY (seller_id) REFERENCES yfc_sellers(id) ON DELETE SET NULL;
-- ALTER TABLE users ADD CONSTRAINT fk_users_shop FOREIGN KEY (shop_id) REFERENCES local_shops(id) ON DELETE SET NULL;

-- Add indexes for role lookups
ALTER TABLE users
ADD INDEX idx_is_seller (is_seller),
ADD INDEX idx_is_yf_vendor (is_yf_vendor),
ADD INDEX idx_is_yf_staff (is_yf_staff),
ADD INDEX idx_is_webcat_user (is_webcat_user);

-- Update existing admin users to also be staff
UPDATE users SET is_yf_staff = TRUE WHERE role = 'admin';

-- Update existing moderators to be associates
UPDATE users SET is_yf_associate = TRUE WHERE role = 'moderator';
