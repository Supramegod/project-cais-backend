<?php

namespace Tests\Unit\Services\Numbering;

use App\Services\Numbering\DocumentVersionChain;
use PHPUnit\Framework\TestCase;

/**
 * Unit test murni (tanpa database) untuk aturan rantai versi.
 *
 * @group numbering
 */
class DocumentVersionChainTest extends TestCase
{
    // ------------------------------------------------------------------ parse

    public function test_parse_nomor_original(): void
    {
        $parsed = DocumentVersionChain::parse('QUOT/ORG/GSU/AABKJ-072025-00001');

        $this->assertSame('072025-00001', $parsed['dateSeq']);
        $this->assertSame([], $parsed['chain']);
    }

    public function test_parse_nomor_dengan_chain_bertingkat(): void
    {
        $parsed = DocumentVersionChain::parse('QUOT/RVS/GSU/AABKJ-072025-00001-K01-V03');

        $this->assertSame('072025-00001', $parsed['dateSeq']);
        $this->assertSame(['K01', 'V03'], $parsed['chain']);
    }

    public function test_parse_membuang_prefix_draft(): void
    {
        $parsed = DocumentVersionChain::parse('draft/PKS/ORG/ION/LS001-072026-00007-A02');

        $this->assertSame('072026-00007', $parsed['dateSeq']);
        $this->assertSame(['A02'], $parsed['chain']);
    }

    public function test_parse_nomor_tak_dikenal_mengembalikan_null(): void
    {
        $parsed = DocumentVersionChain::parse('Q-001');

        $this->assertNull($parsed['dateSeq']);
        $this->assertSame([], $parsed['chain']);
    }

    public function test_parse_nomor_rusak_warisan_bug_lama(): void
    {
        // Nomor persis seperti yang dilaporkan dari produksi.
        $parsed = DocumentVersionChain::parse('QUOT/RVS/GSU/AABKJ-072025-00001-V02-V02-V02-V02');

        $this->assertSame('072025-00001', $parsed['dateSeq']);
        $this->assertSame(['V02', 'V02', 'V02', 'V02'], $parsed['chain']);
        $this->assertTrue(DocumentVersionChain::hasRepeatedCode(
            'QUOT/RVS/GSU/AABKJ-072025-00001-V02-V02-V02-V02'
        ));
    }

    public function test_nomor_sehat_tidak_dianggap_berulang(): void
    {
        $this->assertFalse(DocumentVersionChain::hasRepeatedCode('QUOT/RVS/GSU/AABKJ-072025-00001-K01-V02'));
    }

    // -------------------------------------------------------------- prefixFor

    public function test_prefix_mengganti_segmen_berhuruf_sama(): void
    {
        // Inti perbaikan: revisi dari revisi TIDAK menambah segmen V baru.
        $this->assertSame([], DocumentVersionChain::prefixFor(['V01'], 'V'));
        $this->assertSame(['K01'], DocumentVersionChain::prefixFor(['K01', 'V05'], 'V'));
    }

    public function test_prefix_mempertahankan_segmen_berhuruf_beda(): void
    {
        // Nesting antar huruf berbeda tetap terjadi.
        $this->assertSame(['K01'], DocumentVersionChain::prefixFor(['K01'], 'V'));
        $this->assertSame(['K01', 'V02'], DocumentVersionChain::prefixFor(['K01', 'V02'], 'A'));
    }

    // ------------------------------------------------------------ nextCounter

    public function test_counter_naik_dari_nomor_tertinggi_bukan_jumlah_baris(): void
    {
        $existing = [
            'QUOT/ORG/GSU/AABKJ-072025-00001',
            'QUOT/RVS/GSU/AABKJ-072025-00001-V01',
            'QUOT/RVS/GSU/AABKJ-072025-00001-V02',
        ];

        $this->assertSame(3, DocumentVersionChain::nextCounter($existing, '072025-00001', [], 'V'));
    }

    public function test_counter_terpisah_per_prefix(): void
    {
        $existing = [
            'QUOT/ORG/GSU/AABKJ-072025-00001',
            'QUOT/RVS/GSU/AABKJ-072025-00001-V01',
            'QUOT/RKT/GSU/AABKJ-072025-00001-K01',
            'QUOT/RVS/GSU/AABKJ-072025-00001-K01-V01',
            'QUOT/RVS/GSU/AABKJ-072025-00001-K01-V02',
        ];

        // Revisi di level akar hanya melihat V di level akar.
        $this->assertSame(2, DocumentVersionChain::nextCounter($existing, '072025-00001', [], 'V'));

        // Revisi di bawah K01 hanya melihat V di bawah K01.
        $this->assertSame(3, DocumentVersionChain::nextCounter($existing, '072025-00001', ['K01'], 'V'));

        // Rekontrak berikutnya di level akar.
        $this->assertSame(2, DocumentVersionChain::nextCounter($existing, '072025-00001', [], 'K'));
    }

    public function test_counter_mengabaikan_keluarga_dateseq_lain(): void
    {
        $existing = [
            'QUOT/RVS/GSU/AABKJ-072025-00009-V07',
        ];

        $this->assertSame(1, DocumentVersionChain::nextCounter($existing, '072025-00001', [], 'V'));
    }

    // ----------------------------------------------------------------- render

    public function test_render_menyusun_nomor_lengkap(): void
    {
        $this->assertSame(
            'QUOT/RVS/GSU/AABKJ-072025-00001-K01-V02',
            DocumentVersionChain::render('QUOT/RVS/GSU/AABKJ-', '072025-00001', ['K01', 'V02'])
        );

        $this->assertSame(
            'QUOT/ORG/GSU/AABKJ-072025-00001',
            DocumentVersionChain::render('QUOT/ORG/GSU/AABKJ-', '072025-00001', [])
        );
    }

    // ----------------------------------------------------------- nextSequence

    public function test_sequence_dari_nomor_tertinggi(): void
    {
        $existing = [
            'QUOT/ORG/GSU/AABKJ-072025-00001',
            'QUOT/ORG/GSU/AABKJ-072025-00003',
        ];

        // Bukan count()+1 (yang akan menghasilkan 3 dan menabrak 00003).
        $this->assertSame(
            4,
            DocumentVersionChain::nextSequence($existing, 'QUOT/ORG/GSU/AABKJ-072025-')
        );
    }

    public function test_sequence_memperhitungkan_nomor_draft(): void
    {
        $existing = [
            'draft/PKS/ORG/ION/LS001-072026-00004',
        ];

        $this->assertSame(
            5,
            DocumentVersionChain::nextSequence($existing, 'PKS/ORG/ION/LS001-072026-')
        );
    }

    // ----------------------------------------------------------- assertLength

    public function test_guard_panjang_melempar_exception(): void
    {
        $panjang = 'QUOT/RVS/GSU/' . str_repeat('X', DocumentVersionChain::MAX_LENGTH) . '-072025-00001';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('melebihi batas');

        DocumentVersionChain::assertLength($panjang);
    }

    public function test_guard_panjang_meloloskan_nomor_normal(): void
    {
        $nomor = 'QUOT/RVS/GSU/AABKJ-072025-00001-K01-V02';

        $this->assertSame($nomor, DocumentVersionChain::assertLength($nomor));
    }
}
