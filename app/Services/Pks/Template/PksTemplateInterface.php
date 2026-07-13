<?php

namespace App\Services\Pks\Template;

interface PksTemplateInterface
{
    public function generateAllSections(): array;

    public function insertAgreementSections($pksId, $createdBy): void;
}
