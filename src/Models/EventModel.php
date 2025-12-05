<?php

namespace YakimaFinds\Models;

use PDO;

class EventModel extends BaseModel
{
    protected $table = 'events';
    
    /**
     * Get events with optional filtering
     */
    public function getEvents($filters = [])
    {
        $sql = "SELECT e.*, 
                       GROUP_CONCAT(DISTINCT ec.name) as categories,
                       cs.name as source_name,
                       cs.url as source_url,
                       (SELECT filename FROM event_images WHERE event_id = e.id AND is_primary = 1 LIMIT 1) as primary_image
                FROM events e
                LEFT JOIN event_category_mapping ecr ON e.id = ecr.event_id
                LEFT JOIN event_categories ec ON ecr.category_id = ec.id
                LEFT JOIN calendar_sources cs ON e.source_id = cs.id
                WHERE 1=1";
        
        $params = [];
        
        // Status filter - handle unapproved events display
        if (isset($filters['status'])) {
            $sql .= " AND e.status = :status";
            $params['status'] = $filters['status'];
        } else {
            // Check if we should show unapproved events
            if (isset($filters['include_unapproved']) && $filters['include_unapproved']) {
                // Show both approved and pending events
                $sql .= " AND e.status IN ('approved', 'pending')";
            } else {
                // Default behavior - only approved events
                $sql .= " AND e.status = 'approved'";
            }
        }
        
        // Date range filter
        if (isset($filters['start_date'])) {
            $sql .= " AND e.start_datetime >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }
        
        if (isset($filters['end_date'])) {
            $sql .= " AND e.start_datetime <= :end_date";
            $params['end_date'] = $filters['end_date'];
        }
        
        // Filter out past events for public requests
        if (isset($filters['future_only']) && $filters['future_only']) {
            $sql .= " AND e.start_datetime >= NOW()";
        }
        
        // Location-based filter (radius in miles)
        if (isset($filters['latitude']) && isset($filters['longitude']) && isset($filters['radius'])) {
            $sql .= " AND (6371 * acos(cos(radians(:lat)) * cos(radians(e.latitude)) * 
                     cos(radians(e.longitude) - radians(:lng)) + 
                     sin(radians(:lat)) * sin(radians(e.latitude)))) <= :radius";
            $params['lat'] = $filters['latitude'];
            $params['lng'] = $filters['longitude'];
            $params['radius'] = $filters['radius'];
        }
        
        // Category filter
        if (isset($filters['category'])) {
            $sql .= " AND ec.slug = :category";
            $params['category'] = $filters['category'];
        }
        
        // Featured filter
        if (isset($filters['featured'])) {
            $sql .= " AND e.featured = :featured";
            $params['featured'] = $filters['featured'];
        }
        
        $sql .= " GROUP BY e.id ORDER BY e.start_datetime ASC";
        
