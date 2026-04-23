**DOKUMEN KEBUTUHAN PRODUK (PRD): INTEGRASI LARAVEL-ORTHANC PACS GATEWAY**

### **1. Tinjauan Produk**
Produk ini adalah sebuah **Applicative Gateway** medis berbasis web yang dibangun menggunakan framework **Laravel** untuk menjembatani interaksi pengguna dengan **Orthanc PACS**. Dalam arsitektur ini, Orthanc berfungsi sebagai bank data statis otoritatif untuk seluruh citra **DICOM**, sementara Laravel menangani lapisan keamanan, otentikasi, dan logika bisnis. Sistem ini bertujuan untuk menyediakan antarmuka kustom yang aman bagi tenaga medis tanpa mengekspos server PACS secara langsung ke jaringan publik.

---

### **2. Target Pengguna dan Peran (RBAC)**
Berdasarkan diskusi arsitektur, hak akses didefinisikan sebagai berikut:

| Peran | Tanggung Jawab Utama |
| :--- | :--- |
| **Administrator** | Manajemen sistem penuh, penghapusan data, dan pengelolaan audit log. |
| **Radiolog** | Melakukan interpretasi citra, anotasi (via viewer), dan anonimisasi data untuk riset. |
| **Dokter (Physician)** | Mencari dan melihat riwayat studi pasien yang dirujuk. |
| **Pasien** | Akses terbatas hanya untuk melihat citra milik sendiri (opsional). |

---

### **3. Persyaratan Fungsional**

#### **A. Manajemen Data (Operasi CRUD via Proxy)**
*   **Create (Upload):** Sistem harus mendukung pengunggahan file `.dcm` melalui Laravel yang diteruskan ke endpoint `/instances` Orthanc.
*   **Read (Retrieval):**
    *   Menampilkan daftar pasien, studi, seri, dan *instance* secara hierarkis.
    *   Mendukung pencarian pasien menggunakan *wildcard* (misal: `DOE*`) melalui endpoint `/tools/find`.
*   **Update (Modification):** Memungkinkan perubahan metadata terbatas (seperti deskripsi studi) melalui endpoint `/modify` tanpa merusak integritas file asli.
*   **Delete:** Penghapusan data hanya diizinkan bagi peran Administrator melalui protokol penghapusan fisik di Orthanc.

#### **B. Visualisasi Citra Medis**
*   **Viewer Diagnostik:** Integrasi **Stone Web Viewer** berbasis **WebAssembly** untuk rendering sisi klien guna meminimalkan beban CPU server.
*   **Pratinjau Cepat:** Menampilkan gambar pratinjau (thumbnail) dalam format PNG/JPEG menggunakan endpoint `/rendered` untuk daftar kerja klinis yang cepat.
*   **Fitur Manipulasi:** Penampil harus mendukung *windowing* (WC/WW), *zoom*, *pan*, dan rotasi citra secara interaktif.

---

### **4. Persyaratan Non-Fungsional**

#### **A. Performa dan Optimasi**
*   **Metadata Query:** Mengaktifkan mode `MainDicomTags` pada konfigurasi Orthanc untuk mempercepat kueri metadata tanpa harus membedah file DICOM di disk setiap saat.
*   **Latency:** Respon API proxy Laravel untuk kueri daftar pasien harus di bawah 1 detik untuk 100 entri pertama.
*   **Skalabilitas:** Penggunaan **PostgreSQL** sebagai backend database indeks Orthanc untuk menangani volume data di atas 10.000 studi.

#### **B. Keamanan dan Privasi**
*   **PHI Protection:** Informasi Kesehatan Pasien (PHI) tidak boleh disimpan secara permanen di database Laravel; Laravel hanya menyimpan ID unik Orthanc.
*   **Otentikasi:** Implementasi token sesi (Laravel Sanctum/Passport) dengan masa berlaku yang dibatasi.
*   **Audit Log:** Mencatat setiap aktivitas "Siapa mengakses citra Siapa" untuk kepatuhan regulasi medis.
*   **Gateway Keamanan:** Orthanc harus berjalan di belakang **Reverse Proxy (Nginx)** untuk menangani enkripsi SSL/HTTPS dan mengatasi masalah **CORS**.

---

### **5. Arsitektur Teknis dan Dependensi**
Sistem akan dideploy menggunakan teknologi kontainer untuk konsistensi lingkungan.

*   **Infrastruktur:** Docker atau Podman (dengan flag `:Z` untuk SELinux pada sistem berbasis Linux).
*   **Stack:** 
    *   Laravel 10.x+ (Middleware)
    *   Orthanc 1.12.1+ (PACS Core)
    *   Stone Web Viewer Plugin (Visualization)
    *   DICOMweb Plugin (Protokol Komunikasi Modern)
    *   PostgreSQL 14+ (Indeks Database)

---

