<?php

namespace App\Services\PksTemplate;

interface PksTemplateInterface
{
    public function generateAllSections(): array;

    public function insertAgreementSections($pksId, $createdBy): void;
}
