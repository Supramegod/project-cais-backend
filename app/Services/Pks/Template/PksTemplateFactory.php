<?php

namespace App\Services\Pks\Template;

use App\Models\Company;
use App\Models\Kebutuhan;
use App\Models\Leads;
use App\Models\Pks;
use App\Models\RuleThr;
use App\Models\SalaryRule;
use App\Services\Pks\Template\PksPerjanjianTemplateService;

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
        string $pksNomor,
        ?Pks $pks = null,
        $persentase = null
    ): PksTemplateInterface {
        $class = self::MAP[$company->id] ?? PksPerjanjianTemplateService::class;

        return new $class($leads, $company, $kebutuhan, $ruleThr, $salaryRule, $pksNomor, $pks, $persentase);
    }
}
