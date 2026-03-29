<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EmbeddingService
{
    private string $modelName = 'all-MiniLM-L6-v2';
    private string $pythonPath = 'python3'; // Or 'python' on Windows

    /**
     * Generate embedding for a text chunk
     * Uses sentence-transformers via Python subprocess
     * 
     * @return array|null Vector embedding or null on failure
     */
    public function embed(string $text): ?array
    {
        try {
            $pythonScript = $this->generatePythonScript($text);
            
            $process = new Process([$this->pythonPath, '-c', $pythonScript]);
            $process->setTimeout(30);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error('Embedding generation failed', [
                    'error' => $process->getErrorOutput(),
                    'text' => substr($text, 0, 100),
                ]);
                return null;
            }

            $output = trim($process->getOutput());
            $embedding = json_decode($output, true);

            return is_array($embedding) ? $embedding : null;
        } catch (\Exception $e) {
            Log::error('EmbeddingService exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Batch embed multiple texts
     * 
     * @param array $texts Array of text strings
     * @return array Array of embeddings (same length as input)
     */
    public function embedBatch(array $texts): array
    {
        return array_map(fn($text) => $this->embed($text), $texts);
    }

    /**
     * Calculate cosine similarity between two embeddings
     */
    public static function cosineSimilarity(array $vec1, array $vec2): float
    {
        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;

        for ($i = 0; $i < count($vec1); $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] ** 2;
            $norm2 += $vec2[$i] ** 2;
        }

        $denominateur = sqrt($norm1) * sqrt($norm2);

        return $denominateur > 0 ? $dotProduct / $denominateur : 0;
    }

    /**
     * Generate Python script to embed text using sentence-transformers
     */
    private function generatePythonScript(string $text): string
    {
        $escapedText = addslashes($text);

        return <<<PYTHON
import json
try:
    from sentence_transformers import SentenceTransformer
    model = SentenceTransformer('{$this->modelName}')
    embedding = model.encode('{$escapedText}').tolist()
    print(json.dumps(embedding))
except Exception as e:
    print(json.dumps(None))
PYTHON;
    }

    /**
     * Set which embedding model to use
     */
    public function setModel(string $modelName): self
    {
        $this->modelName = $modelName;
        return $this;
    }

    /**
     * Set Python executable path
     */
    public function setPythonPath(string $path): self
    {
        $this->pythonPath = $path;
        return $this;
    }
}
