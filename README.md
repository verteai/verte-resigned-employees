# Resigned Employee Registry

A lightweight, single-file HR web application for tracking resigned employees. Built with PHP and MySQL, designed to run on XAMPP without any additional dependencies.

---

## Features

- **Dashboard** — at-a-glance stats: total resigned, recent resignations (last 6 months), average tenure, and department breakdown with visual bar charts
- **All Records** — full paginated table with all employee data including account clearance status
- **Add Record** — form to manually enter a single resigned employee
- **Import CSV** — bulk upload records from a `.csv` file exported from Excel
- **Download CSV** — export the entire database to a properly formatted `.csv` file (UTF-8 with BOM for Excel compatibility)

---

## Requirements

| Requirement | Version |
|---|---|
| XAMPP | 1.7.2 or higher |
| PHP | 5.2 or higher |
| MySQL | 5.0 or higher |
| Browser | Any modern browser |

> No Composer, no external libraries, no dependencies. Pure PHP and MySQL.

---

## Project Structure

```
htdocs/emp/
│
├── index.php            # Main application (all views in one file)
├── setup_database.sql   # Database and table creation script
├── README.md            # This file
│
└── css/
    └── fonts.css        # Local font definitions (Syne + DM Sans)
```

---

## Installation

**1. Copy files to XAMPP**

Place the project folder inside your XAMPP `htdocs` directory:

```
D:\xampp\htdocs\emp\
```

**2. Set up the database**

- Open your browser and go to `http://localhost/phpmyadmin`
- Click the **SQL** tab
- Open `setup_database.sql`, copy the entire contents, and paste it into the SQL box
- Click **Go**

This will create the `resigned_emp` database and the `res` table automatically. Sample data is included — delete the `INSERT` block before going to production.

**3. Configure the database connection**

Open `index.php` and update the credentials at the top of the file if needed:

```php
$db_host = 'xxx.xxx.xxx.xxx';   // Your MySQL server IP or 'localhost'
$db_user = 'DB_Admin';       // Your MySQL username
$db_pass = 'yourpassword';   // Your MySQL password
$db_name = 'resigned_emp';   // Database name (must match setup_database.sql)
```

**4. Open the app**

Navigate to:

```
http://localhost/emp/
```

---

## Database Schema

**Database:** `resigned_emp`
**Table:** `res`

| Column | Type | Required | Description |
|---|---|---|---|
| `id` | INT AUTO_INCREMENT | — | Primary key |
| `employee_id` | VARCHAR(50) | Yes | Unique employee identifier (e.g. EMP-00123) |
| `name` | VARCHAR(150) | Yes | Full name |
| `email` | VARCHAR(150) | No | Company email address |
| `department` | VARCHAR(100) | Yes | Department name |
| `position` | VARCHAR(100) | Yes | Job title |
| `manager` | VARCHAR(100) | No | Direct manager's name |
| `hire_date` | DATE | Yes | Date hired |
| `resign_date` | DATE | Yes | Date of resignation |
| `clearance_date` | DATE | No | Date clearance was completed |
| `reason` | TEXT | No | Reason for resignation |
| `wifi` | TINYINT(1) | — | WiFi account cleared (0 = No, 1 = Yes) |
| `goplus` | TINYINT(1) | — | GO+ account cleared (0 = No, 1 = Yes) |
| `imapps` | TINYINT(1) | — | IMAPPS account cleared (0 = No, 1 = Yes) |
| `xo_scanpack` | TINYINT(1) | — | XO/SCANPACK account cleared (0 = No, 1 = Yes) |
| `created_at` | TIMESTAMP | — | Auto-set on insert |

---

## CSV Import Format

To bulk-import records, prepare a `.csv` file with the following column headers in the **first row**. Column names are case-insensitive.

### Required columns

| Header | Description |
|---|---|
| `employee_id` | Unique employee ID |
| `name` | Full name |
| `department` | Department |
| `position` | Job title |
| `hire_date` | Date hired |
| `resign_date` | Date resigned |

