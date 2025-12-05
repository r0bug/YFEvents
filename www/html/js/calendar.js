/**
 * Yakima Finds Event Calendar
 * Main JavaScript functionality for the calendar interface
 */

class YakimaCalendar {
    constructor(options = {}) {
        this.options = {
            apiEndpoint: '/api/events',
            shopsEndpoint: '/api/shops',
            currentDate: new Date(),
            defaultView: 'day',
            userLocation: null,
            categories: [],
            mapOptions: {
                center: { lat: 46.600825, lng: -120.503357 }, // Yakima Finds: 111 S. 2nd St
                zoom: 12
            },
            ...options
        };
        
        this.currentView = this.options.defaultView;
        this.currentDate = new Date(this.options.currentDate);
        this.events = [];
        this.shops = [];
        this.map = null;
        this.dailyMap = null;
        this.markers = [];
        this.dailyMarkers = [];
        this.infoWindow = null;
        this.dailyInfoWindow = null;
        this.markerClusterer = null;
        
        // Bind methods
        this.handleViewChange = this.handleViewChange.bind(this);
        this.handleDateNavigation = this.handleDateNavigation.bind(this);
        this.handleEventClick = this.handleEventClick.bind(this);
        this.handleLocationRequest = this.handleLocationRequest.bind(this);
        this.handleSearch = this.handleSearch.bind(this);
        this.handleFilter = this.handleFilter.bind(this);
    }
    
    /**
     * Initialize the calendar
     */
    init() {
        this.setupEventListeners();
        this.initializeMapDateControls();
        this.updatePeriodDisplay();
        this.loadEvents();
        this.showView(this.currentView);
    }
    
    /**
     * Initialize map date controls
     */
    initializeMapDateControls() {
        const mapDatePicker = document.getElementById('map-date-picker');
        if (mapDatePicker) {
            mapDatePicker.value = this.currentDate.toISOString().split('T')[0];
        }
        this.updateMapDateDisplay();
    }
    
