# Entity Relationship Diagram (ERD) - Cloud Team Management

Berikut adalah rancangan diagram hubungan entitas (ERD) untuk database `cloud_team_management_db`.

## Diagram Mermaid

Anda dapat melihat diagram di bawah ini di platform yang mendukung rendering Mermaid (seperti GitHub atau extension Markdown Preview).

```mermaid
erDiagram
    users {
        int id PK
        varchar username UK
        varchar email UK
        varchar password
        enum role
        timestamp created_at
        timestamp updated_at
    }

    proyek {
        int id PK
        varchar nama_proyek
        text deskripsi
        date tanggal_mulai
        date tanggal_selesai
        enum status
        timestamp created_at
        timestamp updated_at
    }

    anggota {
        int id PK
        varchar nama
        varchar nip_nim UK
        varchar email UK
        varchar telepon
        varchar jabatan
        varchar foto
        int id_user FK, UK
        int id_proyek FK
        timestamp created_at
        timestamp updated_at
    }

    users ||--o| anggota : "memiliki profil anggota (1-to-0/1)"
    proyek ||--o{ anggota : "memiliki anggota tim (1-to-many)"
```

## Deskripsi Hubungan

1. **`users` ke `anggota` (One-to-One / 1:0..1)**
   - Setiap pengguna (`users`) dapat memiliki maksimal satu profil anggota (`anggota`).
   - Setiap anggota (`anggota`) dapat dikaitkan dengan satu akun pengguna (`users`) untuk login ke sistem, atau tidak memiliki akun sama sekali (diwakili dengan nilai `NULL` pada foreign key `id_user`).
   
2. **`proyek` ke `anggota` (One-to-Many / 1:N)**
   - Setiap proyek (`proyek`) dapat menampung banyak anggota tim (`anggota`).
   - Setiap anggota (`anggota`) hanya dapat ditugaskan pada satu proyek dalam satu waktu (diwakili dengan foreign key `id_proyek`). Jika anggota tidak ditugaskan ke proyek mana pun, nilai foreign key bernilai `NULL`.
