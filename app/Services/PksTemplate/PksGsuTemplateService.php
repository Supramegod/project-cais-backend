<?php

namespace App\Services\PksTemplate;

use App\Models\Leads;
use App\Models\Company;
use App\Models\Kebutuhan;
use App\Models\RuleThr;
use App\Models\SalaryRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Template Perjanjian Kerja Sama (PKS) untuk PT GLOBAL SECURINDO UTAMA (Satpam)
 * Auto-generated dari dokumen draft PKS, mengikuti struktur
 * dan arsitektur yang sama dengan PksPerjanjianTemplateService.
 */
class PksGsuTemplateService implements PksTemplateInterface
{
    private $leads;
    private $company;
    private $kebutuhan;
    private $ruleThr;
    private $salaryRule;
    private $pksNomor;
    private $currentDateTime;

    public function __construct(
        Leads $leads,
        Company $company,
        Kebutuhan $kebutuhan,
        RuleThr $ruleThr,
        SalaryRule $salaryRule,
        string $pksNomor
    ) {
        $this->leads = $leads;
        $this->company = $company;
        $this->kebutuhan = $kebutuhan;
        $this->ruleThr = $ruleThr;
        $this->salaryRule = $salaryRule;
        $this->pksNomor = $pksNomor;
        $this->currentDateTime = Carbon::now();
    }

    /**
     * Generate all agreement sections
     */
    public function generateAllSections(): array
    {
        return [
            [
                'pasal' => 'Pembukaan',
                'judul' => 'Pembukaan',
                'raw_text' => $this->generatePembukaan()
            ],
            [
                'pasal' => 'Pasal 1',
                'judul' => 'RUANG LINGKUP PERJANJIAN',
                'raw_text' => $this->generatePasal1()
            ],
            [
                'pasal' => 'Pasal 2',
                'judul' => 'HAK & KEWAJIBAN PARA PIHAK',
                'raw_text' => $this->generatePasal2()
            ],
            [
                'pasal' => 'Pasal 3',
                'judul' => 'TUNJANGAN HARI RAYA',
                'raw_text' => $this->generatePasal3()
            ],
            [
                'pasal' => 'Pasal 4',
                'judul' => 'BIAYA',
                'raw_text' => $this->generatePasal4()
            ],
            [
                'pasal' => 'Pasal 5',
                'judul' => 'JAMINAN PIHAK KEDUA',
                'raw_text' => $this->generatePasal5()
            ],
            [
                'pasal' => 'Pasal 6',
                'judul' => 'FORCE MAJEURE',
                'raw_text' => $this->generatePasal6()
            ],
            [
                'pasal' => 'Pasal 7',
                'judul' => 'JANGKA WAKTU PERJANJIAN',
                'raw_text' => $this->generatePasal7()
            ],
            [
                'pasal' => 'Pasal 8',
                'judul' => 'PENYELESAIAN PERSELISIHAN',
                'raw_text' => $this->generatePasal8()
            ],
            [
                'pasal' => 'Pasal 9',
                'judul' => 'PENUTUP',
                'raw_text' => $this->generatePasal9()
            ],
            [
                'pasal' => 'LAMPIRAN',
                'judul' => "Lampiran Lampiran PKS No: " . $this->pksNomor,
                'raw_text' => $this->generateLampiran()
            ],
        ];
    }

