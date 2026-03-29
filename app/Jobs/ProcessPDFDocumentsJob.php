<?php

namespace App\Jobs;

use App\Models\PdfDocument;
use App\Services\EmbeddingService;
use App\Services\FaissIndexManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class ProcessPDFDocumentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private PdfDocument $pdfDocument;

    public function __construct(PdfDocument $pdfDocument)
    {
        $this->pdfDocument = $pdfDocument;
    }

    /**
     * Execute the job - extract and embed PDF content
     */
    public function handle(): void
    {
        Log::info("Processing PDF: {$this->pdfDocument->title}");

        try {
            // Extract text from PDF
            $pdfPath = Storage::disk('local')->path($this->pdfDocument->file_path);

            if (!file_exists($pdfPath)) {
                throw new \Exception("PDF file not found: {$pdfPath}");
            }

            $parser = new Parser();
            $pdf = $parser->parseFile($pdfPath);
            $text = $pdf->getText();

            if (empty($text)) {
                throw new \Exception('No text extracted from PDF');
            }

            // Chunk the text
            $chunks = $this->chunkText($text);
            Log::info("Extracted {count($chunks)} chunks from PDF");

            // Embed chunks
            $embeddingService = new EmbeddingService();
            $faissManager = new FaissIndexManager();
            $embeddedCount = 0;

            foreach ($chunks as $index => $chunk) {
                $embedding = $embeddingService->embed($chunk['text']);

                if ($embedding) {
                    $faissManager->addDocument(
                        documentId: $this->pdfDocument->id,
                        embedding: $embedding,
                        sourceType: 'pdf',
                        sourceId: $this->pdfDocument->id,
                        tenantId: $this->pdfDocument->tenant_id,
                        metadata: [
                            'content' => $chunk['text'],
                            'pdf_title' => $this->pdfDocument->title,
                            'chunk_index' => $index,
                            'source' => 'pdf',
                        ]
                    );
                    $embeddedCount++;
                }
            }

            // Mark PDF as processed
            $this->pdfDocument->markAsProcessed($embeddedCount);

            Log::info("Processed PDF successfully", [
                'pdf_id' => $this->pdfDocument->id,
                'chunks_embedded' => $embeddedCount,
                'total_chunks' => count($chunks),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to process PDF {$this->pdfDocument->id}: " . $e->getMessage());
            $this->pdfDocument->markAsFailed($e->getMessage());
        }
    }

    /**
     * Split text into chunks with overlap
     */
    private function chunkText(string $text): array
    {
        $chunkSize = config('ai.pdf.chunk_size', 500);
        $overlap = config('ai.pdf.chunk_overlap', 100);

        $chunks = [];
        $textLength = strlen($text);
        $startIndex = 0;

        while ($startIndex < $textLength) {
            $endIndex = min($startIndex + $chunkSize, $textLength);
            $chunk = substr($text, $startIndex, $chunkSize);

            // Clean chunk
            $chunk = trim($chunk);

            if (!empty($chunk)) {
                $chunks[] = [
                    'text' => $chunk,
                    'start' => $startIndex,
                    'end' => $endIndex,
                ];
            }

            // Move start index, accounting for overlap
            $startIndex += ($chunkSize - $overlap);
        }

        return $chunks;
    }
}
