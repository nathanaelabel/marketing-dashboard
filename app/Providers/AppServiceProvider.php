<?php

namespace App\Providers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Builder::macro('whereInMultiple', function (array $columns, array $values) {
            /** @var Builder $this */
            return $this->where(function (Builder $query) use ($columns, $values) {
                foreach ($values as $value) {
                    $query->orWhere(function (Builder $query) use ($columns, $value) {
                        foreach ($columns as $column) {
                            $query->where($column, $value[$column]);
                        }
                    });
                }
            });
        });
    }
}
