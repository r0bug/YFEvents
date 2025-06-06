-- Intelligent Scraper Database Schema
-- Stores LLM-generated scraping methods and patterns

-- Table to store scraping methods generated by LLM
CREATE TABLE intelligent_scraper_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    domain VARCHAR(255) NOT NULL,
    url_pattern VARCHAR(500),
    method_type ENUM('event_list', 'event_detail', 'combined') NOT NULL,
    extraction_rules JSON NOT NULL,
    selector_mappings JSON,
    post_processing JSON,
    llm_model VARCHAR(100),
    confidence_score DECIMAL(3,2),
    test_results JSON,
    active BOOLEAN DEFAULT TRUE,
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_used TIMESTAMP NULL,
    usage_count INT DEFAULT 0,
    success_rate DECIMAL(5,2) DEFAULT 0.00,
    INDEX idx_domain (domain),
    INDEX idx_active (active),
    INDEX idx_confidence (confidence_score),
    INDEX idx_last_used (last_used)
);

-- Table to store LLM analysis sessions
CREATE TABLE intelligent_scraper_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(500) NOT NULL,
    page_content MEDIUMTEXT,
    llm_analysis JSON,
    found_events JSON,
    method_id INT NULL,
    status ENUM('analyzing', 'events_found', 'no_events', 'error', 'approved') DEFAULT 'analyzing',
    error_message TEXT,
    user_feedback TEXT,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (method_id) REFERENCES intelligent_scraper_methods(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Table to cache successful extractions for pattern learning
CREATE TABLE intelligent_scraper_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(500) NOT NULL,
    method_id INT NOT NULL,
    extracted_data JSON NOT NULL,
    extraction_time DECIMAL(10,3),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (method_id) REFERENCES intelligent_scraper_methods(id) ON DELETE CASCADE,
    INDEX idx_url (url),
    INDEX idx_method (method_id),
    INDEX idx_created_at (created_at)
);

-- Table to store URL patterns discovered by LLM
CREATE TABLE intelligent_scraper_patterns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) NOT NULL,
    pattern_type ENUM('event_list', 'event_detail', 'calendar', 'api') NOT NULL,
    url_pattern VARCHAR(500) NOT NULL,
    pattern_regex VARCHAR(500),
    example_urls JSON,
    confidence DECIMAL(3,2),
    discovered_by_session INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (discovered_by_session) REFERENCES intelligent_scraper_sessions(id) ON DELETE SET NULL,
    INDEX idx_domain (domain),
    INDEX idx_pattern_type (pattern_type),
    UNIQUE KEY unique_pattern (domain, url_pattern)
);

-- Add intelligent scraper type to calendar_sources
ALTER TABLE calendar_sources 
    MODIFY COLUMN scrape_type ENUM('ical', 'html', 'json', 'eventbrite', 'facebook', 'yakima_valley', 'intelligent') NOT NULL;

-- Add reference to intelligent method in calendar_sources
ALTER TABLE calendar_sources 
    ADD COLUMN intelligent_method_id INT NULL AFTER scrape_config,
    ADD FOREIGN KEY (intelligent_method_id) REFERENCES intelligent_scraper_methods(id) ON DELETE SET NULL;