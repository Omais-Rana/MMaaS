<?php

return [
    // Navigation & Titles
    'navigation_label' => 'Horaires',
    'navigation_group' => 'Gestion des Horaires',
    'model_label' => 'Horaire',
    'plural_label' => 'Horaires',

    // Resource Pages
    'create_title' => 'Importer un Horaire',
    'list_title' => 'Horaires',
    'view_title' => 'Voir l\'Horaire',

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
    'route_type_description' => 'Type de Service: :type',
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
    'calendar_active_days' => 'Jours Actifs',
    'calendar_exceptions' => 'Exceptions',
    'calendar_operating_dates' => '# Dates',
    'calendar_monday' => 'Lundi',
    'calendar_tuesday' => 'Mardi',
    'calendar_wednesday' => 'Mercredi',
    'calendar_thursday' => 'Jeudi',
    'calendar_friday' => 'Vendredi',
    'calendar_saturday' => 'Samedi',
    'calendar_sunday' => 'Dimanche',
    'calendar_period_description' => 'Période d\'horaire : :start au :end',

    // Day Abbreviations
    'day_mon' => 'Lun',
    'day_tue' => 'Mar',
    'day_wed' => 'Mer',
    'day_thu' => 'Jeu',
    'day_fri' => 'Ven',
    'day_sat' => 'Sam',
    'day_sun' => 'Dim',

    // Calendar Patterns
    'calendar_pattern_weekdays' => 'Jours de semaine (Lun-Ven)',
    'calendar_pattern_saturday' => 'Samedi seulement',
    'calendar_pattern_sunday' => 'Dimanche seulement',
    'calendar_pattern_daily' => 'Quotidien (tous les jours)',
    'calendar_pattern_custom' => 'Modèle d\'horaire personnalisé',
    'calendar_exception_based' => 'Basé sur exceptions',

    // Exception Details
    'view_schedule_details' => 'Voir les Détails',
    'schedule_details_for_service' => 'Détails d\'Horaire pour :service',
    'exceptions_removed' => 'Exceptions de Service (Jours Fermés)',
    'exceptions_removed_description' => 'Le service ne fonctionne PAS à ces dates',
    'exceptions_added' => 'Dates d\'Opération',
    'exceptions_added_description' => 'Le service fonctionne à ces dates spécifiques (pour horaires basés sur exceptions)',
    'view_dates' => 'Voir les Dates',
    'operating_dates_for_service' => 'Dates d\'Opération pour :service',
    'operating_dates' => 'Dates d\'Opération',
    'operating_dates_description' => 'Le service fonctionne à ces dates spécifiques',
    'non_operating_dates' => 'Dates Non-Opérationnelles',
    'non_operating_dates_description' => 'Le service ne fonctionne PAS à ces dates',
    'view_exceptions' => 'Voir les Exceptions',
    'exceptions_for_service' => 'Exceptions pour :service',
    'exception_dates' => 'Dates',
    'close' => 'Fermer',

    // Calendar Empty State
    'calendar_empty_state' => 'Cette importation utilise une planification basée sur les exceptions (calendar_dates.txt) plutôt que des calendriers de service réguliers. Les exceptions de service définissent des dates spécifiques où les services fonctionnent différemment de l\'horaire régulier.',

    // Notifications
    'upload_success' => 'Fichier d\'horaire téléchargé avec succès',
    'upload_processing' => 'Traitement de l\'importation d\'horaire...',
    'import_completed' => 'Importation d\'horaire terminée avec succès',
    'import_failed' => 'Échec de l\'importation d\'horaire',
];
