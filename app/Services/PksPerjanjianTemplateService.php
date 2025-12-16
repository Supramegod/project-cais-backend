<?php

namespace App\Services;

use App\Models\Leads;
use App\Models\Company;
use App\Models\Kebutuhan;
use App\Models\RuleThr;
use App\Models\SalaryRule;
use Illuminate\Support\Carbon;

class PksPerjanjianTemplateService
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
    public function generateAllSections()
    {
        return [
            [
                'pasal' => 'Pembukaan',
                'judul' => 'Pembukaan',
                'raw_text' => $this->generatePembukaan()
            ],
            [
                'pasal' => 'Pasal 1',
                'judul' => 'RUANG LINGKUP PEKERJAAN',
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
                'judul' => 'BIAYA DAN PEMBAYARAN',
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

        return '<p class="NoSpacing1" align="center" style="margin-bottom:6.0pt;text-align:center"><b><span lang="EN-US" style="font-size:14.0pt;
font-family:&quot;Arial&quot;,sans-serif">PERJANJIAN KERJASAMA ALIH DAYA<o:p></o:p></span></b></p>

<p class="NoSpacing1" align="center" style="text-align:center"><b><span lang="EN-US" style="font-size:14.0pt;font-family:&quot;Arial&quot;,sans-serif">ANTARA<o:p></o:p></span></b></p>

<p class="NoSpacing1" align="center" style="text-align:center"><b><span lang="EN-US" style="font-size:14.0pt;font-family:&quot;Arial&quot;,sans-serif">' . $this->leads->nama_perusahaan . '</span></b><b><span lang="IN" style="font-size:14.0pt;font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:
IN"><o:p></o:p></span></b></p>

<p class="NoSpacing1" align="center" style="text-align:center"><b><span lang="EN-US" style="font-size:14.0pt;font-family:&quot;Arial&quot;,sans-serif">DENGAN<o:p></o:p></span></b></p>

<p class="NoSpacing1" align="center" style="text-align:center"><b><span lang="EN-US" style="font-size:14.0pt;font-family:&quot;Arial&quot;,sans-serif">' . $this->company->name . '<o:p></o:p></span></b></p>

<p class="NoSpacing1" align="center" style="text-align:center"><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></b></p>

<p class="NoSpacing1" align="center" style="text-align:center"><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">No:
' . $this->pksNomor . '</span></b><b><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:
IN"><o:p></o:p></span></b></p>

<p class="NoSpacing1" align="center" style="text-align:center"><b><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-ansi-language:IN">&nbsp;</span></b></p>

<p class="MsoNoSpacing" style="text-align:justify;text-justify:inter-ideograph"><b><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:
IN">Pada Hari ini, ' . $tanggalSekarang . ', telah disepakati Perjanjian Kerjasama Alih Daya:<o:p></o:p></span></b></p>

<p class="NoSpacing1"><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-ansi-language:IN">&nbsp;</span></p>

<p class="NoSpacing1" align="center" style="text-align:center"><b><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-ansi-language:IN">Antara:<o:p></o:p></span></b></p>

<p class="NoSpacing1"><b><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-ansi-language:IN">&nbsp;</span></b></p>

<p class="MsoNoSpacing" style="margin-left:211.5pt;text-align:justify;text-justify:
inter-ideograph;text-indent:-211.5pt;tab-stops:202.5pt"><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">' . $this->leads->nama_perusahaan . '</span></b><b><span lang="IN" style="font-size:12.0pt;
font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:IN">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span></b><span lang="IN" style="font-size:12.0pt;
font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:IN">:<b>&nbsp; </b>Dalam hal ini diwakili oleh </span><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">' . strtoupper($this->leads->pic) . ' </span></b><span lang="IN" style="font-size:12.0pt;font-family:
&quot;Arial&quot;,sans-serif;mso-ansi-language:IN">sebagai</span><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif"> </span><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">Direktur </span></b><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:
IN">yang berkedudukan di <span style="background-image: initial; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial;">Jala</span></span><span lang="EN-US" style="font-size: 12pt; font-family: Arial, sans-serif; background-image: initial; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial;">n </span><span lang="EN-US" style="font-size: 12pt; font-family: Arial, sans-serif; background-image: initial; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial;">Pos
No. 2, Ps. Baru, Kecamatan Sawah Besar, Kota Jakarta Pusat, Daerah Khusus Ibukota
Jakarta 10710 </span><span lang="IN" style="font-size: 12pt; font-family: Arial, sans-serif; background-image: initial; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial;">dan b</span><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:IN">ertindak
untuk dan atas nama <b>' . $this->leads->nama_perusahaan . '</b>, untuk selanjutnya dalam
perjanjian ini disebut sebagai <b>PIHAK PERTAMA</b>.<span style="background-image: initial; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial;"><o:p></o:p></span></span></p>

<p class="MsoNoSpacing" style="margin-left:247.5pt;text-indent:-247.5pt"><span lang="IN" style="font-size: 12pt; font-family: Arial, sans-serif; background-image: initial; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial;">&nbsp;</span></p>

<p class="MsoNoSpacing" align="center" style="text-align:center"><b><span lang="IN" style="font-size:12.0pt;
font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:IN">dan<o:p></o:p></span></b></p>

<p class="MsoNoSpacing" style="text-align:justify;text-justify:inter-ideograph"><b><span lang="EN-US" style="font-size:12.0pt;
font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></b></p>

<p class="MsoNoSpacing" style="margin-left:211.5pt;text-align:justify;text-justify:
inter-ideograph;text-indent:-211.5pt;tab-stops:202.5pt"><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">' . $this->company->name . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span></b><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:
IN;mso-bidi-font-weight:bold">:</span><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">&nbsp; </span></b><span lang="EN-US" style="font-size:
12.0pt;font-family:&quot;Arial&quot;,sans-serif;mso-bidi-font-weight:bold">Dalam hal ini
diwakili oleh </span><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">' . strtoupper($this->company->nama_direktur) . ' </span></b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;mso-bidi-font-weight:
bold">sebagai </span><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">Direktur </span></b><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:
IN">yang</span><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">
</span><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-ansi-language:IN">berkedudukan</span><span lang="IN" style="font-size:12.0pt;
font-family:&quot;Arial&quot;,sans-serif"> </span><span lang="IN" style="font-size:12.0pt;
font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:IN">di Jalan</span><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif"> </span><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">Jatiluhur
Raya No. 206E, Kel. Ngesrep, Kec. Banyumanik, Kota Semarang. dan bertindak
untuk dan atas nama</span><span lang="EN-US" style="font-size:12.0pt;font-family:
&quot;Arial&quot;,sans-serif;mso-ansi-language:IN"> </span><b><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-ansi-language:IN">' . $this->company->name . '</span></b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-bidi-font-weight:bold">,</span><span lang="EN-US" style="font-size:12.0pt;
font-family:&quot;Arial&quot;,sans-serif"> </span><span lang="IN" style="font-size:12.0pt;
font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:IN">untuk</span><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif"> </span><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:IN">selanjutnya</span><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif"> </span><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:
IN">di dalam perjanjian ini disebut sebagai <b>PIHAK</b></span><b><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif"> </span></b><b><span lang="IN" style="font-size:12.0pt;
font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:IN">KEDUA</span></b><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:
IN;mso-bidi-font-weight:bold">.</span><span lang="IN" style="font-size:12.0pt;
font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:IN"><o:p></o:p></span></p>

<p class="MsoNormal" style="text-align:justify;text-justify:inter-ideograph;
tab-stops:117.0pt 144.0pt"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></b></p>

<p class="MsoNormal" style="text-align:justify;text-justify:inter-ideograph"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">PIHAK PERTAMA </span></b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-bidi-font-weight:bold">dan
<b>PIHAK KEDUA </b>selanjutnya secara bersama-sama akan disebut sebagai <b>PARA
PIHAK</b></span><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">.<o:p></o:p></span></p>

<p class="MsoNormal" style="margin-bottom:6.0pt;text-align:justify;text-justify:
inter-ideograph;tab-stops:117.0pt"><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-bidi-font-weight:bold">Sebelumnya<b> PIHAK PERTAMA </b>dan<b> PIHAK KEDUA </b>menerangkan
hal-hal sebagai berikut:<o:p></o:p></span></p>

<p class="NoSpacing1" style="margin-top:0cm;margin-right:0cm;margin-bottom:6.0pt;
margin-left:36.0pt;text-align:justify;text-justify:inter-ideograph;text-indent:
-18.0pt;mso-list:l0 level1 lfo1"><!--[if !supportLists]--><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;mso-fareast-font-family:
Arial">1)<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp;
</span></span></b><!--[endif]--><span lang="EN-US" style="font-size:12.0pt;
font-family:&quot;Arial&quot;,sans-serif">Bahwa <b>PIHAK
PERTAMA</b> adalah perseroan terbatas yang bergerak dalam bidang pemberdayaan
bisnis UKM dan UMKM serta ruang hiburan, seni, budaya dan pertemuan komunitas
kreatif;<b><o:p></o:p></span></b></p>

<p class="NoSpacing1" style="margin-top:0cm;margin-right:0cm;margin-bottom:6.0pt;
margin-left:36.0pt;text-align:justify;text-justify:inter-ideograph;text-indent:
-18.0pt;mso-list:l0 level1 lfo1"><!--[if !supportLists]--><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">2)<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">Bahwa <b>PIHAK KEDUA</b> adalah badan usaha yang
secara hukum diizinkan menjalankan usaha Perusahaan Alih Daya dan sanggup
memenuhi kebutuhan <b>PIHAK PERTAMA</b>;<o:p></o:p></span></p>

<p class="NoSpacing1" style="margin-top:0cm;margin-right:0cm;margin-bottom:6.0pt;
margin-left:36.0pt;text-align:justify;text-justify:inter-ideograph;text-indent:
-18.0pt;mso-list:l0 level1 lfo1"><!--[if !supportLists]--><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">3)<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">Bahwa <b>PIHAK
PERTAMA </b>membutuhkan Jasa ' . $this->kebutuhan->nama . ' dan oleh karena itu <b>PIHAK
PERTAMA </b>menunjuk <b>PIHAK KEDUA</b>;<o:p></o:p></span></p>

<p class="NoSpacing1" style="margin-top:0cm;margin-right:0cm;margin-bottom:6.0pt;
margin-left:36.0pt;text-align:justify;text-justify:inter-ideograph;text-indent:
-18.0pt;mso-list:l0 level1 lfo1"><!--[if !supportLists]--><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">4)<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">Bahwa <b>PIHAK
KEDUA </b>dengan ini bersedia untuk melaksanakan penyediaan Jasa ' . $this->kebutuhan->nama . ' sesuai yang disepakati dengan <b>PIHAK PERTAMA</b>;<o:p></o:p></span></p>