    /**
     * Generate Pembukaan section
     */
    private function generatePembukaan()
    {
        $tanggalSekarang = Carbon::now()->locale('id')->isoFormat('dddd, D MMMM Y');

        return '<p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:14pt;font-weight:bold;margin:0 0 6pt 0">PERJANJIAN KERJASAMA ALIH DAYA</p><p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:14pt;font-weight:bold;margin:0 0 4pt 0">ANTARA</p><p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:14pt;font-weight:bold;margin:0 0 4pt 0">' . $this->leads->nama_perusahaan . '</p><p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:14pt;font-weight:bold;margin:0 0 4pt 0">DENGAN</p><p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:14pt;font-weight:bold;margin:0 0 6pt 0">' . $this->company->name . '</p><p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 12pt 0">No: ' . $this->pksNomor . '</p><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0"><b>Pada hari ini, ' . $tanggalSekarang . ', telah disepakati Perjanjian Kerjasama Alih Daya.</b></p><p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 8pt 0">Antara:</p><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0"><b>' . $this->leads->nama_perusahaan . '</b> : Suatu perseroan terbatas yang berkedudukan di ……………. Kota …… Provinsi …………. dalam hal ini diwakili oleh <b>' . strtoupper($this->leads->pic) . '</b> selaku Direktur. Untuk selanjutnya dalam perjanjian ini disebut sebagai <b>PIHAK PERTAMA</b>.</p><p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 8pt 0">Dan</p><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4"><strong>' . $this->company->name . '      </strong>: 	Suatu perseroan terbatas yang berkedudukan di Jl. Semampir Selatan V A  No. 18  Kota  Surabaya  Provinsi Jawa Timur Dalam hal ini diwakili oleh <strong>' . strtoupper($this->company->nama_direktur) . '</strong> selaku <strong>Direktur</strong> <strong>' . $this->company->name . ' </strong>Berdasarkan Akta Pendirian Nomor 5711 Tanggal 30 Agustus 2023 dibuat dihadapan notaris Santy Sagita SH.MKn Notaris di kota Cilegon  yang telah mendapatkan Penetapan pengesahan dari Menteri hukum dan hak asasi manusia Nomor AHU-0064890 AH.01.01 Tahun 2023  tanggal 31 Agustus 2023 Untuk selanjutnya dalam perjanjian ini disebut sebagai <strong>PIHAK KEDUA. </strong></p><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4"><strong>PIHAK PERTAMA </strong>dan <strong>PIHAK KEDUA </strong>selanjutnya secara bersama-sama akan disebut sebagai <strong>PARA PIHAK.</strong></p><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4">Sebelumnya<strong> PIHAK PERTAMA </strong>dan<strong> PIHAK KEDUA </strong>menerangkan hal – hal sebagai berikut:</p><ol style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;padding-left:22pt;line-height:1.4"><li style="margin-bottom:6pt">Bahwa, <strong>PIHAK PERTAMA</strong> adalah pihak Pemberi kerja yang menyerahkan pekerjaan kepada pihak Penerima Kerja dalam hal ini yang ditunjuk adalah <strong>PIHAK KEDUA </strong></li><li style="margin-bottom:6pt">Bahwa Pekerjaan yang diserahkan <strong>PIHAK PERTAMA </strong>pada <strong>PIHAK KEDUA </strong>adalah Alih Daya sebagai Penyedia jasa pengamanan</li><li style="margin-bottom:6pt">Bahwa, <strong>PIHAK KEDUA</strong> adalah badan usaha yang secara hukum diijinkan menjalankan usaha Alih Daya dan sanggup memenuhi kebutuhan <strong>PIHAK PERTAMA</strong> </li><li style="margin-bottom:6pt">Bahwa apabila terjadi pergantian perusahaan Alih Daya  dan jenis pekerjaan masih tetap ada, maka semua Pekerja/buruh yang masih ada akan beralih kepada perusahaan Alih Daya selanjutnya.</li><li style="margin-bottom:6pt">Bahwa <strong>PIHAK KEDUA</strong> telah memberikan induksi dan edukasi Keselamatan,Kesehatan Kerja dan Lingkungan yang akan ditempatkan di <strong>PIHAKPERTAMA</strong>.</li></ol><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4">Berdasarkan hal – hal tersebut di atas kedua belah pihak sepakat untuk mengadakan Perjanjian Kerjasama Alih Daya dengan ketentuan sebagai berikut :</p>';
    }

