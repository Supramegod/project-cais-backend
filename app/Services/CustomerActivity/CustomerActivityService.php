<?php

namespace App\Services\CustomerActivity;

/**
 * @deprecated Split into domain services under App\Services\CustomerActivity\.
 * @see \App\Services\CustomerActivity\ActivityQueryService
 * @see \App\Services\CustomerActivity\ActivityCommandService
 * @see \App\Services\CustomerActivity\ActivityEmailService
 * @see \App\Services\CustomerActivity\ActivityContractService
 */
class CustomerActivityService
{
    public function __construct(
        protected CustomerActivity\ActivityQueryService $queryService,
        protected CustomerActivity\ActivityCommandService $commandService,
        protected CustomerActivity\ActivityEmailService $emailService,
        protected CustomerActivity\ActivityContractService $contractService
    ) {}

    public function __call($method, $parameters)
    {
        $services = [
            $this->queryService,
            $this->commandService,
            $this->emailService,
            $this->contractService,
        ];

        foreach ($services as $service) {
            if (method_exists($service, $method)) {
                return $service->$method(...$parameters);
            }
        }

        throw new \BadMethodCallException("Method {$method} not found in any CustomerActivity service.");
    }
}
