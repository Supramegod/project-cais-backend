<?php

namespace Tests\Unit\Models;

use App\Models\Quotation;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Menjaga agar relasi creator() dan jalur tulis quotation memakai kolom yang
 * benar-benar ada di sl_quotation.
 *
 * Kolom sl_quotation.created_by_id TIDAK ADA di database (yang ada
 * created_by_user_id). Pernah ada regresi di mana created_by_id dimasukkan ke
 * $fillable — Eloquent lalu menyertakannya di INSERT dan pembuatan quotation
 * gagal dengan MySQL 1054 Unknown column. Tabel sl_quotation tidak dibuat oleh
 * migration (prod mengimpor dump), jadi skemanya tidak bisa diassert di test —
 * penjagaan dilakukan di level nama kolom.
 */
class QuotationCreatorRelationTest extends TestCase
{
    #[Test]
    public function creator_relation_uses_the_column_that_exists(): void
    {
        $relation = (new Quotation)->creator();

        $this->assertSame('created_by_user_id', $relation->getForeignKeyName());
        $this->assertInstanceOf(User::class, $relation->getRelated());
    }

    #[Test]
    public function fillable_exposes_created_by_user_id_and_never_created_by_id(): void
    {
        $fillable = (new Quotation)->getFillable();

        $this->assertContains('created_by_user_id', $fillable);
        $this->assertNotContains(
            'created_by_id',
            $fillable,
            'created_by_id bukan kolom sl_quotation — memasukkannya ke $fillable membuat INSERT gagal.'
        );
    }
}
