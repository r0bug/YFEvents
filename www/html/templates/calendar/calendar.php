<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yakima Events Calendar</title>
    <link rel="stylesheet" href="/css/calendar.css">
    <link rel="stylesheet" href="/css/daily-view.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($googleMapsApiKey) ?>&libraries=places" async defer></script>
</head>
<body>
    <div class="calendar-container">
        <!-- Header -->
        <header class="calendar-header">
            <div class="header-content">
                <h1><i class="fas fa-calendar-alt"></i> Yakima Events Calendar</h1>
                <div class="header-actions">
                    <button id="location-btn" class="btn btn-outline">
                        <i class="fas fa-location-dot"></i> Find Events Near Me
                    </button>
                    <a href="/events/submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Event
                    </a>
                    <a href="/yfclaim-simple.php" class="btn btn-outline" style="margin-left: 10px;">
                        <i class="fas fa-shopping-bag"></i> Estate Sales
                    </a>
                    <a href="/admin/" class="btn btn-outline" style="margin-left: 10px;">
                        <i class="fas fa-cog"></i> Admin
                    </a>
                </div>
            </div>
        </header>

        <!-- Navigation & Filters -->
        <nav class="calendar-nav">
            <div class="nav-content">
                <!-- View Toggle -->
                <div class="view-toggle">
                    <button data-view="day" class="view-btn active">
                        <i class="fas fa-calendar-day"></i> Today
                    </button>
                    <button data-view="week" class="view-btn">
                        <i class="fas fa-calendar-week"></i> Week
                    </button>
                    <button data-view="month" class="view-btn">
                        <i class="fas fa-calendar"></i> Month
                    </button>
                    <button data-view="list" class="view-btn">
                        <i class="fas fa-list"></i> List
                    </button>
                    <button data-view="map" class="view-btn">
                        <i class="fas fa-map"></i> Map
                    </button>
                </div>

                <!-- Date Navigation -->
                <div class="date-nav">
                    <button id="prev-period" class="nav-btn">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <h2 id="current-period">Loading...</h2>
                    <button id="next-period" class="nav-btn">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <button id="today-btn" class="btn btn-outline btn-sm">Today</button>
                </div>

                <!-- Filters -->
                <div class="filters">
                    <select id="category-filter" class="filter-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category['slug']) ?>">
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="text" id="search-input" placeholder="Search events..." class="search-input">
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="calendar-main">
            <!-- Loading State -->
            <div id="loading" class="loading-state">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading events...</p>
            </div>

            <!-- Calendar Views -->
            <div id="day-view" class="calendar-view active">
                <div class="day-container">
                    <!-- Date Slider -->
                    <div class="date-slider-container">
                        <input type="date" id="date-picker" class="date-picker" value="">
                        <div class="date-slider-nav">
                            <button id="prev-day" class="date-nav-btn">
                                <i class="fas fa-chevron-left"></i> Previous Day
                            </button>
                            <button id="next-day" class="date-nav-btn">
                                Next Day <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Daily Events Display -->
                    <div class="daily-events-section">
                        <div class="daily-events-header">
                            <h3 id="daily-events-title">Today's Events</h3>
                            <span id="daily-events-count" class="events-count">0 events</span>
                        </div>
                        <div id="daily-events-list" class="daily-events-list">
                            <!-- Daily events will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Daily Map -->
                    <div class="daily-map-section">
                        <h4>Event Locations</h4>
                        <div id="daily-map" class="daily-map">
                            <!-- Map will be initialized by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <div id="month-view" class="calendar-view">
                <div class="month-grid">
                    <div class="month-header">
                        <div class="day-header">Sun</div>
                        <div class="day-header">Mon</div>
                        <div class="day-header">Tue</div>
                        <div class="day-header">Wed</div>
                        <div class="day-header">Thu</div>
                        <div class="day-header">Fri</div>
                        <div class="day-header">Sat</div>
                    </div>
                    <div id="month-calendar" class="month-calendar">
                        <!-- Calendar days will be populated by JavaScript -->
                    </div>
                </div>
            </div>

            <div id="week-view" class="calendar-view">
                <div id="week-calendar" class="week-calendar">
                    <!-- Week view will be populated by JavaScript -->
                </div>
            </div>

            <div id="list-view" class="calendar-view">
                <div id="events-list" class="events-list">
                    <!-- Events list will be populated by JavaScript -->
                </div>
            </div>

            <div id="map-view" class="calendar-view">
                <div class="map-container">
                    <div class="map-sidebar">
                        <div class="map-controls">
                            <h3>Date & Options</h3>
                            
                            <!-- Day Navigation for Map View -->
                            <div class="map-date-nav">
                                <label for="map-date-picker">Select Date:</label>
                                <div class="map-date-controls">
                                    <button id="map-prev-day" class="map-nav-btn" title="Previous Day">
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                    <input type="date" id="map-date-picker" class="map-date-input">
                                    <button id="map-next-day" class="map-nav-btn" title="Next Day">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                                <button id="map-today-btn" class="map-today-btn">Today</button>
                                <div class="selected-date-display">
                                    <span id="map-current-date">Today</span>
                                </div>
                            </div>
                            
                            <div class="map-options-divider"></div>
                            
                            <h4>Map Options</h4>
                            <label class="checkbox-label">
                                <input type="checkbox" id="show-events" checked>
                                <span>Show Events</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" id="show-shops" checked>
                                <span>Show Local Shops</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" id="cluster-markers" checked>
                                <span>Cluster Nearby Markers</span>
                            </label>
                            
                            <div class="distance-filter">
                                <label for="radius-slider">Search Radius: <span id="radius-value">10</span> miles</label>
                                <input type="range" id="radius-slider" min="1" max="50" value="10" class="slider">
                            </div>
                        </div>

                        <div class="nearby-events">
                            <h4>Events in Area</h4>
                            <div id="nearby-events-list" class="mini-events-list">
                                <!-- Populated by JavaScript -->
                            </div>
                        </div>
                    </div>

                    <div id="map" class="map-display">
                        <!-- Google Map will be initialized here -->
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Event Detail Modal -->
    <div id="event-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div id="event-details">
                <!-- Event details will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Location Permission Modal -->
    <div id="location-modal" class="modal">
        <div class="modal-content small">
            <h3>Location Access</h3>
            <p>Allow location access to find events near you?</p>
            <div class="modal-actions">
                <button id="allow-location" class="btn btn-primary">Allow</button>
                <button id="deny-location" class="btn btn-outline">Not Now</button>
            </div>
        </div>
    </div>

    <!-- Error Messages -->
    <div id="error-message" class="error-toast" style="display: none;">
        <i class="fas fa-exclamation-triangle"></i>
        <span id="error-text"></span>
        <button id="dismiss-error" class="dismiss-btn">&times;</button>
    </div>

    <!-- Success Messages -->
    <div id="success-message" class="success-toast" style="display: none;">
        <i class="fas fa-check-circle"></i>
        <span id="success-text"></span>
        <button id="dismiss-success" class="dismiss-btn">&times;</button>
    </div>

    <!-- Scripts -->
    <script src="/js/calendar.js?v=<?= time() ?>"></script>
    <script src="/js/calendar-map-fix.js?v=<?= time() ?>"></script>
    <script src="/js/map-controls.js?v=<?= time() ?>"></script>
    <script>
        // Initialize calendar with configuration
        document.addEventListener('DOMContentLoaded', function() {
            const calendar = new YakimaCalendar({
                apiEndpoint: '/api/events-simple.php',
                shopsEndpoint: '/api/shops/',
                currentDate: new Date(),
                defaultView: 'day',
                userLocation: null,
                categories: <?= json_encode($categories ?? []) ?>,
                mapOptions: {
                    center: { lat: 46.600825, lng: -120.503357 }, // Yakima Finds: 111 S. 2nd St
                    zoom: 12,
                    styles: [] // Can add custom map styles
                }
            });

            // Initialize the calendar
            calendar.init();
        });
    </script>
</body>
</html>