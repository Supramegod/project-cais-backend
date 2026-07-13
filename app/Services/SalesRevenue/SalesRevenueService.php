<?php

namespace App\Services\SalesRevenue;

/**
 * @deprecated Split into domain services under App\Services\SalesRevenue\.
 * @see \App\Services\SalesRevenue\SalesRevenueCalculationService
 * @see \App\Services\SalesRevenue\SalesRevenueKpiService
 * @see \App\Services\SalesRevenue\SalesRevenueQueryService
 */
class SalesRevenueService
{
    public function __construct(
        protected SalesRevenue\SalesRevenueCalculationService $calculationService,
        protected SalesRevenue\SalesRevenueKpiService $kpiService
    ) {}

    public function __call($method, $parameters)
    {
        $services = [$this->calculationService, $this->kpiService];

        foreach ($services as $service) {
            if (method_exists($service, $method)) {
                return $service->$method(...$parameters);
            }
        }

        throw new \BadMethodCallException("Method {$method} not found in any SalesRevenue service.");
    }
}