    /**
     * Generate Pasal 1 section
     */
    private function generatePasal1()
    {
        return '<p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 2pt 0">Pasal 1</p><p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 10pt 0">RUANG LINGKUP PERJANJIAN</p><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4"><strong>PIHAK PERTAMA </strong>sebagai Perusahaan Pemberi Pekerjaan menunjuk<strong> PIHAK KEDUA </strong>sebagai Perusahaan Penyedia Tenaga Keamanan  untuk <strong>PIHAK PERTAMA </strong>dan atas pelaksanaan pekerjaan tersebut <strong>PIHAK PERTAMA </strong>membayarkan Management Fee kepada <strong>PIHAK KEDUA.</strong></p>';
    }

    /**
     * Generate Pasal 2 section
     */
    private function generatePasal2()
    {
        return '<p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 2pt 0">Pasal 2</p><p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 10pt 0">HAK & KEWAJIBAN PARA PIHAK</p><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4"><strong>2.1 KEWAJIBAN PIHAK PERTAMA :</strong></p><ol style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;padding-left:22pt;line-height:1.4"><li style="margin-bottom:6pt">Memberikan segala informasi terkait dengan pelaksanaan pekerjaan yang tidak terbatas pada syarat - syarat dilaksanakannya pekerjaan termasuk mematuhi segala perintah,instruksi termasuk peraturan dan atau ketentuan yang diberlakukan oleh <strong>PIHAK PERTAMA</strong>,sepanjang tidak bertentangan dengan isi perjanjian ini,ketertiban kesusilaan,dan atau peraturan dibidang ketenagakerjaan pada pekerja dari <strong>PIHAK KEDUA.</strong></li><li style="margin-bottom:6pt">Atas Pelaksanaan pekerjaan yang diberikan <strong>PIHAK PERTAMA</strong> pada <strong>PIHAK KEDUA </strong>maka akan diterbitkan invoice pembayaran oleh <strong>PIHAK KEDUA</strong> dan menjadi kewajiban <strong>PIHAK PERTAMA</strong> untuk melakukan pembayaran.</li><li style="margin-bottom:6pt">Bila ada tambahan kebutuhan jam kerja di luar jam kerja biasanya, maka <strong>PIHAK PERTAMA</strong> akan menghitung dan memberikan biaya tambahan yang terjadi akibat penambahan jam kerja atau lembur tersebut pada pekerja. </li><li style="margin-bottom:6pt">Mematuhi segala peraturan perundang-undangan terkait dengan perjanjian Alih Daya  dan peraturan lain di bidang ketenagakerjaan yang berlaku.</li></ol><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4"><strong>2.2 KEWAJIBAN PIHAK KEDUA :</strong></p><ol style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;padding-left:22pt;line-height:1.4"><li style="margin-bottom:6pt">Menyediakan Tenaga Kerja berdasarkan permintaan secara tertulis kepada <strong>PIHAK PERTAMA</strong>, permintaan tertulis yang mana berisikan jangka waktu, persyaratan keterampilan yang dibutuhkan, jenis pekerjaan, jumlah Tenaga Kerja yang dibutuhkan, upah/gaji dan kompensasi lainnya yang ditawarkan kepada Tenaga Kerja.</li><li style="margin-bottom:6pt">Menjaga kerahasiaan <strong>PIHAK PERTAMA</strong> tidak terbatas pada semua keterangan, data – data, catatan – catatan yang diperoleh baik langsung maupun tidak langsung, kepada pihak lain tanpa izin tertulis dari <strong>PIHAK PERTAMA</strong> baik selama berlakunya Perjanjian ini maupun sesudah Perjanjian ini berakhir. Untuk keperluan ini <strong>PIHAK KEDUA</strong> wajib memasikan bahwa Tenaga Kerja telah menandatangani Surat Pernyataan untuk menjaga kerahasiaan <strong>PIHAK PERTAMA.</strong></li><li style="margin-bottom:6pt">Membebaskan <strong>PIHAK PERTAMA </strong>dari segala tuntutan ketenagakerjaan dari pekerja <strong>PIHAK KEDUA </strong>akibat timbulnya dari perjanjian pemborongan pekerjaan tersebut.</li><li style="margin-bottom:6pt">Menyelesaikan secara tuntas segala permasalahan yang timbul baik dalam hubungan dengan pekerja atau pihak lain terkait dengan pelaksanaan perjanjian ini. Termasuk memberikan sanksi secara tegas atas tindakan pelanggaran atau penyelewengan dari pekerja <strong>PIHAK KEDUA </strong>terhadap tata tertib dan segala peraturan yang berlaku di <strong>PIHAK PERTAMA</strong>.</li><li style="margin-bottom:6pt">Menghitung dan membayar Gaji/Upah, Premi Asuransi, Pengakhiran hubungan kerja dan pembayaran lainnya (apabila ada) atas setiap Tenaga Kerja yang dikaryakan di <strong>PIHAK PERTAMA.</strong></li><li style="margin-bottom:6pt">Bahwa tenaga kerja dari <strong>PIHAK KEDUA</strong> yang ditempatkan di lokasi <strong>PIHAK PERTAMA</strong> telah menerima induksi dan edukasi keselamatan, kesehatan kerja dan lingkungan ( K3 L ) dari <strong>PIHAK KEDUA.</strong><ol><li style="margin-bottom:6pt"><strong>HAK PIHAK PERTAMA :</strong></li></ol><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4">Menerima Pekerja dari <strong>PIHAK KEDUA </strong>sesuai dengan syarat-syarat pekerja kompetensi,kualitas dan pencapaian hasil yang telah ditentukan oleh<strong> PIHAK PERTAMA</strong>.<strong> </strong></p><ol style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;padding-left:22pt;line-height:1.4"><li style="margin-bottom:6pt"><strong>HAK PIHAK KEDUA :</strong></li></ol><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4">Menerima pembayaran atas pekerjaan yang telah dilakukan sebagai pihak penerima kerja pada pihak pemberi kerja dalam hal ini <strong>PIHAK PERTAMA</strong></p>';
    }

