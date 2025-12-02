<?php

namespace App\Console\Commands\Cache;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\ReturnComparisonController;

class WarmReturnComparisonCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:warm-return-comparison 
                            {--month= : Specific month to warm (default: current month)}
                            {--year= : Specific year to warm (default: current year)}
                            {--current : Warm current month only}
                            {--last-3-months : Warm last 3 months}
                            {--refresh : Force refresh cache even if exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm up cache for Return Comparison data (supports current month auto-refresh)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('last-3-months')) {
            return $this->warmLastMonths(3);
        }

        if ($this->option('current')) {
            return $this->warmCurrentMonth();
        }

        $month = $this->option('month') ?? date('n');
        $year = $this->option('year') ?? date('Y');

        return $this->warmSpecificMonth($month, $year);
    }

    private function warmCurrentMonth()
    {
        $month = date('n');
        $year = date('Y');

        $this->info(" Refreshing cache for current month: {$month}/{$year}");

        // Force clear current month cache to get fresh data
        $cacheKey = "return_comparison_{$year}_{$month}";
        Cache::forget($cacheKey);

        return $this->warmSpecificMonth($month, $year);
    }

    private function warmLastMonths($count)
    {
        $this->info(" Warming up cache for last {$count} months...");

        $success = 0;
        $failed = 0;

        for ($i = 0; $i < $count; $i++) {
            $date = now()->subMonths($i);
            $month = $date->month;
            $year = $date->year;

            // Hapus cache lama sebelum warming untuk memastikan data fresh
            $cacheKey = "return_comparison_{$year}_{$month}";
            Cache::forget($cacheKey);
            $this->line("  → Cache cleared for {$month}/{$year}");

            $result = $this->warmSpecificMonth($month, $year, false);

            if ($result === Command::SUCCESS) {
                $success++;
            } else {
                $failed++;
            }
        }

        $this->info("\n Summary: {$success} succeeded, {$failed} failed");

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function warmSpecificMonth($month, $year, $showHeader = true)
    {
        if ($showHeader) {
            $this->info("Warming up cache for Return Comparison: Month={$month}, Year={$year}");

            // Jika dipanggil langsung (bukan dari warmLastMonths), hapus cache dulu
            $cacheKey = "return_comparison_{$year}_{$month}";
            Cache::forget($cacheKey);
            $this->line("  → Cache cleared");
        } else {
            $this->line("  → Processing {$month}/{$year}...");
        }

        $controller = new ReturnComparisonController();
        $request = new Request(['month' => $month, 'year' => $year]);

        $startTime = microtime(true);

        try {
            $response = $controller->getData($request);
            $duration = round((microtime(true) - $startTime), 2);

            if ($response->getStatusCode() === 200) {
                $this->info("    Cache warmed successfully in {$duration} seconds");
                return Command::SUCCESS;
            } else {
                $this->error("    Failed to warm cache (HTTP {$response->getStatusCode()})");
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("    Exception: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