### **6. Kriteria Keberhasilan (Success Metrics)**
1.  Pengguna berhasil melakukan login dan hanya melihat data sesuai izin perannya.
2.  File DICOM yang diunggah melalui portal Laravel terindeks dengan benar di server Orthanc.
3.  Stone Web Viewer dapat memuat citra CT/MRI berukuran besar (>500 slice) dalam waktu kurang dari 5 detik di sisi klien.
4.  Seluruh aktivitas akses data tercatat secara akurat di dalam tabel audit sistem.

---

### **7. Di Luar Cakupan (Out of Scope)**
*   Konfigurasi teknis pada mesin modalitas (AETitle/Port di sisi mesin CT-Scan).
*   Penyimpanan jangka panjang (Long-term Archive) di luar sistem file Orthanc (misalnya Tape Library atau Cloud S3).
*   Integrasi protokol HL7 dengan sistem EMR eksternal (memerlukan pengembangan plugin terpisah).


## **Plugin Penyimpanan & Database**

* **AWS S3 Storage** – Penyimpanan ke AWS S3
* **mysql-index / mysql-storage** – Index & storage di MySQL
* **postgresql-index / postgresql-storage** – Index & storage di PostgreSQL
* **odbc-index / odbc-storage** – Index & storage via ODBC

---

## **Plugin Integrasi & API**

* **dicom-web** – Implementasi QIDO-RS, STOW-RS, WADO-RS, WADO-URI
* **transfers** – Optimasi transfer & storage commitment antar Orthanc
* **connectivity-checks** – Cek konektivitas DICOM & server
* **tcia** – Integrasi dengan TCIA

---

## **Plugin Keamanan & Akses**

* **authorization** – Otorisasi tingkat lanjut
* **multitenant-dicom** – Dukungan multi-tenant

---

## **Plugin Manajemen & Operasional**

* **housekeeper** – Optimasi database & storage
* **delayed-deletion** – Penghapusan file async
* **indexer** – Sinkronisasi direktori DICOM

---

## **Plugin Viewer & UI**

* **orthanc-explorer-2** – UI lanjutan
* **ohif** – Integrasi OHIF Viewer
* **web-viewer** – Viewer DICOM berbasis web
* **stone-webviewer / stone-rtviewer** – Viewer berbasis Stone
* **volview** – Viewer VolView (Kitware)
* **wsi** – Viewer whole-slide imaging

---

## **Plugin Format & Ekstensi Medis**

* **gdcm** – Decoder/transcoder DICOM
* **neuro** – Dukungan NIfTI
* **stl** – Dukungan format STL

---

## **Plugin Tambahan**

* **serve-folders** – Serve folder via HTTP
* **worklists** – DICOM modality worklists
* **education** – Plugin edukasi

Plugin bersifat dinamis/opsional tergantung ketersedian plugin yang dipasang pada sistem Orthanc.

Berdasarkan sumber dan riwayat percakapan kita mengenai integrasi sistem PACS, berikut adalah paket-paket (*packages*) dan pustaka yang dapat digunakan di dalam ekosistem **Laravel** untuk membangun *middleware* atau *gateway* medis menuju **Orthanc**:

### 1. Paket Inti Laravel untuk Komunikasi API
*   **GuzzleHTTP (HTTP Client)**: Laravel menyertakan pustaka ini secara bawaan untuk melakukan permintaan HTTP. Ini adalah paket utama yang digunakan oleh *controller* Laravel untuk berkomunikasi dengan **REST API Orthanc** guna mengambil metadata pasien, studi, atau seri [173, 187, Percakapan Sebelumnya].
*   **

### 2. Pustaka untuk Penanganan Citra dan Metadata
*   **CornerstoneJS**: Meskipun integrasi utama disarankan melalui **Stone Web Viewer** (WebAssembly), Anda dapat menggunakan pustaka JavaScript ini di sisi klien untuk memanipulasi dan menampilkan citra medis jika memerlukan kustomisasi *viewer* yang sangat spesifik di dalam aplikasi web Anda [637, Percakapan Sebelumnya].
*   **DCMTK (Pustaka Utilitas)**: Di tingkat sistem (yang bisa dipanggil melalui skrip Laravel), alat seperti `storescu` atau `findscu` dari paket DCMTK sering digunakan untuk pengujian konektivitas DICOM tradisional dan pengiriman data secara sinkron.
*   **Package lain**

**Ringkasan Strategi Integrasi:**
Laravel bertindak sebagai **Applicative Gateway**. Anda tidak perlu menginstal paket DICOM berat di Laravel; cukup gunakan **Guzzle** untuk menarik JSON dari Orthanc, **Sanctum** untuk keamanan, dan sematkan **Stone Web Viewer** (berbasis WebAssembly) menggunakan *iframe* atau komponen web untuk visualisasi citra di sisi klien agar beban server tetap ringan [183, Percakapan Sebelumnya].