    /**
     * Generate Pasal 3 section
     */
    private function generatePasal3()
    {
        return '<p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 2pt 0">Pasal 3</p><p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 10pt 0">TUNJANGAN HARI RAYA</p><ol style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;padding-left:22pt;line-height:1.4"><li style="margin-bottom:6pt">Pekerja yang ditempatkan di <strong>PIHAK PERTAMA</strong> akan mendapatkan Tunjangan Hari Raya (THR) dari <strong>PIHAK KEDUA</strong> sesuai dengan ketentuan peraturan yang berlaku.</li><li style="margin-bottom:6pt">Bahwa <strong>PIHAK KEDUA </strong>akan menagihkan komponen THR pada <strong>PIHAK PERTAMA</strong> dengan berdasarkan upah yang telah disepakati.</li><li style="margin-bottom:6pt">Berdasarkan ketentuan <strong>3.2 </strong>diatas maka untuk pembayaran THR dibayarkan secara proporsional berdasarkan masa kontrak Perjanjian Kerjasama (PKS) antara <strong>PIHAK PERTAMA</strong> dengan <strong>PIHAK KEDUA</strong> berjalan, dengan ditagihkan setiap bulan pada invoice berjalan.</li><li style="margin-bottom:6pt">Schedul THR :</li></ol><table style="border-collapse:collapse;width:100%;font-family:\'Arial\',sans-serif;font-size:11pt;margin:8pt 0"><tr><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p><strong>NO</strong></p></td><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p><strong>Schedule Plan</strong></p></td><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p><strong>Tanggal</strong></p></td></tr><tr><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>1</p></td><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>Penagihan Invoive THR</p></td><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>…………..</p></td></tr><tr><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>2</p></td><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>Pembayaran Invoice THR</p></td><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>……………….</p></td></tr><tr><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>3</p></td><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>Release THR</p></td><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>………………</p></td></tr></table>';
    }

