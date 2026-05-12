# csieDBTeam14 Library Borrowing System

PHP + MySQL web app for the library borrowing management requirements.

## Requirements Covered

- Readers can search books by title, author, category, ISBN, or keyword.
- Readers can register their own accounts.
- Readers can borrow available books and return active loans.
- The system records borrow date, due date, return date, overdue days, and fines.
- Each reader is limited to 3 active loans.
- Admins can add, update, and remove books.
- Admins can add categories, manage reader accounts, force returns, and view reports.
- Reports include popular books, category usage, overdue items, and monthly borrowing counts.

## Database Rules

- Database name: `csieDBTeam14`
- Table naming rule: every table uses the `Y114_` prefix.
- Required examples:
  - `user` table is created as `Y114_user`
  - `student` table is created as `Y114_student`

Main tables:

- `Y114_user`
- `Y114_student`
- `Y114_category`
- `Y114_book`
- `Y114_borrow_record`

## File Overview

- `Dockerfile`: Builds the PHP 8.2 Apache image and installs MySQL-related PHP extensions.
- `docker-compose.yml`: Starts the web app, MySQL database, and phpMyAdmin services.
- `database/init.sql`: Creates `csieDBTeam14`, all `Y114_` tables, relationships, and seed data.
- `src/config.php`: Shared configuration for sessions, database connection, CSRF, login checks, and helpers.
- `src/library_actions.php`: Shared borrow, return, and barcode lookup logic used by normal and mobile flows.
- `src/login.php`: Login page for admin and reader accounts.
- `src/register.php`: Self-registration page for new reader accounts and student records.
- `src/logout.php`: Logs the current user out and redirects to login.
- `src/index.php`: Main dashboard showing borrowing status, statistics, and reader history.
- `src/books.php`: Book search page and normal reader borrow action buttons.
- `src/borrow.php`: Handles standard book borrowing form submissions.
- `src/return.php`: Handles standard book return form submissions.
- `src/admin.php`: Admin dashboard for book list, category creation, reader management, and loan management.
- `src/book_new.php`: Separate page for adding a new book.
- `src/book_edit.php`: Separate page for editing one existing book.
- `src/reports.php`: Admin reports for popular books, category usage, overdue books, and monthly borrowing.
- `src/mobile_scan.php`: Mobile-first borrow/return page for camera barcode scanning or manual ISBN input.
- `src/ajax/scan_action.php`: JSON API used by the mobile scanner to borrow or return books by barcode.
- `src/assets/mobile-scan.js`: Browser camera scanner logic using `BarcodeDetector` with manual-input fallback.
- `src/assets/styles.css`: Shared layout, table, form, dashboard, and mobile scanner styles.
- `src/partials/header.php`: Common page header and navigation links.
- `src/partials/footer.php`: Common page footer.

## Run With Docker

```bash
docker compose up --build
```

Open:

- Web app: http://localhost:8080
- phpMyAdmin: http://localhost:8082
- MySQL host port: `3307`
- Mobile barcode scan page: http://localhost:8080/mobile_scan.php

Demo accounts:

- Admin: `admin` / `admin123`
- Reader: `reader` / `reader123`

phpMyAdmin login:

- Server: `db`
- Username: `csie_user`
- Password: `csiePassword14`
- Database: `csieDBTeam14`

## Reset Database

```bash
docker compose down -v
docker compose up --build
```
