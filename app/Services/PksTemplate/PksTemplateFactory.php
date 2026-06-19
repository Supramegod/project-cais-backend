<?php

namespace App\Services\PksTemplate;

use App\Models\Company;
use App\Models\Kebutuhan;
use App\Models\Leads;
use App\Models\RuleThr;
use App\Models\SalaryRule;
use App\Services\PksPerjanjianTemplateService;

class PksTemplateFactory
{
    private const MAP = [
        13 => PksSigTemplateService::class,
        14 => PksGsuTemplateService::class,
        16 => PksRciTemplateService::class,
        17 => PksIonTemplateService::class,
    ];

    public function make(
        Leads $leads,
        Company $company,
        Kebutuhan $kebutuhan,
        RuleThr $ruleThr,
        SalaryRule $salaryRule,
        string $pksNomor
    ): PksTemplateInterface {
        $class = self::MAP[$company->id] ?? PksPerjanjianTemplateService::class;

        return new $class($leads, $company, $kebutuhan, $ruleThr, $salaryRule, $pksNomor);
    }
}
