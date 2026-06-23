<?php

namespace App\Services\Routing;

use App\Models\TransportNode;
use App\Models\Itinerary;
use App\Services\Gbfs\GbfsClient;

class HeuristicRoutingEngine
{
    public function __construct(
        protected GbfsClient $gbfsClient,
        protected CarbonCalculator $carbonCalc
    ) {}

    /**
     * Compute multimodal itinerary using Trunk and Feeder heuristic.
     */
    public function compute(float $origLat, float $origLon, float $destLat, float $destLon): array
    {
        // 1. Spatial Resolution: Find nearest hubs using MySQL Spatial Index
        $originHubs = TransportNode::nearestHubs($origLat, $origLon, radiusKm: 2.5)->get();
        $destHubs = TransportNode::nearestHubs($destLat, $destLon, radiusKm: 2.5)->get();

        $itineraries = [];

        // 2. Trunk & Feeder Construction
        foreach ($originHubs as $oHub) {
            foreach ($destHubs as $dHub) {
                
                $feeder1 = $this->computeFeeder($origLat, $origLon, $oHub);
                $trunk   = $this->computeTrunk($oHub, $dHub);
                $feeder2 = $this->computeFeeder($dHub, $destLat, $destLon);

                if ($feeder1 && $trunk && $feeder2) {
                    $itinerary = Itinerary::merge($feeder1, $trunk, $feeder2);
                    
                    // 3. Apply CO2 Emission Coefficients
                    $itinerary->co2_emissions = $this->carbonCalc->calculate($itinerary);
                    
                    $itineraries[] = $itinerary;
                }
            }
        }

        // 4. Simultaneous GBFS Injection (Live Bikes & Scooters)
        if ($directGbfs = $this->gbfsClient->findDirectRoute($origLat, $origLon, $destLat, $destLon)) {
            $directGbfs->co2_emissions = 0; // Zero emissions for bikes
            $itineraries[] = $directGbfs;
        }

        // 5. Score and Sort by (Duration + CO2 Weight)
        return collect($itineraries)->sortBy(function ($itinerary) {
            return ($itinerary->duration_minutes * 0.7) + ($itinerary->co2_emissions * 0.3);
        })->values()->toArray();
    }
}
