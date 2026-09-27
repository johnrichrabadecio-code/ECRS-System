# ECRS — Entrance Exam-Based Course Recommender System

A locally hosted PHP + MySQL prototype designed to match the supplied ECRS screenshots while following the final SRS structure: **13 entrance-exam subject areas, 4 study tracks, a 33-program catalog, Admin/Student role separation, and an explainable weighted Fit Score recommender**.

## Important scope note
The recommendation engine is **not machine learning**. It recomputes a deterministic weighted average on request:

`Fit = Σ(subject_score × subject_weight) / Σ(subject_weight)`

Only subjects with non-zero program weights participate.

The 33 program entries and default weight values included by `install.php` are **demo seed data** so the prototype runs immediately. The supplied SRS states that the final catalog and policy-derived prototype weights must be aligned to the referenced institutional source and remain subject to validation. Replace the demo weights in **Admin → Weights** before formal evaluation if you have the approved values.

## XAMPP setup
1. Copy the `ECRS_System` folder to `C:\xampp\htdocs\`.
2. Start **Apache** and **MySQL** in XAMPP.
3. If your MySQL root account has a password or uses a different port, edit `config/config.php`.
4. Open `http://localhost/ECRS_System/install.php`.
5. Click **Install / Repair Database**.
6. Delete or rename `install.php` after installation.

## Demo accounts
- Student: `2026-00451` / `Student123!`
- Admin: `admin@ecrs.local` / `Admin123!`

## Student modules
Dashboard, Entrance Exam Results, Recommendations, Programs / Program Details, My Profile, Settings, Logout.

## Admin modules
Dashboard, Students, Exam Scores, Subjects, Weights, Programs, Logout.

## Implemented safeguards
- Password hashing (`password_hash` / `password_verify`)
- Separate Admin and Student sessions
- Role-guarded pages
- PDO prepared statements
- CSRF tokens on write forms
- Numeric score range validation (0–100)
- Transactional replacement of exam scores
- Referential integrity / cascade deletion
- Reusable Admin and Student layouts

## Folder structure
- `admin/` administrator pages
- `student/` student pages
- `includes/` shared authentication, database, Fit Score, and layouts
- `assets/` CSS and JavaScript
- `config/` database and base URL settings
- `install.php` one-time database/schema/seed installer

## Admin Visual Refresh (September 2026)
The Admin interface was visually upgraded using the supplied screenshots as design references only. Core ECRS scope, the final 13 subject areas, 4 study tracks, weighted Fit Score logic, and existing Student interface were kept intact. The Admin dashboard now derives visual metrics and summaries from the current database rather than hard-coded screenshot values.
