<?php

namespace Tests\Unit\Services\Pks\Fulfillment;

use App\Services\Pks\Fulfillment\PksDashboardFilterBuilder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * @group pks-fulfillment
 */
class PksDashboardFilterBuilderTest extends TestCase
{
    private PksDashboardFilterBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new PksDashboardFilterBuilder;
    }

    /**
     * @dataProvider perPageProvider
     */
    public function test_per_page_dijepit_ke_rentang_aman(mixed $masukan, int $harapan): void
    {
        $request = Request::create('/', 'GET', $masukan === null ? [] : ['per_page' => $masukan]);

        $this->assertSame($harapan, $this->builder->perPage($request));
    }

    /**
     * @return array<string, array{0: mixed, 1: int}>
     */
    public static function perPageProvider(): array
    {
        return [
            'tanpa parameter' => [null, PksDashboardFilterBuilder::PER_PAGE_DEFAULT],
            'nilai wajar' => [25, 25],
            'batas atas' => [PksDashboardFilterBuilder::PER_PAGE_MAX, PksDashboardFilterBuilder::PER_PAGE_MAX],
            'melebihi batas' => [99999, PksDashboardFilterBuilder::PER_PAGE_MAX],
            'negatif' => [-1, PksDashboardFilterBuilder::PER_PAGE_DEFAULT],
            'nol' => [0, PksDashboardFilterBuilder::PER_PAGE_DEFAULT],
            'non numerik' => ['banyak', PksDashboardFilterBuilder::PER_PAGE_DEFAULT],
            'string angka' => ['30', 30],
        ];
    }

    public function test_search_by_tak_dikenal_melempar_validation_exception(): void
    {
        $request = Request::create('/', 'GET', ['search' => 'apa', 'search_by' => 'ngawur']);

        $this->expectException(ValidationException::class);

        $this->builder->build($request, '2026-01-01', '2026-12-31');
    }
}