    /**
     * Set up event listeners
     */
    setupEventListeners() {
        // View toggle buttons
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', this.handleViewChange);
        });
        
        // Date navigation
        document.getElementById('prev-period').addEventListener('click', () => {
            this.handleDateNavigation('prev');
        });
        
        document.getElementById('next-period').addEventListener('click', () => {
            this.handleDateNavigation('next');
        });
        
        document.getElementById('today-btn').addEventListener('click', () => {
            this.currentDate = new Date();
            this.updatePeriodDisplay();
            this.loadEvents();
            this.renderCurrentView();
        });
        
        // Location button
        document.getElementById('location-btn').addEventListener('click', this.handleLocationRequest);
        
        // Search and filters
        document.getElementById('search-input').addEventListener('input', this.handleSearch);
        document.getElementById('category-filter').addEventListener('change', this.handleFilter);
        
        // Modal close
        document.querySelector('.close').addEventListener('click', () => {
            this.closeModal();
        });
        
        // Click outside modal to close
        document.getElementById('event-modal').addEventListener('click', (e) => {
            if (e.target.id === 'event-modal') {
                this.closeModal();
            }
        });
        
        // Map controls
        if (document.getElementById('show-events')) {
            document.getElementById('show-events').addEventListener('change', this.updateMapMarkers.bind(this));
        }
        
        if (document.getElementById('show-shops')) {
            document.getElementById('show-shops').addEventListener('change', this.updateMapMarkers.bind(this));
        }
        
        if (document.getElementById('cluster-markers')) {
            document.getElementById('cluster-markers').addEventListener('change', this.updateMapMarkers.bind(this));
        }
        
        if (document.getElementById('radius-slider')) {
            document.getElementById('radius-slider').addEventListener('input', (e) => {
                document.getElementById('radius-value').textContent = e.target.value;
                this.updateNearbyEvents();
            });
        }
        
        // Map date navigation controls
        if (document.getElementById('map-date-picker')) {
            document.getElementById('map-date-picker').addEventListener('change', (e) => {
                this.currentDate = new Date(e.target.value);
                this.updateMapDateDisplay();
                this.loadEvents();
                this.renderCurrentView();
            });
        }
        
        if (document.getElementById('map-prev-day')) {
            document.getElementById('map-prev-day').addEventListener('click', () => {
                this.currentDate.setDate(this.currentDate.getDate() - 1);
                this.updateMapDateControls();
                this.loadEvents();
                this.renderCurrentView();
            });
        }
        
        if (document.getElementById('map-next-day')) {
            document.getElementById('map-next-day').addEventListener('click', () => {
                this.currentDate.setDate(this.currentDate.getDate() + 1);
                this.updateMapDateControls();
                this.loadEvents();
                this.renderCurrentView();
            });
        }
        
        if (document.getElementById('map-today-btn')) {
            document.getElementById('map-today-btn').addEventListener('click', () => {
                this.currentDate = new Date();
                this.updateMapDateControls();
                this.loadEvents();
                this.renderCurrentView();
            });
        }
        
        // Daily view controls
        document.getElementById('date-picker')?.addEventListener('change', (e) => {
            this.currentDate = new Date(e.target.value);
            this.loadEvents();
            this.renderCurrentView();
        });
        
        document.getElementById('prev-day')?.addEventListener('click', () => {
            this.currentDate.setDate(this.currentDate.getDate() - 1);
            this.updateDatePicker();
            this.loadEvents();
            this.renderCurrentView();
        });
        
        document.getElementById('next-day')?.addEventListener('click', () => {
            this.currentDate.setDate(this.currentDate.getDate() + 1);
            this.updateDatePicker();
            this.loadEvents();
            this.renderCurrentView();
        });

        // Dismiss error/success messages
        document.getElementById('dismiss-error')?.addEventListener('click', () => {
            this.hideMessage('error');
        });
        
        document.getElementById('dismiss-success')?.addEventListener('click', () => {
            this.hideMessage('success');
        });
    }
    
    /**
     * Handle view change
     */
    handleViewChange(e) {
        const view = e.target.closest('.view-btn').dataset.view;
        this.showView(view);
    }
    
    /**
     * Show specific view
     */
    showView(view) {
        this.currentView = view;
        
        // Update active view button
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.view === view);
        });
        
        // Hide all views
        document.querySelectorAll('.calendar-view').forEach(viewEl => {
            viewEl.classList.remove('active');
        });
        
        // Show current view
        document.getElementById(`${view}-view`).classList.add('active');
        
        // Initialize map if switching to map view
        if (view === 'map' && !this.map) {
            this.initializeMap();
        }
        
        // Initialize daily map if switching to day view
        if (view === 'day' && !this.dailyMap) {
            this.initializeDailyMap();
        }
        
        // Update date picker for daily view
        if (view === 'day') {
            this.updateDatePicker();
        }
        
        this.renderCurrentView();
    }
    
    /**
     * Handle date navigation
     */
    handleDateNavigation(direction) {
        const currentDate = new Date(this.currentDate);
        
        switch (this.currentView) {
            case 'day':
                currentDate.setDate(currentDate.getDate() + (direction === 'next' ? 1 : -1));
                break;
            case 'week':
                currentDate.setDate(currentDate.getDate() + (direction === 'next' ? 7 : -7));
                break;
            case 'month':
                currentDate.setMonth(currentDate.getMonth() + (direction === 'next' ? 1 : -1));
                break;
            case 'list':
                currentDate.setMonth(currentDate.getMonth() + (direction === 'next' ? 1 : -1));
                break;
        }
        
        this.currentDate = currentDate;
        this.updatePeriodDisplay();
        this.loadEvents();
        this.renderCurrentView();
    }
    
    /**
     * Update period display
     */
    updatePeriodDisplay() {
        const periodEl = document.getElementById('current-period');
        let periodText = '';
        
        switch (this.currentView) {
            case 'day':
                periodText = this.currentDate.toLocaleDateString('en-US', { 
                    weekday: 'long',
                    month: 'long', 
                    day: 'numeric',
                    year: 'numeric' 
                });
                break;
            case 'week':
                const weekStart = this.getWeekStart(this.currentDate);
                const weekEnd = new Date(weekStart);
                weekEnd.setDate(weekEnd.getDate() + 6);
                
                periodText = `${weekStart.toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric' 
                })} - ${weekEnd.toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric', 
                    year: 'numeric' 
                })}`;
                break;
            case 'list':
            case 'map':
                periodText = this.currentDate.toLocaleDateString('en-US', { 
                    month: 'long', 
                    year: 'numeric' 
                });
                break;
        }
        
        periodEl.textContent = periodText;
    }
    
    /**
     * Update map date controls
     */
    updateMapDateControls() {
        const mapDatePicker = document.getElementById('map-date-picker');
        if (mapDatePicker) {
            mapDatePicker.value = this.currentDate.toISOString().split('T')[0];
        }
        this.updateMapDateDisplay();
        this.updatePeriodDisplay();
    }
    
    /**
     * Update map date display
     */
    updateMapDateDisplay() {
        const mapCurrentDate = document.getElementById('map-current-date');
        if (mapCurrentDate) {
            const today = new Date();
            const isToday = this.isSameDate(this.currentDate, today);
            
            if (isToday) {
                mapCurrentDate.textContent = 'Today';
            } else {
                mapCurrentDate.textContent = this.currentDate.toLocaleDateString('en-US', {
                    weekday: 'short',
                    month: 'short',
                    day: 'numeric',
                    year: this.currentDate.getFullYear() !== today.getFullYear() ? 'numeric' : undefined
                });
            }
        }
    }
    
    /**
     * Load events from API
     */
    async loadEvents() {
        this.showLoading(true);
        
        try {
            const params = new URLSearchParams();
            
            // Date range based on current view
            const { startDate, endDate } = this.getDateRange();
            params.append('start_date', startDate);
            params.append('end_date', endDate);
            
            // Add filters
            const categoryFilter = document.getElementById('category-filter').value;
            if (categoryFilter) {
                params.append('category', categoryFilter);
            }
            
            const searchQuery = document.getElementById('search-input').value.trim();
            if (searchQuery) {
                params.append('search', searchQuery);
            }
            
            // Add location if available
            if (this.options.userLocation) {
                params.append('latitude', this.options.userLocation.lat);
                params.append('longitude', this.options.userLocation.lng);
                params.append('radius', 50); // 50 miles radius
            }
            
            const response = await fetch(`${this.options.apiEndpoint}?${params}`);
            const data = await response.json();
            
            this.events = data.events || [];
            this.renderCurrentView();
            
        } catch (error) {
            console.error('Error loading events:', error);
            this.showMessage('error', 'Failed to load events. Please try again.');
        } finally {
            this.showLoading(false);
        }
    }
    
    /**
     * Load shops from API
     */
    async loadShops() {
        try {
            const params = new URLSearchParams();
            params.append('status', 'active');
            
            if (this.options.userLocation) {
                params.append('latitude', this.options.userLocation.lat);
                params.append('longitude', this.options.userLocation.lng);
                params.append('radius', 25); // 25 miles radius
            }
            
            const response = await fetch(`${this.options.shopsEndpoint}?${params}`);
            const data = await response.json();
            
            this.shops = data.shops || [];
            this.updateMapMarkers();
            
        } catch (error) {
            console.error('Error loading shops:', error);
        }
    }
    
    /**
     * Get date range for current view
     */
    getDateRange() {
        let startDate, endDate;
        
        switch (this.currentView) {
            case 'day':
                startDate = new Date(this.currentDate);
                endDate = new Date(this.currentDate);
                break;
            case 'month':
                startDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), 1);
                endDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 0);
                break;
            case 'week':
                startDate = this.getWeekStart(this.currentDate);
                endDate = new Date(startDate);
                endDate.setDate(endDate.getDate() + 6);
                break;
            case 'list':
            case 'map':
                startDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), 1);
                endDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 0);
                break;
        }
        
        return {
            startDate: startDate.toISOString().split('T')[0],
            endDate: endDate.toISOString().split('T')[0]
        };
    }
    
    /**
     * Render current view
     */
    renderCurrentView() {
        switch (this.currentView) {
            case 'day':
                this.renderDayView();
                break;
            case 'month':
                this.renderMonthView();
                break;
            case 'week':
                this.renderWeekView();
                break;
            case 'list':
                this.renderListView();
                break;
            case 'map':
                this.renderMapView();
                break;
        }
    }
    
    /**
     * Render month view
     */
    renderMonthView() {
        const calendar = document.getElementById('month-calendar');
        calendar.innerHTML = '';
        
        const firstDay = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), 1);
        const lastDay = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 0);
        const startDate = this.getWeekStart(firstDay);
        
        let currentDate = new Date(startDate);
        const today = new Date();
        
        // Generate 6 weeks (42 days) to fill the calendar
        for (let i = 0; i < 42; i++) {
            const dayEl = document.createElement('div');
            dayEl.className = 'calendar-day';
            
            const isCurrentMonth = currentDate.getMonth() === this.currentDate.getMonth();
            const isToday = this.isSameDate(currentDate, today);
            
            if (!isCurrentMonth) {
                dayEl.classList.add('other-month');
            }
            
            if (isToday) {
                dayEl.classList.add('today');
            }
            
            // Day number
            const dayNumber = document.createElement('div');
            dayNumber.className = 'day-number';
            dayNumber.textContent = currentDate.getDate();
            dayEl.appendChild(dayNumber);
            
            // Events for this day
            const dayEvents = this.getEventsForDate(currentDate);
            if (dayEvents.length > 0) {
                dayEl.classList.add('has-events');
                
                const eventsContainer = document.createElement('div');
                eventsContainer.className = 'day-events';
                
                // Show up to 3 events, then "X more"
                const visibleEvents = dayEvents.slice(0, 3);
                visibleEvents.forEach(event => {
                    const eventEl = document.createElement('div');
                    eventEl.className = 'day-event';
                    if (event.featured) {
                        eventEl.classList.add('featured');
                    }
                    eventEl.textContent = event.title;
                    eventEl.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.showEventDetails(event);
                    });
                    eventsContainer.appendChild(eventEl);
                });
                
                if (dayEvents.length > 3) {
                    const moreEl = document.createElement('div');
                    moreEl.className = 'more-events';
                    moreEl.textContent = `+${dayEvents.length - 3} more`;
                    moreEl.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.showDayEvents(currentDate, dayEvents);
                    });
                    eventsContainer.appendChild(moreEl);
                }
                
                dayEl.appendChild(eventsContainer);
            }
            
            // Add click handler for day
            dayEl.addEventListener('click', () => {
                this.handleDayClick(new Date(currentDate));
            });
            
            calendar.appendChild(dayEl);
            currentDate.setDate(currentDate.getDate() + 1);
        }
    }
    
    /**
     * Render week view
     */
    renderWeekView() {
        const weekCalendar = document.getElementById('week-calendar');
        weekCalendar.innerHTML = '';
        
        // Create header
        const header = document.createElement('div');
        header.className = 'week-header';
        
        // Time column header
        const timeHeader = document.createElement('div');
        timeHeader.className = 'week-time-header';
        timeHeader.textContent = 'Time';
        header.appendChild(timeHeader);
        
        // Day headers
        const weekStart = this.getWeekStart(this.currentDate);
        for (let i = 0; i < 7; i++) {
            const day = new Date(weekStart);
            day.setDate(day.getDate() + i);
            
            const dayHeader = document.createElement('div');
            dayHeader.className = 'week-day-header';
            dayHeader.innerHTML = `
                <div>${day.toLocaleDateString('en-US', { weekday: 'short' })}</div>
                <div>${day.getDate()}</div>
            `;
            header.appendChild(dayHeader);
        }
        
        weekCalendar.appendChild(header);
        
        // Create time slots and day columns
        const body = document.createElement('div');
        body.className = 'week-body';
        
        for (let hour = 0; hour < 24; hour++) {
            // Time slot
            const timeSlot = document.createElement('div');
            timeSlot.className = 'time-slot';
            timeSlot.textContent = this.formatHour(hour);
            body.appendChild(timeSlot);
            
            // Day slots
            for (let day = 0; day < 7; day++) {
                const daySlot = document.createElement('div');
                daySlot.className = 'week-day-slot';
                
                // Add events for this hour/day
                const currentDay = new Date(weekStart);
                currentDay.setDate(currentDay.getDate() + day);
                
                const hourEvents = this.getEventsForHour(currentDay, hour);
                hourEvents.forEach(event => {
                    const eventEl = document.createElement('div');
                    eventEl.className = 'week-event';
                    eventEl.textContent = event.title;
                    eventEl.addEventListener('click', () => {
                        this.showEventDetails(event);
                    });
                    daySlot.appendChild(eventEl);
                });
                
                body.appendChild(daySlot);
            }
        }
        
        weekCalendar.appendChild(body);
    }
    
    /**
     * Render list view
     */
    renderListView() {
        const eventsList = document.getElementById('events-list');
        eventsList.innerHTML = '';
        
        if (this.events.length === 0) {
            eventsList.innerHTML = '<div class="no-events">No events found for this period.</div>';
            return;
        }
        
        // Group events by date
        const eventsByDate = this.groupEventsByDate(this.events);
        
        Object.keys(eventsByDate).sort().forEach(dateKey => {
            const events = eventsByDate[dateKey];
            const date = new Date(dateKey);
            
            // Date header
            const dateHeader = document.createElement('div');
            dateHeader.className = 'date-header';
            dateHeader.innerHTML = `
                <h3>${date.toLocaleDateString('en-US', { 
                    weekday: 'long', 
                    month: 'long', 
                    day: 'numeric', 
                    year: 'numeric' 
                })}</h3>
            `;
            eventsList.appendChild(dateHeader);
            
            // Events for this date
            events.forEach(event => {
                const eventItem = this.createEventListItem(event);
                eventsList.appendChild(eventItem);
            });
        });
    }
    
    /**
     * Render map view
     */
    renderMapView() {
        if (this.map) {
            this.updateMapMarkers();
            this.updateNearbyEvents();
        }
    }
    
    /**
     * Initialize Google Map
     */
    initializeMap() {
        const mapEl = document.getElementById('map');
        
        this.map = new google.maps.Map(mapEl, {
            center: this.options.mapOptions.center,
            zoom: this.options.mapOptions.zoom,
            styles: this.options.mapOptions.styles || []
        });
        
        this.infoWindow = new google.maps.InfoWindow();
        
        // Load and display markers
        this.loadShops();
        this.updateMapMarkers();
        
        // Update nearby events when map bounds change
        this.map.addListener('bounds_changed', () => {
            this.updateNearbyEvents();
        });
    }
    
    /**
     * Update map markers
     */
    updateMapMarkers() {
        if (!this.map) return;
        
        // Clear existing markers
        this.clearMarkers();
        
        const showEvents = document.getElementById('show-events')?.checked !== false;
        const showShops = document.getElementById('show-shops')?.checked === true;
        const clusterMarkers = document.getElementById('cluster-markers')?.checked !== false;
        
        // Add event markers - filter by current date in map view
        if (showEvents) {
            this.events.forEach(event => {
                if (event.latitude && event.longitude) {
                    // In map view, only show events for the current date
                    if (this.currentView === 'map') {
                        const eventDate = new Date(event.start_datetime);
                        if (this.isSameDate(eventDate, this.currentDate)) {
                            this.addEventMarker(event);
                        }
                    } else {
                        this.addEventMarker(event);
                    }
                }
            });
        }
        
        // Add shop markers
        if (showShops) {
            this.shops.forEach(shop => {
                if (shop.latitude && shop.longitude) {
                    this.addShopMarker(shop);
                }
            });
        }
        
        // Initialize marker clustering if enabled
        if (clusterMarkers && this.markers.length > 0) {
            this.initializeMarkerClusterer();
        }
    }
    
    /**
     * Add event marker to map
     */
    addEventMarker(event) {
        const marker = new google.maps.Marker({
            position: { lat: parseFloat(event.latitude), lng: parseFloat(event.longitude) },
            map: this.map,
            title: event.title,
            icon: {
                url: '/images/event-marker.png',
                scaledSize: new google.maps.Size(30, 30)
            }
        });
        
        marker.addListener('click', () => {
            this.showEventInfoWindow(event, marker);
        });
        
        this.markers.push(marker);
    }
    
    /**
     * Add shop marker to map
     */
    addShopMarker(shop) {
        const marker = new google.maps.Marker({
            position: { lat: parseFloat(shop.latitude), lng: parseFloat(shop.longitude) },
            map: this.map,
            title: shop.name,
            icon: {
                url: '/images/shop-marker.png',
                scaledSize: new google.maps.Size(25, 25)
            }
        });
        
        marker.addListener('click', () => {
            this.showShopInfoWindow(shop, marker);
        });
        
        this.markers.push(marker);
    }
    
    /**
     * Show event info window
     */
    showEventInfoWindow(event, marker) {
        const eventDate = new Date(event.start_datetime);
        const content = `
            <div class="info-window">
                <h4>${event.title}</h4>
                <p><i class="fas fa-calendar"></i> ${eventDate.toLocaleDateString()}</p>
                <p><i class="fas fa-clock"></i> ${eventDate.toLocaleTimeString()}</p>
                ${event.location ? `<p><i class="fas fa-map-marker-alt"></i> ${event.location}</p>` : ''}
                <button onclick="calendar.showEventDetails(${event.id})" class="btn btn-sm btn-primary">
                    View Details
                </button>
            </div>
        `;
        
        this.infoWindow.setContent(content);
        this.infoWindow.open(this.map, marker);
    }
    
    /**
     * Show shop info window
     */
    showShopInfoWindow(shop, marker) {
        const content = `
            <div class="info-window">
                <h4>${shop.name}</h4>
                ${shop.category_name ? `<p><i class="fas fa-tag"></i> ${shop.category_name}</p>` : ''}
                ${shop.address ? `<p><i class="fas fa-map-marker-alt"></i> ${shop.address}</p>` : ''}
                ${shop.phone ? `<p><i class="fas fa-phone"></i> ${shop.phone}</p>` : ''}
                ${shop.website ? `<a href="${shop.website}" target="_blank" class="btn btn-sm btn-outline">Visit Website</a>` : ''}
            </div>
        `;
        
        this.infoWindow.setContent(content);
        this.infoWindow.open(this.map, marker);
    }
    
    /**
     * Clear all markers
     */
    clearMarkers() {
        this.markers.forEach(marker => {
            marker.setMap(null);
        });
        this.markers = [];
        
        if (this.markerClusterer) {
            this.markerClusterer.clearMarkers();
        }
    }
    
    /**
     * Initialize marker clustering
     */
    initializeMarkerClusterer() {
        // Note: This requires MarkerClustererPlus library
        if (typeof MarkerClusterer !== 'undefined') {
            this.markerClusterer = new MarkerClusterer(this.map, this.markers, {
                imagePath: 'https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/m'
            });
        }
    }
    
    /**
     * Update nearby events list
     */
    updateNearbyEvents() {
        if (!this.map) return;
        
        const bounds = this.map.getBounds();
        if (!bounds) return;
        
        const nearbyEventsList = document.getElementById('nearby-events-list');
        if (!nearbyEventsList) return;
        
        nearbyEventsList.innerHTML = '';
        
        // Filter events within map bounds and by current date
        const visibleEvents = this.events.filter(event => {
            if (!event.latitude || !event.longitude) return false;
            
            const position = new google.maps.LatLng(
                parseFloat(event.latitude), 
                parseFloat(event.longitude)
            );
            
            if (!bounds.contains(position)) return false;
            
            // In map view, only show events for the current date
            if (this.currentView === 'map') {
                const eventDate = new Date(event.start_datetime);
                return this.isSameDate(eventDate, this.currentDate);
            }
            
            return true;
        });
        
        if (visibleEvents.length === 0) {
            const message = this.currentView === 'map' 
                ? 'No events for selected date in current map area.'
                : 'No events in current map area.';
            nearbyEventsList.innerHTML = `<p>${message}</p>`;
            return;
        }
        
        // Sort by date
        visibleEvents.sort((a, b) => new Date(a.start_datetime) - new Date(b.start_datetime));
        
        // Display events
        visibleEvents.slice(0, 10).forEach(event => {
            const eventEl = document.createElement('div');
            eventEl.className = 'mini-event-item';
            
            const eventDate = new Date(event.start_datetime);
            eventEl.innerHTML = `
                <div class="mini-event-title">${event.title}</div>
                <div class="mini-event-time">${eventDate.toLocaleDateString()} at ${eventDate.toLocaleTimeString()}</div>
            `;
            
            eventEl.addEventListener('click', () => {
                this.showEventDetails(event);
            });
            
            nearbyEventsList.appendChild(eventEl);
        });
        
        if (visibleEvents.length > 10) {
            const moreEl = document.createElement('div');
            moreEl.className = 'more-events-notice';
            moreEl.textContent = `+${visibleEvents.length - 10} more events in area`;
            nearbyEventsList.appendChild(moreEl);
        }
    }
    
    /**
     * Handle location request
     */
    handleLocationRequest() {
        if (!navigator.geolocation) {
            this.showMessage('error', 'Geolocation is not supported by this browser.');
            return;
        }
        
        this.showLocationModal();
    }
    
    /**
     * Show location permission modal
     */
    showLocationModal() {
        const modal = document.getElementById('location-modal');
        modal.style.display = 'block';
        
        document.getElementById('allow-location').addEventListener('click', () => {
            this.requestLocation();
            modal.style.display = 'none';
        });
        
        document.getElementById('deny-location').addEventListener('click', () => {
            modal.style.display = 'none';
        });
    }
    
    /**
     * Request user location
     */
    requestLocation() {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                this.options.userLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };
                
                this.showMessage('success', 'Location found! Showing events near you.');
                
                // Update map center if map is active
                if (this.map && this.currentView === 'map') {
                    this.map.setCenter(this.options.userLocation);
                    this.map.setZoom(14);
                }
                
                // Reload events with location
                this.loadEvents();
                
                // Load nearby shops
                this.loadShops();
            },
            (error) => {
                let message = 'Unable to get your location.';
                switch (error.code) {
                    case error.PERMISSION_DENIED:
                        message = 'Location access denied by user.';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        message = 'Location information is unavailable.';
                        break;
                    case error.TIMEOUT:
                        message = 'Location request timed out.';
                        break;
                }
                this.showMessage('error', message);
            }
        );
    }
    
    /**
     * Handle search input
     */
    handleSearch() {
        // Debounce search
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
            this.loadEvents();
        }, 500);
    }
    
    /**
     * Handle filter change
     */
    handleFilter() {
        this.loadEvents();
    }
    
    /**
     * Handle event click
     */
    handleEventClick(event) {
        this.showEventDetails(event);
    }
    
    /**
     * Handle day click in calendar
     */
    handleDayClick(date) {
        // Could implement day detail view or switch to list view for that day
        console.log('Day clicked:', date);
    }
    
    /**
     * Show event details modal
     */
    showEventDetails(eventData) {
        // If eventData is just an ID, fetch full details
        if (typeof eventData === 'number') {
            this.fetchEventDetails(eventData);
            return;
        }
        
        const modal = document.getElementById('event-modal');
        const details = document.getElementById('event-details');
        
        const eventDate = new Date(eventData.start_datetime);
        const endDate = eventData.end_datetime ? new Date(eventData.end_datetime) : null;
        
        details.innerHTML = `
            <div class="event-detail-header">
                <h2 class="event-detail-title">${eventData.title}</h2>
                ${eventData.featured ? '<span class="featured-badge">Featured Event</span>' : ''}
            </div>
            
            <div class="event-detail-meta">
                <p><i class="fas fa-calendar"></i> ${eventDate.toLocaleDateString('en-US', { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                })}</p>
                <p><i class="fas fa-clock"></i> ${eventDate.toLocaleTimeString('en-US', { 
                    hour: 'numeric', 
                    minute: '2-digit' 
                })}${endDate ? ` - ${endDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}` : ''}</p>
                ${eventData.location ? `<p><i class="fas fa-map-marker-alt"></i> ${eventData.location}</p>` : ''}
            </div>
            
            ${eventData.is_unapproved ? `
                <div class="event-disclaimer">
                    <i class="fas fa-exclamation-triangle"></i> ${eventData.disclaimer}
                </div>
            ` : ''}
            
            ${eventData.description ? `
                <div class="event-detail-description">
                    ${eventData.description.replace(/\n/g, '<br>')}
                </div>
            ` : ''}
            
            ${eventData.source_name ? `
                <div class="event-source-attribution">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Source:</strong> 
                    ${eventData.external_url ? 
                        `<a href="${eventData.external_url}" target="_blank" rel="noopener">${eventData.source_name} <i class="fas fa-external-link-alt"></i></a>` :
                        `<a href="${eventData.source_url || '#'}" target="_blank" rel="noopener">${eventData.source_name} <i class="fas fa-external-link-alt"></i></a>`
                    }
                </div>
            ` : ''}
            
            ${eventData.categories ? `
                <div class="event-categories">
                    ${eventData.categories.split(',').map(cat => `<span class="category-tag">${cat.trim()}</span>`).join('')}
                </div>
            ` : ''}
            
            <div class="event-actions">
                ${eventData.external_url ? `<a href="${eventData.external_url}" target="_blank" class="btn btn-primary">
                    <i class="fas fa-external-link-alt"></i> More Info
                </a>` : ''}
                
                ${eventData.latitude && eventData.longitude ? `
                    <button onclick="calendar.getDirections(${eventData.latitude}, ${eventData.longitude})" class="btn btn-outline">
                        <i class="fas fa-directions"></i> Get Directions
                    </button>
                ` : ''}
                
                <button onclick="calendar.addToCalendar(${JSON.stringify(eventData).replace(/"/g, '&quot;')})" class="btn btn-outline">
                    <i class="fas fa-calendar-plus"></i> Add to Calendar
                </button>
            </div>
        `;
        
        modal.style.display = 'block';
    }
    
    /**
     * Fetch event details from API
     */
    async fetchEventDetails(eventId) {
        try {
            const response = await fetch(`${this.options.apiEndpoint}/${eventId}`);
            const eventData = await response.json();
            this.showEventDetails(eventData);
        } catch (error) {
            console.error('Error fetching event details:', error);
            this.showMessage('error', 'Failed to load event details.');
        }
    }
    
    /**
     * Close modal
     */
    closeModal() {
        document.getElementById('event-modal').style.display = 'none';
    }
    
    /**
     * Get directions to event
     */
    getDirections(lat, lng) {
        const url = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
        window.open(url, '_blank');
    }
    
    /**
     * Add event to calendar
     */
    addToCalendar(eventData) {
        const startDate = new Date(eventData.start_datetime);
        const endDate = eventData.end_datetime ? new Date(eventData.end_datetime) : new Date(startDate.getTime() + 60 * 60 * 1000);
        
        const params = new URLSearchParams({
            action: 'TEMPLATE',
            text: eventData.title,
            dates: `${this.formatDateForCalendar(startDate)}/${this.formatDateForCalendar(endDate)}`,
            details: eventData.description || '',
            location: eventData.location || ''
        });
        
        window.open(`https://calendar.google.com/calendar/render?${params}`, '_blank');
    }
    
    /**
     * Format date for calendar export
     */
    formatDateForCalendar(date) {
        return date.toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z';
    }
    
    /**
     * Show loading state
     */
    showLoading(show) {
        const loading = document.getElementById('loading');
        loading.style.display = show ? 'block' : 'none';
    }
    
    /**
     * Show message toast
     */
    showMessage(type, message) {
        const messageEl = document.getElementById(`${type}-message`);
        const textEl = document.getElementById(`${type}-text`);
        
        textEl.textContent = message;
        messageEl.style.display = 'flex';
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            this.hideMessage(type);
        }, 5000);
    }
    
    /**
     * Hide message toast
     */
    hideMessage(type) {
        document.getElementById(`${type}-message`).style.display = 'none';
    }
    
    /**
     * Utility functions
     */
    
    getWeekStart(date) {
        const d = new Date(date);
        const day = d.getDay();
        const diff = d.getDate() - day;
        return new Date(d.setDate(diff));
    }
    
    isSameDate(date1, date2) {
        return date1.getFullYear() === date2.getFullYear() &&
               date1.getMonth() === date2.getMonth() &&
               date1.getDate() === date2.getDate();
    }
    
    getEventsForDate(date) {
        return this.events.filter(event => {
            const eventDate = new Date(event.start_datetime);
            return this.isSameDate(eventDate, date);
        });
    }
    
    getEventsForHour(date, hour) {
        return this.events.filter(event => {
            const eventDate = new Date(event.start_datetime);
            return this.isSameDate(eventDate, date) && eventDate.getHours() === hour;
        });
    }
    
    formatHour(hour) {
        if (hour === 0) return '12 AM';
        if (hour < 12) return `${hour} AM`;
        if (hour === 12) return '12 PM';
        return `${hour - 12} PM`;
    }
    
    groupEventsByDate(events) {
        const grouped = {};
        events.forEach(event => {
            const date = new Date(event.start_datetime);
            const dateKey = date.toISOString().split('T')[0];
            
            if (!grouped[dateKey]) {
                grouped[dateKey] = [];
            }
            grouped[dateKey].push(event);
        });
        return grouped;
    }
    
    createEventListItem(event) {
        const eventDate = new Date(event.start_datetime);
        const eventEl = document.createElement('div');
        eventEl.className = `event-item ${event.is_unapproved ? 'event-unapproved' : ''}`;
        
        eventEl.innerHTML = `
            <div class="event-date">
                <div class="event-month">${eventDate.toLocaleDateString('en-US', { month: 'short' })}</div>
                <div class="event-day">${eventDate.getDate()}</div>
            </div>
            <div class="event-content">
                <h3 class="event-title">
                    ${event.title}
                    ${event.is_unapproved ? '<span class="unapproved-badge">Unverified</span>' : ''}
                </h3>
                <div class="event-meta">
                    <span><i class="fas fa-clock"></i> ${eventDate.toLocaleTimeString('en-US', { 
                        hour: 'numeric', 
                        minute: '2-digit' 
                    })}</span>
                    ${event.location ? `<span><i class="fas fa-map-marker-alt"></i> ${event.location}</span>` : ''}
                    ${event.source_name ? `<span class="event-source"><i class="fas fa-external-link-alt"></i> ${event.source_name}</span>` : ''}
                </div>
                ${event.is_unapproved ? `<div class="event-disclaimer">
                    <i class="fas fa-exclamation-triangle"></i> ${event.disclaimer}
                </div>` : ''}
                ${event.description ? `<div class="event-description">${this.truncateText(event.description, 150)}</div>` : ''}
                ${event.categories ? `
                    <div class="event-categories">
                        ${event.categories.split(',').map(cat => `<span class="category-tag">${cat.trim()}</span>`).join('')}
                    </div>
                ` : ''}
                ${event.source_name && event.source_id ? `
                    <div class="event-source-attribution">
                        <i class="fas fa-info-circle"></i> 
                        Source: ${event.external_url ? 
                            `<a href="${event.external_url}" target="_blank" rel="noopener">${event.source_name} <i class="fas fa-external-link-alt"></i></a>` :
                            `<a href="${event.source_url || '#'}" target="_blank" rel="noopener">${event.source_name} <i class="fas fa-external-link-alt"></i></a>`
                        }
                    </div>
                ` : ''}
            </div>
        `;
        
        eventEl.addEventListener('click', () => {
            this.showEventDetails(event);
        });
        
        return eventEl;
    }
    
    truncateText(text, maxLength) {
        if (text.length <= maxLength) return text;
        return text.substr(0, maxLength) + '...';
    }
    
    showDayEvents(date, events) {
        // Could implement a day-specific modal or switch to list view filtered by day
        console.log('Show day events:', date, events);
    }
    
    /**
     * Update date picker value
     */
    updateDatePicker() {
        const datePicker = document.getElementById('date-picker');
        if (datePicker) {
            datePicker.value = this.currentDate.toISOString().split('T')[0];
        }
    }
    
    /**
     * Initialize daily map
     */
    initializeDailyMap() {
        const mapElement = document.getElementById('daily-map');
        if (!mapElement || !window.google) return;
        
        this.dailyMap = new google.maps.Map(mapElement, {
            center: this.options.mapOptions.center,
            zoom: this.options.mapOptions.zoom,
            styles: [
                {
                    featureType: 'poi',
                    elementType: 'labels',
                    stylers: [{ visibility: 'off' }]
                }
            ]
        });
        
        this.dailyInfoWindow = new google.maps.InfoWindow();
    }
    
    /**
     * Render daily view
     */
    renderDayView() {
        // Get today's events
        const today = this.currentDate.toISOString().split('T')[0];
        const todayEvents = this.events.filter(event => {
            const eventDate = new Date(event.start_datetime).toISOString().split('T')[0];
            return eventDate === today;
        });
        
        // Update title and count
        const titleEl = document.getElementById('daily-events-title');
        const countEl = document.getElementById('daily-events-count');
        
        if (titleEl) {
            const isToday = today === new Date().toISOString().split('T')[0];
            titleEl.textContent = isToday ? "Today's Events" : `Events for ${this.currentDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric' })}`;
        }
        
        if (countEl) {
            countEl.textContent = `${todayEvents.length} event${todayEvents.length !== 1 ? 's' : ''}`;
        }
        
        // Render events list
        this.renderDailyEventsList(todayEvents);
        
        // Update daily map
        this.updateDailyMap(todayEvents);
    }
    
    /**
     * Render daily events list
     */
    renderDailyEventsList(events) {
        const listEl = document.getElementById('daily-events-list');
        if (!listEl) return;
        
        if (events.length === 0) {
            listEl.innerHTML = `
                <div class="no-daily-events">
                    <i class="fas fa-calendar-times"></i>
                    <h4>No Events Today</h4>
                    <p>Check out other days or explore our local shops and venues for upcoming events.</p>
                </div>
            `;
            return;
        }
        
        listEl.innerHTML = events.map(event => {
            const startTime = new Date(event.start_datetime);
            const endTime = event.end_datetime ? new Date(event.end_datetime) : null;
            const categories = event.categories ? event.categories.split(',').filter(cat => cat.trim()) : [];
            
            return `
                <div class="daily-event-item ${event.featured ? 'daily-event-featured' : ''}" data-event-id="${event.id}">
                    <div class="daily-event-time">
                        <i class="fas fa-clock"></i>
                        ${startTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}
                        ${endTime ? ` - ${endTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}` : ''}
                    </div>
                    <div class="daily-event-title">${event.title}</div>
                    ${event.location ? `
                        <div class="daily-event-location">
                            <i class="fas fa-map-marker-alt"></i>
                            ${event.location}
                        </div>
                    ` : ''}
                    ${categories.length > 0 ? `
                        <div class="daily-event-categories">
                            ${categories.map(cat => `<span class="daily-event-category">${cat.trim()}</span>`).join('')}
                        </div>
                    ` : ''}
                </div>
            `;
        }).join('');
        
        // Add click listeners
        listEl.querySelectorAll('.daily-event-item').forEach(item => {
            item.addEventListener('click', () => {
                const eventId = item.dataset.eventId;
                const event = events.find(e => e.id == eventId);
                if (event) {
                    this.showEventDetails(event);
                }
            });
        });
    }
    
    /**
     * Update daily map with events
     */
    updateDailyMap(events) {
        if (!this.dailyMap) return;
        
        // Clear existing markers
        this.dailyMarkers.forEach(marker => marker.setMap(null));
        this.dailyMarkers = [];
        
        // Add markers for events with coordinates
        const bounds = new google.maps.LatLngBounds();
        let hasMarkers = false;
        
        events.forEach(event => {
            if (event.latitude && event.longitude) {
                const position = { lat: parseFloat(event.latitude), lng: parseFloat(event.longitude) };
                
                const marker = new google.maps.Marker({
                    position: position,
                    map: this.dailyMap,
                    title: event.title,
                    icon: {
                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                            <svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="16" cy="16" r="12" fill="#e74c3c" stroke="white" stroke-width="3"/>
                                <text x="16" y="20" font-family="Arial" font-size="14" font-weight="bold" 
                                      text-anchor="middle" fill="white">📅</text>
                            </svg>
                        `),
                        scaledSize: new google.maps.Size(32, 32),
                        anchor: new google.maps.Point(16, 16)
                    }
                });
                
                // Create info window content
                const startTime = new Date(event.start_datetime);
                const endTime = event.end_datetime ? new Date(event.end_datetime) : null;
                
                const infoContent = `
                    <div class="daily-map-info-window">
                        <div class="daily-map-info-content">
                            <div class="daily-map-info-title">${event.title}</div>
                            <div class="daily-map-info-time">
                                <i class="fas fa-clock"></i>
                                ${startTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}
                                ${endTime ? ` - ${endTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}` : ''}
                            </div>
                            ${event.location ? `
                                <div class="daily-map-info-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    ${event.location}
                                </div>
                            ` : ''}
                            <div class="daily-map-info-actions">
                                <button class="daily-map-info-btn primary" onclick="calendar.showEventDetails(${event.id})">
                                    View Details
                                </button>
                                ${event.latitude && event.longitude ? `
                                    <button class="daily-map-info-btn secondary" onclick="calendar.getDirections(${event.latitude}, ${event.longitude})">
                                        Directions
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
                
                marker.addListener('click', () => {
                    this.dailyInfoWindow.setContent(infoContent);
                    this.dailyInfoWindow.open(this.dailyMap, marker);
                });
                
                this.dailyMarkers.push(marker);
                bounds.extend(position);
                hasMarkers = true;
            }
        });
        
        // Fit map to markers or center on default location
        if (hasMarkers) {
            this.dailyMap.fitBounds(bounds);
            // Don't zoom too close if there's only one marker
            google.maps.event.addListenerOnce(this.dailyMap, 'bounds_changed', () => {
                if (this.dailyMap.getZoom() > 15) {
                    this.dailyMap.setZoom(15);
                }
            });
        } else {
            this.dailyMap.setCenter(this.options.mapOptions.center);
            this.dailyMap.setZoom(this.options.mapOptions.zoom);
        }
    }
}

// Make calendar available globally for onclick handlers
let calendar;