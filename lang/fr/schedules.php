<?php

return [
    // Navigation & Titles
    'navigation_label' => 'Importations d\'Horaires',
    'navigation_group' => 'Gestion des Horaires',
    'model_label' => 'Importation d\'Horaire',
    'plural_label' => 'Importations d\'Horaires',

    // Resource Pages
    'create_title' => 'Télécharger un Horaire',
    'list_title' => 'Importations d\'Horaires',
    'view_title' => 'Voir l\'Importation d\'Horaire',

    // Form Fields
    'file_label' => 'Fichier GTFS',
    'file_help' => 'Téléchargez un fichier GTFS (.zip) contenant les données d\'horaires de transport',
    'file_required' => 'Veuillez télécharger un fichier GTFS',

    // Table Columns
    'filename' => 'Nom du fichier',
    'status' => 'Statut',
    'imported_at' => 'Importé le',
    'created_at' => 'Téléchargé le',

    // Status Values
    'status_pending' => 'En attente',
    'status_processing' => 'En cours',
    'status_completed' => 'Terminé',
    'status_failed' => 'Échoué',

    // Infolist Sections
    'section_import_info' => 'Informations d\'Importation',
    'section_statistics' => 'Statistiques',
    'section_error_details' => 'Détails de l\'Erreur',

    // Import Info Fields
    'import_filename' => 'Nom du fichier',
    'import_status' => 'Statut',
    'import_uploaded_at' => 'Téléchargé le',
    'import_completed_at' => 'Terminé le',

    // Statistics Fields
    'stats_routes' => 'Lignes',
    'stats_stops' => 'Arrêts',
    'stats_trips' => 'Trajets',
    'stats_calendars' => 'Calendriers',
    'stats_stop_times' => 'Horaires d\'Arrêt',

    // Error Fields
    'error_message' => 'Message d\'Erreur',
    'error_file' => 'Fichier d\'Erreur',
    'error_line' => 'Ligne d\'Erreur',

    // Relation Managers
    'routes_title' => 'Lignes',
    'stops_title' => 'Arrêts',
    'trips_title' => 'Trajets',
    'calendars_title' => 'Calendriers',

    // Route Table Columns
    'route_line' => 'Ligne',
    'route_destination' => 'Destination',
    'route_type' => 'Type',
    'route_color' => 'Couleur',
    'route_trips_count' => 'Nb. Trajets',

    // Route Type Values
    'type_tram' => 'Tramway',
    'type_subway' => 'Métro',
    'type_rail' => 'Train',
    'type_bus' => 'Bus',
    'type_ferry' => 'Ferry',
    'type_cable_tram' => 'Tramway à Câble',
    'type_aerial_lift' => 'Téléphérique',
    'type_funicular' => 'Funiculaire',
    'type_trolleybus' => 'Trolleybus',
    'type_monorail' => 'Monorail',

    // Stop Table Columns
    'stop_code' => 'Code Arrêt',
    'stop_name' => 'Nom Arrêt',
    'stop_lat' => 'Latitude',
    'stop_lon' => 'Longitude',
    'stop_wheelchair' => 'Accès Fauteuil Roulant',

    // Wheelchair Values
    'wheelchair_yes' => 'Oui',
    'wheelchair_no' => 'Non',
    'wheelchair_unknown' => 'Inconnu',

    // Trip Table Columns
    'trip_line' => 'Ligne',
    'trip_destination' => 'Destination',
    'trip_service' => 'Service',
    'trip_direction' => 'Direction',
    'trip_wheelchair' => 'Accès Fauteuil Roulant',
    'trip_stops_count' => 'Nb. Arrêts',

    // Direction Values
    'direction_outbound' => 'Aller',
    'direction_inbound' => 'Retour',
    'direction_na' => 'N/D',

    // Calendar Table Columns
    'calendar_service' => 'ID Service',
    'calendar_start' => 'Date de Début',
    'calendar_end' => 'Date de Fin',
    'calendar_monday' => 'Lundi',
    'calendar_tuesday' => 'Mardi',
    'calendar_wednesday' => 'Mercredi',
    'calendar_thursday' => 'Jeudi',
    'calendar_friday' => 'Vendredi',
    'calendar_saturday' => 'Samedi',
    'calendar_sunday' => 'Dimanche',
    'calendar_exceptions' => 'Exceptions',

    // Calendar Empty State
    'calendar_empty_state' => 'Cette importation utilise une planification basée sur les exceptions (calendar_dates.txt) plutôt que des calendriers de service réguliers. Les exceptions de service définissent des dates spécifiques où les services fonctionnent différemment de l\'horaire régulier.',

    // Notifications
    'upload_success' => 'Fichier d\'horaire téléchargé avec succès',
    'upload_processing' => 'Traitement de l\'importation d\'horaire...',
    'import_completed' => 'Importation d\'horaire terminée avec succès',
    'import_failed' => 'Échec de l\'importation d\'horaire',
];