    /**
     * Generate Pasal 4 section
     */
    private function generatePasal4()
    {
        return '<p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 2pt 0">Pasal 4</p><p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 10pt 0">BIAYA</p><ol style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;padding-left:22pt;line-height:1.4"><li style="margin-bottom:6pt">Komponen dalam invoice adalah :</li></ol><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4">a. Gaji Pokok </p><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4">b. Manajemen Fee</p><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4">c. BPJS Ketenagakerjaan</p><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4">d. BPJS Kesehatan</p><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4">Biaya Jasa/Manajemen Fee ditetapkan sebesar ….%(……) </p><ol style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;padding-left:22pt;line-height:1.4"><li style="margin-bottom:6pt"> Pembiayaan</li></ol><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4">Mengacu pada rincian pembiayaan, ringkasan penagihan diterbitkan oleh <strong>PIHAK KEDUA</strong> setiap bulan kepada <strong>PIHAK PERTAMA </strong>sesuai dengan Kesepakatan <strong>PARA</strong> <strong>PIHAK.</strong></p><ol style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;padding-left:22pt;line-height:1.4"><li style="margin-bottom:6pt"><strong>Jadwal Penagihan dan Pembayaran</strong></li></ol><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4"><strong>PIHAK KEDUA</strong> menerbitkan Tagihan kepada <strong>PIHAK PERTAMA</strong> paling lambat tanggal … (….) bulan berjalan dan <strong>PIHAK PERTAMA</strong> melakukan pembayaran paling lambat .. (…) hari setelah invoice di terbitkan ke rekening <strong>PIHAK KEDUA </strong>setelah invoice asli diterima oleh<strong> PIHAK PERTAMA</strong>:</p><table style="border-collapse:collapse;width:100%;font-family:\'Arial\',sans-serif;font-size:11pt;margin:8pt 0"><tr><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p><strong>No</strong></p></td><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p><strong>Keterangan</strong></p></td><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p><strong>Tanggal</strong></p></td></tr><tr><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>1</p></td><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>Periode Cut Off</p></td><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>…….</p></td></tr><tr><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>2</p></td><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>Crosscheck Absensi</p></td><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>….</p></td></tr><tr><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>3</p></td><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>Pengiriman Invoice</p></td><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>…..</p></td></tr><tr><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>4</p></td><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>Release Pembayaran</p></td><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>..........</p></td></tr><tr><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>5</p></td><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>Release Gaji / Penggajian</p></td><td style="border:1px solid #000;padding:4pt 6pt;vertical-align:top"><p>.......</p></td></tr></table><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4"><strong>BANK ………….</strong></p><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4"><strong>a.n ' . $this->company->name . '</strong></p><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4"><strong>a/c ……………………..</strong></p><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4">Jika tanggal jatuh tempo jatuh pada hari Sabtu, Minggu atau hari Libur Nasional, maka <strong>PIHAK PERTAMA</strong> dapat melakukan pembayaran pada minggu berikutnya. Keterlambatan pembayaran lebih dari satu minggu, akan dikenakan denda per hari 0.1 % (Nol koma satu persen) dari nilai kontrak.</p>';
    }

    /**
     * Generate Pasal 5 section
     */
    private function generatePasal5()
    {
        return '<p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 2pt 0">Pasal 5</p><p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 10pt 0">JAMINAN PIHAK KEDUA</p><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4">Pekerja yang ditempatkan oleh <strong>PIHAK KEDUA</strong> telah melalui Proses :</p><ol style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;padding-left:22pt;line-height:1.4"><li style="margin-bottom:6pt">Selalu melakukan wawancara dalam proses seleksi dan penerimaan.</li><li style="margin-bottom:6pt">Pemeriksaan Dokumen Tenaga Kerja mencakup identitas diri (termasuk foto), ijasah atau sertifikat yang menerangkan pendidikan formal maupun non formal yang pernah ditempuh Tenaga Kerja, Surat Referensi. </li></ol><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4">Bahwa Tenaga Kerja yang ditempatkan pada <strong>PIHAK PERTAMA </strong>tunduk kepada peraturan <strong>PIHAK PERTAMA</strong> dan <strong>PIHAK KEDUA</strong>. Jika terjadi pelanggaran atas peraturan internal <strong>PIHAK PERTAMA</strong> maka <strong>PIHAK PERTAMA</strong> wajib memberitahukan kepada <strong>PIHAK KEDUA</strong> untuk pembuatan Surat Peringatan tahap pertama sampai dengan tahap ketiga beserta pengambilan tindakan/sanksi sebagaimana mestinya sesuai dengan peraturan perundang – undangan yang berlaku.</p>';
    }

