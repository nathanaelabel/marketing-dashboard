<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use App\Http\Controllers\ReturnComparisonController;

class WarmReturnComparisonCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:warm-return-comparison {--month=} {--year=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm up cache for Return Comparison data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $month = $this->option('month') ?? date('n');
        $year = $this->option('year') ?? date('Y');

        $this->info("Warming up cache for Return Comparison: Month={$month}, Year={$year}");

        $controller = new ReturnComparisonController();
        $request = new Request(['month' => $month, 'year' => $year]);

        $startTime = microtime(true);
        $response = $controller->getData($request);
        $duration = round((microtime(true) - $startTime), 2);

        if ($response->getStatusCode() === 200) {
            $this->info("✓ Cache warmed successfully in {$duration} seconds");
            return Command::SUCCESS;
        } else {
            $this->error("✗ Failed to warm cache");
            return Command::FAILURE;
        }
    }
}
