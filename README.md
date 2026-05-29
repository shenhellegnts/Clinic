# M.V. Masangkay Clinic Appointment System

A full-stack web-based clinic appointment and queue management system built with PHP, MySQL, and vanilla JavaScript.

## Features

- **Patient Portal** — OTP-based login, multi-profile support, online appointment booking, live queue tracking
- **Admin Dashboard** — Appointment review with full medical history, queue control, SMS notifications, analytics
- **Semaphore SMS Integration** — OTP verification, appointment approvals, queue call notifications
- **Glassmorphic UI** — iOS-inspired design with pastel gradient backgrounds

## Setup

1. Import `clinic_db.sql` into phpMyAdmin
2. Copy `includes/config.local.example.php` → `includes/config.local.php` and add your Semaphore API key
3. Point your web server root to this folder
4. Visit `http://localhost/clinic/`

## Tech Stack

- PHP 8.x
- MySQL / MariaDB
- Vanilla JavaScript
- Semaphore SMS API