<p class="MsoListParagraph" style="margin-bottom:6.0pt;text-align:justify;
text-justify:inter-ideograph;text-indent:-18.0pt;mso-pagination:widow-orphan;
mso-list:l0 level1 lfo1;mso-layout-grid-align:auto;text-autospace:ideograph-numeric ideograph-other"><!--[if !supportLists]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">5)<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Bahwa apabila terjadi
pergantian perusahaan penyedia jasa alih daya dan jenis pekerjaan masih tetap
ada, maka semua Tenaga Kerja yang masih ada akan beralih kepada perusahaan
penyedia jasa alih daya selanjutnya dengan berdasarkan hasil evaluasi kinerja Tenaga
Kerja yang telah ditetapkan dan disepakati;<o:p></o:p></span></p>

<p class="MsoListParagraph" style="text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-pagination:widow-orphan;mso-list:l0 level1 lfo1;
mso-layout-grid-align:auto;text-autospace:ideograph-numeric ideograph-other"><!--[if !supportLists]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">6)<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><span lang="EN-US" style="font-family: Arial, sans-serif;">Bahwa apabila
terjadi perubahan terhadap Upah Minimum Kota/Kabupaten (<b>UMK</b>) yang ditetapkan oleh Gubernur melalui <b>PERATURAN GUBERNUR</b> maka <b>PARA
PIHAK </b>sepakat untuk musyawarah terlebih dahulu.</span><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif"><o:p></o:p></span></p>

<p class="NoSpacing1" style="text-align:justify;text-justify:inter-ideograph"><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></p>

