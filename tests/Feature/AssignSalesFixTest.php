<?php

namespace Tests\Feature;

use App\Models\Leads;
use App\Models\LeadsKebutuhan;
use App\Models\Kebutuhan;
use App\Models\TimSalesDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AssignSalesFixTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $lead;
    protected $salesDetail;
    protected $kebutuhan;
    protected $kebutuhan2;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user with allowed role
        $this->user = User::factory()->create([
            'cais_role_id' => 30
        ]);

        // Create kebutuhan
        $this->kebutuhan = Kebutuhan::factory()->create(['nama' => 'Kebutuhan A']);
        $this->kebutuhan2 = Kebutuhan::factory()->create(['nama' => 'Kebutuhan B']);

        // Create sales detail
        $this->salesDetail = TimSalesDetail::factory()->create();

        // Create lead
        $this->lead = Leads::factory()->create();
    }

    /**
     * Test Fix #2: updateOrCreate tidak membuat duplikasi record
     * Skenario: Assign kebutuhan ke sales A, kemudian ke sales B
     * Expected: Record di-UPDATE, bukan di-CREATE duplikat
     */
    public function test_assign_same_kebutuhan_to_different_sales_updates_not_duplicates()
    {
        $sales1 = TimSalesDetail::factory()->create();
        $sales2 = TimSalesDetail::factory()->create();

        // Assign pertama
        $response = $this->actingAs($this->user)->putJson("/api/leads/assign-sales/{$this->lead->id}", [
            'assignments' => [
                [
                    'tim_sales_d_id' => $sales1->id,
                    'kebutuhan_ids' => [$this->kebutuhan->id]
                ]
            ]
        ]);

        $response->assertStatus(200);

        // Cek 1 record terbuat
        $this->assertCount(1, LeadsKebutuhan::where('leads_id', $this->lead->id)->get());
        $this->assertEquals($sales1->id, LeadsKebutuhan::where('leads_id', $this->lead->id)->first()->tim_sales_d_id);

        // Assign kedua ke sales berbeda
        $response = $this->actingAs($this->user)->putJson("/api/leads/assign-sales/{$this->lead->id}", [
            'assignments' => [
                [
                    'tim_sales_d_id' => $sales2->id,
                    'kebutuhan_ids' => [$this->kebutuhan->id]
                ]
            ]
        ]);

        $response->assertStatus(200);

        // ✅ Masih 1 record, bukan 2 (di-UPDATE, bukan duplikasi)
        $this->assertCount(1, LeadsKebutuhan::where('leads_id', $this->lead->id)->get());

        // ✅ Sales sudah ter-UPDATE ke sales2
        $this->assertEquals($sales2->id, LeadsKebutuhan::where('leads_id', $this->lead->id)->first()->tim_sales_d_id);
    }

    /**
     * Test Fix #1: Pre-load kebutuhan tanpa N+1 query
     * Expected: Tidak ada N+1 query saat fetch nama kebutuhan
     */
    public function test_assign_sales_preloads_kebutuhan_without_n_plus_one()
    {
        $sales = TimSalesDetail::factory()->create();

        // Assign multiple kebutuhan
        $response = $this->actingAs($this->user)->putJson("/api/leads/assign-sales/{$this->lead->id}", [
            'assignments' => [
                [
                    'tim_sales_d_id' => $sales->id,
                    'kebutuhan_ids' => [$this->kebutuhan->id, $this->kebutuhan2->id]
                ]
            ]
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.assignments.0.sales_assigned.sales_name', $sales->user->full_name);
        $response->assertJsonPath('data.assignments.0.kebutuhan_assigned', [$this->kebutuhan->id, $this->kebutuhan2->id]);
    }

    /**
     * Test Fix #3: Soft delete filter pada leadsKebutuhan relationship
     * Expected: Kebutuhan yang di-soft-delete tidak di-include
     */
    public function test_leads_kebutuhan_relationship_filters_soft_deleted_records()
    {
        $sales = TimSalesDetail::factory()->create();

        // Create records
        LeadsKebutuhan::create([
            'leads_id' => $this->lead->id,
            'kebutuhan_id' => $this->kebutuhan->id,
            'tim_sales_id' => $sales->tim_sales_id,
            'tim_sales_d_id' => $sales->id
        ]);

        LeadsKebutuhan::create([
            'leads_id' => $this->lead->id,
            'kebutuhan_id' => $this->kebutuhan2->id,
            'tim_sales_id' => $sales->tim_sales_id,
            'tim_sales_d_id' => $sales->id
        ]);

        // Soft delete satu
        LeadsKebutuhan::where('leads_id', $this->lead->id)
            ->where('kebutuhan_id', $this->kebutuhan2->id)
            ->delete();

        // ✅ Via relationship, hanya 1 record (tidak termasuk soft deleted)
        $this->assertCount(1, $this->lead->leadsKebutuhan);
        $this->assertEquals($this->kebutuhan->id, $this->lead->leadsKebutuhan->first()->kebutuhan_id);
    }

    /**
     * Test Fix #4: Activity log mencatat semua sales (bukan cuma terakhir)
     * Expected: Activity notes mencakup semua sales yang di-assign
     */
    public function test_assign_sales_activity_log_includes_all_sales()
    {
        $sales1 = TimSalesDetail::factory()->create();
        $sales2 = TimSalesDetail::factory()->create();

        $response = $this->actingAs($this->user)->putJson("/api/leads/assign-sales/{$this->lead->id}", [
            'assignments' => [
                [
                    'tim_sales_d_id' => $sales1->id,
                    'kebutuhan_ids' => [$this->kebutuhan->id]
                ],
                [
                    'tim_sales_d_id' => $sales2->id,
                    'kebutuhan_ids' => [$this->kebutuhan2->id]
                ]
            ]
        ]);

        $response->assertStatus(200);

        // ✅ Activity log harus include kedua sales names
        $activity = \App\Models\CustomerActivity::where('leads_id', $this->lead->id)->first();
        $this->assertNotNull($activity);
        $this->assertStringContainsString($sales1->user->full_name, $activity->notes);
        $this->assertStringContainsString($sales2->user->full_name, $activity->notes);
    }

    /**
     * Test authorization check
     */
    public function test_assign_sales_requires_allowed_role()
    {
        $unauthorized = User::factory()->create(['cais_role_id' => 99]);

        $response = $this->actingAs($unauthorized)->putJson("/api/leads/assign-sales/{$this->lead->id}", [
            'assignments' => [
                [
                    'tim_sales_d_id' => $this->salesDetail->id,
                    'kebutuhan_ids' => [$this->kebutuhan->id]
                ]
            ]
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test lead not found
     */
    public function test_assign_sales_lead_not_found()
    {
        $response = $this->actingAs($this->user)->putJson('/api/leads/assign-sales/99999', [
            'assignments' => [
                [
                    'tim_sales_d_id' => $this->salesDetail->id,
                    'kebutuhan_ids' => [$this->kebutuhan->id]
                ]
            ]
        ]);

        $response->assertStatus(404);
    }
}
