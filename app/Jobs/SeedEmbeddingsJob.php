<?php

namespace App\Jobs;

use App\Models\Route;
use App\Models\Stop;
use App\Services\EmbeddingService;
use App\Services\FaissIndexManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SeedEmbeddingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
    }

    /**
     * Execute the job - embed all routes and stops
     */
    public function handle(): void
    {
        Log::info('SeedEmbeddingsJob started');

        $embeddingService = new EmbeddingService();
        $faissManager = new FaissIndexManager();

        // Clear existing embeddings
        // $faissManager->clear();

        // Get routes and stops
        $routes = Route::whereNotNull('route_long_name')
            ->withoutGlobalScopes()
            ->get();

        $stops = Stop::whereNotNull('stop_name')
            ->withoutGlobalScopes()
            ->get();

        Log::info("Found {$routes->count()} routes and {$stops->count()} stops to embed");

        // Embed routes
        $routeCount = 0;
        foreach ($routes as $route) {
            $text = "{$route->route_long_name} - {$route->route_desc}";
            $embedding = $embeddingService->embed($text);

            if ($embedding) {
                $faissManager->addDocument(
                    documentId: $route->id,
                    embedding: $embedding,
                    sourceType: 'route',
                    sourceId: $route->id,
                    tenantId: $route->tenant_id,
                    metadata: [
                        'content' => $text,
                        'route_name' => $route->route_long_name,
                        'route_type' => $route->route_type,
                        'source' => 'route',
                    ]
                );
                $routeCount++;
                Log::info("Embedded route: {$route->route_long_name}");
            }
        }

        // Embed stops
        $stopCount = 0;
        foreach ($stops as $stop) {
            $text = "{$stop->stop_name} - {$stop->stop_desc} ({$stop->city})";
            $embedding = $embeddingService->embed($text);

            if ($embedding) {
                $faissManager->addDocument(
                    documentId: $stop->id,
                    embedding: $embedding,
                    sourceType: 'stop',
                    sourceId: $stop->id,
                    tenantId: $stop->tenant_id,
                    metadata: [
                        'content' => $text,
                        'stop_name' => $stop->stop_name,
                        'city' => $stop->city,
                        'lat' => (float) $stop->stop_lat,
                        'lon' => (float) $stop->stop_lon,
                        'source' => 'stop',
                    ]
                );
                $stopCount++;
                Log::info("Embedded stop: {$stop->stop_name}");
            }
        }

        $stats = $faissManager->stats();
        Log::info('SeedEmbeddingsJob completed', [
            'routes_embedded' => $routeCount,
            'stops_embedded' => $stopCount,
            'total_documents' => $stats['total_documents'],
        ]);
    }
}