    /**
     * Generate Pasal 6 section
     */
    private function generatePasal6()
    {
        return '<p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 2pt 0">Pasal 6</p><p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 10pt 0">FORCE MAJEURE</p><ol style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;padding-left:22pt;line-height:1.4"><li style="margin-bottom:6pt">Yang dimaksud dengan <em>force majeure</em> adalah keadaan yang tidak dapat dipenuhinya pelaksanaan Perjanjian oleh Para Pihak, karena terjadi suatu peristiwa yang bukan karena kesalahan Para Pihak, peristiwa mana tidak dapat diketahui/ tidak dapat diduga sebelumnya dan di luar kemampuan manusia, seperti bencana alam (gempa bumi, angin topan, kebakaran, banjir), huru-hara, perang, pemogokan umum yang bukan kesalahan Para Pihak, <em>sabotase</em>, pemberontakan, dan <em>epidemi</em> yang secara keseluruhan ada hubungan langsung dengan penyelesaian pelaksanaan Perjanjian ini;</li><li style="margin-bottom:6pt">Apabila terjadi <em>force majeure</em>, maka Pihak yang terkena <em>force majeure</em> harus memberitahukan secara tertulis kepada Pihak yang tidak terkena <em>force majeure</em> selambat-lambatnya 7 (tujuh) hari kalender sejak terjadinya <em>force majeure</em> tersebut disertai bukti-bukti yang sah, selanjutnya Pihak yang tidak terkena <em>force majeure</em> akan menanggapi;</li><li style="margin-bottom:6pt">Apabila hal tersebut tidak dilakukan oleh Pihak yang terkena <em>force majeure</em>, maka Pihak yang tidak terkena <em>force majeure</em> menganggap tidak terjadi <em>force majeure</em>;</li><li style="margin-bottom:6pt">Dalam hal terjadi <em>force majeure</em>, maka pelaksanaan kewajiban masing-masing Pihak akan ditunda berdasarkan kesepakatan Para Pihak</li></ol>';
    }

    /**
     * Generate Pasal 7 section
     */
    private function generatePasal7()
    {
        return '<p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 2pt 0">Pasal 7</p><p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 10pt 0">JANGKA WAKTU PERJANJIAN</p><ol style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;padding-left:22pt;line-height:1.4"><li style="margin-bottom:6pt">Masa berlakunya Perjanjian ini adalah terhitung dari <strong>Tanggal….Bulan… 2026 sampai </strong>dengan<strong> tanggal…Bulan… 2027</strong> dan dapat ditinjau kembali oleh <strong>PARA PIHAK.</strong></li><li style="margin-bottom:6pt">Sebelum perjanjian ini berakhir <strong>PARA PIHAK </strong>akan memberitahukan secara tertulis maksud untuk memperpanjang atau tidak memperpanjang Perjanjian ini 30 hari sebelum berakhirnya perjanjian ini.</li><li style="margin-bottom:6pt">Apabila <strong>PARA PIHAK</strong> tidak memberitahukan hal tersebut maka secara otomatis perjanjian ini diperpanjang dengan mendasarkan pada ketentuan Upah Minimum Kabupaten/Kota yang berlaku.</li></ol>';
    }

    /**
     * Generate Pasal 8 section
     */
    private function generatePasal8()
    {
        return '<p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 2pt 0">Pasal 8</p><p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 10pt 0">PENYELESAIAN PERSELISIHAN</p><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4">Apabila dikemudian hari terjadi perselisihan atau permasalahan antara kedua belah pihak, sehubungan dengan pelaksanaan dan penafsiran perjanjian ini, maka <strong>PARA PIHAK</strong> setuju untuk menyelesaikan permasalahan atau perselisihan dengan musyawarah untuk mufakat.</p>';
    }

