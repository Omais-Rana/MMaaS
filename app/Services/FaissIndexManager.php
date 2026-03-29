<?php

namespace App\Services;

use App\Models\VectorDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use stdClass;

class FaissIndexManager
{
    private string $indexPath = 'storage/app/vectors/index.faiss';
    private string $metadataPath = 'storage/app/vectors/metadata.json';
    private ?object $index = null;
    private array $metadata = [];

    /**
     * Initialize or load existing FAISS index
     */
    public function __construct()
    {
        $this->ensureDirectoryExists();
        $this->loadIndex();
    }

    /**
     * Add a document with its embedding to the index
     * In this MVP, we store embedding metadata in MySQL and query there
     */
    public function addDocument(
        int $documentId,
        array $embedding,
        string $sourceType,
        ?int $sourceId,
        ?int $tenantId,
        array $metadata = []
    ): void {
        // Store in database instead of FAISS file for simplicity
        // This allows MySQL queries + Python compute layer
        try {
            VectorDocument::create([
                'tenant_id' => $tenantId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'content_chunk' => $metadata['content'] ?? '',
                'content_hash' => hash('sha256', $metadata['content'] ?? ''),
                'embedding' => $embedding, // Store as JSON
                'embedding_model' => 'all-MiniLM-L6-v2',
                'metadata' => $metadata,
                'chunk_index' => $metadata['chunk_index'] ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to add document to index: ' . $e->getMessage());
        }
    }

    /**
     * Search for similar documents using cosine similarity
     * 
     * @param array $queryEmbedding Vector to search for
     * @param int $k Number of results to return
     * @param ?int $tenantId Optionally filter by tenant
     * @return array Array of similar documents with scores
     */
    public function search(array $queryEmbedding, int $k = 5, ?int $tenantId = null): array
    {
        try {
            $query = VectorDocument::query();

            if ($tenantId) {
                $query->forTenant($tenantId);
            }

            $documents = $query->get();

            if ($documents->isEmpty()) {
                return [];
            }

            // Calculate similarity for each document
            $similarities = [];
            foreach ($documents as $doc) {
                if ($doc->embedding && is_array($doc->embedding)) {
                    $score = EmbeddingService::cosineSimilarity($queryEmbedding, $doc->embedding);
                    $similarities[] = [
                        'document' => $doc,
                        'score' => $score,
                    ];
                }
            }

            // Sort by score descending
            usort($similarities, fn($a, $b) => $b['score'] <=> $a['score']);

            // Return top K
            return array_slice($similarities, 0, $k);
        } catch (\Exception $e) {
            Log::error('FAISS search failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Build index from all routes and stops in database
     * Called by SeedEmbeddingsJob
     */
    public function buildFromRoutes(\App\Models\Route $routeModel, array $embeddings): int
    {
        $count = 0;
        $routes = $routeModel->whereNotNull('route_long_name')->get();

        foreach ($routes as $route) {
            $text = "{$route->route_long_name} - {$route->route_desc}";
            $hash = hash('sha256', $text);

            // Check if already indexed
            if (VectorDocument::where('content_hash', $hash)->exists()) {
                continue;
            }

            if (isset($embeddings[$hash])) {
                $this->addDocument(
                    documentId: $route->id,
                    embedding: $embeddings[$hash],
                    sourceType: 'route',
                    sourceId: $route->id,
                    tenantId: $route->tenant_id,
                    metadata: [
                        'content' => $text,
                        'route_name' => $route->route_long_name,
                        'source' => 'route',
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * Build index from all stops in database
     */
    public function buildFromStops(\App\Models\Stop $stopModel, array $embeddings): int
    {
        $count = 0;
        $stops = $stopModel->whereNotNull('stop_name')->get();

        foreach ($stops as $stop) {
            $text = "{$stop->stop_name} - {$stop->stop_desc} ({$stop->city})";
            $hash = hash('sha256', $text);

            if (VectorDocument::where('content_hash', $hash)->exists()) {
                continue;
            }

            if (isset($embeddings[$hash])) {
                $this->addDocument(
                    documentId: $stop->id,
                    embedding: $embeddings[$hash],
                    sourceType: 'stop',
                    sourceId: $stop->id,
                    tenantId: $stop->tenant_id,
                    metadata: [
                        'content' => $text,
                        'stop_name' => $stop->stop_name,
                        'city' => $stop->city,
                        'lat' => $stop->stop_lat,
                        'lon' => $stop->stop_lon,
                        'source' => 'stop',
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get index statistics
     */
    public function stats(): array
    {
        $total = VectorDocument::count();
        $byType = VectorDocument::select('source_type')
            ->selectRaw('count(*) as count')
            ->groupBy('source_type')
            ->pluck('count', 'source_type')
            ->toArray();

        return [
            'total_documents' => $total,
            'by_type' => $byType,
        ];
    }

    /**
     * Clear all indexed documents
     */
    public function clear(): void
    {
        VectorDocument::truncate();
        Log::info('FAISS index cleared');
    }

    private function ensureDirectoryExists(): void
    {
        if (! is_dir('storage/app/vectors')) {
            mkdir('storage/app/vectors', 0755, true);
        }
    }

    private function loadIndex(): void
    {
        // Index is loaded dynamically from DB
        // No need to load from file in this approach
    }
}
