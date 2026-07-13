<?php

namespace App\Services\Report;

/**
 * @deprecated Split into domain services under App\Services\Report\.
 * @see \App\Services\Report\ReportSalesService
 * @see \App\Services\Report\ReportRole30Service
 * @see \App\Services\Report\ReportDetailService
 */
class ReportService
{
    public function __construct(
        protected Report\ReportSalesService $salesService,
        protected Report\ReportRole30Service $role30Service,
        protected Report\ReportDetailService $detailService
    ) {}

    public function __call($method, $parameters)
    {
        $services = [$this->salesService, $this->role30Service, $this->detailService];

        foreach ($services as $service) {
            if (method_exists($service, $method)) {
                return $service->$method(...$parameters);
            }
        }

        throw new \BadMethodCallException("Method {$method} not found in any Report service.");
    }
}
