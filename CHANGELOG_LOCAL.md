# YFEvents Local Changes Log
## Changes made on 2025-05-26

### New Files Created:
1. `/www/html/calendar.php` - Main calendar entry point
2. `/www/html/calendar-init.js` - Calendar initialization override
3. `/www/html/api/events-simple.php` - Simplified events API endpoint
4. `/www/html/api/events/index.php` - Events API router
5. `/www/html/api/shops/index.php` - Shops API endpoint
6. `/www/html/events/submit/index.php` - Event submission page
7. `/www/html/admin/index.php` - Simple admin dashboard
8. `/www/html/test-db.php` - Database connection test
9. `/www/html/test-ajax.php` - AJAX endpoint test
10. `/www/html/calendar-debug.php` - Calendar debugging tool
11. `/www/html/simple-calendar.html` - Simple calendar test
12. `/config/database.php` - Database configuration
13. `/.env` - Environment configuration (from .env.example)
14. `/composer.json` - PHP dependencies
15. `/add_test_data.php` - Test data insertion script
16. `/import_schema_ordered.php` - Database schema import script
17. `/INSTALL.md` - Installation instructions

### Modified Files:
1. `/www/html/templates/calendar/calendar.php`
   - Changed API endpoint from '/api/events' to '/api/events-simple.php'
   - Fixed categories variable to handle undefined case
2. `/www/html/api/events-simple.php`
   - Added support for both 'start_date' and 'start' parameters
3. `/www/html/index.php`
   - Replaced with dynamic version that checks .env and redirects

### Database Changes:
1. Created all tables from schema
2. Added 6 test events (all prefixed with "DELETE LATER")
3. Added 5 test shops within 1km (all prefixed with "DELETE LATER")

### Configuration:
1. Set up `.env` with database credentials and Google Maps API key
2. Configured Apache virtual host for the application

### Map Fixes Applied:
1. Created `/www/html/js/calendar-map-fix.js` - JavaScript patches for map functionality
   - Added Yakima Finds marker at 111 S 2nd St (red pin)
   - Fixed event markers (blue pins) 
   - Fixed shop markers (green pins)
   - Added detailed info windows for all markers
   - Added console logging for debugging
   - Set proper map center to Yakima Finds location

2. Modified `/www/html/templates/calendar/calendar.php`
   - Added script tag to load calendar-map-fix.js

### Map Features:
- Yakima Finds location shows as red marker at center
- Events show as blue markers
- Shops show as green markers
- Click any marker for details
- Map defaults to showing both events and shops

### Authentication System Added:
1. Created `/www/html/admin/login.php` - Admin login page
   - Username: YakFind
   - Password: MapTime
2. Created `/www/html/admin/logout.php` - Logout functionality
3. Modified `/www/html/admin/index.php` - Added authentication check
4. Modified `/www/html/admin/calendar/index.php` - Added authentication check
5. Modified `/www/html/templates/calendar/calendar.php` - Added Admin button to header
6. Modified `/www/html/js/calendar-map-fix.js` - Fixed Yakima Finds location to 111 S 2nd St

### Security Features:
- Session-based authentication
- All admin pages protected
- Logout functionality
- Admin button visible on main calendar page

### Web Scraper Features Added:
1. Created `/www/html/admin/scrapers.php` - Scraper management interface
   - Add/edit/delete scraper sources
   - Support for iCal, HTML, JSON, Eventbrite, Facebook
   - View scraping logs and status
   - Manual scrape trigger button
2. Created `/www/html/js/map-controls.js` - Map layer toggle controls
   - Toggle events on/off
   - Toggle shops on/off  
   - Toggle Yakima Finds marker on/off
   - Toggle all button
3. Modified `/www/html/admin/index.php` - Added "Manage Scrapers" link
4. Modified `/www/html/templates/calendar/calendar.php` - Added map-controls.js
5. Modified `/www/html/js/calendar-map-fix.js` - Made Yakima Finds marker toggleable

### Cron Job:
- Existing at `/cron/scrape-events.php`
- Run daily: `0 2 * * * php /path/to/cron/scrape-events.php`
- Scrapes all active sources
- Logs results to database
- Can be triggered manually from admin panel

### Additional Updates (2025-05-26 Part 2):
1. Created `/www/html/admin/scrape-now.php` - AJAX endpoint for immediate scraping
2. Modified `/www/html/admin/scrapers.php` - Added AJAX scrape functionality
3. Created `/add_image_url_column.php` - Database migration for shop images
4. Created `/import_yakima_shops.php` - Import script for yakimafinds.com shops
5. Modified `/www/html/js/calendar-map-fix.js` - Added shop images to info windows
6. Added 15 real shops from yakimafinds.com with images

### Shop Import Features:
- Imported 15 local shops from yakimafinds.com/other-local-shops
- Shops include images that display in map info windows
- Images are loaded from remote URLs (yakimafinds.com)
- All shops geocoded to actual addresses

### Custom Yakima Valley Event Scraper Added:
1. Created `/src/Scrapers/YakimaValleyEventScraper.php` - Custom HTML parser
   - Parses events with date ranges (e.g., "May 23 - 25")
   - Handles cross-month date ranges (e.g., "May 30 - Jul 25")
   - Extracts venue, location, and categories
   - Maps category icons to event types
2. Modified `/src/Scrapers/EventScraper.php` - Added 'yakima_valley' scrape type
3. Modified `/www/html/admin/scrapers.php` - Added Yakima Valley option
4. Created test files for validation

### Scraper Features:
- Automatic date parsing with year handling
- Category extraction (Wine, Food, Music, Arts, Family, Outdoor, Beer)
- Venue and location parsing
- External URL preservation
- Duplicate detection via external event ID