### Optional columns

| Header | Accepted aliases | Notes |
|---|---|---|
| `email` | `email_address` | |
| `manager` | `direct_manager` | |
| `clearance_date` | `clearancedate` | |
| `reason` | `resignation_reason` | |
| `wifi` | — | Use `1` or `Yes` to mark as cleared |
| `goplus` | `go+` | Use `1` or `Yes` to mark as cleared |
| `imapps` | — | Use `1` or `Yes` to mark as cleared |
| `xo_scanpack` | `xo/scanpack`, `xo` | Use `1` or `Yes` to mark as cleared |

### Date formats accepted

- `YYYY-MM-DD` (e.g. `2024-11-30`)
- `MM/DD/YYYY` (e.g. `11/30/2024`)
- Excel serial date numbers are also handled automatically

### Example CSV

```
employee_id,name,department,position,email,manager,hire_date,resign_date,clearance_date,reason,wifi,goplus,imapps,xo_scanpack
EMP-00010,Maria Santos,IT,Systems Analyst,maria@company.com,Juan dela Cruz,2020-01-15,2024-11-30,2024-12-05,Career advancement,1,1,1,0
EMP-00011,Pedro Garcia,Finance,Accountant,pedro@company.com,Ana Lim,2019-06-01,2024-10-31,,Relocation,1,0,0,1
```

### How to export from Excel

1. Open your Excel file
2. Click **File → Save As**
3. Choose file type: **CSV (Comma delimited) (.csv)**
4. Click **Save**

> Duplicate `employee_id` values are automatically skipped during import. Rows with missing required fields are skipped and reported.

---

## Upgrading from an Older Version

If you already have a `res` table from a previous version of this app (without the new columns), **do not run the full `setup_database.sql`** as it will drop your existing data.

Instead, open `setup_database.sql`, scroll to the bottom, and run only the `ALTER TABLE` block:

```sql
ALTER TABLE `res`
  ADD COLUMN IF NOT EXISTS `employee_id`    VARCHAR(50)  NOT NULL DEFAULT '' AFTER `id`,
  ADD COLUMN IF NOT EXISTS `manager`        VARCHAR(100) NOT NULL DEFAULT '' AFTER `email`,
  ADD COLUMN IF NOT EXISTS `clearance_date` DATE         NULL DEFAULT NULL AFTER `resign_date`,
  ADD COLUMN IF NOT EXISTS `wifi`           TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `goplus`         TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `imapps`         TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `xo_scanpack`    TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP;
```

---

## Troubleshooting

**Parse error / syntax error on startup**
Your PHP version may be older than 5.4. The app is written to be compatible with PHP 5.2+. If errors persist, check your XAMPP PHP version in the XAMPP Control Panel.

**Database connection failed**
- Confirm MySQL is running in the XAMPP Control Panel
- Double-check the `$db_host`, `$db_user`, `$db_pass`, and `$db_name` values at the top of `index.php`
- Make sure you have run `setup_database.sql` first

**Import says "0 records added"**
- Ensure your CSV has the correct headers in row 1
- Open the file in a text editor to confirm it is comma-separated and not tab-separated
- Make sure the `employee_id` values don't already exist in the database

**Garbled characters in downloaded CSV when opened in Excel**
The download includes a UTF-8 BOM automatically. If characters still appear garbled, open Excel first, use **Data → From Text/CSV**, and select UTF-8 encoding during import.

**Fonts not loading**
Make sure the `css/fonts.css` file and associated font files exist in `htdocs/emp/css/`. The app uses Syne and DM Sans.

---

## Notes

- This application uses `mysqli_*` functions and is **not** compatible with the deprecated `mysql_*` functions from PHP 4.
- All data is stored locally in your XAMPP MySQL instance. There is no cloud sync or external API.
- The app does not currently support editing existing records. To correct a record, remove it and re-add it with the correct data.
- Passwords and credentials stored in `index.php` are in plain text. Do not expose this application to a public network.

---

*Resigned Employee Registry — Internal HR Tool*
