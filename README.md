# MultiModal Mobility Platform (MaaS)

Unifying Fragmented Territories through Data Integration.

## 📖 Overview
This project is a comprehensive **Mobility as a Service (MaaS)** platform designed to resolve the geographic and administrative fragmentation of public transportation networks. In regions like Bourgogne-Franche-Comté, the mobility landscape is a "mille-feuille" of independent Autorités Organisatrices de la Mobilité (AOMs) and Opérateurs de Mobilité (OMs). 

This platform bridges these disjointed networks by aggregating heterogeneous transit data into a unified, multi-tenant system. It empowers end-users with seamless, door-to-door multimodal itinerary planning that intelligently combines public transit (buses, trains) and live micromobility (shared bikes and cars) while calculating precise CO₂ emission savings.

## 🚀 Key Features
- **Multi-Tenant Architecture**: Robust data isolation cryptographically tagging transport elements to specific operators (Tenants) and regional authorities.
- **Heterogeneous Data Aggregation**: 
  - Parses static **GTFS** (General Transit Feed Specification) schedules via background queue workers.
  - Consumes live, dynamic **GBFS** (General Bikeshare Feed Specification) feeds for free-floating bikes and cars.
- **Custom SQL-Optimized Routing Algorithm**: Instead of traditional RAM-heavy graph algorithms (like Dijkstra or RAPTOR) which face "state explosion", this platform employs a deterministic, data-driven heuristic algorithm. It leverages MySQL spatial indexing to dynamically calculate real-time multimodal journeys (e.g., Walk -> Shared Bike -> Train -> Walk).
- **CO₂ Impact Tracking**: Dynamically calculates the ecological footprint of every generated route based on transport modes (electric train vs diesel bus).
- **Server-Driven Admin Panels**: Fast and modular administrative dashboards built using Filament v4.

## 🛠️ Tech Stack
- **Backend Framework**: Laravel 12 (PHP 8.3)
- **Administration UI**: Filament v4 (Server-Driven UI)
- **Database**: MySQL (optimized with Spatial Indexing for Haversine distances)
- **Frontend Map**: Leaflet.js (custom vanilla JS implementations for route rendering)
- **Data Ingestion**: Laravel Queues / Jobs

## 👥 User Roles & Authorities
The platform defines three primary actors with distinct authority levels:
1. **AOM (Regional Superadmin)**: Manages territories, oversees subordinate OMs, analyzes global network usage, and dictates high-level pricing.
2. **OM (Tenant Admin)**: Autonomous mobility operators who upload their own GTFS feeds, track localized fleet analytics, and manage their staff without relying on the AOM.
3. **End User (Customer)**: Interacts with the unified public portal to search for multimodal journeys across all integrated territories.

## ⚙️ Installation & Setup
To run this project locally, ensure you have PHP 8.3, Composer, and MySQL installed (or use Laravel Herd / Sail).

```bash
# 1. Clone the repository
git clone https://github.com/your-repo/multimodal-mobility.git
cd multimodal-mobility

# 2. Install dependencies
composer install
npm install
npm run build

# 3. Environment configuration
cp .env.example .env
php artisan key:generate

# Configure your database credentials in the .env file
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=maas_db
# DB_USERNAME=root
# DB_PASSWORD=

# 4. Run Migrations & Seeders
php artisan migrate --seed

# 5. Start Queue Workers (Crucial for GTFS parsing and GBFS syncing)
php artisan queue:work

# 6. Serve the application
php artisan serve
```

## 🗺️ Roadmap & Future Perspectives
- **MuCar (Carpooling Integration)**: Integration of a peer-to-peer carpooling network where citizen commutes can act as "Feeder" legs to major transit hubs.
- **Unified Pricing & Ticketing (Level 3 MaaS)**: Transitioning from route discovery to financial settlement. Users will pay a single subscription, and a complex clearinghouse algorithm will distribute revenue to specific OMs based on validation metrics.
- **Real-Time Delays (GTFS-RT / SIRI)**: Implement live vehicle tracking and delay notifications.

## 🎓 Acknowledgements
This project was developed by **Muhammad Omais Rana** (Master 1 IoT) under the supervision of **Philippe Canalda**. It serves as a foundational platform for unifying disparate mobility ecosystems, encouraging a massive shift towards greener transportation.