    /**
     * Generate Pasal 9 section
     */
    private function generatePasal9()
    {
        return '<p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 2pt 0">Pasal 9</p><p style="text-align:center;font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 10pt 0">PENUTUP</p><ol style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;padding-left:22pt;line-height:1.4"><li style="margin-bottom:6pt">Apabila terdapat perubahan, tambahan dan atau hal – hal lain yang belum cukup diatur dalam Perjanjian ini, maka akan dibuat secara tertulis dan ditanda tangani oleh kedua belah pihak dan merupakan bagian yang tidak terpisahkan dari Perjanjian ini.</li><li style="margin-bottom:6pt">Perjanjian ini dibuat rangkap dua (2), masing – masing bermaterai cukup dan memiliki kekuatan hukum yang sama dan berlaku sejak ditandatangani oleh kedua belah pihak.</li></ol><table style="border-collapse:collapse;width:100%;font-family:\'Arial\',sans-serif;font-size:12pt;margin:12pt 0"><tr><td style="width:50%;padding:4pt 10pt;vertical-align:top;border:none"><p style="margin:0 0 4pt 0"><b>PIHAK PERTAMA</b></p><p style="margin:0 0 4pt 0"><b>' . $this->leads->nama_perusahaan . '</b></p><p style="margin:0;height:60pt">&nbsp;</p><p style="margin:0 0 2pt 0"><b><u>' . strtoupper($this->leads->pic) . '</u></b></p><p style="margin:0"><b>Direktur</b></p></td><td style="width:50%;padding:4pt 10pt;vertical-align:top;border:none"><p style="margin:0 0 4pt 0"><b>PIHAK KEDUA</b></p><p style="margin:0 0 4pt 0"><b>' . $this->company->name . '</b></p><p style="margin:0;height:60pt">&nbsp;</p><p style="margin:0 0 2pt 0"><b><u>' . strtoupper($this->company->nama_direktur) . '</u></b></p><p style="margin:0"><b>Direktur</b></p></td></tr></table>';
    }

    /**
     * Generate Lampiran section
     */
    private function generateLampiran()
    {
        return '<p style="font-family:\'Arial\',sans-serif;font-size:12pt;font-weight:bold;margin:0 0 8pt 0">Lampiran PKS No: ' . $this->pksNomor . '</p><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4"><strong>Lampiran Harga:</strong></p><p style="text-align:justify;font-family:\'Arial\',sans-serif;font-size:12pt;margin:0 0 8pt 0;line-height:1.4"><em>Note: Mengacu pada ketentuan dalam Peraturan Pemerintah Nomor 35 Tahun 2021, khususnya Pasal 15,16, dan 17 mengenai kompensasi atas Perjanjian Kerja Waktu Tertentu (PKWT), apabila terdapat temuan atau pelaporan terkait hak kompensasi PKWT oleh Tenaga Kerja kepada instansi atau lembaga yang berwenang, maka nilai kompensasi dimaksud akan menjadi tanggung jawab PIHAK PERTAMA dan wajib dibayarkan oleh PIHAK PERTAMA kepada PIHAK KEDUA, sesuai dengan ketentuan peraturan perundang-undangan yang berlaku</em><strong><em>	</em></strong></p>';
    }

    /**
     * Insert all agreement sections into database
     */
    public function insertAgreementSections($pksId, $createdBy): void
    {
        $sections = $this->generateAllSections();
        $insertData = [];

        foreach ($sections as $section) {
            $insertData[] = [
                'pks_id' => $pksId,
                'pasal' => $section['pasal'],
                'judul' => $section['judul'],
                'raw_text' => $section['raw_text'],
                'created_at' => $this->currentDateTime,
                'created_by' => $createdBy,
                'created_by_user_id' => Auth::id(),
                'updated_at' => $this->currentDateTime,
            ];
        }

        \DB::table('sl_pks_perjanjian')->insert($insertData);
    }
}