<?php

namespace App\Console\Commands;

use App\Services\EmbeddingService;
use Illuminate\Console\Command;

class TestEmbeddingCommand extends Command
{
    protected $signature = 'embeddings:test {text}';

    protected $description = 'Generate an embedding for the provided text.';

    public function handle(EmbeddingService $embeddings): int
    {
        $vector = $embeddings->embedText($this->argument('text'));

        $this->info('Provider: '.$embeddings->provider());
        $this->info('Model: '.$embeddings->model());
        $this->info('Dimensions: '.count($vector));
        $this->line('First 5 values: '.implode(', ', array_map(
            fn (float $value): string => (string) round($value, 6),
            array_slice($vector, 0, 5)
        )));

        return self::SUCCESS;
    }
}
