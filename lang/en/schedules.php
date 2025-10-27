<?php

return [
    // Navigation & Titles
    'navigation_label' => 'Schedule Imports',
    'navigation_group' => 'Schedule Management',
    'model_label' => 'Schedule Import',
    'plural_label' => 'Schedule Imports',

    // Resource Pages
    'create_title' => 'Upload Schedule',
    'list_title' => 'Schedule Imports',
    'view_title' => 'View Schedule Import',

    // Form Fields
    'file_label' => 'GTFS File',
    'file_help' => 'Upload a GTFS (.zip) file containing transit schedule data',
    'file_required' => 'Please upload a GTFS file',

    // Table Columns
    'filename' => 'Filename',
    'status' => 'Status',
    'imported_at' => 'Imported At',
    'created_at' => 'Uploaded At',

    // Status Values
    'status_pending' => 'Pending',
    'status_processing' => 'Processing',
    'status_completed' => 'Completed',
    'status_failed' => 'Failed',

    // Infolist Sections
    'section_import_info' => 'Import Information',
    'section_statistics' => 'Statistics',
    'section_error_details' => 'Error Details',

    // Import Info Fields
    'import_filename' => 'Filename',
    'import_status' => 'Status',
    'import_uploaded_at' => 'Uploaded At',
    'import_completed_at' => 'Completed At',

    // Statistics Fields
    'stats_routes' => 'Routes',
    'stats_stops' => 'Stops',
    'stats_trips' => 'Trips',
    'stats_calendars' => 'Calendars',
    'stats_stop_times' => 'Stop Times',

    // Error Fields
    'error_message' => 'Error Message',
    'error_file' => 'Error File',
    'error_line' => 'Error Line',

    // Relation Managers
    'routes_title' => 'Routes',
    'stops_title' => 'Stops',
    'trips_title' => 'Trips',
    'calendars_title' => 'Calendars',

    // Route Table Columns
    'route_line' => 'Line',
    'route_destination' => 'Destination',
    'route_type' => 'Type',
    'route_color' => 'Color',
    'route_trips_count' => '# Trips',

    // Route Type Values
    'type_tram' => 'Tram',
    'type_subway' => 'Subway',
    'type_rail' => 'Rail',
    'type_bus' => 'Bus',
    'type_ferry' => 'Ferry',
    'type_cable_tram' => 'Cable Tram',
    'type_aerial_lift' => 'Aerial Lift',
    'type_funicular' => 'Funicular',
    'type_trolleybus' => 'Trolleybus',
    'type_monorail' => 'Monorail',

    // Stop Table Columns
    'stop_code' => 'Stop Code',
    'stop_name' => 'Stop Name',
    'stop_lat' => 'Latitude',
    'stop_lon' => 'Longitude',
    'stop_wheelchair' => 'Wheelchair Access',

    // Wheelchair Values
    'wheelchair_yes' => 'Yes',
    'wheelchair_no' => 'No',
    'wheelchair_unknown' => 'Unknown',

    // Trip Table Columns
    'trip_line' => 'Line',
    'trip_destination' => 'Destination',
    'trip_service' => 'Service',
    'trip_direction' => 'Direction',
    'trip_wheelchair' => 'Wheelchair Access',
    'trip_stops_count' => '# Stops',

    // Direction Values
    'direction_outbound' => 'Outbound',
    'direction_inbound' => 'Inbound',
    'direction_na' => 'N/A',

    // Calendar Table Columns
    'calendar_service' => 'Service ID',
    'calendar_start' => 'Start Date',
    'calendar_end' => 'End Date',
    'calendar_monday' => 'Monday',
    'calendar_tuesday' => 'Tuesday',
    'calendar_wednesday' => 'Wednesday',
    'calendar_thursday' => 'Thursday',
    'calendar_friday' => 'Friday',
    'calendar_saturday' => 'Saturday',
    'calendar_sunday' => 'Sunday',
    'calendar_exceptions' => 'Exceptions',

    // Calendar Empty State
    'calendar_empty_state' => 'This import uses exception-based scheduling (calendar_dates.txt) rather than regular service calendars. Service exceptions define specific dates when services run differently from the regular schedule.',

    // Notifications
    'upload_success' => 'Schedule file uploaded successfully',
    'upload_processing' => 'Processing schedule import...',
    'import_completed' => 'Schedule import completed successfully',
    'import_failed' => 'Schedule import failed',
];