        // Pagination
        if (isset($filters['limit'])) {
            $sql .= " LIMIT :limit";
            $params['limit'] = (int)$filters['limit'];
            
            if (isset($filters['offset'])) {
                $sql .= " OFFSET :offset";
                $params['offset'] = (int)$filters['offset'];
            }
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get events for today with location data
     */
    public function getTodaysEvents()
    {
        $sql = "SELECT e.*, cs.name as source_name, ei.filename as primary_image
                FROM events e
                LEFT JOIN calendar_sources cs ON e.source_id = cs.id
                LEFT JOIN event_images ei ON e.id = ei.event_id AND ei.is_primary = 1
                WHERE e.status = 'approved' 
                AND DATE(e.start_datetime) = CURDATE()
                AND e.latitude IS NOT NULL 
                AND e.longitude IS NOT NULL
                ORDER BY e.start_datetime ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get events near a location
     */
    public function getNearbyEvents($latitude, $longitude, $radius = 10)
    {
        $sql = "SELECT e.*, 
                       cs.name as source_name,
                       ei.filename as primary_image,
                       (6371 * acos(cos(radians(:lat)) * cos(radians(e.latitude)) * 
                        cos(radians(e.longitude) - radians(:lng)) + 
                        sin(radians(:lat)) * sin(radians(e.latitude)))) AS distance
                FROM events e
                LEFT JOIN calendar_sources cs ON e.source_id = cs.id
                LEFT JOIN event_images ei ON e.id = ei.event_id AND ei.is_primary = 1
                WHERE e.status = 'approved'
                AND e.latitude IS NOT NULL 
                AND e.longitude IS NOT NULL
                AND e.start_datetime >= NOW()
                HAVING distance <= :radius
                ORDER BY distance ASC, e.start_datetime ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'lat' => $latitude,
            'lng' => $longitude,
            'radius' => $radius
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create new event
     */
    public function createEvent($data)
    {
        // Geocode address if provided
        if (!empty($data['address']) && empty($data['latitude'])) {
            $coordinates = $this->geocodeAddress($data['address']);
            if ($coordinates) {
                $data['latitude'] = $coordinates['lat'];
                $data['longitude'] = $coordinates['lng'];
            }
        }
        
        $sql = "INSERT INTO events (title, description, start_datetime, end_datetime, 
                location, address, latitude, longitude, contact_info, external_url, 
                source_id, cms_user_id, status, featured, external_event_id) 
                VALUES (:title, :description, :start_datetime, :end_datetime, 
                :location, :address, :latitude, :longitude, :contact_info, :external_url, 
                :source_id, :cms_user_id, :status, :featured, :external_event_id)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_datetime' => $data['start_datetime'],
            'end_datetime' => $data['end_datetime'] ?? null,
            'location' => $data['location'] ?? null,
            'address' => $data['address'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'contact_info' => isset($data['contact_info']) ? json_encode($data['contact_info']) : null,
            'external_url' => $data['external_url'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'cms_user_id' => $data['cms_user_id'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'featured' => $data['featured'] ?? 0,
            'external_event_id' => $data['external_event_id'] ?? null
        ]);
        
        return $result ? $this->db->lastInsertId() : false;
    }
    
    /**
     * Update event
     */
    public function updateEvent($id, $data)
    {
        // Geocode address if changed
        if (!empty($data['address']) && 
            (empty($data['latitude']) || $this->addressChanged($id, $data['address']))) {
            $coordinates = $this->geocodeAddress($data['address']);
            if ($coordinates) {
                $data['latitude'] = $coordinates['lat'];
                $data['longitude'] = $coordinates['lng'];
            }
        }
        
        $setParts = [];
        $params = ['id' => $id];
        
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $setParts[] = "$key = :$key";
                $params[$key] = $value;
            }
        }
        
        if (empty($setParts)) {
            return false;
        }
        
        $sql = "UPDATE events SET " . implode(', ', $setParts) . ", updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }
    
    /**
     * Delete event
     */
    public function deleteEvent($id)
    {
        $sql = "DELETE FROM events WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Get event by ID with full details
     */
    public function getEventById($id)
    {
        $sql = "SELECT e.*, 
                       cs.name as source_name,
                       GROUP_CONCAT(DISTINCT ec.name) as categories,
                       GROUP_CONCAT(DISTINCT ei.filename) as images
                FROM events e
                LEFT JOIN calendar_sources cs ON e.source_id = cs.id
                LEFT JOIN event_category_mapping ecr ON e.id = ecr.event_id
                LEFT JOIN event_categories ec ON ecr.category_id = ec.id
                LEFT JOIN event_images ei ON e.id = ei.event_id
                WHERE e.id = :id
                GROUP BY e.id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check for duplicate events
     */
    public function findDuplicates($title, $start_datetime, $latitude = null, $longitude = null)
    {
        // Enhanced duplicate detection with multiple strategies
        
        // Strategy 1: Exact title and date match (strict duplicates)
        $exactMatches = $this->findExactDuplicates($title, $start_datetime);
        if (!empty($exactMatches)) {
            return $exactMatches;
        }
        
        // Strategy 2: Similar title with close time proximity (likely duplicates)
        $similarMatches = $this->findSimilarDuplicates($title, $start_datetime, $latitude, $longitude);
        if (!empty($similarMatches)) {
            return $similarMatches;
        }
        
        // Strategy 3: Check for recent identical events (prevent rapid re-scraping)
        $recentMatches = $this->findRecentDuplicates($title, $start_datetime);
        return $recentMatches;
    }
    
    private function findExactDuplicates($title, $start_datetime)
    {
        $sql = "SELECT id, title, start_datetime, latitude, longitude
                FROM events 
                WHERE title = :title 
                AND start_datetime = :start_datetime";
        
        $params = [
            'title' => $title,
            'start_datetime' => $start_datetime
        ];
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function findSimilarDuplicates($title, $start_datetime, $latitude = null, $longitude = null)
    {
        // Use similarity function for title matching
        // Note: Each parameter can only appear once in PDO queries with named params
        $sql = "SELECT id, title, start_datetime, latitude, longitude,
                       (CASE
                        WHEN title = :exact_title1 THEN 100
                        WHEN SOUNDEX(title) = SOUNDEX(:exact_title2) THEN 80
                        WHEN title LIKE :fuzzy_title1 THEN 60
                        ELSE 0
                       END) as similarity_score
                FROM events
                WHERE (title = :exact_title3
                       OR SOUNDEX(title) = SOUNDEX(:exact_title4)
                       OR title LIKE :fuzzy_title2)
                AND ABS(TIMESTAMPDIFF(MINUTE, start_datetime, :start_datetime)) <= 30";

        $fuzzyTitle = '%' . $title . '%';
        $params = [
            'exact_title1' => $title,
            'exact_title2' => $title,
            'exact_title3' => $title,
            'exact_title4' => $title,
            'fuzzy_title1' => $fuzzyTitle,
            'fuzzy_title2' => $fuzzyTitle,
            'start_datetime' => $start_datetime
        ];

        // Add location proximity check if coordinates provided
        if ($latitude && $longitude) {
            $sql .= " AND ((latitude IS NULL OR longitude IS NULL) OR
                     (6371 * acos(cos(radians(:lat1)) * cos(radians(latitude)) *
                      cos(radians(longitude) - radians(:lng)) +
                      sin(radians(:lat2)) * sin(radians(latitude)))) <= 0.1)";
            $params['lat1'] = $latitude;
            $params['lat2'] = $latitude;
            $params['lng'] = $longitude;
        }

        $sql .= " ORDER BY similarity_score DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Only return high-confidence matches (similarity >= 80)
        return array_filter($results, function($row) {
            return $row['similarity_score'] >= 80;
        });
    }
    
    private function findRecentDuplicates($title, $start_datetime)
    {
        // Check for events with same title scraped recently (within 24 hours)
        $sql = "SELECT id, title, start_datetime, latitude, longitude
                FROM events 
                WHERE title = :title 
                AND DATE(start_datetime) = DATE(:start_datetime)
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $params = [
            'title' => $title,
            'start_datetime' => $start_datetime
        ];
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Clean up obvious duplicate events
     */
    public function cleanupDuplicates($dryRun = true)
    {
        // Find groups of events with exact same title and datetime
        $sql = "SELECT title, start_datetime, COUNT(*) as count, GROUP_CONCAT(id) as ids
                FROM events 
                GROUP BY title, start_datetime 
                HAVING count > 1
                ORDER BY count DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $duplicateGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $cleanupResults = [
            'groups_found' => count($duplicateGroups),
            'events_to_remove' => 0,
            'removed_ids' => []
        ];
        
        foreach ($duplicateGroups as $group) {
            $ids = explode(',', $group['ids']);
            // Keep the first event, remove the rest
            $keepId = array_shift($ids);
            $removeIds = $ids;
            
            $cleanupResults['events_to_remove'] += count($removeIds);
            
            if (!$dryRun) {
                foreach ($removeIds as $removeId) {
                    $this->deleteEvent($removeId);
                    $cleanupResults['removed_ids'][] = $removeId;
                }
            }
        }
        
        return $cleanupResults;
    }
    
    /**
     * Approve event
     */
    public function approveEvent($id, $userId = null)
    {
        $data = ['status' => 'approved'];
        if ($userId) {
            $data['approved_by'] = $userId;
        }
        return $this->updateEvent($id, $data);
    }
    
    /**
     * Reject event
     */
    public function rejectEvent($id, $userId = null)
    {
        $data = ['status' => 'rejected'];
        if ($userId) {
            $data['rejected_by'] = $userId;
        }
        return $this->updateEvent($id, $data);
    }
    
    /**
     * Get pending events for approval
     */
    public function getPendingEvents()
    {
        return $this->getEvents(['status' => 'pending']);
    }
    
    /**
     * Geocode address using Google Maps API
     */
    private function geocodeAddress($address)
    {
        // This would use Google Maps Geocoding API
        // For now, return null - implement based on API key availability
        return null;
    }
    
    /**
     * Check if address has changed for an event
     */
    private function addressChanged($id, $newAddress)
    {
        $sql = "SELECT address FROM events WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $current && $current['address'] !== $newAddress;
    }
    
    /**
     * Add categories to event
     */
    public function addEventCategories($eventId, $categoryIds)
    {
        // First remove existing categories
        $sql = "DELETE FROM event_category_mapping WHERE event_id = :event_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['event_id' => $eventId]);
        
        // Add new categories
        if (!empty($categoryIds)) {
            $sql = "INSERT INTO event_category_mapping (event_id, category_id) VALUES ";
            $values = [];
            $params = ['event_id' => $eventId];
            
            foreach ($categoryIds as $index => $categoryId) {
                $values[] = "(:event_id, :category_$index)";
                $params["category_$index"] = $categoryId;
            }
            
            $sql .= implode(', ', $values);
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        }
        
        return true;
    }
}