<p class="NoSpacing1" style="text-align:justify;text-justify:inter-ideograph;
tab-stops:0cm"><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">Berdasarkan
hal - hal tersebut di atas <b>PARA PIHAK</b> sepakat untuk mengadakan
Perjanjian dengan ketentuan sebagai berikut:<o:p></o:p></span></p>';
    }

    /**
     * Generate Pasal 1 section
     */
    private function generatePasal1()
    {
        return '<p class="ListParagraph1CxSpFirst" align="center" style="margin-left:0cm;
mso-add-space:auto;text-align:center;tab-stops:0cm"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Pasal 1<o:p></o:p></span></b></p><p class="ListParagraph1CxSpMiddle" align="center" style="margin-left:0cm;
mso-add-space:auto;text-align:center;tab-stops:0cm"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">RUANG LINGKUP
PEKERJAAN<o:p></o:p></span></b></p><p class="ListParagraph1CxSpMiddle" style="margin-left:0cm;mso-add-space:auto;
tab-stops:0cm"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></b></p><p class="ListParagraph1" style="margin-left:0cm;text-align:justify;text-justify:
inter-ideograph;tab-stops:0cm">





</p><p class="ListParagraph1" style="margin-left:0cm;text-align:justify;text-justify:
inter-ideograph;tab-stops:0cm"><b><span lang="EN-US" style="mso-bidi-font-size:11.0pt;font-family:&quot;Arial&quot;,sans-serif">PIHAK
PERTAMA </span></b><span lang="EN-US" style="mso-bidi-font-size:11.0pt;
font-family:&quot;Arial&quot;,sans-serif">menunjuk<b>
PIHAK KEDUA </b>sebagai jasa penyedia dan pengelola Tenaga Kerja alih daya<b> </b>(<i>outsourcing</i>)<b> </b>Jasa ' . $this->kebutuhan->nama . ' untuk <b>PIHAK
PERTAMA </b>yang akan ditempatkan di jalan
Kebon Rojo, Krembangan Sel., Kec. Krembangan, Surabaya, Jawa Timur 60175 dan
atas pelaksanaan pekerjaan tersebut <b>PIHAK
PERTAMA </b>akan membayarkan <i>Management Fee</i> kepada <b>PIHAK KEDUA</b>.<o:p></o:p></span></p>';
    }

    /**
     * Generate Pasal 2 section (partial example, you need to add full content)
     */
    private function generatePasal2()
    {
        // Add the full Pasal 2 content here
        return $pasal2 = '<p class="ListParagraph1CxSpFirst" align="center" style="margin-left:0cm;
mso-add-space:auto;text-align:center;tab-stops:0cm"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Pasal 2<o:p></o:p></span></b></p><p class="ListParagraph1CxSpMiddle" align="center" style="margin-left:0cm;
mso-add-space:auto;text-align:center;tab-stops:0cm"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">HAK &amp;
KEWAJIBAN PARA PIHAK<o:p></o:p></span></b></p><p class="ListParagraph1CxSpMiddle" style="margin-left:0cm;mso-add-space:auto;
tab-stops:0cm"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></b></p><p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-indent:-18.0pt;mso-list:l0 level1 lfo2"><!--[if !supportLists]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">1.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">KEWAJIBAN
PIHAK PERTAMA:<o:p></o:p></span></b></p><p class="ListParagraph1" style="margin-bottom:6.0pt;text-align:justify;
text-justify:inter-ideograph;text-indent:-18.0pt;mso-list:l2 level1 lfo1;
tab-stops:0cm"><!--[if !supportLists]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">a.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-bidi-font-weight:bold">Memberikan
segala informasi terkait dengan pelaksanaan pekerjaan yang tidak terbatas pada
syarat - syarat dilaksanakannya pekerjaan termasuk mematuhi segala perintah, instruksi
termasuk peraturan dan atau ketentuan yang diberlakukan oleh </span><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">PIHAK
PERTAMA</span></b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-bidi-font-weight:bold">, sepanjang tidak bertentangan dengan isi perjanjian
ini, ketertiban kesusilaan,dan atau peraturan dibidang ketenagakerjaan pada
pekerja dari </span><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">PIHAK KEDUA</span></b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-bidi-font-weight:bold">;</span><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif"><o:p></o:p></span></b></p><p class="ListParagraph1" style="margin-bottom:6.0pt;text-align:justify;
text-justify:inter-ideograph;text-indent:-18.0pt;mso-list:l2 level1 lfo1;
tab-stops:0cm"><!--[if !supportLists]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">b.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-bidi-font-weight:bold">Atas
Pelaksanaan pekerjaan yang diberikan oleh </span><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">PIHAK PERTAMA</span></b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-bidi-font-weight:bold"> kepada
</span><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">PIHAK KEDUA </span></b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-bidi-font-weight:bold">maka akan
diterbitkan <i>invoice</i> pembayaran oleh </span><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">PIHAK KEDUA</span></b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-bidi-font-weight:bold">
dan menjadi kewajiban </span><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">PIHAK PERTAMA</span></b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-bidi-font-weight:bold">
untuk melakukan pembayaran sesuai dengan waktu yang sudah disepakati;</span><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif"><o:p></o:p></span></b></p><p class="ListParagraph1" style="margin-bottom:6.0pt;text-align:justify;
text-justify:inter-ideograph;text-indent:-18.0pt;mso-list:l2 level1 lfo1;
tab-stops:0cm"><!--[if !supportLists]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">c.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span><!--[endif]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">PIHAK
PERTAMA</span></b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">
berhak meminta/mengajukan penggantian Tenaga Kerja <b>PIHAK KEDUA</b> yang dinilai atau dianggap tidak produktif dalam
performa bertugas berdasarkan hasil penilaian dan evaluasi yang ditetapkan pada
SOP; <b><o:p></o:p></b></span></p><p class="ListParagraph1CxSpMiddle" style="text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l2 level1 lfo1;tab-stops:0cm"><!--[if !supportLists]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-fareast-font-family:Arial">d.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp;
</span></span><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Mematuhi
segala peraturan perundang-undangan terkait dengan perjanjian kerjasama alih
daya dan peraturan lain terkait ketenagakerjaan yang berlaku.<b><o:p></o:p></b></span></p><p class="ListParagraph1CxSpMiddle" style="margin-left:0cm;mso-add-space:auto"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></b></p><p class="ListParagraph1CxSpMiddle" style="margin-left:0cm;mso-add-space:auto"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></b></p><p class="ListParagraph1CxSpMiddle" style="margin-left:0cm;mso-add-space:auto"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></b></p><p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-indent:-18.0pt;mso-list:l0 level1 lfo2"><!--[if !supportLists]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">2.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">KEWAJIBAN
PIHAK KEDUA:<o:p></o:p></span></b></p><p class="ListParagraph1" style="margin-bottom:6.0pt;text-align:justify;
text-justify:inter-ideograph;text-indent:-18.0pt;mso-list:l1 level1 lfo3"><!--[if !supportLists]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-fareast-font-family:Arial;
mso-bidi-font-weight:bold">a.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Menyediakan Tenaga Kerja
berdasarkan permintaan secara tertulis dari <b>PIHAK PERTAMA </b>kepada <b>PIHAK
KEDUA</b> yang berisikan surat penunjukan kesepakatan kerjasama, jenis
pekerjaan, dan jumlah Tenaga Kerja yang dibutuhkan;<b><o:p></o:p></b></span></p><p class="ListParagraph1" style="margin-bottom:6.0pt;text-align:justify;
text-justify:inter-ideograph;text-indent:-18.0pt;mso-list:l1 level1 lfo3"><!--[if !supportLists]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-fareast-font-family:Arial;
mso-bidi-font-weight:bold">b.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Menjaga kerahasiaan <b>PIHAK PERTAMA</b> tidak terbatas pada semua
keterangan, data - data, catatan - catatan yang diperoleh baik langsung maupun
tidak langsung, kepada pihak lain tanpa izin tertulis dari <b>PIHAK PERTAMA</b> baik selama berlakunya Perjanjian ini maupun sesudah
Perjanjian ini berakhir. Untuk keperluan ini <b>PIHAK KEDUA</b> wajib memasikan bahwa Tenaga Kerja telah menandatangani
Surat Pernyataan untuk menjaga kerahasiaan <b>PIHAK
PERTAMA</b>;<b><o:p></o:p></b></span></p><p class="ListParagraph1" style="margin-bottom:6.0pt;text-align:justify;
text-justify:inter-ideograph;text-indent:-18.0pt;mso-list:l1 level1 lfo3"><!--[if !supportLists]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-fareast-font-family:Arial;
mso-bidi-font-weight:bold">c.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-bidi-font-weight:bold">Membebaskan
</span><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">PIHAK PERTAMA </span></b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-bidi-font-weight:bold">dari
segala tuntutan ketenagakerjaan dari pekerja </span><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">PIHAK KEDUA </span></b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-bidi-font-weight:bold">akibat
timbulnya dari perjanjian ini;</span><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif"><o:p></o:p></span></b></p><p class="ListParagraph1" style="margin-bottom:6.0pt;text-align:justify;
text-justify:inter-ideograph;text-indent:-18.0pt;mso-list:l1 level1 lfo3"><!--[if !supportLists]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-fareast-font-family:Arial;
mso-bidi-font-weight:bold">d.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span><!--[endif]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">PIHAK
KEDUA </span></b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">akan menugaskan
Tenaga Kerja di lokasi <b>PIHAK PERTAMA</b>
dengan jadwal kerja yang telah disepakati oleh <b>PARA PIHAK</b>;<o:p></o:p></span></p><p class="ListParagraph1" style="margin-bottom:6.0pt;text-align:justify;
text-justify:inter-ideograph;text-indent:-18.0pt;mso-list:l1 level1 lfo3"><!--[if !supportLists]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-fareast-font-family:Arial;
mso-bidi-font-weight:bold">e.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-bidi-font-weight:bold">Menyelesaikan
secara tuntas segala permasalahan yang timbul baik dalam hubungan dengan Tenaga
Kerja atau pihak lain terkait dengan pelaksanaan perjanjian ini, termasuk
memberikan sanksi secara tegas atas tindakan pelanggaran atau penyelewengan
dari Tenaga Kerja </span><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">PIHAK KEDUA </span></b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-bidi-font-weight:bold">terhadap
tata tertib dan segala peraturan yang berlaku di lokasi </span><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">PIHAK
PERTAMA</span></b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-bidi-font-weight:bold">;</span><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif"><o:p></o:p></span></b></p><p class="ListParagraph1" style="margin-bottom:6.0pt;text-align:justify;
text-justify:inter-ideograph;text-indent:-18.0pt;mso-list:l1 level1 lfo3;
tab-stops:0cm"><!--[if !supportLists]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial;mso-bidi-font-weight:bold">f.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp;&nbsp;
</span></span><!--[endif]--><span lang="FI" style="font-family:&quot;Arial&quot;,sans-serif;
mso-ansi-language:FI">Apabila ada Tenaga Kerja yang melakukan pelanggaran
indisipliner maupun pelanggaran lain terhadap SOP, maka <b>PIHAK KEDUA</b>
wajib melakukan pembinaan dan konseling terhadap Tenaga Kerja, jika tidak
terjadi perubahan dan perbaikan pada Tenaga Kerja, maka <b>PIHAK KEDUA</b>
wajib melakukan pengajuan pergantian/penarikan Tenaga Kerja, dengan persetujuan
<b>PIHAK PERTAMA</b>;</span><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif"><o:p></o:p></span></p><p class="ListParagraph1" style="margin-bottom:6.0pt;text-align:justify;
text-justify:inter-ideograph;text-indent:-18.0pt;mso-list:l1 level1 lfo3;
tab-stops:0cm"><!--[if !supportLists]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial;mso-bidi-font-weight:bold">g.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp;
</span></span><!--[endif]--><b><span lang="ES" style="font-family:&quot;Arial&quot;,sans-serif;
mso-ansi-language:ES">PIHAK KEDUA</span></b><span lang="ES" style="font-family:
&quot;Arial&quot;,sans-serif;mso-ansi-language:ES"> </span><span lang="FI" style="font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:FI">wajib segera
mengirimkan </span><span lang="IN" style="font-family:&quot;Arial&quot;,sans-serif;
mso-ansi-language:IN">Tenaga Kerja</span><span lang="IN" style="font-family:&quot;Arial&quot;,sans-serif">
</span><span lang="FI" style="font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:
FI">pengganti sementara jika terjadi <i>turn over</i> dalam waktu 1x24 jam
untuk memastikan tidak terjadi kekosongan, selanjutnya untuk proses rekrutmen
kandidat dan sampai adanya keputusan penerimaan yakni selambat-lambatnya 4
(empat) hari kerja sejak terjadinya <i>turn over</i> dan disepakati oleh <b>PARA
PIHAK</b>;</span><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif"><o:p></o:p></span></p><p class="ListParagraph1" style="margin-bottom:6.0pt;text-align:justify;
text-justify:inter-ideograph;text-indent:-18.0pt;mso-list:l1 level1 lfo3;
tab-stops:0cm"><!--[if !supportLists]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial;mso-bidi-font-weight:bold">h.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp;
</span></span><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Apabila
Tenaga Kerja <b>PIHAK KEDUA</b> melakukan pelanggaran yang mengakibatkan
kerugian pada <b>PIHAK PERTAMA</b> baik secara materil maupun immateril, maka
akan dilakukan investigasi dan musyawarah mufakat untuk menentukan nilai
kerugian yang harus ditanggung oleh <b>PIHAK KEDUA </b>sesuai dengan hasil
investigasi dan nilai kesepakatan <b>PARA PIHAK</b>;<o:p></o:p></span></p><p class="ListParagraph1" style="text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l1 level1 lfo3"><!--[if !supportLists]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-fareast-font-family:Arial;
mso-bidi-font-weight:bold">i.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span></span><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Menghitung dan membayar
gaji/upah pokok beserta <i>variable</i> dan pembayaran lainnya (apabila ada)
atas setiap Tenaga Kerja yang dikaryakan di <b>PIHAK PERTAMA</b>.<o:p></o:p></span></p><p class="ListParagraph1" style="margin-left:0cm;text-align:justify;text-justify:
inter-ideograph"><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></p><p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l0 level1 lfo2"><!--[if !supportLists]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">3.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">HAK
PIHAK PERTAMA:<o:p></o:p></span></b></p><p class="ListParagraph1CxSpMiddle" style="margin-left:18.0pt;mso-add-space:auto;
text-align:justify;text-justify:inter-ideograph"><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Menerima Tenaga Kerja dari <b>PIHAK
KEDUA </b>sesuai dengan syarat-syarat kualifikasi, kompetensi, kualitas dan
pencapaian hasil yang telah ditentukan oleh<b> PIHAK PERTAMA</b>.<o:p></o:p></span></p><p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l0 level1 lfo2"><!--[if !supportLists]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">4.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">HAK
PIHAK KEDUA:<o:p></o:p></span></b></p><p class="ListParagraph1CxSpFirst" align="center" style="margin-left:0cm;
mso-add-space:auto;text-align:center;tab-stops:0cm">

















































</p><p class="ListParagraph1" style="margin-left:18.0pt;text-align:justify;
text-justify:inter-ideograph"><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Menerima
pembayaran atau <i>management fee</i> atas pekerjaan yang telah dilaksanakan <b>PIHAK KEDUA</b> dari <b>PIHAK PERTAMA </b>dengan nominal/nilai yang telah disepakati.<b><o:p></o:p></b></span></p>';
    }

    /**
     * Generate Pasal 3 section
     */
    private function generatePasal3()
    {
        return '<p class="MsoNoSpacing" align="center" style="text-align:center;tab-stops:22.5pt"><b><span lang="EN-US" style="font-size:12.0pt;
font-family:&quot;Arial&quot;,sans-serif">Pasal</span></b><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-ansi-language:IN"> </span></b><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">3<o:p></o:p></span></b></p><p class="MsoNoSpacing" align="center" style="text-align:center;tab-stops:22.5pt"><b><span lang="IN" style="font-size:12.0pt;
font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:IN">TUNJANGAN HARI RAYA<o:p></o:p></span></b></p><p class="MsoNoSpacing"><b><span lang="IN" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-ansi-language:IN">&nbsp;</span></b></p><p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l0 level1 lfo1"><!--[if !supportLists]--><b><span lang="EN-US" style="mso-bidi-font-size:11.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">1.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><b><span lang="EN-US" style="mso-bidi-font-size:11.0pt;font-family:&quot;Arial&quot;,sans-serif">PIHAK
PERTAMA </span></b><span lang="EN-US" style="mso-bidi-font-size:11.0pt;
font-family:&quot;Arial&quot;,sans-serif;mso-bidi-font-weight:bold">akan membayarkan Tunjangan
Hari Raya Keagamaan selanjutnya disebut </span><b><span lang="EN-US" style="mso-bidi-font-size:11.0pt;font-family:&quot;Arial&quot;,sans-serif">THR
</span></b><span lang="EN-US" style="mso-bidi-font-size:11.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-bidi-font-weight:bold">kepada Tenaga Kerja </span><b><span lang="EN-US" style="mso-bidi-font-size:11.0pt;font-family:&quot;Arial&quot;,sans-serif">PIHAK KEDUA</span></b><span lang="EN-US" style="mso-bidi-font-size:11.0pt;font-family:&quot;Arial&quot;,sans-serif;mso-bidi-font-weight:
bold"> yang ditempatkan di </span><b><span lang="EN-US" style="mso-bidi-font-size:11.0pt;font-family:&quot;Arial&quot;,sans-serif">PIHAK
PERTAMA</span></b><span lang="EN-US" style="mso-bidi-font-size:11.0pt;font-family:
&quot;Arial&quot;,sans-serif;mso-bidi-font-weight:bold"> sesuai dengan ketentuan
peraturan yang berlaku;<o:p></o:p></span></p><p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l0 level1 lfo1"><!--[if !supportLists]--><b><span lang="EN-US" style="mso-bidi-font-size:11.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">2.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><span lang="EN-US" style="mso-bidi-font-size:11.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-bidi-font-weight:bold">Bahwa <b>PIHAK KEDUA </b>akan menagihkan komponen
THR pada <b>PIHAK PERTAMA</b> dengan perhitungan berdasarkan upah pokok sesuai
dengan peraturan menteri Tenaga Kerja (<b>Permenaker</b>) no.6 tahun
2016/tentang tunjangan hari raya keagamaan bagi pekerja/buruh di perusahaan;<o:p></o:p></span></p><p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l0 level1 lfo1">









</p><p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l0 level1 lfo1"><!--[if !supportLists]--><b><span lang="EN-US" style="mso-bidi-font-size:11.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">3.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><span lang="EN-US" style="mso-bidi-font-size:11.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-bidi-font-weight:bold">THR</span><b><span lang="EN-US" style="mso-bidi-font-size:11.0pt;font-family:&quot;Arial&quot;,sans-serif"> </span></b><span lang="EN-US" style="mso-bidi-font-size:11.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-bidi-font-weight:bold">Keagamaan pada saat Hari Raya Idul Fitri sebesar 1
(satu) kali gaji kepada Tenaga Kerja yang memiliki masa kerja selama 12 (dua
belas) bulan berturut-turut. Apabila masa kerja Tenaga Kerja belum mencapai 1
(satu) tahun namun telah melebihi 1 (satu) bulan maka akan diberikan THR secara
Prorata, dengan skema penagihan <i>invoice</i> sesuai tabel dibawah.</span></p><p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l0 level1 lfo1"><span lang="EN-US" style="mso-bidi-font-size:11.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-bidi-font-weight:bold"><br></span></p><p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l0 level1 lfo1"><span lang="EN-US" style="mso-bidi-font-size:11.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-bidi-font-weight:bold">&nbsp; &nbsp;&nbsp;</span><table></table><span lang="EN-US" style="mso-bidi-font-size:11.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-bidi-font-weight:bold"><o:p></o:p><div style="display: flex; justify-content: center; align-items: center; width: 100%;">
    <table style="border-collapse: collapse; width: 70%;">
        <tr>
            <th style="border: 2px solid black; padding: 10px; text-align: center; background-color: #d9d9d9; font-weight: bold;">No</th>
            <th style="border: 2px solid black; padding: 10px; text-align: center; background-color: #d9d9d9; font-weight: bold;">Schedule Plan</th>
            <th style="border: 2px solid black; padding: 10px; text-align: center; background-color: #d9d9d9; font-weight: bold;">Time</th>
        </tr>
        <tr>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">1</td>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">Penagihan <i>Invoice</i> THR</td>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">Ditagihkan H-' . $this->ruleThr->hari_penagihan_invoice . '</td>
        </tr>
        <tr>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">2</td>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">Pembayaran <i>Invoice</i> THR</td>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">Maksimal h-' . $this->ruleThr->hari_pembayaran_invoice . ' hari raya</td>
        </tr>
        <tr>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">3</td>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">Rilis THR</td>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">Maksimal h-' . $this->ruleThr->hari_rilis_thr . ' hari raya</td>
        </tr>
    </table>
</div></span></p>';
    }

    /**
     * Generate Pasal 4 section (partial example)
     */
    private function generatePasal4()
    {
        // Add the full Pasal 4 content here
        return '<p class="MsoNormal" align="center" style="text-align:center"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Pasal 4<o:p></o:p></span></b></p>

<p class="MsoNormal" align="center" style="text-align:center;tab-stops:0cm"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">BIAYA
DAN PEMBAYARAN<o:p></o:p></span></b></p>

<p class="MsoNormal" align="center" style="text-align:center;tab-stops:0cm"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></b></p>

<p class="NoSpacing1" style="margin-top:0cm;margin-right:0cm;margin-bottom:6.0pt;
margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;text-indent:
-18.0pt;mso-list:l0 level1 lfo2"><!--[if !supportLists]--><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">1.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><b><span lang="EN-US" style="font-size:12.0pt;
font-family:&quot;Arial&quot;,sans-serif">BIAYA KONTRAK<o:p></o:p></span></b></p>

<p class="MsoNormal" style="margin-top:0cm;margin-right:0cm;margin-bottom:6.0pt;
margin-left:31.5pt;text-align:justify;text-justify:inter-ideograph;text-indent:
-13.5pt;mso-pagination:widow-orphan;mso-list:l1 level1 lfo1;mso-layout-grid-align:
auto;text-autospace:ideograph-numeric ideograph-other"><!--[if !supportLists]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-fareast-font-family:Arial">a.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp; </span></span><!--[endif]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">PIHAK
PERTAMA </span></b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">sepakat
untuk membayar <b>Biaya Kontrak</b> setiap
bulan (menyesuaikan <i>actual</i> <i>invoice</i>) kepada <b>PIHAK KEDUA </b>untuk pekerjaan alih daya ' . $this->kebutuhan->nama . ' yang
dikaryakan di tempat <b>PIHAK PERTAMA</b>;<o:p></o:p></span></p>

<p class="MsoNormal" style="margin-left:31.7pt;text-align:justify;text-justify:
inter-ideograph;text-indent:-13.7pt;mso-pagination:widow-orphan;mso-list:l1 level1 lfo1;
mso-layout-grid-align:auto;text-autospace:ideograph-numeric ideograph-other"><!--[if !supportLists]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-fareast-font-family:Arial">b.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp; </span></span><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Biaya kontrak sudah termasuk
upah pokok beserta <i>variable</i> <i>breakdown</i> lainnya, seperti tunjangan, premi BPJS Ketenagakerjaan, BPJS
Kesehatan, biaya monitoring dan kontrol, biaya provisi seragam, biaya provisi
chemical dan tools, ppn 12%, pph -2% dan <i>managemen</i><i>t fee</i> yang telah disepakati.<o:p></o:p></span></p>

<p class="MsoNormal" style="margin-left:31.7pt;text-align:justify;text-justify:
inter-ideograph;mso-pagination:widow-orphan;mso-layout-grid-align:auto;
text-autospace:ideograph-numeric ideograph-other"><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></p>

<p class="NoSpacing1" style="margin-top:0cm;margin-right:0cm;margin-bottom:6.0pt;
margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;text-indent:
-18.0pt;mso-list:l0 level1 lfo2"><!--[if !supportLists]--><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">2.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><b><i><span lang="EN-US" style="font-size:12.0pt;
font-family:&quot;Arial&quot;,sans-serif">INVOICE</span></i></b><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">/PENAGIHAN</span></b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif"><o:p></o:p></span></p>

<p class="NoSpacing1" style="margin-top:0cm;margin-right:0cm;margin-bottom:6.0pt;
margin-left:31.5pt;text-align:justify;text-justify:inter-ideograph;text-indent:
-13.5pt;mso-list:l2 level1 lfo3"><!--[if !supportLists]--><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;mso-fareast-font-family:
Arial">a.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;
</span></span><!--[endif]--><span lang="EN-US" style="font-size:12.0pt;
font-family:&quot;Arial&quot;,sans-serif">Jadwal Penagihan &amp; Pembayaran</span><span lang="EN-US" style="font-size:14.0pt;mso-bidi-font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif"><o:p></o:p></span></p>

<p class="LO-normal" style="margin-top:0cm;margin-right:0cm;margin-bottom:0cm;
margin-left:31.7pt;margin-bottom:.0001pt;text-align:justify;text-justify:inter-ideograph;
text-indent:0cm"><b><span lang="EN-US" style="mso-bidi-font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">PIHAK KEDUA</span></b><span lang="EN-US" style="mso-bidi-font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">
menerbitkan tagihan/<i>invoice</i> kepada <b>PIHAK PERTAMA</b> dengan perhitungan <i>cut-off</i> 21 bulan sebelumnya 20 bulan
selanjutnya dan rilis penggajian Tenaga Kerja pada tanggal 1 bulan berikutnya
dengan skema tabel dibawah ini:</span></p><p class="LO-normal" style="margin-top:0cm;margin-right:0cm;margin-bottom:0cm;
margin-left:31.7pt;margin-bottom:.0001pt;text-align:justify;text-justify:inter-ideograph;
text-indent:0cm">
<div style="display: flex; justify-content: center; align-items: center; width: 100%;">
    <table style="border-collapse: collapse; width: 70%;">
        <tr>
            <th style="border: 2px solid black; padding: 10px; text-align: center; background-color: #d9d9d9; font-weight: bold;">No</th>
            <th style="border: 2px solid black; padding: 10px; text-align: center; background-color: #d9d9d9; font-weight: bold;">Schedule Plan</th>
            <th style="border: 2px solid black; padding: 10px; text-align: center; background-color: #d9d9d9; font-weight: bold;">Periode</th>
        </tr>
        <tr>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">1</td>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">Cut-Off</td>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">' . $this->salaryRule->cutoff . '</td>
        </tr>
        <tr>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">2</td>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">Crosscheck Absen</td>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">' . $this->salaryRule->crosscheck_absen . '</td>
        </tr>
        <tr>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">3</td>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">Pengiriman <i>Invoice</i></td>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">' . $this->salaryRule->pengiriman_invoice . '</td>
        </tr>
        <tr>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">4</td>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">Perkiraan <i>Invoice</i> Diterima Pelanggan</td>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">' . $this->salaryRule->perkiraan_invoice_diterima . '</td>
        </tr>
        <tr>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">5</td>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">Pembayaran <i>Invoice</i></td>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">' . $this->salaryRule->pembayaran_invoice . '</td>
        </tr>
        <tr>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">6</td>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">Rilis <i>Payroll</i> / Gaji</td>
            <td style="border: 2px solid black; padding: 10px; text-align: center;">' . $this->salaryRule->rilis_payroll . '</td>
        </tr>
        <!-- Tambahkan baris lain di sini -->
    </table>
</div>
<span lang="EN-US" style="mso-bidi-font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif"><br></span></p>

<p class="LO-normal" style="margin-top:0cm;margin-right:-.05pt;margin-bottom:
6.0pt;margin-left:31.5pt;text-align:justify;text-justify:inter-ideograph;
text-indent:-13.5pt;mso-list:l2 level1 lfo3"><!--[if !supportLists]--><span lang="EN-US" style="mso-bidi-font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">b.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp; </span></span><!--[endif]--><b><span lang="EN-US" style="mso-bidi-font-size:
12.0pt;font-family:&quot;Arial&quot;,sans-serif">PIHAK PERTAMA</span></b><span lang="EN-US" style="mso-bidi-font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">
melakukan pembayaran terhitung maksimal 14 hari kalender setelah menerima <i>invoice </i>asli bermaterai dari<b> PIHAK KE</b></span><b><span lang="IN" style="mso-bidi-font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif;
mso-ansi-language:IN">DU</span></b><b><span lang="EN-US" style="mso-bidi-font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">A </span></b><span lang="EN-US" style="mso-bidi-font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">melalui
transfer bank ke:<o:p></o:p></span></p>

<p class="MsoNormal" style="text-indent: 32.4pt; background-image: initial; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial;"><span lang="NL" style="font-family: Arial, sans-serif;">Bank&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; :
<b>MANDIRI</b></span><span lang="NL" style="font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:NL"><o:p></o:p></span></p>

<p class="MsoNormal" style="text-indent: 32.4pt; background-image: initial; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial;"><span lang="NL" style="font-family: Arial, sans-serif;">Cabang Pembantu &nbsp;&nbsp; :
<b>KCP SURABAYA RUNGKUT MEGAH RAYA</b></span><span lang="NL" style="font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:NL"><o:p></o:p></span></p>

<p class="MsoNormal" style="text-indent: 32.4pt; background-image: initial; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial;"><span lang="NL" style="font-family: Arial, sans-serif;">Nomor Rekening&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; :
<b>1420001290823</b></span><span lang="NL" style="font-family:&quot;Arial&quot;,sans-serif;
mso-ansi-language:NL"><o:p></o:p></span></p>

<p class="MsoNormal" style="margin-bottom: 6pt; text-indent: 32.4pt; background-image: initial; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial;"><span lang="IN" style="font-family: Arial, sans-serif;">Nama Rekening</span><span lang="NL" style="font-family: Arial, sans-serif;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; : <b>' . $this->company->name . '</b></span><b><span lang="NL" style="font-family:&quot;Arial&quot;,sans-serif;
mso-ansi-language:NL"><o:p></o:p></span></b></p>

<p class="LO-normal" style="margin-top:0cm;margin-right:0cm;margin-bottom:6.0pt;
margin-left:0cm;text-align:justify;text-justify:inter-ideograph;text-indent:
0cm"><span lang="EN-US" style="mso-bidi-font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">Jika
tanggal tempo pembayaran jatuh pada hari Sabtu, Minggu atau hari Libur
Nasional, maka <b>PIHAK PERTAMA</b> dapat
melakukan pembayaran pada hari efektif berikutnya;<o:p></o:p></span></p>

<p class="LO-normal" style="margin-top:0cm;margin-right:1.45pt;margin-bottom:
0cm;margin-left:0cm;margin-bottom:.0001pt;text-align:justify;text-justify:inter-ideograph;
text-indent:0cm"><span lang="EN-US" style="mso-bidi-font-size:12.0pt;font-family:
&quot;Arial&quot;,sans-serif">Dalam hal terjadi perubahan data rekening, <b>PIHAK KEDUA</b> wajib segera memberitahukan
secara tertulis kepada <b>PIHAK PERTAMA</b>
sebelum jadwal pembayaran berikutnya. Kelalaian <b>PIHAK KEDUA</b> dalam menyampaikan pemberitahuan tersebut tidak akan
menimbulkan akibat atau kompensasi apapun terhadap <b>PIHAK PERTAMA</b>.<o:p></o:p></span></p>';
    }

    /**
     * Generate Pasal 5 section
     */
    private function generatePasal5()
    {
        return '<p class="ListParagraph1CxSpFirst" align="center" style="margin-left:0cm;
mso-add-space:auto;text-align:center;tab-stops:0cm"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Pasal 5<o:p></o:p></span></b></p><p class="ListParagraph1CxSpMiddle" align="center" style="margin-left:0cm;
mso-add-space:auto;text-align:center;tab-stops:0cm"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">JAMINAN PIHAK
KEDUA<o:p></o:p></span></b></p><p class="ListParagraph1CxSpMiddle" align="center" style="margin-left:26.25pt;
mso-add-space:auto;text-align:center;text-indent:-26.25pt;mso-char-indent-count:
-2.18;tab-stops:0cm"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></b></p><p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:0cm;text-align:justify;text-justify:inter-ideograph"><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Pekerja yang ditempatkan oleh
<b>PIHAK KEDUA</b> telah melalui proses:<b><o:p></o:p></b></span></p><p class="ListParagraph1" style="margin-bottom:6.0pt;text-align:justify;
text-justify:inter-ideograph;text-indent:-18.0pt;mso-list:l0 level1 lfo1"><!--[if !supportLists]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-fareast-font-family:Arial">a.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp;
</span></span><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Wawancara
dalam proses seleksi dan penerimaan sesuai dengan kualifikasi yang dibutukan;<o:p></o:p></span></p><p class="ListParagraph1" style="text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l0 level1 lfo1"><!--[if !supportLists]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-fareast-font-family:Arial">b.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp;
</span></span><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Pemeriksaan
dokumen calon Tenaga Kerja/kandidat mencakup identitas diri/<i>CV</i> (termasuk foto), ijazah atau sertifikat
pendidikan formal maupun non formal, sertifikat pelatihan, surat
referensi/pengalaman kerja, dll.<o:p></o:p></span></p><p class="ListParagraph1" style="margin-left:0cm;text-align:justify;text-justify:
inter-ideograph"><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></p><p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:0cm;text-align:justify;text-justify:inter-ideograph">













</p><p class="ListParagraph1CxSpLast" style="margin-left:0cm;mso-add-space:auto;
text-align:justify;text-justify:inter-ideograph"><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Bahwa Tenaga Kerja yang ditempatkan pada
<b>PIHAK PERTAMA </b>tunduk kepada
peraturan <b>PIHAK PERTAMA</b> dan <b>PIHAK KEDUA</b>. Jika terjadi pelanggaran
atas peraturan internal <b>PIHAK PERTAMA</b>
maka <b>PIHAK PERTAMA</b> wajib
memberitahukan kepada <b>PIHAK KEDUA</b>
untuk penindakan secara tertulis melalui Surat Peringatan (<b>SP</b>) tahap pertama sampai dengan tahap ketiga beserta pengambilan
sanksi tindakan sebagaimana mestinya sesuai dengan SOP &amp; peraturan
perundang - undangan yang berlaku.<o:p></o:p></span></p>';
    }

    /**
     * Generate Pasal 6 section
     */
    private function generatePasal6()
    {
        return '<p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l0 level1 lfo1"><!--[if !supportLists]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">1.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Yang dimaksud dengan <i>force majeure</i> adalah keadaan yang tidak
dapat dipenuhinya pelaksanaan Perjanjian oleh <b>PARA PIHAK</b>, karena terjadi
suatu peristiwa yang bukan karena kesalahan Para Pihak, peristiwa mana tidak
dapat diketahui/ tidak dapat diduga sebelumnya dan di luar kemampuan manusia, seperti
bencana alam (gempa bumi, angin topan, kebakaran, banjir), huru-hara, perang,
pemogokan umum yang bukan kesalahan <b>PARA PIHAK</b>, <i>sabotase</i>, pemberontakan, dan <i>epidemi</i>
yang secara keseluruhan ada hubungan langsung dengan penyelesaian pelaksanaan
Perjanjian ini;<b><o:p></o:p></b></span></p>

<p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l0 level1 lfo1"><!--[if !supportLists]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">2.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Apabila terjadi <i>force majeure</i>, maka Pihak yang terkena <i>force majeure</i> harus memberitahukan
secara tertulis kepada Pihak yang tidak terkena <i>force majeure</i> selambat-lambatnya 7 (tujuh) hari kalender sejak
terjadinya <i>force majeure</i> tersebut
disertai bukti-bukti yang sah, selanjutnya Pihak yang tidak terkena <i>force majeure</i> akan menanggapi;<b><o:p></o:p></b></span></p>

<p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l0 level1 lfo1"><!--[if !supportLists]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">3.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Apabila hal tersebut tidak
dilakukan oleh Pihak yang terkena <i>force
majeure</i>, maka Pihak yang tidak terkena <i>force
majeure</i> menganggap tidak terjadi <i>force
majeure</i>;<b>&nbsp;</b></span><span style="font-family: Arial, sans-serif; font-size: 12pt; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);"><br></span></p><p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l0 level1 lfo1"><span style="font-family: Arial, sans-serif; font-size: 12pt; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">4.&nbsp; Dalam hal terjadi </span><i style="font-family: Arial, sans-serif; font-size: 12pt; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">force majeure</i><span style="font-family: Arial, sans-serif; font-size: 12pt; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">, maka pelaksanaan kewajiban masing-masing Pihak akan
ditunda berdasarkan kesepakatan </span><b style="font-family: Arial, sans-serif; font-size: 12pt; background-color: var(--bs-card-bg); text-align: var(--bs-body-text-align);">PARA PIHAK</b><span style="font-family: Arial, sans-serif; font-size: 12pt; background-color: var(--bs-card-bg); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">.</span></p>';
    }

    /**
     * Generate Pasal 7 section
     */
    private function generatePasal7()
    {
        return '<p class="ListParagraph1CxSpFirst" align="center" style="margin-left:0cm;
mso-add-space:auto;text-align:center;tab-stops:0cm"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Pasal 6<o:p></o:p></span></b></p><p class="ListParagraph1CxSpMiddle" align="center" style="margin-left:0cm;
mso-add-space:auto;text-align:center;tab-stops:0cm"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">FORCE MAJEURE<o:p></o:p></span></b></p><p class="ListParagraph1CxSpMiddle" style="margin-left:0cm;mso-add-space:auto;
text-align:justify;text-justify:inter-ideograph"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></b></p><p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l0 level1 lfo1"><!--[if !supportLists]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">1.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Yang dimaksud dengan <i>force majeure</i> adalah keadaan yang tidak
dapat dipenuhinya pelaksanaan Perjanjian oleh <b>PARA PIHAK</b>, karena terjadi
suatu peristiwa yang bukan karena kesalahan Para Pihak, peristiwa mana tidak
dapat diketahui/ tidak dapat diduga sebelumnya dan di luar kemampuan manusia, seperti
bencana alam (gempa bumi, angin topan, kebakaran, banjir), huru-hara, perang,
pemogokan umum yang bukan kesalahan <b>PARA PIHAK</b>, <i>sabotase</i>, pemberontakan, dan <i>epidemi</i>
yang secara keseluruhan ada hubungan langsung dengan penyelesaian pelaksanaan
Perjanjian ini;<b><o:p></o:p></b></span></p><p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l0 level1 lfo1"><!--[if !supportLists]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">2.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Apabila terjadi <i>force majeure</i>, maka Pihak yang terkena <i>force majeure</i> harus memberitahukan
secara tertulis kepada Pihak yang tidak terkena <i>force majeure</i> selambat-lambatnya 7 (tujuh) hari kalender sejak
terjadinya <i>force majeure</i> tersebut
disertai bukti-bukti yang sah, selanjutnya Pihak yang tidak terkena <i>force majeure</i> akan menanggapi;<b><o:p></o:p></b></span></p><p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l0 level1 lfo1"><!--[if !supportLists]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">3.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Apabila hal tersebut tidak
dilakukan oleh Pihak yang terkena <i>force
majeure</i>, maka Pihak yang tidak terkena <i>force
majeure</i> menganggap tidak terjadi <i>force
majeure</i>;<b><o:p></o:p></b></span></p><p class="MsoNoSpacing" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l0 level2 lfo1">











</p><p class="ListParagraph1CxSpLast" style="margin-left:18.0pt;mso-add-space:auto;
text-align:justify;text-justify:inter-ideograph;text-indent:-18.0pt;mso-list:
l0 level1 lfo1"><!--[if !supportLists]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-fareast-font-family:Arial">4.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp;
</span></span></b><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Dalam
hal terjadi <i>force majeure</i>, maka
pelaksanaan kewajiban masing-masing Pihak akan ditunda berdasarkan kesepakatan <b>PARA
PIHAK</b>.<b><o:p></o:p></b></span></p>';
    }

    /**
     * Generate Pasal 8 section
     */
    private function generatePasal8()
    {
        return '<p class="ListParagraph1CxSpFirst" align="center" style="margin-left:0cm;
mso-add-space:auto;text-align:center;tab-stops:0cm"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Pasal 8<o:p></o:p></span></b></p><p class="ListParagraph1CxSpMiddle" align="center" style="margin-left:0cm;
mso-add-space:auto;text-align:center;tab-stops:0cm"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">PENYELESAIAN
PERSELISIHAN<o:p></o:p></span></b></p><p class="ListParagraph1CxSpMiddle" align="center" style="margin-left:0cm;
mso-add-space:auto;text-align:center;tab-stops:0cm"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></b></p><p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l0 level1 lfo1"><!--[if !supportLists]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">1.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Apabila dikemudian hari
terjadi perselisihan atau permasalahan antara kedua belah pihak, sehubungan
dengan pelaksanaan dan penafsiran perjanjian ini, maka <b>PARA PIHAK</b> setuju untuk menyelesaikan permasalahan atau
perselisihan dengan musyawarah untuk mufakat;<b><o:p></o:p></b></span></p><p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l0 level1 lfo1"><!--[if !supportLists]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-fareast-font-family:Arial">2.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp;
</span></span></b><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Apabila
cara penyelesaian musyawarah tersebut di atas gagal untuk mencapai kata
mufakat, maka <b>PARA PIHAK</b> setuju menunjuk Kantor Panitera Pengadilan
Negeri Surabaya untuk lembaga penyelesaian sengketa;<b><o:p></o:p></b></span></p><p class="ListParagraph1CxSpFirst" align="center" style="margin-left:0cm;
mso-add-space:auto;text-align:center;tab-stops:0cm">









</p><p class="ListParagraph1" style="margin-left:18.0pt;text-align:justify;
text-justify:inter-ideograph;text-indent:-18.0pt;mso-list:l0 level1 lfo1"><!--[if !supportLists]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">3.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><span lang="EN-US" style="mso-bidi-font-size:11.0pt;font-family:&quot;Arial&quot;,sans-serif">Selama
masa penyelesaian sengketa di pengadilan, <b>PARA PIHAK </b>tetap diwajibkan
untuk menjalankan masing-masing kewajibannya.</span><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif"><o:p></o:p></span></b></p>';
    }

    /**
     * Generate Pasal 9 section
     */
    private function generatePasal9()
    {
        return            $pasal9 = '<p class="ListParagraph1CxSpFirst" align="center" style="margin-left:0cm;
mso-add-space:auto;text-align:center;tab-stops:0cm"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Pasal 9<o:p></o:p></span></b></p><p class="ListParagraph1CxSpMiddle" align="center" style="margin-left:0cm;
mso-add-space:auto;text-align:center;tab-stops:0cm"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">PENUTUP<o:p></o:p></span></b></p><p class="ListParagraph1CxSpMiddle" align="center" style="margin-left:0cm;
mso-add-space:auto;text-align:center;tab-stops:0cm"><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></b></p><p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l0 level1 lfo1"><!--[if !supportLists]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;
mso-fareast-font-family:Arial">1.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp; </span></span></b><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Apabila terdapat perubahan,
tambahan dan atau hal - hal lain yang belum cukup diatur dalam Perjanjian ini,
maka akan dibuat secara tertulis dan ditanda tangani oleh kedua belah pihak dan
merupakan bagian yang tidak terpisahkan dari Perjanjian ini;<o:p></o:p></span></p><p class="ListParagraph1CxSpLast" style="margin-left:18.0pt;mso-add-space:auto;
text-align:justify;text-justify:inter-ideograph;text-indent:-18.0pt;mso-list:
l0 level1 lfo1"><!--[if !supportLists]--><b><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif;mso-fareast-font-family:Arial">2.<span style="font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-weight: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: &quot;Times New Roman&quot;;">&nbsp;&nbsp;&nbsp;
</span></span></b><!--[endif]--><span lang="EN-US" style="font-family:&quot;Arial&quot;,sans-serif">Perjanjian
ini dibuat rangkap dua (2), masing - masing bermaterai cukup dan memiliki
kekuatan hukum yang sama dan berlaku sejak ditandatangani oleh kedua belah
pihak.<o:p></o:p></span></p><p class="MsoNoSpacing"><span lang="EN-US" style="font-size:12.0pt;font-family:
&quot;Arial&quot;,sans-serif">&nbsp;</span></p><p class="MsoNoSpacing"><span lang="EN-US" style="font-size:12.0pt;font-family:
&quot;Arial&quot;,sans-serif">&nbsp;</span></p><table class="MsoNormalTable" border="1" cellspacing="0" cellpadding="0" width="618" style="margin-left: -4.5pt; border: none;">
 <tbody><tr>
  <td width="390" valign="top" style="width: 292.5pt; border-width: initial; border-style: none; border-color: initial; padding: 0cm 5.4pt;">
  <p class="NoSpacing1" style="margin-left:-.9pt"><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">PIHAK
  PERTAMA<o:p></o:p></span></b></p>
  <p class="NoSpacing1" style="margin-left:-.9pt"><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">' . $this->leads->nama_perusahaan . '<o:p></o:p></span></b></p>
  <p class="NoSpacing1" style="margin-left:-.9pt"><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></b></p>
  <p class="NoSpacing1"><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></b></p>
  <p class="NoSpacing1"><b><span lang="EN-US" style="font-size:12.0pt;font-family:
  &quot;Arial&quot;,sans-serif">&nbsp;</span></b></p>
  <p class="NoSpacing1"><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></b></p>
  <p class="NoSpacing1"><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></b></p>
  <p class="NoSpacing1" style="margin-left:-.9pt"><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></b></p>
  <p class="NoSpacing1" style="margin-left:-.9pt"><b><u><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">' . strtoupper($this->leads->pic) . '<o:p></o:p></span></u></b></p>
  <p class="NoSpacing1" style="margin-left:-.9pt"><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">Direktur<o:p></o:p></span></b></p>
  </td>
  <td width="228" valign="top" style="width: 171pt; border-width: initial; border-style: none; border-color: initial; padding: 0cm 5.4pt;">
  <p class="NoSpacing1"><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">PIHAK KEDUA<o:p></o:p></span></b></p>
  <p class="NoSpacing1"><b><span lang="EN-US" style="font-size:12.0pt;font-family:
  &quot;Arial&quot;,sans-serif">' . $this->company->name . '<o:p></o:p></span></b></p>
  <p class="NoSpacing1"><span lang="EN-US" style="font-size:12.0pt;font-family:
  &quot;Arial&quot;,sans-serif">&nbsp;</span></p>
  <p class="NoSpacing1"><span lang="EN-US" style="font-size:12.0pt;font-family:
  &quot;Arial&quot;,sans-serif">&nbsp;</span></p>
  <p class="NoSpacing1"><span lang="EN-US" style="font-size:12.0pt;font-family:
  &quot;Arial&quot;,sans-serif">&nbsp;</span></p>
  <p class="NoSpacing1"><span lang="EN-US" style="font-size:12.0pt;font-family:
  &quot;Arial&quot;,sans-serif">&nbsp;</span></p>
  <p class="NoSpacing1"><span lang="EN-US" style="font-size:12.0pt;font-family:
  &quot;Arial&quot;,sans-serif">&nbsp;</span></p>
  <p class="NoSpacing1"><b><span lang="EN-US" style="font-size:12.0pt;font-family:
  &quot;Arial&quot;,sans-serif">&nbsp;</span></b></p>
  <p class="NoSpacing1"><b><u><span lang="EN-US" style="font-size:12.0pt;
  font-family:&quot;Arial&quot;,sans-serif">' . strtoupper($this->company->nama_direktur) . '<o:p></o:p></span></u></b></p>
  <p class="NoSpacing1"><b><span lang="EN-US" style="font-size:12.0pt;font-family:
  &quot;Arial&quot;,sans-serif">Direktur<u><o:p></o:p></u></span></b></p>
  </td>
 </tr>
</tbody></table><p class="NoSpacing1"><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></b></p><p class="NoSpacing1"><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></b></p><p class="ListParagraph1" style="margin-top:0cm;margin-right:0cm;margin-bottom:
6.0pt;margin-left:18.0pt;text-align:justify;text-justify:inter-ideograph;
text-indent:-18.0pt;mso-list:l0 level1 lfo1">



















</p><p class="NoSpacing1"><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">&nbsp;</span></b></p>';
    }

    /**
     * Generate Lampiran section
     */
    private function generateLampiran()
    {
        return '<p class="NoSpacing1"><b><span lang="EN-US" style="font-size:12.0pt;font-family:&quot;Arial&quot;,sans-serif">Lampiran PKS No: ' . $this->pksNomor . '</span></b><b><span lang="IN" style="font-size:12.0pt;
font-family:&quot;Arial&quot;,sans-serif;mso-ansi-language:IN"><o:p></o:p></span></b></p><p></p>';
    }

    /**
     * Insert all agreement sections into database
     */
    public function insertAgreementSections($pksId, $createdBy)
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
                'updated_at' => $this->currentDateTime,
            ];
        }

        \DB::table('sl_pks_perjanjian')->insert($insertData);
    }
}